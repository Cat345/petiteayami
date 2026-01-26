<?php

namespace YOOtheme;

/**
 * A static class which provides utilities for working with directory paths.
 */
abstract class Path
{
    /**
     * @var array<string, array<string, string>>
     */
    protected static array $aliases = [];

    /**
     * Gets an absolute path by resolving aliases and current directory.
     *
     * @example
     * Path::get('~app/dir');
     * // => /app/dir
     */
    public static function get(string $path, ?string $base = null): string
    {
        $path = static::resolveAlias($path);

        // path is `.`, `..` or starts with `./`, `../`
        if (str_starts_with($path, '.') && preg_match('/^\.\.?(?=\/|$)/', $path)) {
            return static::join($base ?? dirname(Reflection::getCaller()['file']), $path);
        }

        return $path;
    }

    /**
     * Sets a path alias.
     *
     * @example
     * Path::setAlias('~app', '/app');
     *
     * Path::resolveAlias('~app/resource');
     * // => /app/resource
     */
    public static function setAlias(string $alias, string $path): void
    {
        if (!str_starts_with($alias, '~')) {
            throw new \InvalidArgumentException("The alias '{$alias}' must start with ~");
        }

        $path = rtrim(static::resolveAlias($path), '/');
        $alias = rtrim(strtr($alias, '\\', '/'), '/');

        [$name] = explode('/', $alias, 2);

        static::$aliases[$name]["$alias/"] = "$path/";
    }

    /**
     * Resolve a path with alias.
     *
     * @example
     * Path::setAlias('~app', '/app');
     *
     * Path::resolveAlias('~app/resource');
     * // => /app/resource
     */
    public static function resolveAlias(string $path): string
    {
        $path = strtr($path, '\\', '/');

        if (!str_starts_with($path, '~')) {
            return $path;
        }

        [$name] = explode('/', $path, 2);

        $trim = !str_ends_with($path, '/');

        $path = Event::emit("path {$name}|filter", $path, substr($path, strlen($name)));

        if (isset(static::$aliases[$name])) {
            $path = strtr($trim ? "{$path}/" : $path, static::$aliases[$name]);
        }

        return $trim ? rtrim($path, '/') : $path;
    }

    /**
     * Resolves a sequence of paths or path segments into an absolute path. All path segments are processed from right to left.
     *
     * @example
     * Path::resolve('~app/dir/dir', '../resource');
     * // => /app/dir/resource
     */
    public static function resolve(string ...$paths): string
    {
        $parts = [];

        foreach (array_reverse($paths) as $path) {
            $path = static::resolveAlias($path);

            array_unshift($parts, $path);

            if (static::isAbsolute($path)) {
                break;
            }
        }

        $path = static::join(...$parts);

        return $path !== '/' ? rtrim($path, '/') : $path;
    }

    /**
     * Returns trailing name component of path.
     *
     * @example
     * Path::basename('~app/dir/file.php');
     * // => file.php
     */
    public static function basename(string $path, string $suffix = ''): string
    {
        return basename(static::resolveAlias($path), $suffix);
    }

    /**
     * Returns the extension of the path.
     *
     * @example
     * Path::extname('~app/dir/file.php');
     * // => php
     */
    public static function extname(string $path): string
    {
        $extension = pathinfo(static::resolveAlias($path), PATHINFO_EXTENSION);
        return $extension ? ".{$extension}" : '';
    }

    /**
     * Returns a parent directory's path.
     *
     * @example
     * Path::dirname('~app/dir/file.php');
     * // => /app/dir
     */
    public static function dirname(string $path): string
    {
        return dirname(static::resolveAlias($path));
    }

