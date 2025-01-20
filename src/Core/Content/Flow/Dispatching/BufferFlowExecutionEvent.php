<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('services-settings')]
class BufferFlowExecutionEvent extends Event
{
    public function __construct(
        private readonly FlowEventAware $event,
    ) {
    }

    public function getEvent(): FlowEventAware
    {
        return $this->event;
    }
}
