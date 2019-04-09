<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Dependency;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Plugin;

/**
 * Models the resolution algorithm for plugin dependency bundles. The algorithm is straight-forward:
 *
 * 1. Start with a list of all known plugins (active or inactive, installed or uninstalled).
 * 2. For each plugin, ask the plugin to provide descriptors for its dependency bundles.
 * 3. For each of these descriptors, if a dependency bundle with the same name is already known, keep only the one with
 *    the highest version number.
 * 4. This results in a list of resolved plugin dependency descriptors, i.e. a list containing at most one descriptor
 *    for each dependency bundle name. Ask all of them to register their namespaces and return the resulting Bundles.
 */
class PluginDependencyBundleResolver
{
    /**
     * @var Plugin[]
     */
    private $plugins;

    /**
     * @var ClassLoader
     */
    private $classLoader;

    /**
     * @var PluginDependencyBundleDescriptor[]
     */
    private $resolvedDependencyDescriptors = [];

    /**
     * @var array
     */
    private $pluginsDependingOnDependencyBundles = [];

    /**
     * @var PluginDependencyBundle[]|null
     */
    private $resolvedBundles = null;

    /**
     * @param Plugin[] $plugins
     */
    public function __construct(array $plugins, ClassLoader $classLoader)
    {
        $this->plugins = $plugins;
        $this->classLoader = $classLoader;
    }

    /**
     * @return PluginDependencyBundle[]
     */
    public function getResolvedBundles(): array
    {
        if ($this->resolvedBundles !== null) {
            return $this->resolvedBundles;
        }

        $this->resolvedBundles = $this->resolveBundles();

        return $this->resolvedBundles;
    }

    private function resolveBundles(): array
    {
        /* @var Plugin $activePlugin */
        foreach ($this->plugins as $plugin) {
            $dependencyDescriptors = $plugin->getDependencyBundleDescriptors();
            /** @var PluginDependencyBundleDescriptor $dependencyDescriptor */
            foreach ($dependencyDescriptors as $dependencyDescriptor) {
                $this->registerDependencyDescriptor($dependencyDescriptor, $plugin);
            }
        }

        $resolvedBundles = [];
        foreach ($this->resolvedDependencyDescriptors as $dependencyDescriptor) {
            $dependencyBundle = $dependencyDescriptor->getBundle($this->classLoader);
            $dependencyBundle->setVersion($dependencyDescriptor->getVersion());
            $dependencyBundle->setPath($dependencyDescriptor->getPath());
            /** @var Plugin $plugin */
            foreach ($this->pluginsDependingOnDependencyBundles[$dependencyDescriptor->getName()] as $plugin) {
                $plugin->dependencyResolved($dependencyDescriptor);
            }
            $resolvedBundles[] = $dependencyBundle;
        }

        return $resolvedBundles;
    }

    private function registerDependencyDescriptor(PluginDependencyBundleDescriptor $dependencyDescriptor, Plugin $plugin)
    {
        $bundleName = $dependencyDescriptor->getName();

        // Dependency bundle is registered for the first time.
        if (!isset($this->resolvedDependencyDescriptors[$bundleName])) {
            $this->resolvedDependencyDescriptors[$bundleName] = $dependencyDescriptor;
            $this->pluginsDependingOnDependencyBundles[$bundleName] = [$plugin];

            return;
        }

        // When multiple dependency bundles of the same name are registered, resolve the conflict by using the latest
        // version.
        $existingDependencyDescriptor = $this->resolvedDependencyDescriptors[$bundleName];
        if (version_compare($dependencyDescriptor->getVersion(), $existingDependencyDescriptor->getVersion(), '>')) {
            $this->resolvedDependencyDescriptors[$bundleName] = $dependencyDescriptor;
        }
        $this->pluginsDependingOnDependencyBundles[$bundleName][] = $plugin;
    }
}
