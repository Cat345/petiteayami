<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class SaveMenuLocations
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(array $config): array
    {
        // skip when WPML active
        if (class_exists('SitePress', false)) {
            return $config;
        }

        $locations = [];

        // get menu locations from theme config
        foreach ($config['menu']['positions'] ?? [] as $name => $position) {
            if (!empty($position['menu'])) {
                $locations[$name] = $position['menu'];
            }
        }

        // save menu locations in theme mod
        set_theme_mod('nav_menu_locations', $locations);

        return $config;
    }
}
