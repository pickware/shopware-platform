<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface SnippetValidatorInterface
{
    public function validate(): array;
}
