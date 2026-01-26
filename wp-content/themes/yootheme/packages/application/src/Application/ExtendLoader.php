<?php

namespace YOOtheme\Application;

use YOOtheme\Container;

class ExtendLoader
{
    /**
     * Load service extenders.
     *
     * @param list<array<string, callable>> $configs
     */
    public function __invoke(Container $container, array $configs): void
    {
        foreach ($configs as $config) {
            foreach ($config as $id => $callback) {
                $container->extend($id, $callback);
            }
        }
    }
}
