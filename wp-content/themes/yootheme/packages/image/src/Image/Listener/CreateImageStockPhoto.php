<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Image;
use YOOtheme\Image\ImageStockPhoto;

/**
 * Listener for creating StockPhoto images.
 */
class CreateImageStockPhoto
{
    public const REGEX = '#^https?://images\.(unsplash\.com|pexels\.com/photos)/#i';

    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        $supported = preg_match(static::REGEX, $file);

        return $supported ? new ImageStockPhoto($file) : $next($file);
    }
}
