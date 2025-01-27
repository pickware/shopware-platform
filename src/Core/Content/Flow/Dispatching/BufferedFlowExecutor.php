<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
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
    private const MAXIMUM_EXECUTION_DEPTH = 10;

    public function __construct(
        private readonly ContainerInterface $container,
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
        $flowLoader = $this->container->get(FlowLoader::class);
        $flowFactory = $this->container->get(FlowFactory::class);
        $batchCounter = 0;

        # Always attempt to execute the buffered events at least once, if the buffer is empty nothing will happen.
        # If after the first iteration the buffer is still not empty, this means that the triggered flows added new
        # events to the buffer, so we execute them as well.
        do {
            $events = $this->bufferedEvents;
            $this->bufferedEvents = [];
            $flows = $flowLoader->load();

            foreach ($events as $event) {
                $storableFlow = $flowFactory->create($event);
                $this->callFlowExecutor($storableFlow, $flows);
            }

            $batchCounter++;
        } while (!empty($this->bufferedEvents) && $batchCounter < self::MAXIMUM_EXECUTION_DEPTH);

        if ($batchCounter >= self::MAXIMUM_EXECUTION_DEPTH) {
            $eventNames = array_map(
                static fn (FlowEventAware $event) => $event->getName(),
                $this->bufferedEvents
            );

            $this->container->get('logger')->error(
                'Maximum execution depth reached for buffered flow executor. This might be caused by a cyclic flow execution.',
                ['bufferedEvents' => $eventNames],
            );
        }
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
            try {
                $flowExecutor->execute($flow['payload'], $event);
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
            } catch (\Throwable $e) {
                $this->container->get('logger')->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
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
}
