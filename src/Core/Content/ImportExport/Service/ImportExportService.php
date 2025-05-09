<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 *
 * @phpstan-type Config array{mapping?: list<array{key: string, mappedKey: string}>|array<Mapping>|null, updateBy?: array<string, mixed>|null, parameters?: array<string, mixed>|null}
 */
#[Package('fundamentals@after-sales')]
class ImportExportService
{
    /**
     * @param EntityRepository<ImportExportLogCollection> $logRepository
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<EntityCollection<ImportExportProfileEntity>> $profileRepository
     */
    public function __construct(
        private readonly EntityRepository $logRepository,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $profileRepository,
        private readonly AbstractFileService $fileService
    ) {
    }

    /**
     * @param Config $config
     * @param ImportExportLogEntity::ACTIVITY_* $activity
     */
    public function prepareExport(
        Context $context,
        string $profileId,
        \DateTimeInterface $expireDate,
        ?string $originalFileName = null,
        array $config = [],
        ?string $destinationPath = null,
        string $activity = ImportExportLogEntity::ACTIVITY_EXPORT
    ): ImportExportLogEntity {
        $profileEntity = $this->findProfile($context, $profileId);

        if (!\in_array($profileEntity->getType(), [ImportExportProfileEntity::TYPE_EXPORT, ImportExportProfileEntity::TYPE_IMPORT_EXPORT], true)
            && $activity !== ImportExportLogEntity::ACTIVITY_INVALID_RECORDS_EXPORT
        ) {
            throw ImportExportException::profileWrongType($profileEntity->getId(), $profileEntity->getType());
        }

        if ($originalFileName === null) {
            $originalFileName = $this->fileService->generateFilename($profileEntity);
        }

        if ($profileEntity->getMapping() !== null) {
            $mappings = MappingCollection::fromIterable($profileEntity->getMapping());
            $profileEntity->setMapping($mappings->sortByPosition());
        }

        $fileEntity = $this->fileService->storeFile($context, $expireDate, null, $originalFileName, $activity, $destinationPath);

        return $this->createLog($context, $activity, $fileEntity, $profileEntity, $config);
    }

    /**
     * @param Config $config
     */
    public function prepareImport(
        Context $context,
        string $profileId,
        \DateTimeInterface $expireDate,
        UploadedFile $file,
        array $config = [],
        bool $dryRun = false
    ): ImportExportLogEntity {
        $profileEntity = $this->findProfile($context, $profileId);

        if (!\in_array($profileEntity->getType(), [ImportExportProfileEntity::TYPE_IMPORT, ImportExportProfileEntity::TYPE_IMPORT_EXPORT], true)) {
            throw ImportExportException::profileWrongType($profileEntity->getId(), $profileEntity->getType());
        }

        $type = $this->fileService->detectType($file);
        if ($type !== $profileEntity->getFileType()) {
            throw ImportExportException::unexpectedFileType($file->getClientMimeType(), $profileEntity->getFileType());
        }

        $fileEntity = $this->fileService->storeFile($context, $expireDate, $file->getPathname(), $file->getClientOriginalName(), ImportExportLogEntity::ACTIVITY_IMPORT);
        $activity = $dryRun ? ImportExportLogEntity::ACTIVITY_DRYRUN : ImportExportLogEntity::ACTIVITY_IMPORT;

        return $this->createLog($context, $activity, $fileEntity, $profileEntity, $config);
    }

    public function cancel(Context $context, string $logId): void
    {
        $logEntity = $this->findLog($context, $logId);

        $canceledProgress = new Progress($logId, Progress::STATE_ABORTED);
        $canceledProgress->addProcessedRecords($logEntity->getRecords());

        $this->saveProgress($canceledProgress);
    }

    public function getProgress(string $logId, int $offset): Progress
    {
        $criteria = new Criteria([$logId]);
        $criteria->addAssociation('file');
        $current = $this->logRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$current instanceof ImportExportLogEntity) {
            throw ImportExportException::logEntityNotFound($logId);
        }

