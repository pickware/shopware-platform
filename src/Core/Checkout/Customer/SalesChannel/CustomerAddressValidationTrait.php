<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
trait CustomerAddressValidationTrait
{
    private function validateAddress(string $id, SalesChannelContext $context, CustomerEntity $customer): void
    {
        $criteria = (new Criteria([$id]))
            ->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $total = $this->addressRepository->searchIds($criteria, $context->getContext())->getTotal();
        if ($total !== 0) {
            return;
        }

        throw CustomerException::addressNotFound($id);
    }
}
