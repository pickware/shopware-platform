<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class MostRecentFailedExecutionDateFieldCalculationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowDefinition::EVENT_LOADED => 'onFlowLoaded',
        ];
    }

    public function onFlowLoaded(EntityLoadedEvent $event): void
    {
        $mostRecentFailedExecutionDatesByFlowId = array_merge(...array_map(
            fn (array $row) => [
                bin2hex($row['flow_id']) => $row['most_recent_failed_execution_date'],
            ],
            $this->connection->fetchAllAssociative(
                'SELECT `flow_id`, MAX(`created_at`) as `most_recent_failed_execution_date`
            FROM `flow_execution`
            WHERE `flow_id` IN (:flowIds)
            AND `successful` = 0
            GROUP BY `flow_id`',
                ['flowIds' => array_map('hex2bin', $event->getIds())],
                ['flowIds' => ArrayParameterType::BINARY],
            )
        ));

        /** @var FlowEntity $flow */
        foreach ($event->getEntities() as $flow) {
            $flowId = $flow->getId();

            if (!isset($mostRecentFailedExecutionDatesByFlowId[$flowId])) {
                continue;
            }

            $flow->assign([
                'mostRecentFailedExecutionDate' => new \DateTimeImmutable($mostRecentFailedExecutionDatesByFlowId[$flowId]),
            ]);
        }
    }
}
