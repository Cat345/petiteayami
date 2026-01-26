<?php

namespace YOOtheme;

abstract class Storage implements \JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    protected array $values = [];

    protected bool $modified = false;

    /**
     * Gets a value (shortcut).
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function __invoke(string $key, $default = null)
    {
        return Arr::get($this->values, $key, $default);
    }

    /**
     * Checks if a key exists.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->values, $key);
    }

    /**
     * Gets a value.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->values, $key, $default);
    }

    /**
     * Sets a value.
     *
     * @param mixed  $value
     *
     * @return $this
     */
    public function set(string $key, $value): self
    {
        Arr::set($this->values, $key, $value);

        $this->modified = true;

        return $this;
    }

    /**
     * Deletes a value.
     *
     * @return $this
     */
    public function del(string $key): self
    {
        Arr::del($this->values, $key);

        $this->modified = true;

        return $this;
    }

    /**
     * Checks if values are modified.
     */
    public function isModified(): bool
    {
        return $this->modified;
    }

    /**
     * Gets values which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->values;
    }

    /**
     * Adds values from JSON.
     *
     * @return $this
     */
    protected function addJson(string $json): self
    {
        $this->values = Arr::merge($this->values, json_decode($json, true) ?: []);

        return $this;
    }
}
