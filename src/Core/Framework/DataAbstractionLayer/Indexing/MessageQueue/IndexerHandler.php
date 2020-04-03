<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class IndexerHandler extends AbstractMessageHandler
{
    /**
     * @var IndexerRegistryInterface
     */
    private $registry;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(IndexerRegistryInterface $registry, MessageBusInterface $bus, LoggerInterface $logger)
    {
        $this->registry = $registry;
        $this->bus = $bus;
        $this->logger = $logger;
    }

    /**
     * @param IndexerMessage $message
     *
     * @throws \Exception
     */
    public function handle($message): void
    {
        $this->logger->info('Handling', ['message' => $message]);
        $result = $this->registry->partial($message->getCurrentIndexerName(), $message->getOffset(), $message->getTimestamp());
        if ($result === null) {
            return;
        }

        $remainingIndexers = $message->getIndexerNames();

        // current indexer is finished
        if ($result->getOffset() === null) {
            array_shift($remainingIndexers);
        }

        if (empty($remainingIndexers)) {
            // no indexers left
            return;
        }

        // add new message for next id or next indexer
        $new = new IndexerMessage($remainingIndexers);
        $new->setOffset($result->getOffset());
        $new->setTimestamp($message->getTimestamp());
        $this->bus->dispatch($new);
    }

    public static function getHandledMessages(): iterable
    {
        return [IndexerMessage::class];
    }
}
