<?php

namespace YOOtheme\Builder\Source;

use YOOtheme\Arr;
use YOOtheme\Str;

trait SourceFilter
{
    /**
     * @var array
     */
    public $filters;

    /**
     * Constructor.
     *
     * @param array $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = array_merge(
            [
                'date' => [$this, 'applyDate'],
                'limit' => [$this, 'applyLimit'],
                'search' => [$this, 'applySearch'],
                'before' => [$this, 'applyBefore'],
                'after' => [$this, 'applyAfter'],
                'condition' => [$this, 'applyCondition'],
            ],
            $filters,
        );
    }

    /**
     * Adds a filter.
     *
     * @param string   $name
     * @param callable $filter
     * @param int      $offset
     */
    public function addFilter($name, callable $filter, $offset = null)
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
     * @param array $filters
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
                '…',
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
     * @return false|string
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
     * @param array $filters
     *
     * @return false|string
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
     * Apply "condition" filter.
     *
     * @param mixed $value
     * @param mixed $operator
     * @param array $filters
     *
     * @return bool
     */
    public function applyCondition($value, $operator, array $filters)
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
