<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\Plugin\PluginDefinition;

/**
 * @internal
 */
class PluginProtectionExtension extends EntityExtension
{
    public function extendProtections(EntityProtectionCollection $protections): void
    {
        $protections->add(new ReadProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE));
        $protections->add(new WriteProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE));
    }

    public function getEntityName(): string
    {
        return PluginDefinition::ENTITY_NAME;
    }
}
