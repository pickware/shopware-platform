<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollectionBuilder as SymfonyRouteCollectionBuilder;

class RouteCollectionBuilder extends SymfonyRouteCollectionBuilder
{
    const LATEST_API_VERSION = 2;
    const LATEST_API_VERSION_PATH_PREFIX = '/api/v' . self::LATEST_API_VERSION . '/';
    const LATEST_API_VERSION_GENERIC_PATH_PREFIX = '/api/latest/';

    const PREVIOUS_API_VERSION = self::LATEST_API_VERSION - 1;
    const PREVIOUS_API_VERSION_IDENTIFIER = 'v' . self::PREVIOUS_API_VERSION;
    const PREVIOUS_API_VERSION_PATH_PREFIX = '/api/' . self::PREVIOUS_API_VERSION_IDENTIFIER . '/';

    public function mount($prefix, SymfonyRouteCollectionBuilder $builder): void
    {
        // Use reflection to get the builder's routes
        $reflectionClass = new \ReflectionClass($builder);
        $routesProperty = $reflectionClass->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($builder);

        $allRoutes = $routes;
        foreach ($routes as $name => $route) {
            $path = $route->getPath();
            if (mb_stripos($path, self::LATEST_API_VERSION_GENERIC_PATH_PREFIX) !== 0) {
                continue;
            }

            // Update existing route for 'latest' API version
            $actualPath = mb_substr($path, mb_strlen(self::LATEST_API_VERSION_GENERIC_PATH_PREFIX));
            $route->setPath(self::LATEST_API_VERSION_PATH_PREFIX . $actualPath);

            // Check for same route of an old API version
            $oldApiVersionRouteDefined = false;
            $oldApiVersionPath = self::PREVIOUS_API_VERSION_PATH_PREFIX . $actualPath;
            foreach ($routes as $otherRoute) {
                if ($otherRoute->getPath() === $oldApiVersionPath) {
                    $oldApiVersionRouteDefined = true;
                    break;
                }
            }

            if (!$oldApiVersionRouteDefined) {
                // Clone the 'latest' route for the old API version
                $clonedRoute = clone $route;
                $clonedRoute->setPath($oldApiVersionPath);
                $allRoutes[$name . '.' . self::PREVIOUS_API_VERSION_IDENTIFIER] = $clonedRoute;
            }
        }

        $routesProperty->setValue($builder, $allRoutes);

        parent::mount($prefix, $builder);
    }
}
