<?php

namespace YOOtheme;

abstract class Event
{
    protected static ?EventDispatcher $dispatcher = null;

    /**
     * Adds an event listener.
     */
    public static function on(string $event, callable $listener, int $priority = 0): void
    {
        static::getDispatcher()->addListener($event, $listener, $priority);
    }

    /**
     * Removes an event listener.
     */
    public static function off(string $event, ?callable $listener = null): bool
    {
        return static::getDispatcher()->removeListener($event, $listener);
    }

    /**
     * Emits an event with arguments.
     *
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    public static function emit(string $event, ...$arguments)
    {
        return static::getDispatcher()->dispatch($event, ...$arguments);
    }

    /**
     * Gets the event dispatcher instance.
     */
    public static function getDispatcher(): EventDispatcher
    {
        return static::$dispatcher ??= new EventDispatcher();
    }
}
