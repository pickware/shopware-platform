<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerGroupSubscriber implements EventSubscriberInterface
{
    private const ROUTE_NAME = 'frontend.account.customer-group-registration.page';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $languageRepository,
        private readonly SeoUrlPersister $persister,
        private readonly SlugifyInterface $slugify
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'customer_group_translation.written' => 'updatedCustomerGroup',
            'customer_group_registration_sales_channels.written' => 'newSalesChannelAddedToCustomerGroup',
            'customer_group_translation.deleted' => 'deleteCustomerGroup',
        ];
    }

    public function newSalesChannelAddedToCustomerGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            /** @var array<string, string> $pk */
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['customerGroupId'];
        }

        if (\count($ids) === 0) {
            return;
        }

        $this->createUrls($ids, $event->getContext());
    }

    public function updatedCustomerGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->hasPayload('registrationTitle')) {
                /** @var array<string, string> $pk */
                $pk = $writeResult->getPrimaryKey();
                $ids[] = $pk['customerGroupId'];
            }
        }

        if (\count($ids) === 0) {
            return;
        }

        $this->createUrls($ids, $event->getContext());
    }

    public function deleteCustomerGroup(EntityDeletedEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            /** @var array<string, string> $pk */
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['customerGroupId'];
        }

        if (\count($ids) === 0) {
            return;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('foreignKey', $ids))
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        /** @var array<string> $ids */
        $ids = array_values($this->seoUrlRepository->searchIds($criteria, $event->getContext())->getIds());

        if (\count($ids) === 0) {
            return;
        }

        $this->seoUrlRepository->delete(array_map(fn (string $id) => ['id' => $id], $ids), $event->getContext());
    }

    /**
     * @param list<string> $ids
     */
    private function createUrls(array $ids, Context $context): void
    {
        $criteria = (new Criteria($ids))
            ->addFilter(new EqualsFilter('registrationActive', true))
            ->addAssociations(['registrationSalesChannels.languages', 'translations']);

        $criteria->getAssociation('registrationSalesChannels')
            ->addFilter(new NandFilter([new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_API)]));

        $groups = $this->customerGroupRepository->search($criteria, $context)->getEntities();
        $buildUrls = [];

        foreach ($groups as $group) {
            if ($group->getRegistrationSalesChannels() === null) {
                continue;
            }

            foreach ($group->getRegistrationSalesChannels() as $registrationSalesChannel) {
                if ($registrationSalesChannel->getLanguages() === null) {
                    continue;
                }

                if ($registrationSalesChannel->getTypeId() === Defaults::SALES_CHANNEL_TYPE_API) {
                    continue;
                }

                /** @var array<string> $languageIds */
                $languageIds = $registrationSalesChannel->getLanguages()->getIds();

                $languageCollection = $this->languageRepository->search(new Criteria($languageIds), $context)->getEntities();

                foreach ($languageIds as $languageId) {
                    $language = $languageCollection->get($languageId);
                    if (!$language) {
                        continue;
                    }

                    $title = $this->getTranslatedTitle($group->getTranslations(), $language);

                    if (empty($title)) {
                        continue;
                    }

                    if (!isset($buildUrls[$languageId])) {
                        $buildUrls[$languageId] = [
                            'urls' => [],
                            'salesChannel' => $registrationSalesChannel,
                        ];
                    }

                    $buildUrls[$languageId]['urls'][] = [
                        'salesChannelId' => $registrationSalesChannel->getId(),
                        'foreignKey' => $group->getId(),
                        'routeName' => self::ROUTE_NAME,
                        'pathInfo' => '/customer-group-registration/' . $group->getId(),
                        'isCanonical' => true,
                        'seoPathInfo' => '/' . $this->slugify->slugify($title),
                    ];
                }
            }
        }

        foreach ($buildUrls as $languageId => $config) {
            $context = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$languageId]
            );

            $this->persister->updateSeoUrls(
                $context,
                self::ROUTE_NAME,
                array_column($config['urls'], 'foreignKey'),
                $config['urls'],
                $config['salesChannel']
            );
        }
    }

    private function getTranslatedTitle(?CustomerGroupTranslationCollection $translations, LanguageEntity $language): string
    {
        if ($translations === null) {
            return '';
        }

        // Requested translation
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === $language->getId() && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        // Inherited translation
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === $language->getParentId() && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        // System Language
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === Defaults::LANGUAGE_SYSTEM && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        return '';
    }
}
