<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Shopware\Administration\Controller\AdminExtensionApiController;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminExtensionApiController::class)]
class AdminExtensionApiControllerTest extends TestCase
{
    private MockObject&AppPayloadServiceHelper $appPayloadServiceHelper;

    private Context $context;

    /** @var MockObject&EntityRepository<AppCollection> */
    private MockObject&EntityRepository $entityRepository;

    private MockObject&Executor $executor;

    private MockObject&QuerySigner $querySigner;

    private AdminExtensionApiController $controller;

    protected function setUp(): void
    {
        $this->appPayloadServiceHelper = $this->createMock(AppPayloadServiceHelper::class);
        $this->context = Context::createDefaultContext();
        $this->querySigner = $this->createMock(QuerySigner::class);
        $this->executor = $this->createMock(Executor::class);
        $this->entityRepository = $this->createMock(EntityRepository::class);

        $this->controller = new AdminExtensionApiController(
            $this->executor,
            $this->appPayloadServiceHelper,
            $this->entityRepository,
            $this->querySigner
        );
    }

    public function testRunActionThrowsAppByNameNotFoundExceptionWhenAppIsNotFound(): void
    {
        $this->expectException(AppNotFoundException::class);

        $this->controller->runAction(new RequestDataBag(['appName' => 'test-app']), $this->context);
    }

    public function testRunActionThrowsAppByNameNotFoundExceptionWhenAppSecretIsNull(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(AppException::appSecretMissing('test-app')->getMessage());

        $entity = $this->buildAppEntity('test-app', null, []);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(new RequestDataBag(['appName' => $entity->getName()]), $this->context);
    }

    public function testRunActionThrowsUnallowedHostExceptionWhenTargetHostIsEmpty(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(AppException::hostNotAllowed('', 'test-app')->getMessage());

        $entity = $this->buildAppEntity('test-app', 'test-secrets', []);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(new RequestDataBag(['appName' => $entity->getName()]), $this->context);
    }

    public function testRunActionThrowsUnallowedHostExceptionWhenTargetHostIsNotAllowed(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(AppException::hostNotAllowed('test-host', 'test-app')->getMessage());

        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['shopware']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(
            new RequestDataBag(['appName' => $entity->getName(), 'url' => 'test-host']),
            $this->context
        );
    }

    public function testRunActionThrowsInvalidArgumentExceptionWhenNoIdInRequestBag(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(AppException::invalidArgument('Ids must be an array')->getMessage());

        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(
            new RequestDataBag(['appName' => $entity->getName(), 'url' => 'https://foo.bar/test']),
            $this->context
        );
    }

    public function testRunActionExecutesAnAppAction(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->appPayloadServiceHelper->expects($this->once())->method('buildSource')->with('1.0.0', $entity->getName());
        $this->executor->expects($this->once())->method('execute');

        $this->controller->runAction(
            new RequestDataBag([
                'appName' => $entity->getName(),
                'url' => 'https://foo.bar',
                'ids' => [Uuid::randomHex()],
                'entity' => 'app',
                'action' => 'do-nothing',
            ]),
            $this->context,
        );
    }

    public function testSignUriThrowsAppByNameNotFoundExceptionWhenAppIsNotFound(): void
    {
        $this->expectException(AppNotFoundException::class);

        $this->controller->signUri(new RequestDataBag(['appName' => 'test-app']), $this->context);
    }

    public function testSignUriReturnsJsonResponseWithUri(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $this->assertEntityRepositoryWithEntity($entity);

        $requestBag = new RequestDataBag(['appName' => $entity->getName(), 'uri' => 'test-uri']);

        $this->querySigner->expects($this->once())->method('signUri')
            ->with($requestBag->get('uri'), $entity, $this->context)
            ->willReturn($this->createMock(UriInterface::class));

        $response = $this->controller->signUri($requestBag, $this->context);

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"uri":""}', $response->getContent());
    }

    protected function assertEntityRepositoryWithEntity(AppEntity $entity): void
    {
        $collection = new EntityCollection();
        $collection->add($entity);
        $collection->add($this->buildAppEntity('secondAppDiscarded', null, []));

        $this->entityRepository->expects($this->once())->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'app',
                    2,
                    $collection,
                    null,
                    new Criteria(),
                    $this->context
                )
            );
    }

    /**
     * @param list<string>|null $allowedHosts
     */
    protected function buildAppEntity(string $name, ?string $appSecret, ?array $allowedHosts): AppEntity
    {
        $entity = new AppEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setName($name);
        $entity->setVersion('1.0.0');
        $entity->setAppSecret($appSecret);
        $entity->setAllowedHosts($allowedHosts);

        return $entity;
    }
}
