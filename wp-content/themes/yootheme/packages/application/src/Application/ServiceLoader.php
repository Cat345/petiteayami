<?php

namespace YOOtheme\Application;

use YOOtheme\Container;
use YOOtheme\Container\Service;

/**
 * @phpstan-type ServiceDefinition array{class?: string, factory?: callable|string, arguments?: array<string, mixed>, shared?: bool}
 */
class ServiceLoader
{
    /**
     * Load services configuration∆.
     *
     * @param list<array<string, string|callable|Service|ServiceDefinition>> $configs
     */
    public function __invoke(Container $container, array $configs): void
    {
        $config = array_merge(...$configs);

        foreach ($config as $id => $service) {
            if (is_array($service)) {
                $definition = $container->add($id);

                if (isset($service['class'])) {
                    $definition->setClass($service['class']);
                }

                if (isset($service['factory'])) {
                    $definition->setFactory($service['factory']);
                }

                if (isset($service['arguments'])) {
                    $definition->setArguments($service['arguments']);
                }

                if (isset($service['shared'])) {
                    $definition->setShared($service['shared']);
                }
            } else {
                $container->add($id, $service);
            }
        }
    }
}
