<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Execution;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowExecutionEntity>
 */
#[Package('services-settings')]
class FlowExecutionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FlowExecutionEntity::class;
    }
}
