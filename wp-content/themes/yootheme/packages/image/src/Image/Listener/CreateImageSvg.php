<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Image;
use YOOtheme\Image\ImageSvg;

/**
 * Listener for creating Svg images.
 */
class CreateImageSvg
{
    public const REGEX = '#^(?!/|[a-z]*:[/\\\\]).+\.svg($|[?\#])#i';

    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        $supported = preg_match(static::REGEX, $file);

        return $supported ? new ImageSvg($file) : $next($file);
    }
}
