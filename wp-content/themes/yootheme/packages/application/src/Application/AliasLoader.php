<?php

namespace YOOtheme\Application;

use YOOtheme\Container;

class AliasLoader
{
    /**
     * Load service aliases.
     *
     * @param list<array<string, string|list<string>>> $configs
     */
    public function __invoke(Container $container, array $configs): void
    {
        foreach ($configs as $config) {
            foreach ($config as $id => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $container->setAlias($id, $alias);
                }
            }
        }
    }
}
