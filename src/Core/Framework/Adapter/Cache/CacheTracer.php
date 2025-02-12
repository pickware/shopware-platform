<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @deprecated tag:v6.7.0 - reason:decoration-will-be-removed - Will be removed, cache tags are collected via AddCacheTagEvent
 *
 * @extends AbstractCacheTracer<mixed|null>
 */
#[Package('framework')]
class CacheTracer extends AbstractCacheTracer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $config,
        private readonly AbstractTranslator $translator,
        private readonly CacheTagCollection $collection
    ) {
    }

    public function getDecorated(): AbstractCacheTracer
    {
        throw new DecorationPatternException(self::class);
    }

    public function trace(string $key, \Closure $param)
    {
        return $this->collection->trace($key, fn () => $this->translator->trace($key, fn () => $this->config->trace($key, $param)));
    }

    public function get(string $key): array
    {
        return array_merge(
            $this->collection->getTrace($key),
            $this->config->getTrace($key),
            $this->translator->getTrace($key)
        );
    }
}
