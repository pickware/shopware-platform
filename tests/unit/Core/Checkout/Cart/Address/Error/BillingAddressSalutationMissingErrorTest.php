<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(BillingAddressSalutationMissingError::class)]
class BillingAddressSalutationMissingErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $address = new CustomerAddressEntity();
        $address->setFirstName('Max');
        $address->setLastName('Mustermann');
        $address->setStreet('Musterstraße 1');
        $address->setZipcode('12345');
        $address->setCity('Musterstadt');
        $address->setId('address-id');

        $error = new BillingAddressSalutationMissingError($address);

        static::assertSame('salutation-missing-billing-address', $error->getId());
        static::assertSame('A salutation needs to be defined for the billing address "Max Mustermann, 12345 Musterstadt".', $error->getMessage());
        static::assertSame('salutation-missing-billing-address', $error->getMessageKey());
        static::assertSame(10, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertSame(['addressId' => 'address-id'], $error->getParameters());
    }
}
