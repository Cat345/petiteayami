<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class AddSvgFileType
{
    /**
     * Filters the “real” file type of the given file.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_check_filetype_and_ext/
     *
     * @param array<string, string> $data
     * @param string $file
     * @param string $filename
     *
     * @return array<string, string>
     */
    public static function handle($data, $file, $filename): array
    {
        if (empty($data['type']) && str_ends_with($filename, '.svg')) {
            $data['ext'] = 'svg';
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }
}
