<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\Exception\PasswordPoliciesUpdatedException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    public function testLoginByValidCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);

        $loggedinSalesChannelContext = Generator::generateSalesChannelContext();
        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->once())
            ->method('restore')
            ->willReturn($loggedinSalesChannelContext);

        $beforeLoginEventCalled = false;
        $loginEventCalled = false;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerBeforeLoginEvent::class,
            function (CustomerBeforeLoginEvent $event) use ($salesChannelContext, &$beforeLoginEventCalled): void {
                $beforeLoginEventCalled = true;
                static::assertSame('foo@bar.de', $event->getEmail());
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
            },
        );

        $eventDispatcher->addListener(
            CustomerLoginEvent::class,
            function (CustomerLoginEvent $event) use ($customer, $loggedinSalesChannelContext, &$loginEventCalled): void {
                $loginEventCalled = true;
                static::assertSame($customer, $event->getCustomer());
                static::assertSame($loggedinSalesChannelContext, $event->getSalesChannelContext());
                static::assertSame($loggedinSalesChannelContext->getToken(), $event->getContextToken());
            },
        );

        $accountService = new AccountService(
            $customerRepository,
            $eventDispatcher,
            $this->createMock(LegacyPasswordVerifier::class),
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
        );

        $token = $accountService->loginByCredentials('foo@bar.de', 'shopware', $salesChannelContext);
        static::assertSame($loggedinSalesChannelContext->getToken(), $token);
        static::assertTrue($beforeLoginEventCalled);
        static::assertTrue($loginEventCalled);
        static::assertCount(1, $customerRepository->updates);
        static::assertCount(1, $customerRepository->updates[0]);
        static::assertIsArray($customerRepository->updates[0][0]);
        static::assertCount(2, $customerRepository->updates[0][0]);
        static::assertSame($customer->getId(), $customerRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $customerRepository->updates[0][0]['lastLogin']);
    }

    public function testLoginFailsByInvalidCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->never())
            ->method('restore');

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            $this->createMock(LegacyPasswordVerifier::class),
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials('foo@bar.de', 'invalidPassword', $salesChannelContext);
    }

    public function testGetCustomerByIdThrowsPasswordPoliciesChangedException(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setLegacyPassword('foo');
        $customer->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $customer)
            ->willReturn(true);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $exception = new WriteConstraintViolationException(new ConstraintViolationList([new ConstraintViolation('', '', [], '', '/password', '')]), '/');
        $writeException = new WriteException();
        $writeException->add($exception);

        $customerRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $customer->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $salesChannelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $customerRepository,
            $this->createMock(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $this->createMock(CartRestorer::class),
        );

        $this->expectException(PasswordPoliciesUpdatedException::class);
        $this->expectExceptionMessage('Password policies updated.');
        $accountService->getCustomerByLogin('user', 'password', $salesChannelContext);
    }

    public function testGetCustomerByIdIgnoresOtherWriteViolations(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setLegacyPassword('foo');
        $customer->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $customer)
            ->willReturn(true);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $exception = CustomerException::badCredentials();
        $writeException = new WriteException();
        $writeException->add($exception);

        $customerRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $customer->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $salesChannelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $customerRepository,
            $this->createMock(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $this->createMock(CartRestorer::class),
        );

        $this->expectException(WriteException::class);
        $accountService->getCustomerByLogin('user', 'password', $salesChannelContext);
    }

    public function testSetDefaultBillingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();

        static::assertNotNull($customer);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('billing-address-id', AbstractSwitchDefaultAddressRoute::TYPE_BILLING, $context, $customer);

        $accountService = new AccountService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LegacyPasswordVerifier::class),
            $switcher,
            $this->createMock(CartRestorer::class),
        );

        $accountService->setDefaultBillingAddress('billing-address-id', $context, $customer);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();

        static::assertNotNull($customer);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('shipping-address-id', AbstractSwitchDefaultAddressRoute::TYPE_SHIPPING, $context, $customer);

        $accountService = new AccountService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LegacyPasswordVerifier::class),
            $switcher,
            $this->createMock(CartRestorer::class),
        );

        $accountService->setDefaultShippingAddress('shipping-address-id', $context, $customer);
    }

    public function testLoginById(): void
    {
        $context = Generator::generateSalesChannelContext();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setBoundSalesChannelId($context->getSalesChannel()->getId());
        $customer->setEmail('foo@bar.de');

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($context, $customer): bool {
                if ($event instanceof CustomerBeforeLoginEvent) {
                    static::assertSame($context, $event->getSalesChannelContext());
                    static::assertSame($customer->getEmail(), $event->getEmail());

                    return true;
                }

                if ($event instanceof CustomerLoginEvent) {
                    static::assertSame($customer, $event->getCustomer());

                    return true;
                }

                return false;
            }));

        $accountService = new AccountService(
            $repo,
            $dispatcher,
            $this->createMock(LegacyPasswordVerifier::class),
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $this->createMock(CartRestorer::class),
        );

        $accountService->loginById($customer->getId(), $context);
    }

    public function testLoginByIdWithNonValidId(): void
    {
        $context = Generator::generateSalesChannelContext();

        $accountService = new AccountService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LegacyPasswordVerifier::class),
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $this->createMock(CartRestorer::class),
        );

        $this->expectException(BadCredentialsException::class);

        $accountService->loginById('foo', $context);
    }

    public function testLoginByIdNotFound(): void
    {
        $context = Generator::generateSalesChannelContext();

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                0,
                new CustomerCollection(),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $accountService = new AccountService(
            $repo,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LegacyPasswordVerifier::class),
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $this->createMock(CartRestorer::class),
        );

        $this->expectException(CustomerNotFoundByIdException::class);

        $accountService->loginById(Uuid::randomHex(), $context);
    }
}
