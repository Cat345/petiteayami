<?php

namespace YOOtheme\Application;

use YOOtheme\Container;
use YOOtheme\Container\ParameterResolver;
use YOOtheme\Event;
use YOOtheme\EventDispatcher;

class EventLoader
{
    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    public function __construct()
    {
        $this->dispatcher = Event::getDispatcher();
    }

    /**
     * Load event listeners.
     *
     * @param Container $container
     * @param list<array<string, array<class-string, string|list{string, ?int}|list<list{string, ?int}>>>>  $configs
     */
    public function __invoke(Container $container, array $configs): void
    {
        foreach ($configs as $events) {
            foreach ($events as $event => $listeners) {
                foreach ($listeners as $class => $parameters) {
                    $parameters = (array) $parameters;

                    if (is_string($parameters[0])) {
                        $parameters = [$parameters];
                    }

                    foreach ($parameters as $params) {
                        $this->addListener($container, $event, $class, ...$params);
                    }
                }
            }
        }
    }

    /**
     * Adds a listener.
     *
     * @param int $params
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

        $this->dispatcher->addListener(
            $event,
            fn(...$arguments) => $isStatic && !ParameterResolver::needsResolving($listener)
                ? $listener(...$arguments)
                : $container->call($listener, $arguments),
            ...$params,
        );
    }
}
