<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @internal
 */
class RemoteAddressFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRemoteAddressSerializerInvalidField(): void
    {
        $serializer = $this->getSerializer();
        $data = new KeyValuePair('remoteAddress', null, false);

        $this->expectException(DataAbstractionLayerException::class);
        $serializer->encode(
            (new IntField('remote_address', 'remoteAddress'))->addFlags(new ApiAware()),
            EntityExistence::createEmpty(),
            $data,
            $this->getWriteParameterBagMock()
        )->current();
    }

    public function testRemoteAddressSerializerValidField(): void
    {
        $serializer = $this->getSerializer();
        $data = new KeyValuePair('remoteAddress', '127.0.0.1', false);

        $curr = $serializer->encode(
            $this->getRemoteAddressField(),
            EntityExistence::createEmpty(),
            $data,
            $this->getWriteParameterBagMock()
        )->current();

        static::assertNotNull($curr);
    }

    public function testRemoteAddressSerializerAnonymize(): void
    {
        $this->setConfig();

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderCustomer = static::getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(OrderCustomerEntity::class, $orderCustomer);
        static::assertNotSame($remoteAddress, $orderCustomer->getRemoteAddress());
        static::assertSame(IpUtils::anonymize($remoteAddress), $orderCustomer->getRemoteAddress());
    }

    public function testRemoteAddressSerializerNoAnonymize(): void
    {
        $this->setConfig(true);

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderCustomer = static::getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(OrderCustomerEntity::class, $orderCustomer);
        static::assertSame($remoteAddress, $orderCustomer->getRemoteAddress());
    }

    public function testSetRemoteAddressByLogin(): void
    {
        $this->setConfig();

        $customerId = $this->createCustomer();

        static::getContainer()->get(AccountService::class)
            ->loginByCredentials('test@example.com', 'shopware', $this->createSalesChannelContext());

        $criteria = new Criteria([$customerId]);

        $customer = static::getContainer()->get('customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertNotSame('127.0.0.1', $customer->getRemoteAddress());
        static::assertSame(IpUtils::anonymize('127.0.0.1'), $customer->getRemoteAddress());
    }

    private function setConfig(bool $value = false): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.customerIpAddressesNotAnonymously', $value);
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    private function createOrderWithRemoteAddress(string $remoteAddress): string
    {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);

        $customerId = $this->createCustomer();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'remoteAddress' => $remoteAddress,
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        static::getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());

        return $orderId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'remoteAddress' => '127.0.0.1',
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('customer.repository')->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function getSerializer(): RemoteAddressFieldSerializer
    {
        return static::getContainer()->get(RemoteAddressFieldSerializer::class);
    }

    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    private function getRemoteAddressField(): RemoteAddressField
    {
        return new RemoteAddressField('remote_address', 'remoteAddress');
    }
}
