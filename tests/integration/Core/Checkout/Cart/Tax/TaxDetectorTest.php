<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class TaxDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUseGrossPrices(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(true);
        $context->expects($this->once())->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->useGross($context));
    }

    public function testDoNotUseGrossPrices(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(false);
        $context->expects($this->once())->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->useGross($context));
    }

    public function testIsNetDelivery(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();

        $country->setCustomerTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));
        $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTax(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);
        $data = [
            'id' => $country->getId(),
            'customerTax' => [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
        ];

        $countryRepository->update([$data], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);

        $customer = new CustomerEntity();
        $customer->setCompany('ABC Company');
        $customer->setVatIds(['DE123123123']);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $context->expects($this->once())->method('getCustomer')->willReturn(
            $customer
        );

        $taxDetector = static::getContainer()->get(TaxDetector::class);

        static::assertTrue($taxDetector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTax(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => false,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTaxAndWrongVatIdPattern(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);
        $data = [
            'id' => $deCountry->getId(),
            'customerTax' => [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ];

        $countryRepository->update([$data], Context::createDefaultContext());
        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);

        $customer = new CustomerEntity();
        $customer->setCompany('ABC Company');
        $customer->setVatIds(['VN123123']);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($deCountry)
        );

        $context->expects($this->once())->method('getCustomer')->willReturn(
            $customer
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTaxAndNullVatId(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'vatIdPattern' => '...',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'ABC Compay',
            'vatIds' => [null],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $context->expects($this->once())->method('getCustomer')->willReturn(
            $customer
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTaxAndWrongVatIdButVatIdCheckDisabled(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);
        $data = [
            'id' => $deCountry->getId(),
            'customerTax' => [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => false,
        ];

        $countryRepository->update([$data], Context::createDefaultContext());
        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);

        $customer = new CustomerEntity();
        $customer->setCompany('ABC Company');
        $customer->setVatIds(['VN123123']);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($deCountry)
        );

        $context->expects($this->once())->method('getCustomer')->willReturn(
            $customer
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNotNetDelivery(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => false,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }
}
