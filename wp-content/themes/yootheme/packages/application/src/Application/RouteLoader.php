<?php

namespace YOOtheme\Application;

use YOOtheme\Container;
use YOOtheme\Routes;

/**
 * @phpstan-type RouteMethod array{csrf?: bool, allowed?: bool, save?: bool}
 * @phpstan-type Route array{'post'|'get'|'delete'|'put'|'patch', string, string|callable, RouteMethod}
 */
class RouteLoader
{
    /**
     * Load routes.
     *
     * @param list<list<Route>> $configs
     */
    public function __invoke(Container $container, array $configs): void
    {
        $container->extend('routes', static function (Routes $routes) use ($configs) {
            foreach ($configs as $config) {
                foreach ($config as $route) {
                    $routes->map(...$route);
                }
            }
        });
    }
}
