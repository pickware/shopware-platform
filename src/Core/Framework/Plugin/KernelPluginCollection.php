<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Dependency\PluginDependencyBundleResolver;

class KernelPluginCollection
{
    /**
     * @var Plugin[]
     */
    private $plugins;

    /**
     * @var PluginDependencyBundleResolver|null
     */
    private $pluginDependencyBundleResolver;

    /**
     * @var bool
     */
    private $sealed;

    /**
     * @param Plugin[] $plugins
     */
    public function __construct(array $plugins = [])
    {
        $this->plugins = [];
        foreach ($plugins as $plugin) {
            $this->add($plugin);
        }
    }

    public function add(Plugin $plugin): void
    {
        if ($this->sealed) {
            throw new \LogicException(
                'Cannot add more plugins to KernelPluginCollection because it is already sealed.'
            );
        }

        /** @var string|false $class */
        $class = \get_class($plugin);
        if ($class === false) {
            return;
        }

        if ($this->has($class)) {
            return;
        }

        $this->plugins[$class] = $plugin;
    }

    public function seal(): void
    {
        $this->sealed = true;
    }

    public function has($name): bool
    {
        return array_key_exists($name, $this->plugins);
    }

    public function get($name): ?Plugin
    {
        return $this->has($name) ? $this->plugins[$name] : null;
    }

    /**
     * @return Plugin[]
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * @return Plugin[]
     */
    public function getActives(): array
    {
        return array_filter($this->plugins, function (Plugin $plugin) {
            return $plugin->isActive();
        });
    }

    public function getPluginDependencyBundles(ClassLoader $classLoader): array
    {
        if ($this->pluginDependencyBundleResolver === null) {
            $this->seal();
            $this->pluginDependencyBundleResolver = new PluginDependencyBundleResolver(array_values($this->plugins), $classLoader);
        }

        return $this->pluginDependencyBundleResolver->getResolvedBundles();
    }

    public function filter(\Closure $closure): object
    {
        return new static(array_filter($this->plugins, $closure));
    }
}
