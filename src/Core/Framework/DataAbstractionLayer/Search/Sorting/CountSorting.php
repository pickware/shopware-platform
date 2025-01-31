<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class CountSorting extends FieldSorting
{
    protected string $type = 'count';
}
