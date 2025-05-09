<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class PluginLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PLUGIN_LOADED_EVENT => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<PluginEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $plugin) {
            if ($plugin->getIconRaw()) {
                $plugin->setIcon(base64_encode($plugin->getIconRaw()));
            }
        }
    }
}
