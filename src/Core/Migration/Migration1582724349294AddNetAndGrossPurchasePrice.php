<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1582724349294AddNetAndGrossPurchasePrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1582724349294;
    }

    public function update(Connection $connection): void
    {
        $this->addPurchasePriceFieldToOrderLineItems($connection);
        $this->migrateProductPurchasePriceField($connection);
        $this->addSynchDatabaseTrigger($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function addPurchasePriceFieldToOrderLineItems(Connection $connection): void
    {
        $connection->executeQuery('ALTER TABLE `order_line_item` ADD `purchase_price` JSON NULL AFTER `price`;');
    }

    private function migrateProductPurchasePriceField(Connection $connection): void
    {
        // Fetch all product and their current purchase prices
        $products = $connection->executeQuery(
            'SELECT
                product.id,
                product.purchase_price,
                tax.tax_rate
            FROM product
            LEFT JOIN tax ON product.tax_id = tax.id'
        )->fetchAll();

        // Change purchase price field
        $connection->executeQuery('ALTER TABLE `product` ADD `purchase_prices` JSON NULL AFTER `purchase_price`;');

        // Insert purchase prices into the new purchase price field
        $defaultCurrencyId = Defaults::CURRENCY;
        foreach ($products as $product) {
            if (!$product['purchase_price']) {
                continue;
            }

            $priceKey = sprintf('c%s', $defaultCurrencyId);
            $connection->executeQuery(
                'UPDATE `product` SET purchase_prices = :purchasePrice WHERE id = :productId',
                [
                    'productId' => $product['id'],
                    'purchasePrice' => json_encode([
                        $priceKey => [
                            'net' => $product['purchase_price'] / (1 + ($product['tax_rate'] / 100)),
                            'gross' => $product['purchase_price'],
                            'linked' => true,
                            'currencyId' => $defaultCurrencyId,
                        ],
                    ]),
                ]
            );
        }
    }

    /**
     * Adds a database trigger that keeps the fields 'purchase_price' and 'purchase_prices' in sync. That means updating
     * either value will update the other.
     */
    private function addSynchDatabaseTrigger(Connection $connection): void
    {
        $connection->exec('CREATE TRIGGER product_purchase_prices_sync BEFORE UPDATE ON product FOR EACH ROW
BEGIN
IF NEW.purchase_prices != OLD.purchase_prices THEN BEGIN

    SET NEW.purchase_price = JSON_EXTRACT(NEW.purchase_prices, \'$.cb7d2554b0ce847cd82f3ac9bd1c0dfca.gross\');

END; ELSE BEGIN

    IF NEW.purchase_price != OLD.purchase_price THEN BEGIN
        DECLARE taxRate DECIMAL(10,2);
        SET taxRate = (SELECT tax_rate FROM tax WHERE id = NEW.tax);

        SET NEW.purchase_prices = CONCAT(
            \'{"cb7d2554b0ce847cd82f3ac9bd1c0dfca": {"net": \',
            CONVERT((NEW.purchase_price / (1 + (taxRate/100))), CHAR(50)),
            \', "gross": \',
            CONVERT(NEW.purchase_price, CHAR(50)),
            \', "linked": true, "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca"}}\'
        );
    END; END IF;

END; END IF;
END
        ');
    }
}
