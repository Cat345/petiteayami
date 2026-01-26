<?php

namespace YOOtheme;

use ArrayIterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<Route>
 */
class Routes implements IteratorAggregate
{
    /**
     * @var array<string, Route>
     */
    protected array $index = [];

    /**
     * @var list<Route>
     */
    protected array $routes = [];

    /**
     * Adds a route.
     *
     * @param string|list<string> $method
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     */
    public function map($method, string $path, $handler, array $attributes = []): Route
    {
        $route = new Route($path, $handler, $method);
        $route->setAttributes($attributes);

        if ($this->index) {
            $this->index = [];
        }

        return $this->routes[] = $route;
    }

    /**
     * Adds a GET route.
     *
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     */
    public function get(string $path, $handler, array $attributes = []): Route
    {
        return $this->map('GET', $path, $handler, $attributes);
    }

    /**
     * Adds a POST route.
     *
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     *
     * @return Route
     */
    public function post(string $path, $handler, array $attributes = []): Route
    {
        return $this->map('POST', $path, $handler, $attributes);
    }

    /**
     * Adds a PUT route.
     *
     * @param string|string[] $path
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     *
     * @return Route
     */
    public function put($path, $handler, array $attributes = []): Route
    {
        return $this->map('PUT', $path, $handler, $attributes);
    }

    /**
     * Adds a PATCH route.
     *
     * @param string|string[] $path
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     *
     * @return Route
     */
    public function patch($path, $handler, array $attributes = []): Route
    {
        return $this->map('PATCH', $path, $handler, $attributes);
    }

    /**
     * Adds a DELETE route.
     *
     * @param string|string[] $path
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     *
     * @return Route
     */
    public function delete($path, $handler, array $attributes = []): Route
    {
        return $this->map('DELETE', $path, $handler, $attributes);
    }

    /**
     * Adds a HEAD route.
     *
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     */
    public function head(string $path, $handler, array $attributes = []): Route
    {
        return $this->map('HEAD', $path, $handler, $attributes);
    }

    /**
     * Adds a OPTIONS route.
     *
     * @param string|callable $handler
     * @param array<string, mixed> $attributes
     */
    public function options(string $path, $handler, array $attributes = []): Route
    {
        return $this->map('OPTIONS', $path, $handler, $attributes);
    }

    /**
     * Adds a group of routes.
     */
    public function group(string $prefix, callable $group): self
    {
        $routes = new self();

        $group($routes);

        return $this->mount($prefix, $routes);
    }

    /**
     * Mounts a route collection.
     *
     * @return $this
     */
    public function mount(string $prefix, Routes $routes): self
    {
        $prefix = trim($prefix, '/');

        foreach ($routes as $route) {
            $this->routes[] = $route->setPath($prefix . $route->getPath());
        }

        return $this;
    }

    /**
     * Gets a route by name.
     */
    public function getRoute(string $name): ?Route
    {
        return $this->getIndex()[$name] ?? null;
    }

    /**
     * Gets an index of routes.
     *
     * @return array<string, Route>
     */
    public function getIndex(): array
    {
        if (!$this->index) {
            foreach ($this->routes as $index => $route) {
                $this->index[$route->getAttribute('name', "route_{$index}")] = $route;
            }
        }

        return $this->index;
    }

    /**
     * Implements the IteratorAggregate.
     *
     * @return ArrayIterator<int, Route>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->routes);
    }
}