        $progress = new Progress(
            $current->getId(),
            $current->getState(),
            $offset
        );
        if ($current->getInvalidRecordsLogId()) {
            $progress->setInvalidRecordsLogId($current->getInvalidRecordsLogId());
        }

        $progress->addProcessedRecords($current->getRecords());

        return $progress;
    }

    /**
     * @param array<array<mixed>>|null $result
     */
    public function saveProgress(Progress $progress, ?array $result = null): void
    {
        $logData = [
            'id' => $progress->getLogId(),
            'records' => $progress->getProcessedRecords(),
        ];
        if ($progress->getState() !== Progress::STATE_PROGRESS) {
            $logData['state'] = $progress->getState();
        }
        if ($progress->getInvalidRecordsLogId()) {
            $logData['invalidRecordsLogId'] = $progress->getInvalidRecordsLogId();
        }
        if ($result) {
            $logData['result'] = $result;
        }

        $context = Context::createDefaultContext();
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logData): void {
            $this->logRepository->update([$logData], $context);
        });
    }

    public function findLog(Context $context, string $logId): ImportExportLogEntity
    {
        $criteria = new Criteria([$logId]);
        $criteria->addAssociation('profile');
        $criteria->addAssociation('file');
        $criteria->addAssociation('invalidRecordsLog.file');

        $logEntity = $this->logRepository->search($criteria, $context)->getEntities()->first();

        if ($logEntity === null) {
            throw ImportExportException::logEntityNotFound($logId);
        }

        return $logEntity;
    }

    private function findProfile(Context $context, string $profileId): ImportExportProfileEntity
    {
        $profile = $this->profileRepository
            ->search(new Criteria([$profileId]), $context)
            ->getEntities()
            ->first();

        if ($profile !== null) {
            return $profile;
        }

        throw ImportExportException::profileNotFound($profileId);
    }

    /**
     * @param Config $config
     */
    private function createLog(
        Context $context,
        string $activity,
        ImportExportFileEntity $file,
        ImportExportProfileEntity $profile,
        array $config
    ): ImportExportLogEntity {
        $logEntity = new ImportExportLogEntity();
        $logEntity->setId(Uuid::randomHex());
        $logEntity->setActivity($activity);
        $logEntity->setState(Progress::STATE_PROGRESS);
        $logEntity->setProfileId($profile->getId());
        $logEntity->setProfileName($profile->getTranslation('label'));
        $logEntity->setFileId($file->getId());
        $logEntity->setRecords(0);
        $logEntity->setConfig($this->getConfig($profile, $config));

        $contextSource = $context->getSource();
        $userId = $contextSource instanceof AdminApiSource ? $contextSource->getUserId() : null;
        if ($userId !== null) {
            $logEntity->setUsername($this->findUser($context, $userId)->getUsername());
            $logEntity->setUserId($userId);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logEntity): void {
            $logData = array_filter($logEntity->jsonSerialize(), fn ($value) => $value !== null);
            $this->logRepository->create([$logData], $context);
        });

        $logEntity->setProfile($profile);
        $logEntity->setFile($file);

        return $logEntity;
    }

    private function findUser(Context $context, string $userId): UserEntity
    {
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        \assert($user !== null);

        return $user;
    }

    /**
     * @param Config $config
     *
     * @return Config
     */
    private function getConfig(ImportExportProfileEntity $profileEntity, array $config): array
    {
        $parameters = $profileEntity->getConfig();

        $parameters['delimiter'] = $profileEntity->getDelimiter();
        $parameters['enclosure'] = $profileEntity->getEnclosure();
        $parameters['sourceEntity'] = $profileEntity->getSourceEntity();
        $parameters['fileType'] = $profileEntity->getFileType();
        $parameters['profileName'] = $profileEntity->getTranslation('label');

        return [
            'mapping' => $config['mapping'] ?? $profileEntity->getMapping(),
            'updateBy' => $config['updateBy'] ?? $profileEntity->getUpdateBy(),
            'parameters' => array_merge($parameters, $config['parameters'] ?? []),
        ];
    }
}
