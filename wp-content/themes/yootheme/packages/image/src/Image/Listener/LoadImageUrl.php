<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Config;
use YOOtheme\Http\Uri;
use YOOtheme\Path;
use YOOtheme\Url;
use function YOOtheme\app;

class LoadImageUrl
{
    public const PREFIX = 'image:/';

    /**
     * @var array <string, string>
     */
    protected static array $config = [];

    /**
     * @param array<string, mixed> $parameters
     */
    public static function resolve(
        string $path,
        array $parameters,
        ?bool $secure,
        callable $next
    ): ?string {
        if (!str_starts_with($path, static::PREFIX)) {
            return $next($path, $parameters, $secure);
        }

        $url = substr($path, strlen(static::PREFIX));

        if (static::getConfig('app.isCustomizer')) {
            $url .= '&cache=false';
        }

        [$src, $query] = explode('?', $url, 2);

        parse_str($query, $params);

        if (empty($params['cachekey'])) {
            return null;
        }

        $cacheKey = static::getCacheKey($src, $params);
        // Escape literal percent signs before the URI filter preserves encoded sequences.
        $cachePath = (new Uri(''))->withPath(str_replace('%', '%25', $cacheKey))->getPath();

        if (
            (!static::getConfig('app.sef') || !static::getConfig('~theme.image_urls')) &&
            is_file(Path::join(static::getConfig('image.cacheDir'), $cacheKey))
        ) {
            return $next(
                Path::join(static::getConfig('image.cacheDir'), $cachePath),
                $parameters,
                $secure,
            );
        }

        unset($params['cachekey']);

        // commas are srcset separators, therefore `hash` is last parameter to avoid trailing comma in url (which is being ignored in chrome)
        return Url::route(
            "cache/{$cachePath}",
            ['src' => $src] + $params + ['hash' => static::getHash($url)],
        );
    }

    public static function getHash(string $data): string
    {
        return hash(
            PHP_VERSION_ID < 80100 ? 'fnv132' : 'xxh32',
            "{$data}#" . static::getConfig('app.secret'),
        );
    }

    /**
     * @param array<string, string> $params
     */
    public static function getCacheKey(string $file, array $params): string
    {
        $key = $params['cachekey'];
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $type = empty($params['type'])
            ? pathinfo($file, PATHINFO_EXTENSION)
            : explode(',', $params['type'])[0];

        return sprintf('%s/%s-%s.%s', substr($key, 0, 2), $filename, $key, $type);
    }

    protected static function getConfig(string $key): string
    {
        return static::$config[$key] ??= (string) app(Config::class)->get($key);
    }
}
