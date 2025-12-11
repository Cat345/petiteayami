<?php

namespace YOOtheme;

class HookCollection
{
    protected const WRAP = 'wrap';
    protected const BEFORE = 'before';
    protected const AFTER = 'after';
    protected const FILTER = 'filter';
    protected const ERROR = 'error';
    protected array $hooks = [];

    /**
     * Call hooks for given name.
     *
     * @param  string|string[] $name
     * @return mixed
     */
    public function call($name, callable $callback, ...$arguments)
    {
        $names = is_array($name) ? array_reverse($name) : [$name];

        foreach ($names as $name) {
            if ($hooks = $this->hooks[$name] ?? null) {
                $callback = $this->build($callback, $hooks);
            }
        }

        return $callback(...$arguments);
    }

    /**
     * Add "wrap" hook for given name.
     */
    public function wrap(string $name, callable $callback): void
    {
        $this->hooks[$name][] = [static::WRAP, $callback];
    }

    /**
     * Add "before" hook for given name.
     */
    public function before(string $name, callable $callback): void
    {
        $this->hooks[$name][] = [static::BEFORE, $callback];
    }

    /**
     * Add "after" hook for given name.
     */
    public function after(string $name, callable $callback): void
    {
        $this->hooks[$name][] = [static::AFTER, $callback];
    }

    /**
     * Add "filter" hook for given name.
     */
    public function filter(string $name, callable $callback): void
    {
        $this->hooks[$name][] = [static::FILTER, $callback];
    }

    /**
     * Add "error" hook for given name.
     */
    public function error(string $name, callable $callback): void
    {
        $this->hooks[$name][] = [static::ERROR, $callback];
    }

    /**
     * Removes hook for given name.
     */
    public function remove(string $name, callable $callback): void
    {
        $this->hooks[$name] = array_filter(
            $this->hooks[$name] ?? [],
            fn($hook) => $hook[1] === $callback,
        );
    }

    /**
     * Builds a function from given hook array.
     */
    protected function build(callable $method, array $hooks): callable
    {
        $errors = [];

        foreach ($hooks as $i => [$kind, $callback]) {
            if ($kind === static::ERROR) {
                $errors[] = [$kind, $callback];
                unset($hooks[$i]);
            }
        }

        return array_reduce([...$hooks, ...$errors], [$this, 'reduce'], $method);
    }

    /**
     * Returns a function for given hook.
     */
    protected function reduce(callable $method, array $hook): \Closure
    {
        [$kind, $callback] = $hook;

        if ($kind === static::WRAP) {
            return function (...$arguments) use ($method, $callback) {
                return $callback($method, ...$arguments);
            };
        }

        if ($kind === static::BEFORE) {
            return function (...$arguments) use ($method, $callback) {
                $callback(...$arguments);

                return $method(...$arguments);
            };
        }

        if ($kind === static::AFTER) {
            return function (...$arguments) use ($method, $callback) {
                $callback($result = $method(...$arguments), ...$arguments);

                return $result;
            };
        }

        if ($kind === static::FILTER) {
            return function (...$arguments) use ($method, $callback) {
                return $callback($method(...$arguments), ...$arguments);
            };
        }

        return function (...$arguments) use ($method, $callback) {
            try {
                $result = $method(...$arguments);
            } catch (\Throwable $error) {
                $result = $callback($error, ...$arguments);
            }

            return $result;
        };
    }
}
