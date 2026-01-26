<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Image;
use YOOtheme\Image\ImageQuery;

/**
 * Listener for loading exif data from jpg images.
 */
class LoadExifData
{
    /**
     * @param callable(string $file): ?Image $next
     */
    public static function handle(string $file, callable $next): ?Image
    {
        $image = $next($file);

        if (
            $image instanceof ImageQuery &&
            $image->isType('jpeg') &&
            function_exists('exif_read_data')
        ) {
            return static::applyOrientation($image);
        }

        return $image;
    }

    protected static function applyOrientation(ImageQuery $image): ImageQuery
    {
        static $cache = [];

        // read exif data
        $file = $image->getPath();
        $exif = $cache[$file] ??= @exif_read_data($file);

        // check orientation and rotate it if needed
        switch ($exif['Orientation'] ?? 0) {
            case 2:
                $image = $image->flip(true, false);
                break;
            case 3:
                $image = $image->flip(true, true); // rotate 180
                break;
            case 4:
                $image = $image->flip(false, true);
                break;
            case 5:
                $image = $image->rotate(90)->flip(false, true);
                break;
            case 6:
                $image = $image->rotate(270);
                break;
            case 7:
                $image = $image->rotate(90)->flip(true, false);
                break;
            case 8:
                $image = $image->rotate(90);
                break;
        }

        return $image;
    }
}
