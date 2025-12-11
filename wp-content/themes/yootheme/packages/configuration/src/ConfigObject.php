<?php

namespace YOOtheme;

class ConfigObject extends \ArrayObject
{
    /**
     * Constructor.
     */
    public function __construct(array $values = [])
    {
        parent::__construct($values, static::ARRAY_AS_PROPS);
    }
    /**
     * Get all configuration values.
     */
    public function all(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Get a configuration value.
     *
     * @param  mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this, $key, $default);
    }

    /**
     * Set a configuration value.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        Arr::set($this, $key, $value);
    }

    /**
     * Remove the last value from an array configuration value.
     *
     * @return mixed
     */
    public function pop(string $key)
    {
        $array = $this->get($key, []);
        $value = array_pop($array);

        $this->set($key, $array);

        return $value;
    }

    /**
     * Push a value onto an array configuration value.
     *
     * @param mixed ...$values
     */
    public function push(string $key, ...$values): void
    {
        $array = $this->get($key, []);

        array_push($array, ...$values);

        $this->set($key, $array);
    }

    /**
     * Remove the first value from an array configuration value.
     *
     * @return mixed
     */
    public function shift(string $key)
    {
        $array = $this->get($key, []);
        $value = array_shift($array);

        $this->set($key, $array);

        return $value;
    }

    /**
     * Prepend a value onto an array configuration value.
     *
     * @param mixed ...$values
     */
    public function unshift(string $key, ...$values): void
    {
        $array = $this->get($key, []);

        array_unshift($array, ...$values);

        $this->set($key, $array);
    }

    /**
     * Assign an array of configuration values.
     */
    public function assign(array $values): void
    {
        $this->exchangeArray(array_replace($this->getArrayCopy(), $values));
    }

    /**
     * Merge an array of configuration values recursively.
     */
    public function merge(array $values): void
    {
        $this->exchangeArray(array_replace_recursive($this->getArrayCopy(), $values));
    }
}
