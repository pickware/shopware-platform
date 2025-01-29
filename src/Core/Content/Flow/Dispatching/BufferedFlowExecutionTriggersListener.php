<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class BufferedFlowExecutionTriggersListener implements EventSubscriberInterface, ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly BufferedFlowQueue $bufferedFlowQueue,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
//        if (!Feature::isActive('v6.7.0.0')) {
//            return [];
//        }

        return [
            KernelEvents::TERMINATE => 'triggerBufferedFlowExecution',
            WorkerMessageHandledEvent::class => 'triggerBufferedFlowExecution',
            ConsoleEvents::TERMINATE => 'triggerBufferedFlowExecution',
        ];
    }

    public function triggerBufferedFlowExecution(): void
    {
        if ($this->bufferedFlowQueue->isEmpty()) {
            return;
        }
        xdebug_break();

        $this->container->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        return [
            BufferedFlowExecutor::class,
        ];
    }
}
