<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ProductReviewSaveRoute::class)]
class ProductReviewSaveRouteTest extends TestCase
{
    private MockObject&EntityRepository $repository;

    private MockObject&DataValidator $validator;

    private StaticSystemConfigService $config;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private ProductReviewSaveRoute $route;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->validator = $this->createMock(DataValidator::class);
        $this->config = new StaticSystemConfigService([
            'test' => [
                'core.listing.showReview' => true,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewNotActive' => [
                'core.listing.showReview' => false,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
        ]);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->route = new ProductReviewSaveRoute(
            $this->repository,
            $this->validator,
            $this->config,
            $this->eventDispatcher
        );
    }

    public function testSave(): void
    {
        $id = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $data = new RequestDataBag([
            'id' => $id,
            'title' => 'foo',
            'content' => 'bar',
            'points' => 3,
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');
        $customer->setEmail('foo@example.com');
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('test');

        $salesChannelContext->expects($this->once())->method('getCustomer')->willReturn($customer);
        $salesChannelContext->expects($this->exactly(1))->method('getSalesChannelId')->willReturn($salesChannel->getId());
        $salesChannelContext->expects($this->exactly(1))->method('getLanguageId')->willReturn($context->getLanguageId());
        $salesChannelContext->expects($this->exactly(3))->method('getContext')->willReturn($context);

        $this->validator->expects($this->once())->method('getViolations')->willReturn(new ConstraintViolationList());

        $this->repository
            ->expects($this->once())
            ->method('upsert')
            ->with([
                [
                    'productId' => $productId,
                    'customerId' => $customer->getId(),
                    'salesChannelId' => $salesChannel->getId(),
                    'languageId' => $context->getLanguageId(),
                    'externalUser' => $customer->getFirstName(),
                    'externalEmail' => $customer->getEmail(),
                    'title' => $data->get('title'),
                    'content' => $data->get('content'),
                    'points' => $data->get('points'),
                    'status' => false,
                    'id' => $data->get('id'),
                ],
            ], $context);

        $event = new ReviewFormEvent(
            $context,
            $salesChannel->getId(),
            new MailRecipientStruct(['foo@example.com' => 'Max Mustermann']),
            new RequestDataBag([
                'title' => 'foo',
                'content' => 'bar',
                'points' => 3,
                'name' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'customerId' => $customer->getId(),
                'productId' => $productId,
                'id' => $id,
            ]),
            $productId,
            $customer->getId()
        );

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($event, ReviewFormEvent::EVENT_NAME);

        $this->route->save($productId, $data, $salesChannelContext);
    }

    public function testSaveReviewDeactivated(): void
    {
        $ids = new IdsCollection();

        $this->expectExceptionObject(ProductException::reviewNotActive());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->exactly(1))->method('getSalesChannelId')->willReturn('testReviewNotActive');

        $this->route->save(
            $ids->get('productId'),
            new RequestDataBag(['test' => 'test']),
            $salesChannelContext,
        );
    }
}
