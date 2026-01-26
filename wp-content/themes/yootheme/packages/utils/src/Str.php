<?php

namespace YOOtheme;

/**
 * A static class which provides utilities for working with strings.
 */
abstract class Str
{
    public static string $encoding = 'UTF-8';

    /**
     * Checks if string matches a given pattern.
     *
     * @param string $pattern
     * @param string $string
     *
     * @example
     * Str::is('foo/*', 'foo/bar/baz');
     * // => true
     */
    public static function is(string $pattern, string $string): bool
    {
        static $cache;

        if ($pattern === $string) {
            return true;
        }

        if (empty($cache[$pattern])) {
            $regexp = addcslashes($pattern, '/\\.+^$()=!<>|#');
            $regexp = strtr($regexp, ['*' => '.*', '?' => '.?']);
            $regexp = static::convertBraces($regexp);

            $cache[$pattern] = "#^{$regexp}$#s";
        }

        return (bool) preg_match($cache[$pattern], $string);
    }

    /**
     * Checks if string contains a given substring.
     *
     * @param string|string[] $needles
     *
     * @example
     * Str::contains('taylor', 'ylo');
     * // => true
     */
    public static function contains(string $haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle, 0, static::$encoding) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if string starts with a given substring.
     *
     * @param string|string[] $needles
     *
     * @example
     * Str::startsWith('jason', 'jas');
     * // => true
     */
    public static function startsWith(string $haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_starts_with($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if string ends with a given substring.
     *
     * @param string|string[] $needles
     *
     * @example
     * Str::endsWith('jason', 'on');
     * // => true
     */
    public static function endsWith(string $haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_ends_with($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the string length.
     *
     * @example
     * Str::length('foo bar baz');
     * // => 11
     */
    public static function length(string $string): int
    {
        return mb_strlen($string, static::$encoding);
    }

    /**
     * Convert string to lower case.
     *
     * @example
     * Str::lower('fOo Bar bAz');
     * // => foo bar baz
     */
    public static function lower(string $string): string
    {
        return mb_strtolower($string, static::$encoding);
    }

    /**
     * Converts the first character of string to lower case.
     *
     * @example
     * Str::lowerFirst('FOO BAR BAZ');
     * // => fOO BAR BAZ
     */
    public static function lowerFirst(string $string): string
    {
        return static::lower(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Converts string to upper case.
     *
     * @example
     * Str::upper('fOo Bar bAz');
     * // => FOO BAR BAZ
     */
    public static function upper(string $string): string
    {
        return mb_strtoupper($string, static::$encoding);
    }

    /**
     * Converts the first character of string to upper case.
     *
     * @example
     * Str::upperFirst('foo bar baz');
     * // => Foo bar baz
     */
    public static function upperFirst(string $string): string
    {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Converts string to title case.
     *
     * @param string|string[] $string
     *
     * @example
     * Str::titleCase('jefferson costella');
     * // => Jefferson Costella
     */
    public static function titleCase($string): string
    {
        return mb_convert_case(join(' ', (array) $string), MB_CASE_TITLE, static::$encoding);
    }

    /**
     * Converts string to camel case (https://en.wikipedia.org/wiki/Camel_case).
     *
     * @param string|string[] $string
     *
     * @example
     * Str::camelCase('Yootheme Framework');
     * // => yoothemeFramework
     */
    public static function camelCase($string, bool $upper = false): string
    {
        $string = join(' ', (array) $string);
        $string = str_replace(['-', '_'], ' ', $string);
        $string = str_replace(' ', '', ucwords($string));

        return $upper ? $string : lcfirst($string);
    }

    /**
     * Converts string to snake case (https://en.wikipedia.org/wiki/Snake_case).
     *
     * @param string|string[] $string
     *
     * @example
     * Str::snakeCase('Yootheme Framework');
     * // => yootheme_framework
     */
    public static function snakeCase($string, string $delimiter = '_'): string
    {
        $string = join(' ', (array) $string);

        if (!ctype_lower($string)) {
            $string = preg_replace('/[^a-zA-Z0-9]/u', ' ', $string);
            $string = preg_replace('/\s+/u', '', ucwords($string));
            $string = static::lower(
                preg_replace(
                    '/([a-z])(?=[A-Z0-9])|([0-9]+)(?=[a-zA-Z])|([A-Z]+)(?=[A-Z])/u',
                    "$0{$delimiter}",
                    $string,
                ),
            );
        }

        return $string;
    }

    /**
     * Returns part of a string.
     *
     * @example
     * Str::substr('Yootheme Framework', 3, 5);
     * // => theme
     */
    public static function substr(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, static::$encoding);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @example
     * Str::limit('hi-diddly-ho there, neighborino', 24);
     * // => hi-diddly-ho there, n...
     */
    public static function limit(
        string $string,
        int $length = 100,
        string $omission = '...',
        bool $exact = true
    ): string {
        $strLength = mb_strwidth($string, static::$encoding);
        $omitLength = $length - mb_strwidth($omission, static::$encoding);

        if ($omitLength <= 0) {
            return '';
        }

        if ($strLength <= $length) {
            return $string;
        }

        $trimmed = rtrim(
            mb_strimwidth($string, 0, $omitLength, '', static::$encoding),
            " \n\r\t\v\x00,.!?:", // Remove trailing whitespace and punctuation
        );

        if ($exact || mb_substr($string, mb_strwidth($trimmed), 1, static::$encoding) === ' ') {
            return $trimmed . $omission;
        }

        return preg_replace('/(.*)\s.*/s', '$1', ltrim($trimmed)) . $omission;
    }

    /**
     * Generates a "random" alphanumeric string.
     *
     * @example
     * Str::random();
     * // => X2wvU09F1j4ZCzKD
     */
    public static function random(int $length = 16): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $bytes = random_bytes($size = $length - $len);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Expands glob braces to array.
     *
     * @return list<string>
     *
     * @example
     * Str::expandBraces('foo/{2,3}/bar');
     * // => ['foo/2/bar', 'foo/3/bar']
     */
    public static function expandBraces(string $pattern): array
    {
        $braces = [];
        $expanded = [];
        $callback = function ($matches) use (&$braces) {
            $index = '{' . count($braces) . '}';
            $braces[$index] = $matches[0];

            return $index;
        };

        if (
            preg_match($regex = '/{((?:[^{}]+|(?R))*)}/', $pattern, $matches, PREG_OFFSET_CAPTURE)
        ) {
            [$matches, [$replaces]] = $matches;

            foreach (
                explode(',', preg_replace_callback($regex, $callback, $replaces))
                as $replace
            ) {
                $expand = substr_replace(
                    $pattern,
                    strtr($replace, $braces),
                    $matches[1],
                    strlen($matches[0]),
                );
                $expanded = array_merge($expanded, static::expandBraces($expand));
            }
        }

        return $expanded ?: [$pattern];
    }

    /**
     * Converts glob braces to a regex.
     *
     * @example
     * Str::convertBraces('foo/{2,3}/bar');
     * // => foo/(2|3)/bar
     */
    public static function convertBraces(string $pattern): string
    {
        if (preg_match_all('/{((?:[^{}]+|(?R))*)}/', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            [$matches, $replaces] = $matches;

            foreach ($matches as $i => $m) {
                $replace = str_replace(',', '|', static::convertBraces($replaces[$i][0]));
                $pattern = substr_replace($pattern, "({$replace})", $m[1], strlen($m[0]));
            }
        }

        return $pattern;
    }
}
