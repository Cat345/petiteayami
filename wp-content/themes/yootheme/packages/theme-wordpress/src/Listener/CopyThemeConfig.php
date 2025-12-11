<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class CopyThemeConfig
{
    /**
     * Copy theme config in child-theme on first activation.
     *
     * Theme functions attached to this hook are only triggered in the theme (and/or child theme) being activated
     *
     * @link https://developer.wordpress.org/reference/hooks/after_switch_theme/
     */
    public static function handle()
    {
        if (!is_child_theme()) {
            return;
        }

        $config = json_decode(get_theme_mod('config', '{}'), true);

        // if child-theme config is empty, get parent theme_mods (contain menu, widgets and theme configuration)
        if (empty($config)) {
            $theme = wp_get_theme();

            if ($theme_mods = get_option("theme_mods_{$theme->get_template()}")) {
                update_option("theme_mods_{$theme->get_stylesheet()}", $theme_mods);
            }
        }
    }
}
