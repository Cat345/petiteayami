<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class FilterPostGallery
{
    /**
     * Filters post galleries.
     *
     * @link https://developer.wordpress.org/reference/hooks/post_gallery/
     *
     * @param string $output
     * @param array<string, string> $attr
     */
    public static function handle($output, $attr): string
    {
        ob_start();

        set_query_var('gallery_attr', $attr);
        get_template_part('templates/gallery');

        return (string) ob_get_clean();
    }
}
