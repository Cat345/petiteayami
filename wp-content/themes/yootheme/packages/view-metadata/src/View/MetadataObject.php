<?php

namespace YOOtheme\View;

/**
 * @property string $href
 * @property string $src
 * @property string $defer
 * @property string|null $version
 */
class MetadataObject
{
    public string $tag;
    public string $name;

    /**
     * @var mixed
     */
    public $value;

    /**
     * @var array<string, mixed>
     */
    public array $attributes;

    /**
     * Constructor.
     *
     * @param mixed  $value
     * @param array<string, mixed>  $attributes
     */
    public function __construct(string $name, $value, array $attributes = [])
    {
        $tag = substr($name, 0, strpos($name, ':'));

        $this->tag = $tag ?: $name;
        $this->name = $name;
        $this->value = $value;
        $this->attributes = $attributes;
    }

    /**
     * Gets an attribute value.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Checks if an attribute value exists.
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Gets the rendered tag as string.
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Renders the tag.
     */
    public function render(): string
    {
        $metadata = $this;

        if (is_callable($callback = $this->value)) {
            $metadata = $callback($this) ?: $this;
        }

        return HtmlElement::tag($metadata->tag, $metadata->attributes, $metadata->value);
    }

    /**
     * Gets the tag.
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * Sets the tag.
     *
     * @return static
     */
    public function withTag(string $tag): self
    {
        $clone = clone $this;
        $clone->tag = $tag;

        return $clone;
    }

    /**
     * Gets the name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name.
     *
     * @return static
     */
    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    /**
     * Gets the value.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets the value.
     *
     * @param mixed $value
     *
     * @return static
     */
    public function withValue($value): self
    {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }

    /**
     * Gets an attribute.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->$name ?? $default;
    }

    /**
     * Adds an attribute.
     *
     * @param mixed $value
     *
     * @return static
     */
    public function withAttribute(string $name, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * Gets attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Merges multiple attributes.
     *
     * @param array<string, mixed> $attributes
     *
     * @return static
     */
    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = array_merge($this->attributes, $attributes);

        return $clone;
    }
}
