<?php

namespace YOOtheme\Container;

use YOOtheme\Container;

class Service
{
    public string $class;
    public bool $shared;

    /**
     * @var ?callable
     */
    protected $factory;

    /**
     * @var array<mixed>
     */
    protected array $arguments = [];

    public function __construct(string $class, bool $shared = false)
    {
        $this->class = $class;
        $this->shared = $shared;
    }

    /**
     * Sets service class.
     *
     * @return $this
     */
    public function setClass(string $class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Checks if service is shared.
     */
    public function isShared(): bool
    {
        return $this->shared;
    }

    /**
     * Sets service as shared.
     *
     * @return $this
     */
    public function setShared(bool $shared = true)
    {
        $this->shared = $shared;

        return $this;
    }

    /**
     * Gets a service factory.
     */
    public function getFactory(): ?callable
    {
        return $this->factory;
    }

    /**
     * Sets a service factory.
     *
     * @param callable $factory
     *
     * @return $this
     */
    public function setFactory(callable $factory)
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * Sets an argument value.
     *
     * @param mixed  $value
     *
     * @return $this
     */
    public function setArgument(string $name, $value)
    {
        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Gets arguments for given function.
     *
     * @return array< mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Sets an array of arguments.
     *
     * @param array<mixed> $arguments
     *
     * @return $this
     */
    public function setArguments(array $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Resolves a new instance.
     *
     * @param Container $container
     *
     * @throws LogicException
     * @throws \ReflectionException
     */
    public function resolveInstance(Container $container): ?object
    {
        return $this->factory
            ? $container->call($this->factory, $this->arguments)
            : $this->resolveClass($container);
    }

    /**
     * Resolves an instance from class.
     *
     * @throws LogicException
     * @throws \ReflectionException
     */
    protected function resolveClass(Container $container): object
    {
        $class = new \ReflectionClass($this->class);

        if (!$class->isInstantiable()) {
            throw new LogicException("Can't instantiate {$this->class}");
        }

        if (!($constructor = $class->getConstructor())) {
            return $class->newInstance();
        }

        $resolver = new ParameterResolver($container);
        $arguments = $resolver->resolve($constructor, $this->arguments);

        return $class->newInstanceArgs($arguments);
    }
}
