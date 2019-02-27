<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Dependency;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * A description of a versioned bundle which one is required as a dependency by one or more plugins.
 *
 * This class allows the Shopware kernel to coordinate loading of shared, versioned plugin dependency bundles by first
 * resolving which version of the plugin dependency to give preference to (which should always be the one with the
 * highest version number), and only then asking the plugin to register the dependency bundle's namespace and actually
 * constructing the bundle class by calling the passed $bundleCreator closure. This layer of indirection is required to
 * ensure that the correct versions of the bundle's namespaces are registered, resulting in the correct version of the
 * bundle's classes being loaded - because once PHP has loaded a class, the class cannot be redefined.
 */
class PluginDependencyBundleDescriptor
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $version;

    /**
     * @var callable
     */
    private $bundleCreator;

    /**
     * @var Bundle|null
     */
    private $bundle;

    /**
     * @param callable $bundleCreator A closure which registers the bundle's namespace if required and returns an
     *                                instance of the bundle
     */
    public function __construct(string $name, string $version, callable $bundleCreator)
    {
        $this->name = $name;
        $this->version = $version;
        $this->bundleCreator = $bundleCreator;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getBundle(): Bundle
    {
        if (!$this->bundle) {
            $this->bundle = ($this->bundleCreator)();
        }

        return $this->bundle;
    }
}
