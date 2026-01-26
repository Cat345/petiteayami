<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class AddSvgMimeType
{
    /**
     * Filters list of allowed mime types and file extensions.
     *
     * @param array<string, string> $mimes
     *
     * @return array<string, string>
     *
     * @link https://developer.wordpress.org/reference/hooks/upload_mimes/
     */
    public static function handle(array $mimes): array
    {
        $mimes['svg|svgz'] = 'image/svg+xml';

        return $mimes;
    }
}
