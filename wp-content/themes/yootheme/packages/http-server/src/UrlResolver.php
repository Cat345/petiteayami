<?php

namespace YOOtheme;

class UrlResolver
{
    protected static string $root;

    /**
     * @param array<string, mixed> $parameters
     */
    public static function resolve(
        string $path,
        array $parameters,
        ?bool $secure,
        callable $next
    ): ?string {
        $path = Path::resolveAlias($path);

        static::$root ??= app(Config::class)->get('app.rootDir');

        if (Path::isBasePath(static::$root, $path)) {
            $path = Path::relative(static::$root, $path);
        }

        return $next($path, $parameters, $secure);
    }
}
