<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class CurrencyLoadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CurrencyEvents::CURRENCY_LOADED_EVENT => 'setDefault',
            'currency.partial_loaded' => 'setDefault',
        ];
    }

    /**
     * @param EntityLoadedEvent<CurrencyEntity|PartialEntity> $event
     */
    public function setDefault(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            $entity->assign([
                'isSystemDefault' => ($entity->get('id') === Defaults::CURRENCY),
            ]);
        }
    }
}
