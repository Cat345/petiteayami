<?php

namespace YOOtheme;

use ArrayAccess;

/**
 * A static class which provides utilities for working with arrays.
 *
 * @phpstan-type Accessible array<mixed>|ArrayAccess<int|string, mixed>
 * @phpstan-type Predicate callable|array<mixed>
 * @phpstan-type Key int|string|null
 */
abstract class Arr
{
    /**
     * Checks if the given key exists.
     *
     * @param Accessible $array
     * @param Key $key
     *
     * @example
     * $array = ['a' => ['b' => 2]];
     *
     * Arr::has($array, 'a');
     * // => true
     *
     * Arr::has($array, 'a.b');
     * // => true
     */
    public static function has($array, $key): bool
    {
        if (!$array || is_null($key)) {
            return false;
        }

        if (static::exists($array, $key)) {
            return true;
        }

        if (!str_contains($key, '.')) {
            return false;
        }

        foreach (explode('.', $key) as $part) {
            if (static::exists($array, $part)) {
                $array = $array[$part];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets a value by key.
     *
     * @param Accessible $array
     * @param Key $key
     * @param mixed $default
     *
     * @return mixed
     *
     * @example
     * $array = ['a' => [['b' => ['c' => 3]]]];
     *
     * Arr::get($array, 'a.0.b.c');
     * // => 3
     *
     * Arr::get($array, 'a.b.c', 'default');
     * // => 'default'
     */
    public static function get($array, $key, $default = null)
    {
        if (!static::accessible($array)) {
            return $default;
        }

        if ($key === null) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $part) {
            if (static::exists($array, $part)) {
                $array = $array[$part];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Sets a value.
     *
     * @param Accessible $array
     * @param Key $key
     * @param mixed $value
     *
     * @return Accessible
     *
     * @example
     * $array = ['a' => [['b' => ['c' => 3]]]];
     *
     * Arr::set($array, 'a.0.b.c', 4);
     * // => ['a' => [['b' => ['c' => 4]]]]
     */
    public static function set(&$array, $key, $value)
    {
        if ($key === null) {
            return $array = $value;
        }

        $arr = &$array;
        $parts = explode('.', $key);
        $last = array_key_last($parts);

        foreach ($parts as $key => $part) {
            if ($key === $last) {
                $arr[$part] = $value;
                break;
            }

            if (!is_array($arr[$part] ?? null)) {
                $arr[$part] = [];
            }

            $arr = &$arr[$part];
        }

        return $array;
    }

    /**
     * Deletes a value from array by key.
     *
     * @param Accessible $array
     * @param int|string $key
     *
     * @example
     * $array = ['a' => [['b' => ['c' => 3]]]];
     *
     * Arr::del($array, 'a.0.b.c');
     *
     * print_r($array);
     * // => ['a' => [['b' => []]]]
     */
    public static function del(&$array, $key): void
    {
        // if the exact key exists in the top-level, delete it
        if (static::exists($array, $key)) {
            unset($array[$key]);
            return;
        }

        $parts = explode('.', $key);
        $last = array_key_last($parts);

        foreach ($parts as $key => $part) {
            if ($key === $last) {
                unset($array[$part]);
                break;
            }

            if (is_array($array[$part] ?? null)) {
                $array = &$array[$part];
            } else {
                return;
            }
        }
    }

    /**
     * Get a value from the array, and delete it.
     *
     * @param Accessible $array
     * @param int|string $key
     * @param mixed $default
     *
     * @return mixed
     *
     * @example
     * $array = ['a' => [['b' => ['c' => 3]]]];
     *
     * Arr::pull($array, 'a.0.b.c');
     * // => 3
     *
     * print_r($array);
     * // => ['a' => [['b' => []]]]
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = static::get($array, $key, $default);

        static::del($array, $key);

        return $value;
    }

    /**
     * Set a value using an update callback.
     *
     * @param Accessible $array
     * @param Key $key
     * @param callable $callback
     *
     * @return Accessible
     *
     * @example
     * $array = ['a' => [['b' => ['c' => 3]]]];
     *
     * Arr::update($array, 'a.0.b.c', function($n) { return $n * $n; });
     *
     * print_r($array);
     * // => ['a' => [['b' => ['c' => 9]]]]
     */
    public static function update(&$array, $key, callable $callback)
    {
        return static::set($array, $key, $callback(static::get($array, $key)));
    }

    /**
     * Checks if all values pass the predicate truth test.
     *
     * @param Accessible $array
     * @param Predicate $predicate
     *
     * @example
     * $collection = [
     *     ['user' => 'barney', 'role' => 'editor', 'age' => 36, 'active' => false],
     *     ['user' => 'joana', 'role' => 'editor', 'age' => 23, 'active' => true]
     * ];
     *
     * Arr::every($collection, ['role' => 'editor']);
     * // true
     *
     * Arr::every($collection, function($v) { return $v['active']; });
     * // false
     */
    public static function every($array, $predicate): bool
    {
        $callback = is_callable($predicate) ? $predicate : static::matches($predicate);

        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if some values pass the predicate truth test.
     *
     * @param Accessible $array
     * @param Predicate $predicate
     *
     * @example
     * $collection = [
     *     ['user' => 'barney', 'role' => 'editor', 'age' => 36, 'active' => false],
     *     ['user' => 'joana', 'role' => 'editor', 'age' => 23, 'active' => true]
     * ];
     *
     * Arr::some($collection, ['user' => 'barney']);
     * // true
     *
     * Arr::some($collection, function($v) { return $v['active']; });
     * // true
     */
    public static function some($array, $predicate): bool
    {
        $callback = is_callable($predicate) ? $predicate : static::matches($predicate);

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the picked values from the given array.
     *
     * @param array<mixed> $array
     * @param string|Predicate $predicate
     *
     * @return array<mixed>
     *
     * @example
     * $array = ['a' => 1, 'b' => 2, 'c' => 3];
     *
     * Arr::pick($array, ['a', 'c']);
     * // ['a' => 1, 'c' => 3];
     */
    public static function pick(array $array, $predicate): array
    {
        if (is_callable($predicate)) {
            return array_filter($array, $predicate, ARRAY_FILTER_USE_BOTH);
        }

        return array_intersect_key($array, array_flip((array) $predicate));
    }

    /**
     * Gets an array composed of the properties of the given array that are not omitted.
     *
     * @param array<mixed> $array
     * @param string|Predicate $predicate
     *
     * @return array<mixed>
     *
     * @example
     * $array = ['a' => 1, 'b' => 2, 'c' => 3];
     *
     * Arr::omit($array, ['b']);
     * // ['a' => 1, 'c' => 3];
     */
    public static function omit(array $array, $predicate): array
    {
        if (is_callable($predicate)) {
            return array_diff_key($array, array_filter($array, $predicate, ARRAY_FILTER_USE_BOTH));
        }

        return array_diff_key($array, array_flip((array) $predicate));
    }

    /**
     * Gets the first value in an array passing the predicate truth test.
     *
     * @param Accessible $array
     * @param Predicate $predicate
     *
     * @return mixed
     *
     * @example
     * $collection = [
     *     ['user' => 'barney', 'role' => 'editor', 'age' => 36, 'active' => false],
     *     ['user' => 'joana', 'role' => 'editor', 'age' => 23, 'active' => true]
     * ];
     *
     * Arr::find($collection, ['user' => 'barney']);
     * // $collection[0]
     *
     * Arr::find($collection, function($v) { return $v['age'] === 23; });
     * // $collection[1]
     */
    public static function find($array, $predicate)
    {
        $callback = is_callable($predicate) ? $predicate : static::matches($predicate);

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Gets all values in an array passing the predicate truth test.
     *
     * @param array<mixed> $array
     * @param Predicate $predicate
     *
     * @return array<mixed>
     *
     * @example
     * $collection = [
     *     ['user' => 'barney', 'role' => 'editor', 'age' => 36, 'active' => false],
     *     ['user' => 'joana', 'role' => 'editor', 'age' => 23, 'active' => true],
     *     ['user' => 'fred', 'role' => 'editor', 'age' => 40, 'active' => false]
     * ];
     *
     * Arr::filter($collection, ['active' => true]);
     * // [$collection[1]]
     *
     * Arr::filter($collection, function($v) { return $v['age'] > 30; });
     * // [$collection[0], $collection[2]]
     */
    public static function filter(array $array, $predicate = null): array
    {
        if (is_null($predicate)) {
            return array_filter($array);
        }

        $callback = is_callable($predicate) ? $predicate : static::matches($predicate);

        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Merges two arrays recursively.
     *
     * @param Accessible $array1
     * @param Accessible $array2
     *
     * @return Accessible
     *
     * @example
     * $array = ['a' => [['b' => 2], ['d' => 4]]];
     * $other = ['a' => [['c' => 3], ['e' => 5]]];
     *
     * Arr::merge($array, $other);
     * // =>['a' => [['b' => 2], ['d' => 4], ['c' => 3], ['e' => 5]]]
     */
    public static function merge($array1, $array2)
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key])) {
                if (is_int($key)) {
                    $array1[] = $value;
                } elseif (static::accessible($value) && static::accessible($array1[$key])) {
                    $array1[$key] = static::merge($array1[$key], $value);
                } else {
                    $array1[$key] = $value;
                }
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array<mixed> $array
     *
     * @return array<mixed>
     *
     * @example
     * $array = [1, [2, [3, [4]], 5]];
     *
     * Arr::flatten($array);
     * // => [1, 2, 3, 4, 5]
     *
     * Arr::flatten($array, 1);
     * // => [1, 2, [3, [4]], 5]
     */
    public static function flatten(array $array, int $depth = 0): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    /**
     * Chunks an array evenly into columns.
     *
     * @param array<mixed> $array
     * @param int $columns
     *
     * @return list<mixed>
     *
     * @example
     * $array = [1, 2, 3, 4, 5];
     *
     * Arr::columns($array, 2);
     * // => [[1, 2, 3], [4, 5]]
     *
     * Arr::columns($array, 4);
     * // => [[1, 2], [3], [4], [5]]
     */
    public static function columns(array $array, int $columns): array
    {
        $count = count($array);
        $columns = max(1, min($count, $columns));
        $rows = max(1, (int) ceil($count / $columns));
        $remainder = $count % $columns;

        if (!$remainder) {
            return array_chunk($array, $rows);
        }

        $result = [];
        for ($i = 0; $i < $columns; $i++) {
            $result[] = array_slice(
                $array,
                $i * $rows - max($i - $remainder, 0),
                $rows - ($i >= $remainder ? 1 : 0),
            );
        }

        return $result;
    }

    /**
     * Checks if the given key exists.
     *
     * @param Accessible $array
     * @param int|string $key
     */
    public static function exists($array, $key): bool
    {
        if (!static::accessible($array)) {
            return false;
        }

        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * Wraps given value in array, if it is not one.
     *
     * @param mixed $value
     *
     * @return array<mixed>
     */
    public static function wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Checks if the given value is array accessible.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Removes a portion of the array and replaces it with something else, preserving keys.
     *
     * @param array<mixed> $array
     * @param int|null $offset
     * @param int|null $length
     * @param array<mixed> $replacement
     *
     * @return array<mixed>
     *
     * @example
     * $array = ['a' => 1, 'b' => 2, 'c' => 3];
     *
     * Arr::splice($array, 1, 1);
     * // => ['a' => 1, 'c' => 3]
     *
     * Arr::splice($array, 1, 2, ['d' => 4]);
     * // => ['a' => 1, 'd' => 4]
     */
    public static function splice(
        array &$array,
        ?int $offset = null,
        ?int $length = null,
        array $replacement = []
    ): array {
        $result = $offset !== null && $length ? array_slice($array, $offset, $length, true) : [];

        $array = array_merge(
            array_slice($array, 0, $offset, true),
            static::wrap($replacement),
            $offset !== null ? array_slice($array, $offset + $length, null, true) : [],
        );

        return $result;
    }

    /**
     * Renames keys in an array. It does not preserve key order.
     *
     * @param Accessible $array
     * @param array<int|string|callable> $keys
     *
     * @return Accessible
     *
     * @example
     * $array = ['a' => 1, 'b' => 2, 'c' => 3];
     *
     * Arr::updateKeys($array, ['b' => 'd']);
     * // => ['a' => 1, 'c' => 3, 'd' => 2]
     */
    public static function updateKeys(&$array, array $keys)
    {
        foreach ($keys as $oldKey => $newKey) {
            if (static::has($array, $oldKey)) {
                $value = static::pull($array, $oldKey);

                if (is_callable($newKey)) {
                    foreach ($newKey($value) ?: [] as $key => $value) {
                        static::set($array, $key, $value);
                    }
                } else {
                    static::set($array, $newKey, $value);
                }
            }
        }

        return $array;
    }

    /**
     * Creates a callback function to match array values.
     *
     * @param array<mixed> $predicate
     */
    protected static function matches(array $predicate): callable
    {
        return function ($array) use ($predicate): bool {
            if (!static::accessible($array)) {
                return false;
            }

            foreach ($predicate as $key => $value) {
                if (!static::exists($array, $key)) {
                    return false;
                }

                if ($array[$key] != $value) {
                    return false;
                }
            }

            return true;
        };
    }
}
