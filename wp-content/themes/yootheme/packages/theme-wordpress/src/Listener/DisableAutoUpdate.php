<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class DisableAutoUpdate
{
    /**
     * Prepares themes for JavaScript.
     *
     * @param array<string, array<string, mixed>> $themes
     *
     * @return  array<string, array<string, mixed>>
     *
     * @link https://developer.wordpress.org/reference/functions/wp_prepare_themes_for_js/
     * @note $themes is not \WP_Theme[]
     */
    public static function handle(?array $themes = null): array
    {
        $name = get_template();

        if (!empty($themes[$name]['autoupdate']['supported'])) {
            $themes[$name]['autoupdate']['supported'] = false;
        }

        return $themes;
    }
}