    /**
     * Gets the relative path to a given base path.
     *
     * @example
     * Path::relative('/path/dir/test/aaa', '/path/dir/impl/bbb');
     * // => ../../impl/bbb
     */
    public static function relative(string $from, string $to): string
    {
        $from = static::resolveAlias($from);
        $to = static::resolveAlias($to);

        if ($to === '') {
            return $from;
        }

        $_from = static::parse($from);
        $_to = static::parse($to);

        if ($_from['root'] !== $_to['root']) {
            throw new \InvalidArgumentException(
                "The path '{$to}' can\'t be made relative to the path '{$from}'. Path roots aren\'t equal.",
            );
        }

        $fromParts = explode('/', $_from['pathname']);
        $toParts = explode('/', $_to['pathname']);

        $match = true;
        $prefix = '';

        foreach ($fromParts as $i => $fromPart) {
            if ('' === $fromPart) {
                continue;
            }

            if ($match && isset($toParts[$i]) && $fromPart === $toParts[$i]) {
                unset($toParts[$i]);
                continue;
            }

            $match = false;
            $prefix .= '../';
        }

        return rtrim($prefix . join('/', $toParts), '/');
    }

    /**
     * Normalizes a path, resolving '..' and '.' segments.
     *
     * @example
     * Path::normalize('/path1/.././file.txt');
     * // => /file.txt
     */
    public static function normalize(string $path): string
    {
        static $cache;

        if (!$path) {
            return '';
        }

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        $result = [];
        $parsed = static::parse($path);
        $parts = explode('/', $parsed['pathname']);

        foreach ($parts as $i => $part) {
            if ('.' === $part) {
                continue;
            }

            if ('' === $part && isset($parts[$i + 1])) {
                continue;
            }

            if ($part === '..' && $result && end($result) !== '..') {
                array_pop($result);
                continue;
            }

            if ($part !== '..' || $parsed['root'] === '') {
                $result[] = $part;
            }
        }

        return $cache[$path] = $parsed['root'] . join('/', $result);
    }

    /**
     * Joins all given path segments together.
     *
     * @example
     * Path::join('/foo', '/bar', 'baz/asdf', 'quux', '..');
     * // => /foo/bar/baz/asdf
     */
    public static function join(string ...$parts): string
    {
        return static::normalize(join('/', $parts));
    }

    /**
     * Returns information about a path.
     *
     * @return array{root: string, pathname: string, dirname: string, basename: string, filename: string, extension: string}
     *
     * @example
     * Path::parse('/foo/file.txt');
     * // => ['root' => '/', 'pathname' => 'foo/file.txt', 'dirname' => '/foo', 'basename' => 'file.txt', 'filename' => 'file', 'extension' => 'txt']
     */
    public static function parse(string $path): array
    {
        $path = strtr($path, '\\', '/');
        $root = static::root($path) ?: '';

        return pathinfo($path) + [
            'root' => $root,
            'pathname' => substr($path, strlen($root)),
            'dirname' => null,
            'extension' => null,
        ];
    }

    /**
     * Checks if path is absolute.
     *
     * @example
     * Path::isAbsolute('/foo/file.txt');
     * // => true
     */
    public static function isAbsolute(string $path): bool
    {
        return (bool) static::root($path);
    }

    /**
     * Checks if path is relative.
     *
     * @example
     * Path::isRelative('foo/file.txt');
     * // => true
     */
    public static function isRelative(string $path): bool
    {
        return !static::root($path);
    }

    /**
     * Checks if path is a base path of another path.
     *
     * @example
     * Path::isBasePath('/foo/', '/foo/file.txt');
     * // => true
     * Path::isBasePath('/foo', '/foo');
     * // => true
     * Path::isBasePath('/foo', '/foo/..');
     * // => false
     */
    public static function isBasePath(string $basePath, string $path): bool
    {
        $basePath = static::normalize(static::resolveAlias($basePath));
        $path = static::normalize(static::resolveAlias($path));

        return str_starts_with("{$path}/", rtrim($basePath, '/') . '/');
    }

    /**
     * Returns path root.
     */
    public static function root(string $path): ?string
    {
        $path = strtr($path, '\\', '/');

        if ($path && $path[0] === '/') {
            return '/';
        }

        if (strpos($path, ':') && preg_match('/^([a-z]*:)?(\/\/|\/)/i', $path, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
