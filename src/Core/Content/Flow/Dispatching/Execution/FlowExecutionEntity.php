<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Execution;

use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceEntity;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class FlowExecutionEntity extends Entity implements IdAware
{
    use EntityIdTrait;

    protected string $flowId;

    protected ?FlowEntity $flow = null;

    protected bool $successful;

    protected ?string $errorMessage;

    protected ?string $failedFlowSequenceId = null;

    protected ?FlowSequenceEntity $failedFlowSequence = null;

    /**
     * @var array<string, mixed>
     */
    protected array $eventData;

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function setFlowId(string $flowId): void
    {
        $this->flowId = $flowId;
    }

    public function getFlow(): ?FlowEntity
    {
        return $this->flow;
    }

    public function setFlow(FlowEntity $flow): void
    {
        $this->flow = $flow;
    }

    public function getSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): void
    {
        $this->successful = $successful;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getFailedFlowSequenceId(): ?string
    {
        return $this->failedFlowSequenceId;
    }

    public function setFailedFlowSequenceId(?string $failedFlowSequenceId): void
    {
        $this->failedFlowSequenceId = $failedFlowSequenceId;
    }

    public function getFailedFlowSequence(): ?FlowSequenceEntity
    {
        return $this->failedFlowSequence;
    }

    public function setFailedFlowSequence(FlowSequenceEntity $failedFlowSequence): void
    {
        $this->failedFlowSequence = $failedFlowSequence;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        return $this->eventData;
    }

    /**
     * @param array<string, mixed> $eventData
     */
    public function setEventData(array $eventData): void
    {
        $this->eventData = $eventData;
    }
}
