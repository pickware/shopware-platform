<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

abstract class AbstractLineItemPurchasePriceRule extends Rule
{
    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = $amount;
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchPurchasePriceCondition($scope->getLineItem(), $scope->getContext());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchPurchasePriceCondition($lineItem, $scope->getContext())) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    abstract public function getName(): string;

    abstract protected function getPriceAmount(LineItem $lineItem, string $currencyId): ?float;

    /**
     * @throws PayloadKeyNotFoundException
     * @throws UnsupportedOperatorException
     */
    private function matchPurchasePriceCondition(LineItem $lineItem, Context $context): bool
    {
        $purchaseAmount = $this->getPriceAmount($lineItem, $context->getCurrencyId());
        if (!$purchaseAmount) {
            return false;
        }

        $this->amount = (float) $this->amount;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($purchaseAmount, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($purchaseAmount, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($purchaseAmount, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($purchaseAmount, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($purchaseAmount, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($purchaseAmount, $this->amount);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
