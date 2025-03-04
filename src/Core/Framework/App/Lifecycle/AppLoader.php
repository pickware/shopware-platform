<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
class AppLoader
{
    final public const COMPOSER_TYPE = 'shopware-app';

    public function __construct(
        private readonly string $appDir,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, Manifest>
     */
    public function load(): array
    {
        return [...$this->loadFromAppDir(), ...$this->loadFromComposer()];
    }

    public function deleteApp(string $technicalName): void
    {
        $apps = $this->load();

        if (!isset($apps[$technicalName])) {
            return;
        }

        $manifest = $apps[$technicalName];

        if ($manifest->isManagedByComposer()) {
            throw AppException::cannotDeleteManaged($technicalName);
        }

        (new Filesystem())->remove($manifest->getPath());
    }

    /**
     * @return array<string, Manifest>
     */
    private function loadFromAppDir(): array
    {
        if (!file_exists($this->appDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in-app root folders
            ->followLinks()
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException $exception) {
                $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            }
        }

        // Overriding with local manifests
        $finder = new Finder();

        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in-app root folders
            ->followLinks()
            ->name('manifest.local.xml');

        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException $exception) {
                $this->logger->error('Local manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            }
        }

        return $manifests;
    }

    /**
     * @return array<string, Manifest>
     */
    private function loadFromComposer(): array
    {
        $manifests = [];

        foreach (InstalledVersions::getInstalledPackagesByType(self::COMPOSER_TYPE) as $packageName) {
            $path = InstalledVersions::getInstallPath($packageName);

            if ($path !== null) {
                try {
                    $manifest = Manifest::createFromXmlFile($path . '/manifest.xml');
                    $manifest->setManagedByComposer(true);

                    $manifests[$manifest->getMetadata()->getName()] = $manifest;
                } catch (AppXmlParsingException $exception) {
                    $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
                }
            }
        }

        return $manifests;
    }
}
