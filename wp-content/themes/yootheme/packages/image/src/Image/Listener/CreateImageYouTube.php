<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Image;
use YOOtheme\Image\ImageYoutube;

/**
 * Listener for creating YouTube images.
 */
class CreateImageYouTube
{
    public const REGEX = '#^https://img.youtube.com/vi/([\w-]{11})/(\w+)\.jpg#i';

    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        $supported = ini_get('allow_url_fopen') && preg_match(static::REGEX, $file);

        return $supported ? new ImageYoutube($file) : $next($file);
    }
}
