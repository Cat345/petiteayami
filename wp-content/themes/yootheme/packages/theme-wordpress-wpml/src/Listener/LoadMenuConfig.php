<?php

namespace YOOtheme\Theme\Wordpress\WPML\Listener;

use YOOtheme\Theme\Wordpress\MenuConfig;

class LoadMenuConfig
{
    /**
     * @param MenuConfig $menu
     */
    public static function handle($menu): void
    {
        if (!class_exists('SitePress', false)) {
            return;
        }

        // Disable menu edit
        $menu->set('canEditLocation', false);
    }
}
