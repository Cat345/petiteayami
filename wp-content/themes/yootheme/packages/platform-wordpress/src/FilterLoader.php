<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Application\EventLoader;
use YOOtheme\Container;
use YOOtheme\Container\ParameterResolver;

class FilterLoader extends EventLoader
{
    /**
     * Adds a listener.
     */
    public function addListener(
        Container $container,
        string $event,
        string $class,
        string $method,
        ...$params
    ): void {
        $isStatic = $method[0] !== '@';
        $listener = $isStatic ? [$class, $method] : $class . $method;

        add_filter(
            $event,
            fn(...$arguments) => $isStatic && !ParameterResolver::needsResolving($listener)
                ? $listener(...$arguments)
                : $container->call($listener, $arguments),
            ...$params,
        );
    }
}
