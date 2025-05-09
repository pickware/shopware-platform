<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SendPasswordRecoveryMailRoute::class)]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    protected EntityRepository&MockObject $customerRepository;

    protected EntityRepository&MockObject $customerRecoveryRepository;

    protected EventDispatcherInterface&MockObject $eventDispatcher;

    protected DataValidator&MockObject $validator;

    protected SystemConfigService&MockObject $systemConfigService;

    protected RequestStack&MockObject $requestStack;

    protected RateLimiter&MockObject $rateLimiter;

    protected SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->customerRecoveryRepository = $this->createMock(EntityRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->validator = $this->createMock(DataValidator::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testSendRecoveryMail(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foo');

        $customerCollection = new CustomerCollection([$customer]);

        $this->customerRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    $customerCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $this->customerRecoveryRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(function (array $recoveryData): bool {
                    static::assertCount(1, $recoveryData);

                    $updateData = $recoveryData[0];

                    static::assertArrayHasKey('customerId', $updateData);
                    static::assertArrayHasKey('hash', $updateData);

                    static::assertSame('foo', $updateData['customerId']);
                    static::assertSame(32, \strlen($updateData['hash']));

                    return true;
                }),
                $this->context->getContext()
            );

        $customerRecovery = new CustomerRecoveryEntity();
        $customerRecovery->setId('customer-recovery-id');
        $customerRecovery->setUniqueIdentifier('customer-recovery-id');
        $customerRecovery->setCustomerId($customer->getId());
        $customerRecovery->setHash('super-secret-hash');
        $customerRecovery->setCustomer($customer);

        $customerRecoveryCollection = new CustomerRecoveryCollection([$customerRecovery]);

        $this->customerRecoveryRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer_recovery',
                    1,
                    $customerRecoveryCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $MailRoute = new SendPasswordRecoveryMailRoute(
            $this->customerRepository,
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter
        );

        $this->context->getSalesChannel()->setTranslated(['name' => 'FooBar']);
        $event = new CustomerAccountRecoverRequestEvent($this->context, $customerRecovery, 'https://test.example.dev/account/recover/password?hash=super-secret-hash');

        $this->eventDispatcher
            ->method('dispatch')
            ->with(static::callback(function (Event $dispatched) use ($event): bool {
                if ($dispatched instanceof CustomerAccountRecoverRequestEvent) {
                    static::assertEquals($event, $dispatched);
                }

                return true;
            }), static::anything());

        $data = new RequestDataBag();
        $data->set('email', 'test@test.dev');
        $data->set('storefrontUrl', 'https://test.example.dev');

        $MailRoute->sendRecoveryMail($data, $this->context);
    }

    public function testNoCustomerFound(): void
    {
        $mailRoute = new SendPasswordRecoveryMailRoute(
            $this->customerRepository,
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter
        );

        $data = new RequestDataBag();
        $data->set('email', 'foo@foo');

        $response = $mailRoute->sendRecoveryMail($data, $this->context)->getObject()->getVars();

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }
}
