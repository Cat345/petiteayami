<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class AddSvgFileType
{
    /**
     * Filters the “real” file type of the given file.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_check_filetype_and_ext/
     *
     * @param array $data
     * @param string $file
     * @param string $filename
     */
    public static function handle($data, $file, $filename)
    {
        if (empty($data['type']) && str_ends_with($filename, '.svg')) {
            $data['ext'] = 'svg';
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }
}
