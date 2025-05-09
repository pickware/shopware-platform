<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Package('checkout')]
class TaxRule extends Struct
{
    public function __construct(
        protected float $taxRate,
        protected float $percentage = 100.0
    ) {
        $this->taxRate = FloatComparator::cast($taxRate);
        $this->percentage = FloatComparator::cast($percentage);
    }

    public function getTaxRate(): float
    {
        return FloatComparator::cast($this->taxRate);
    }

    public function getPercentage(): float
    {
        return FloatComparator::cast($this->percentage);
    }

    /**
     * @return array<string, list<Constraint>>
     */
    public static function getConstraints(): array
    {
        return [
            'taxRate' => [new NotBlank(), new Type('numeric')],
            'percentage' => [new Type('numeric')],
        ];
    }

    public function getApiAlias(): string
    {
        return 'cart_tax_rule';
    }
}
