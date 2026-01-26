<?php

namespace YOOtheme\Builder\Source;

use YOOtheme\Arr;
use YOOtheme\Str;

trait SourceFilter
{
    /**
     * @var array<string, callable>
     */
    public array $filters;

    /**
     * Constructor.
     *
     * @param array<string, callable> $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = array_merge(
            [
                'date' => [$this, 'applyDate'],
                'limit' => [$this, 'applyLimit'],
                'search' => [$this, 'applySearch'],
                'transform' => [$this, 'applyTransform'],
                'before' => [$this, 'applyBefore'],
                'after' => [$this, 'applyAfter'],
                'condition' => [$this, 'applyCondition'],
            ],
            $filters,
        );
    }

    /**
     * Adds a filter.
     */
    public function addFilter(string $name, callable $filter, ?int $offset = null): void
    {
        Arr::splice($this->filters, $offset, 0, [$name => $filter]);
    }

    /**
     * Apply "before" filter.
     *
     * @param mixed $value
     * @param mixed $before
     *
     * @return string
     */
    public function applyBefore($value, $before)
    {
        return $value != '' ? $before . $value : $value;
    }

    /**
     * Apply "after" filter.
     *
     * @param mixed $value
     * @param mixed $after
     *
     * @return string
     */
    public function applyAfter($value, $after)
    {
        return $value != '' ? $value . $after : $value;
    }

    /**
     * Apply "limit" filter.
     *
     * @param mixed $value
     * @param mixed $limit
     * @param array<string, mixed> $filters
     *
     * @return string
     */
    public function applyLimit($value, $limit, array $filters)
    {
        if ($limit) {
            $value = preg_replace('/\s*<br[^<]*?\/?>\s*/', ' ', $value);
            $value = Str::limit(
                strip_tags($value),
                intval($limit),
                $limit > 1 ? '…' : '',
                !($filters['preserve'] ?? false),
            );
        }

        return $value;
    }

    /**
     * Apply "date" filter.
     *
     * @param mixed $value
     * @param mixed $format
     *
     * @return string|false
     */
    public function applyDate($value, $format)
    {
        if (!$value) {
            return $value;
        }

        if (is_string($value) && !is_numeric($value)) {
            $value = strtotime($value);
        }

        return date($format ?: 'd/m/Y', intval($value) ?: time());
    }

    /**
     * Apply "search" filter.
     *
     * @param mixed $value
     * @param mixed $search
     * @param array<string, mixed> $filters
     *
     * @return string|false
     */
    public function applySearch($value, $search, array $filters)
    {
        $replace = $filters['replace'] ?? '';

        if ($search && $search[0] === '/') {
            return @preg_replace($search, $replace, $value);
        }

        return str_replace($search, $replace, $value);
    }

    /**
     * Apply "transform" filter.
     *
     * @param mixed $value
     * @param mixed $transform
     *
     * @return string|false
     */
    public function applyTransform($value, $transform)
    {
        if (is_int($transform)) {
            return mb_convert_case($value, $transform, 'UTF-8');
        }
        return $value;
    }

    /**
     * Apply "condition" filter.
     *
     * @param mixed $value
     * @param mixed $operator
     * @param array<string, mixed> $filters
     */
    public function applyCondition($value, $operator, array $filters): bool
    {
        $propertyValue = html_entity_decode($value);
        $conditionValue = $filters['condition_value'] ?? '';

        if ($operator === '!') {
            return empty($propertyValue);
        }

        if ($operator === '!!') {
            return !empty($propertyValue);
        }

        if ($operator === '=') {
            return $propertyValue == $conditionValue;
        }

        if ($operator === '!=') {
            return $propertyValue != $conditionValue;
        }

        if ($operator === '<') {
            return $propertyValue < $conditionValue;
        }

        if ($operator === '>') {
            return $propertyValue > $conditionValue;
        }

        if ($operator === '~=') {
            return str_contains($propertyValue, $conditionValue);
        }

        if ($operator === '!~=') {
            return !str_contains($propertyValue, $conditionValue);
        }

        if ($operator === '^=') {
            return str_starts_with($propertyValue, $conditionValue);
        }

        if ($operator === '!^=') {
            return !str_starts_with($propertyValue, $conditionValue);
        }

        if ($operator === '$=') {
            return str_ends_with($propertyValue, $conditionValue);
        }

        if ($operator === '!$=') {
            return !str_ends_with($propertyValue, $conditionValue);
        }

        if ($operator === 'regex') {
            return @preg_match($conditionValue, $propertyValue) > 0;
        }

        return !!$propertyValue;
    }
}
