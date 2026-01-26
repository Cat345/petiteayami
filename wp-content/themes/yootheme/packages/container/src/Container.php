<?php

namespace YOOtheme;

use Closure;
use Psr\Container\ContainerInterface;
use YOOtheme\Container\BadFunctionCallException;
use YOOtheme\Container\InvalidArgumentException;
use YOOtheme\Container\LogicException;
use YOOtheme\Container\ParameterResolver;
use YOOtheme\Container\RuntimeException;
use YOOtheme\Container\Service;
use YOOtheme\Container\ServiceNotFoundException;

class Container implements ContainerInterface
{
    /**
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * @var array<string, Service>
     */
    protected array $services = [];

    /**
     * @var array<string, list<callable>>
     */
    protected array $extenders = [];

    /**
     * @var array<string, object|null>
     */
    protected array $instances = [];

    /**
     * @var array<string, true|object|null>
     */
    protected array $resolving = [];

    /**
     * Gets a service.
     *
     * @return mixed|array
     */
    public function __invoke(string $id, string ...$ids)
    {
        return $ids ? array_map([$this, 'get'], [$id, ...$ids]) : $this->get($id);
    }

    /**
     * Gets a service.
     *
     * @return mixed
     */
    public function __get(string $id)
    {
        return $this->get($id);
    }

    /**
     * Checks if service exists.
     */
    public function __isset(string $id): bool
    {
        return $this->has($id);
    }

    /**
     * @inheritdoc
     */
    public function has($id): bool
    {
        return isset($this->services[$id]) || isset($this->instances[$id]) || $this->isAlias($id);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return $this->resolve($id);
    }

    /**
     * Sets a service instance.
     *
     * @param mixed  $instance
     *
     * @return mixed
     */
    public function set(string $id, $instance)
    {
        unset($this->aliases[$id]);

        return $this->instances[$id] = $instance;
    }

    /**
     * Adds a service definition.
     *
     * @param string|Closure|Service $service
     */
    public function add(string $id, $service = null, bool $shared = true): Service
    {
        if (is_string($service) || is_null($service)) {
            $service = new Service($service ?: $id, $shared);
        } elseif ($service instanceof Closure) {
            $service = (new Service($id, $shared))->setFactory($service);
            //@phpstan-ignore instanceof.alwaysTrue
        } elseif (!$service instanceof Service) {
            throw new InvalidArgumentException('Service definition must be string or Closure');
        }

        unset($this->instances[$id], $this->aliases[$id]);

        return $this->services[$id] = $service;
    }

    /**
     * Adds a callback to extend a service.
     */
    public function extend(string $id, callable $callback): void
    {
        $id = $this->getAlias($id);

        if (isset($this->instances[$id])) {
            $extended = $callback($this->instances[$id], $this);

            if (isset($extended) && $extended !== $this->instances[$id]) {
                throw new LogicException(
                    "Extending a resolved service {$id} must return the same instance",
                );
            }
        } else {
            $this->extenders[$id][] = $callback;
        }
    }

    /**
     * Checks if a service is shared.
     */
    public function isShared(string $id): bool
    {
        return !empty($this->services[$id]->shared) || isset($this->instances[$id]);
    }

    /**
     * Checks if an alias exists.
     */
    public function isAlias(string $id): bool
    {
        return isset($this->aliases[$id]);
    }

    /**
     * Gets an alias.
     *
     * @throws LogicException
     */
    public function getAlias(string $alias): string
    {
        if (!isset($this->aliases[$alias])) {
            return $alias;
        }

        if ($this->aliases[$alias] === $alias) {
            throw new LogicException("[{$alias}] is aliased to itself");
        }

        return $this->getAlias($this->aliases[$alias]);
    }

    /**
     * Sets an alias.
     */
    public function setAlias(string $id, string $alias): void
    {
        $this->aliases[$alias] = $id;
    }

    /**
     * Gets a callback from service@method or service::method syntax.
     *
     * @param callable|string $callback
     */
    public function callback($callback): ?callable
    {
        if (is_string($callback)) {
            if (str_contains($callback, '::')) {
                [$service, $method] = explode('::', $callback, 2);

                $callback = [$this->getAlias($service), $method];
            } elseif (str_contains($callback, '@')) {
                [$service, $method] = explode('@', $callback, 2);

                $callback = [$this->get($service), $method];
            } elseif ($this->has($callback) || class_exists($callback)) {
                $callback = $this->get($callback);
            }
        }

        return is_callable($callback) ? $callback : null;
    }

    /**
     * Calls the callback with given parameters.
     *
     * @param callable|string $callback
     * @param list<mixed> $parameters
     *
     * @return mixed
     *
     * @throws BadFunctionCallException
     */
    public function call($callback, array $parameters = [], bool $resolve = true)
    {
        if (!($callable = $this->callback($callback))) {
            throw BadFunctionCallException::create($callback);
        }

        if ($resolve && ParameterResolver::needsResolving($callable)) {
            $resolver = new ParameterResolver($this);
            $function = Reflection::getFunction($callable);
            $parameters = $resolver->resolve($function, $parameters);
        }

        return $callable(...$parameters);
    }

    /**
     * Wraps the callback with optional parameter resolving.
     *
     * @param callable|string       $callback
     * @param list<mixed> $parameters
     *
     * @return callable
     */
    public function wrap($callback, array $parameters = [], bool $resolve = true): callable
    {
        return fn(
            ...$params
        ) => $this->call($callback, array_replace($parameters, $params), $resolve);
    }

    /**
     * Resolves a service from the container.
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function resolve(string $id)
    {
        $id = $this->getAlias($id);

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            if ($this->resolving[$id] === true) {
                throw new RuntimeException(
                    sprintf(
                        'Circular reference detected %s => %s',
                        join(' => ', array_keys($this->resolving)),
                        $id,
                    ),
                );
            }

            return $this->instances[$id] = $this->resolving[$id];
        }

        $this->resolving[$id] = true;

        $instance = $this->resolveService($id);

        if ($this->isShared($id)) {
            $this->instances[$id] = $instance;
        }

        unset($this->resolving[$id]);

        return $instance;
    }

    /**
     * Resolves a service instance.
     *
     * @throws \Exception
     * @throws \ReflectionException
     */
    protected function resolveService(string $id): ?object
    {
        if (empty($this->services[$id]) && !class_exists($id)) {
            throw new ServiceNotFoundException("Service '{$id}' not found");
        }

        $service = $this->services[$id] ?? new Service($id);
        $instance = $service->resolveInstance($this);

        $this->resolving[$id] = $this->isShared($id) ? $instance : null;

        foreach ($this->extenders[$id] ?? [] as $extender) {
            $instance = $extender($instance, $this) ?: $instance;
        }

        if (isset($this->instances[$id]) && $this->instances[$id] !== $instance) {
            throw new LogicException(
                "Extending a resolved service {$id} must return the same instance",
            );
        }

        return $instance;
    }
}
