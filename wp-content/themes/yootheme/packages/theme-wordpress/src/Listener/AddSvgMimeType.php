<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class AddSvgMimeType
{
    /**
     * Filters list of allowed mime types and file extensions.
     *
     * @link https://developer.wordpress.org/reference/hooks/upload_mimes/
     */
    public static function handle(array $mimes): array
    {
        $mimes['svg|svgz'] = 'image/svg+xml';

        return $mimes;
    }
}
