<?php

/**
 * Route collector that introspects the Laravel router directly.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing\Collectors
 */

namespace Equidna\LaravelDocbot\Routing\Collectors;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Equidna\LaravelDocbot\Contracts\RouteCollector;

/**
 * Produces normalized route metadata for downstream doc writers.
 */
final class RouterRouteCollector implements RouteCollector
{
    /**
     * Creates a new collector instance.
     *
     * @param Router $router Application router instance.
     */
    public function __construct(private Router $router)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function collect(): array
    {
        $routeCollection = $this->router->getRoutes();

        /** @var array<int, Route> $routes */
        $routes = method_exists($routeCollection, 'getRoutes')
            ? $routeCollection->getRoutes()
            : [];

        $described = [];

        foreach ($routes as $route) {
            if (!$route instanceof Route) {
                continue;
            }

            $described[] = $this->describeRoute($route);
        }

        return $described;
    }

    /**
     * Builds a normalized route descriptor from the Illuminate route object.
     *
     * @param  Route $route
     * @return array<string, mixed>
     */
    private function describeRoute(Route $route): array
    {
        $uri = ltrim($route->uri(), '/');

        $methods = array_values(
            array_filter(
                $route->methods(),
                static function ($method): bool {
                    if (!is_string($method)) {
                        return false;
                    }

                    return !in_array($method, ['HEAD', 'OPTIONS'], true);
                },
            ),
        );

        $pathParameters = [];

        preg_match_all(
            '/\{(\w+)\??\}/',
            $uri,
            $matches,
        );

        if (!empty($matches[1])) {
            $pathParameters = array_values(array_unique($matches[1]));
        }

        return [
            'methods' => $methods,
            'uri' => $uri,
            'name' => $route->getName(),
            'action' => $route->getActionName(),
            'middleware' => $route->gatherMiddleware(),
            'domain' => $route->getDomain(),
            'path_parameters' => $pathParameters,
        ];
    }
}
