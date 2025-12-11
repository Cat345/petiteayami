<?php

namespace YOOtheme\Application;

use YOOtheme\Container;
use YOOtheme\Event;
use YOOtheme\EventDispatcher;

class EventLoader
{
    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->dispatcher = Event::getDispatcher();
    }

    /**
     * Load event listeners.
     */
    public function __invoke(Container $container, array $configs)
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
     */
    public function addListener(
        Container $container,
        string $event,
        string $class,
        string $method,
        ...$params
    ): void {
        $this->dispatcher->addListener(
            $event,
            fn(...$arguments) => $container->call(
                $method[0] === '@' ? $class . $method : [$class, $method],
                $arguments,
            ),
            ...$params,
        );
    }
}
