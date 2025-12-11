<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Application\EventLoader;
use YOOtheme\Container;

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
        add_filter(
            $event,
            fn(...$arguments) => $container->call(
                $method[0] === '@' ? $class . $method : [$class, $method],
                $arguments,
            ),
            ...$params,
        );
    }
}
