<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

class PriceTransformer
{
    public static function transformCollection(PriceCollection $prices): array
    {
        $output = [];
        foreach ($prices as $price) {
            $output[] = self::transform($price);
        }

        return $output;
    }

    public static function transform(Price $price): array
    {
        return [
            'gross' => $price->getGross(),
            'net' => $price->getNet(),
            'linked' => $price->getLinked(),
            'currencyId' => $price->getCurrencyId(),
            'listPrice' => $price->getListPrice() ? self::transform($price->getListPrice()) : null,
        ];
    }
}
