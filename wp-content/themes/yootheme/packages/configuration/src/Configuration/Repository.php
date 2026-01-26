<?php

namespace YOOtheme\Configuration;

use YOOtheme\Arr;

class Repository
{
    /**
     * @var array<string, mixed>
     */
    protected array $values = [];

    /**
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Gets a value (shortcut).
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function __invoke(string $index, $default = null)
    {
        return $this->get($index, $default);
    }

    /**
     * Gets a value.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $index, $default = null)
    {
        $index = strtr($index, $this->aliases);

        return static::getValue($this->values, $index, $default);
    }

    /**
     * Sets a value.
     *
     * @param mixed  $value
     *
     * @return $this
     */
    public function set(string $index, $value)
    {
        $index = strtr($index, $this->aliases);

        Arr::set($this->values, $index, $value);

        return $this;
    }

    /**
     * Deletes a value.
     *
     * @return $this
     */
    public function del(string $index)
    {
        $index = strtr($index, $this->aliases);

        Arr::del($this->values, $index);

        return $this;
    }

    /**
     * Adds a value array.
     *
     * @param array<string, mixed> $values
     *
     * @return $this
     */
    public function add(string $index, array $values = [], bool $replace = true)
    {
        $value = $index ? $this->get($index) : $this->values;

        if (is_array($value)) {
            $arrays = $replace ? [$value, $values] : [$values, $value];
            $values = array_replace_recursive(...$arrays);
        }

        if ($index) {
            $this->set($index, $values);
        } else {
            $this->values = $values;
        }

        return $this;
    }

    /**
     * Sets a value using a update callback.
     *
     * @return $this
     */
    public function update(string $index, callable $callback)
    {
        $index = strtr($index, $this->aliases);

        Arr::update($this->values, $index, $callback);

        return $this;
    }

    /**
     * Adds an alias.
     *
     * @return $this
     */
    public function addAlias(string $name, string $index)
    {
        $this->aliases[$name] = $index;

        return $this;
    }

    /**
     * Gets a value from array or object.
     *
     * @param mixed                $object
     * @param string|array<string> $index
     * @param mixed                $default
     *
     * @return mixed
     */
    protected static function getValue($object, $index, $default = null)
    {
        $index = is_array($index) ? $index : explode('.', $index);

        foreach ($index as $key) {
            if ((is_array($object) || $object instanceof \ArrayAccess) && isset($object[$key])) {
                $object = $object[$key];
            } elseif (is_object($object) && isset($object->$key)) {
                $object = $object->$key;
            } elseif (is_callable($callable = [$object, $key])) {
                $object = $callable();
            } else {
                return $default;
            }
        }

        return $object instanceof \Closure ? $object() : $object;
    }
}
