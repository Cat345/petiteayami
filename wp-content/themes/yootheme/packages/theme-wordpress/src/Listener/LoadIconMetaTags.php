<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class LoadIconMetaTags
{
    /**
     * If WP site icon is not set, try to load icons from the theme settings.
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_head/
     */
    public static function handle(): void
    {
        if (!did_filter('site_icon_meta_tags')) {
            echo implode("\n", array_filter(apply_filters('site_icon_meta_tags', [])));
        }
    }
}
