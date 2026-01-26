<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Image;
use YOOtheme\Image\ImageGif;

/**
 * Listener for creating GIF images.
 */
class CreateImageGif
{
    public const REGEX = '#^(?!/|[a-z]*:[/\\\\]).+\.gif($|[?\#])#i';

    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        $supported = preg_match(static::REGEX, $file);

        return $supported ? new ImageGif($file) : $next($file);
    }
}
