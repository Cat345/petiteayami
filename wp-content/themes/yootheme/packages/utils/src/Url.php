<?php

namespace YOOtheme;

use YOOtheme\Http\Uri;

abstract class Url
{
    protected static string $base;
    protected static ?Uri $baseUri;

    /**
     * Gets the base URL.
     */
    public static function base(?bool $secure = null): string
    {
        if (is_null($secure)) {
            return static::getBase()->getPath();
        }

        return (string) static::getBase()->withScheme($secure ? 'https' : 'http');
    }

    /**
     * Sets the base URL.
     */
    public static function setBase(string $base): void
    {
        static::$baseUri = null;
        static::$base = $base;
    }

    /**
     * Generates a URL to a path.
     *
     * @param array<string, mixed>  $parameters
     *
     * @return string|false
     */
    public static function to(string $path, array $parameters = [], ?bool $secure = null)
    {
        try {
            if (empty($parameters) && is_null($secure) && static::isValid($path)) {
                return $path;
            }

            return (string) Event::emit(
                'url.resolve|middleware',
                [static::class, 'generate'],
                $path,
                $parameters,
                $secure,
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generates a URL to a route.
     *
     * @param array<string, mixed>  $parameters
     */
    public static function route(
        string $pattern = '',
        array $parameters = [],
        ?bool $secure = null
    ): string {
        return (string) Event::emit('url.route', $pattern, $parameters, $secure);
    }

    /**
     * Generates a URL to a path.
     *
     * @param array<string, mixed>  $parameters
     */
    public static function generate(string $path, array $parameters = [], ?bool $secure = null): Uri
    {
        $url = new Uri($path);
        $base = static::getBase();

        if (!$url->getScheme() && !$url->getHost() && !str_starts_with($url->getPath(), '/')) {
            $url = $url->withPath(Path::join($base->getPath(), $url->getPath()));
        }

        if ($query = array_replace($url->getQueryParams(), $parameters)) {
            $url = $url->withQueryParams($query);
        }

        if (is_bool($secure)) {
            if (!$url->getHost()) {
                $url = $url->withHost($base->getHost())->withPort($base->getPort());
            }

            $url = $url->withScheme($secure ? 'https' : 'http');
        }

        return $url;
    }

    public static function relative(string $url, ?string $baseUrl = null): string
    {
        $baseUrl ??= static::base();
        return Path::relative($baseUrl ?: '/', $url);
    }

    /**
     * Checks if the given path is a valid URL.
     */
    public static function isValid(string $path): bool
    {
        $valid = ['http://', 'https://', 'mailto:', 'tel:', '//', '#'];

        return Str::startsWith($path, $valid) || filter_var($path, FILTER_VALIDATE_URL);
    }

    /**
     * Gets the base URL.
     */
    protected static function getBase(): Uri
    {
        return static::$baseUri ??= new Uri(static::$base);
    }
}
