<?php

namespace YOOtheme;

class Route
{
    protected string $name;
    protected string $path;

    /**
     * @var string|callable
     */
    protected $callable;

    /**
     * @var list<string>
     */
    protected array $methods = [];

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Constructor.
     *
     * @param string|callable $callable
     * @param string|list<string> $methods
     */
    public function __construct(string $path, $callable, $methods = [])
    {
        $this->setPath($path);
        $this->setMethods($methods);
        $this->callable = $callable;
    }

    /**
     * Gets the path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Sets the path.
     *
     * @return $this
     */
    public function setPath(string $path): self
    {
        $this->path = '/' . trim($path, '/');

        return $this;
    }

    /**
     * Gets the callable.
     *
     * @return string|callable
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * Gets the methods.
     *
     * @return list<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Sets the methods.
     *
     * @param string|list<string> $methods
     *
     * @return $this
     */
    public function setMethods($methods): self
    {
        $this->methods = array_map('strtoupper', (array) $methods);

        return $this;
    }

    /**
     * Gets an attribute.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Sets an attribute.
     *
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttribute(string $name, $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Gets the attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Sets the attributes.
     *
     * @param array<string, mixed> $attributes
     *
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }
}
