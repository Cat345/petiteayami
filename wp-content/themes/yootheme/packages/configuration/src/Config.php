<?php

namespace YOOtheme;

interface Config
{
    /**
     * Gets a value (shortcut).
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function __invoke(string $index, $default = null);

    /**
     * Gets a value.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $index, $default = null);

    /**
     * Sets a value.
     *
     * @param mixed  $value
     *
     * @return $this
     */
    public function set(string $index, $value);

    /**
     * Deletes a value.
     *
     * @return $this
     */
    public function del(string $index);

    /**
     * Adds a value array.
     *
     * @param array<string, mixed>  $values
     *
     * @return $this
     */
    public function add(string $index, array $values = [], bool $replace = true);

    /**
     * Sets a value using a update callback.
     *
     * @return $this
     */
    public function update(string $index, callable $callback);

    /**
     * Adds an alias.
     *
     * @return $this
     */
    public function addAlias(string $name, string $index);

    /**
     * Adds a file.
     *
     * @return $this
     */
    public function addFile(string $index, string $file, bool $replace = true);

    /**
     * Adds a filter callback.
     *
     * @return $this
     */
    public function addFilter(string $name, callable $filter);

    /**
     * Loads a config file.
     *
     * @return array<string, mixed>
     */
    public function loadFile(string $file): array;
}
