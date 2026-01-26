<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Image;
use YOOtheme\Image\ImageQuery;

/**
 * Listener for creating local images.
 */
class CreateImageQuery
{
    public const REGEX = '#^(?!/|[a-z]*:[/\\\\]).+\.(avif|a?png|jpe?g|webp)($|[?\#])#i';

    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        $supported = preg_match(static::REGEX, $file);

        return $supported ? new ImageQuery($file) : $next($file);
    }
}
