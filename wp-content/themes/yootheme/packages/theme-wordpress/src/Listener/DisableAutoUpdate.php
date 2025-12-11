<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class DisableAutoUpdate
{
    /**
     * Prepares themes for JavaScript.
     *
     * @link https://developer.wordpress.org/reference/functions/wp_prepare_themes_for_js/
     */
    public static function handle(?array $themes = null)
    {
        $name = get_template();

        if (!empty($themes[$name]['autoupdate']['supported'])) {
            $themes[$name]['autoupdate']['supported'] = false;
        }

        return $themes;
    }
}
