<?php

namespace YOOtheme\Configuration;

class Filter
{
    /**
     * @var array<string,  mixed>
     */
    protected array $filters = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $filters
     */
    public function __construct(array $filters = [])
    {
        foreach ($filters as $name => $filter) {
            $this->add($name, $filter);
        }
    }

    /**
     * Adds a filter function.
     */
    public function add(string $name, callable $filter): void
    {
        $this->filters[$name] = $filter;
    }

    /**
     * Applies filters to a value.
     *
     * @param mixed           $value
     * @param string|string[] $filters
     * @param mixed           ...$arguments
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function apply($filters, $value, ...$arguments)
    {
        if (is_string($filters)) {
            $filters = explode('|', $filters);
        }

        foreach ($filters as $name) {
            if (!isset($this->filters[$name])) {
                throw new \RuntimeException("Undefined filter '{$name}'");
            }

            $value = $this->filters[$name]($value, ...$arguments);
        }

        return $value;
    }
}
