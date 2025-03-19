<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
#[Package('checkout')]
class PaymentMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
                ['id' => $this->ids->get('payment2')],
                ['id' => $this->ids->get('payment3')],
            ],
        ]);
    }

    public function testLoading(): void
    {
        $this->browser->request('POST', '/store-api/payment-method');

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $ids = array_column($response['elements'], 'id');

        static::assertSame(3, $response['total']);
        static::assertContains($this->ids->get('payment'), $ids);
        static::assertContains($this->ids->get('payment2'), $ids);
        static::assertContains($this->ids->get('payment3'), $ids);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(PaymentMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/payment-method',
            [
                'includes' => [
                    'payment_method' => [
                        'name',
                    ],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => 'Payment 1',
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2099-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => 'Payment 2',
                'technicalName' => 'payment_test2',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2099-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('payment3'),
                'name' => 'Payment 3',
                'technicalName' => 'payment_test3',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2000-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('payment_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
