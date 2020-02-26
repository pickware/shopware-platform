<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

class ProductCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testDeliveryInformation(): void
    {
        $product = $this->getLineItem();

        static::assertInstanceOf(DeliveryInformation::class, $product->getDeliveryInformation());

        $info = $product->getDeliveryInformation();
        static::assertEquals(100, $info->getWeight());
        static::assertEquals(101, $info->getHeight());
        static::assertEquals(102, $info->getWidth());
        static::assertEquals(103, $info->getLength());
    }

    public function testPurchasePrices(): void
    {
        $purchasePrice = $this->getLineItem()->getPurchasePrice();

        static::assertInstanceOf(PriceCollection::class, $purchasePrice);
        static::assertEquals(1.5, $purchasePrice->getCurrencyPrice(Defaults::CURRENCY)->getGross());
        static::assertEquals(1.0, $purchasePrice->getCurrencyPrice(Defaults::CURRENCY)->getNet());
    }

    private function getLineItem(): LineItem
    {
        $this->createProduct();
        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $service = $this->getContainer()->get(CartService::class);

        $token = $this->ids->create('token');
        $context = $this->getContainer()->get(SalesChannelContextService::class)
            ->get(Defaults::SALES_CHANNEL, $token);

        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        return $cart->get($product->getId());
    }

    private function createProduct(): void
    {
        $data = [
            'id' => $this->ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'purchasePrice' => 1.5,
            'purchasePrices' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 1.5, 'net' => 1.0, 'linked' => false],
            ],
            'active' => true,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'weight' => 100,
            'height' => 101,
            'width' => 102,
            'length' => 103,
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
