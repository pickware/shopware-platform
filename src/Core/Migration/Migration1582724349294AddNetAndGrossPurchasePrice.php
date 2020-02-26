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
        $this->addTriggerToNewPurchasePriceField($connection);
        $this->addTriggerToOldPurchasePriceField($connection);
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

    private function addTriggerToNewPurchasePriceField(Connection $connection): void
    {
        $connection->exec('CREATE TRIGGER product_purchase_price_to_new BEFORE UPDATE ON product FOR EACH ROW
BEGIN

IF NEW.purchase_prices != OLD.purchase_prices THEN BEGIN

    DECLARE finished TINYINT(1) DEFAULT 0;
    DECLARE currencyId BINARY(16) DEFAULT NULL;
    DECLARE currencyKey CHAR(50) DEFAULT NULL;
    DECLARE currencyJsonPath CHAR(50) DEFAULT NULL;
    DECLARE cursorCurrency CURSOR FOR SELECT id FROM currency;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

    OPEN cursorCurrency;
    findUsedCurrency: LOOP
        FETCH cursorCurrency INTO currencyId;
        IF finished = 1 THEN BEGIN
            LEAVE findUsedCurrency;
        END; END IF;

        SET currencyKey = LOWER(CONCAT(\'c\', CONVERT(HEX(currencyId), CHAR(50))));
        SET currencyJsonPath = CONCAT(\'$."\', currencyKey, \'"\');

        IF JSON_CONTAINS_PATH(NEW.purchase_prices, \'one\', currencyJsonPath) THEN BEGIN
           SET NEW.purchase_price = JSON_EXTRACT(JSON_EXTRACT(NEW.purchase_prices, currencyJsonPath), \'$."gross"\');
           LEAVE findUsedCurrency;
        END; END IF;

    END LOOP findUsedCurrency;

END; END IF;

END
        ');
    }

    private function addTriggerToOldPurchasePriceField(Connection $connection): void
    {
        $connection->exec('CREATE TRIGGER product_purchase_price_to_old BEFORE UPDATE ON product FOR EACH ROW
BEGIN

IF NEW.purchase_price != OLD.purchase_price THEN BEGIN

DECLARE finished TINYINT(1) DEFAULT 0;
DECLARE currencyId BINARY(16) DEFAULT NULL;
DECLARE currencyKey CHAR(50) DEFAULT NULL;
DECLARE currencyJsonPath CHAR(50) DEFAULT NULL;
DECLARE cursorCurrency CURSOR FOR SELECT id FROM currency;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

OPEN cursorCurrency;
findUsedCurrency: LOOP
    FETCH cursorCurrency INTO currencyId;
    IF finished = 1 THEN BEGIN
        LEAVE findUsedCurrency;
    END; END IF;

    SET currencyKey = LOWER(CONCAT(\'c\', CONVERT(HEX(currencyId), CHAR(100))));
    SET currencyJsonPath = CONCAT(\'$."\', currencyKey, \'"\');

	IF JSON_CONTAINS_PATH(NEW.price, \'one\', currencyJsonPath) THEN BEGIN
	    LEAVE findUsedCurrency;
    END; ELSE BEGIN
    	SET currencyKey = NULL;
    END; END IF;
END LOOP findUsedCurrency;

IF (currencyKey IS NOT NULL) THEN BEGIN
    DECLARE taxRate DECIMAL(10,2);
    SET taxRate = (SELECT tax_rate FROM tax WHERE id = NEW.tax);

    SET NEW.purchase_prices = CONCAT(
        \'{"\',
        currencyKey,
        \'": {"net": \',
        CONVERT((NEW.purchase_price / (1 + (taxRate/100))), CHAR(50)),
        \', "gross": \',
        CONVERT(NEW.purchase_price, CHAR(50)),
        \', "linked": true, "currencyId": "\',
        LOWER(CONVERT(HEX(currencyId), CHAR(100))),
        \'"}}\'
    );

END; END IF;

END; END IF;

END
        ');
    }
}
