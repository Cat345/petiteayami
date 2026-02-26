<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\HttpClientInterface;
use YOOtheme\Image;
use YOOtheme\Image\ImageVimeo;
use function YOOtheme\app;

/**
 * Listener for creating YouTube images.
 */
class CreateImageVimeo
{
    public const REGEX = '#(?:player\.)?vimeo\.com(?:/video)?/(?P<id>\d+)#i';

    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        if (ini_get('allow_url_fopen') && preg_match(static::REGEX, $file, $matches)) {
            return new ImageVimeo("https://vimeo.com/{$matches['id']}.jpg");
        }

        return $next($file);
    }

    public static function resolve(string $file): string
    {
        if (!preg_match(static::REGEX, $file, $matches)) {
            return $file;
        }

        $url = urlencode("https://vimeo.com/{$matches[1]}");

        /** @var HttpClientInterface $client */
        $client = app(HttpClientInterface::class);
        $response = $client->get("https://vimeo.com/api/oembed.json?url={$url}");

        if (!$response->isOk()) {
            return '';
        }

        return json_decode($response->getBody(), true)['thumbnail_url'] ?? '';
    }
}
