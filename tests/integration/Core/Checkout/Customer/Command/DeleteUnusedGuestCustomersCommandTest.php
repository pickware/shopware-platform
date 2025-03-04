<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Command\DeleteUnusedGuestCustomersCommand;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('checkout')]
class DeleteUnusedGuestCustomersCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private DeleteUnusedGuestCustomersCommand $command;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->command = static::getContainer()->get(DeleteUnusedGuestCustomersCommand::class);
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestCustomerLifetime', 86400);
    }

    public function testExecuteWithoutUnusedGuestCustomers(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertIsInt(\mb_strpos($string, 'No unused guest customers found.'));
    }

    public function testExecuteWithoutConfirm(): void
    {
        $customerRepository = static::getContainer()->get('customer.repository');

        $customerGuestWithOrder = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerGuest = (new CustomerBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customer = (new CustomerBuilder($this->ids, '10002'))
            ->add('guest', false)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([
            $customerGuestWithOrder->build(),
            $customerGuest->build(),
            $customer->build(),
        ], Context::createDefaultContext());

        $this->createOrderForCustomer($customerGuestWithOrder->build());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertIsInt(\mb_strpos($string, 'Aborting due to user input.'));

        $customers = $customerRepository->search(
            (new Criteria([
                $this->ids->get('10000'),
                $this->ids->get('10001'),
                $this->ids->get('10002'),
            ]))->addAssociation('orderCustomers'),
            Context::createDefaultContext()
        )->getEntities();

        static::assertContains($this->ids->get('10000'), $customers->getIds());
        static::assertContains($this->ids->get('10001'), $customers->getIds());
        static::assertContains($this->ids->get('10002'), $customers->getIds());

        $customerGuestWithOrder = $customers->get($this->ids->get('10000'));
        $customerGuest = $customers->get($this->ids->get('10001'));
        $customer = $customers->get($this->ids->get('10002'));

        static::assertInstanceOf(CustomerEntity::class, $customerGuestWithOrder);
        static::assertNotNull($customerGuestWithOrder->getOrderCustomers());
        static::assertCount(1, $customerGuestWithOrder->getOrderCustomers());
        static::assertTrue($customerGuestWithOrder->getGuest());

        static::assertInstanceOf(CustomerEntity::class, $customerGuest);
        static::assertTrue($customerGuest->getGuest());

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
    }

    public function testExecuteWithConfirm(): void
    {
        $customerRepository = static::getContainer()->get('customer.repository');

        $customerGuestWithOrder = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerGuest = (new CustomerBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customer = (new CustomerBuilder($this->ids, '10002'))
            ->add('guest', false)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([
            $customerGuestWithOrder->build(),
            $customerGuest->build(),
            $customer->build(),
        ], Context::createDefaultContext());

        $this->createOrderForCustomer($customerGuestWithOrder->build());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $customers = $customerRepository->search(
            (new Criteria([
                $this->ids->get('10000'),
                $this->ids->get('10001'),
                $this->ids->get('10002'),
            ]))->addAssociation('orderCustomers'),
            Context::createDefaultContext()
        )->getEntities();

        static::assertContains($this->ids->get('10000'), $customers->getIds());
        static::assertNotContains($this->ids->get('10001'), $customers->getIds());
        static::assertContains($this->ids->get('10002'), $customers->getIds());

        $customerGuestWithOrder = $customers->get($this->ids->get('10000'));
        $customer = $customers->get($this->ids->get('10002'));

        static::assertInstanceOf(CustomerEntity::class, $customerGuestWithOrder);
        static::assertNotNull($customerGuestWithOrder->getOrderCustomers());
        static::assertCount(1, $customerGuestWithOrder->getOrderCustomers());
        static::assertTrue($customerGuestWithOrder->getGuest());

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
    }

    /**
     * @param array<mixed> $customer
     */
    private function createOrderForCustomer(array $customer): string
    {
        $productRepository = static::getContainer()->get('product.repository');
        $orderRepository = static::getContainer()->get('order.repository');

        $product = (new ProductBuilder($this->ids, 'Product-1'))
            ->price(10)
            ->build();

        $productRepository->create([$product], Context::createDefaultContext());

        $orderId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'addresses' => [
                array_merge(
                    $customer['addresses']['default-address'],
                    ['id' => $customer['defaultShippingAddressId']]
                ),
            ],
            'deliveries' => [
                [
                    'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                    'shippingMethodId' => $this->getValidShippingMethodId(),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => date(\DATE_ATOM),
                    'shippingDateLatest' => date(\DATE_ATOM),
                    'shippingOrderAddressId' => $customer['defaultShippingAddressId'],
                    'positions' => [
                        [
                            'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'orderLineItemId' => $this->ids->get('Product-1'),
                        ],
                    ],
                ],
            ],
            'lineItems' => [
                [
                    'id' => $this->ids->get('Product-1'),
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                    'good' => true,
                ],
            ],
            'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
            'orderCustomer' => [
                'email' => $customer['email'],
                'firstName' => $customer['firstName'],
                'lastName' => $customer['lastName'],
                'customerNumber' => $customer['customerNumber'],
                'salutationId' => $customer['salutationId'] ?? $this->getValidSalutationId(),
                'customerId' => $customer['id'],
            ],
            'billingAddressId' => $customer['defaultBillingAddressId'],
        ];

        $orderRepository->create([$order], Context::createDefaultContext());

        return $orderId;
    }
}
