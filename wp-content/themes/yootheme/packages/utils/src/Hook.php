<?php

namespace YOOtheme;

abstract class Hook
{
    protected static ?HookCollection $collection;

    /**
     * Call hooks for given name.
     *
     * @param  string|string[] $name
     * @return mixed
     */
    public static function call($name, callable $callback, ...$arguments)
    {
        return static::getCollection()->call($name, $callback, ...$arguments);
    }

    /**
     * Add "wrap" hook for given name.
     */
    public static function wrap(string $name, callable $callback): void
    {
        static::getCollection()->wrap($name, $callback);
    }

    /**
     * Add "before" hook for given name.
     */
    public static function before(string $name, callable $callback): void
    {
        static::getCollection()->before($name, $callback);
    }

    /**
     * Add "after" hook for given name.
     */
    public static function after(string $name, callable $callback): void
    {
        static::getCollection()->after($name, $callback);
    }

    /**
     * Add "filter" hook for given name.
     */
    public static function filter(string $name, callable $callback): void
    {
        static::getCollection()->filter($name, $callback);
    }

    /**
     * Add "error" hook for given name.
     */
    public static function error(string $name, callable $callback): void
    {
        static::getCollection()->error($name, $callback);
    }

    /**
     * Removes hook for given name.
     */
    public static function remove(string $name, callable $callback): void
    {
        static::getCollection()->remove($name, $callback);
    }

    /**
     * Returns hook collection.
     */
    public static function getCollection(): HookCollection
    {
        return static::$collection ?? (static::$collection = new HookCollection());
    }
}
