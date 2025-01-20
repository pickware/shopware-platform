<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('services-settings')]
class BufferedFlowExecutor implements EventSubscriberInterface, ServiceSubscriberInterface
{
    /**
     * @var array<FlowEventAware>
     */
    private array $bufferedEvents = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EntityRepository $flowExecutionRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return [];
        }

        return [
            BufferFlowExecutionEvent::class => 'handleBufferFlowExecutionEvent',
            KernelEvents::TERMINATE => 'executeBufferedEvents',
        ];
    }

    public function handleBufferFlowExecutionEvent(BufferFlowExecutionEvent $bufferFlowExecutionEvent): void
    {
        $this->bufferedEvents[] = $bufferFlowExecutionEvent->getEvent();
    }

    public function executeBufferedEvents(): void
    {
        do {
            $events = $this->bufferedEvents;
            $this->bufferedEvents = [];
            $flowLoader = $this->container->get(FlowLoader::class);
            $flows = $flowLoader->load();

            foreach ($events as $event) {
                $storableFlow = $this->container->get(FlowFactory::class)->create($event);
                $this->callFlowExecutor($storableFlow, $flows);
            }
        } while (!empty($this->bufferedEvents));
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        return [
            'logger',
            Connection::class,
            FlowFactory::class,
            FlowExecutor::class,
            FlowLoader::class,
        ];
    }

    /**
     * @param array<string, array<array{id: string, name: string, payload: array<mixed>}>> $flowList
     */
    private function callFlowExecutor(StorableFlow $event, array $flowList): void
    {
        $flows = $this->getFlowsForEvent($event->getName(), $flowList);

        if (empty($flows)) {
            return;
        }

        $flowExecutor = $this->container->get(FlowExecutor::class);

        foreach ($flows as $flow) {
            $flowExecutionPayload = [
                'flowId' => $flow['id'],
                'eventData' => $event->stored(),
            ];

            try {
                $flowExecutor->execute($flow['payload'], $event);

                $flowExecutionPayload['successful'] = true;
            } catch (ExecuteSequenceException $e) {
                $this->container->get('logger')->warning(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . 'Sequence id: ' . $e->getSequenceId() . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
                $flowExecutionPayload = array_merge($flowExecutionPayload, [
                    'successful' => false,
                    'errorMessage' => $e->getMessage(),
                    'failedFlowSequenceId' => $e->getSequenceId(),
                ]);

                if ($e->getPrevious() && $this->isInNestedTransaction()) {
                    $flowExecutionPayload = array_merge($flowExecutionPayload, [
                        'successful' => false,
                        'errorMessage' => \sprintf(
                            'Flow failed in nested transaction: %s',
                            $e->getPrevious()->getMessage(),
                        ),
                    ]);

                    /**
                     * If we are already in a nested transaction, that does not have save points enabled, we must inform the caller of the rollback.
                     * We do this via an exception, so that the outer transaction can also be rolled back.
                     *
                     * Otherwise, when it attempts to commit, it would fail.
                     */
                    throw $e->getPrevious();
                }
            } catch (\Throwable $e) {
                $this->container->get('logger')->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
                $flowExecutionPayload = array_merge($flowExecutionPayload, [
                    'successful' => false,
                    'errorMessage' => $e->getMessage(),
                ]);
            } finally {
                $this->flowExecutionRepository->create([$flowExecutionPayload], $event->getContext());
            }
        }
    }

    /**
     * @param array<string, array<array{id: string, name: string, payload: array<mixed>}>> $flowList
     *
     * @return array<string, mixed>
     */
    private function getFlowsForEvent(string $eventName, array $flowList): array
    {
        $result = [];
        if (\array_key_exists($eventName, $flowList)) {
            $result = $flowList[$eventName];
        }

        return $result;
    }

    private function isInNestedTransaction(): bool
    {
        return $this->container->get(Connection::class)->getTransactionNestingLevel() !== 1 && !$this->container->get(Connection::class)->getNestTransactionsWithSavepoints();
    }
}
