<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class LoadMenuLocations
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        $locations = get_nav_menu_locations();

        // set menu locations in theme config
        foreach (array_keys(get_registered_nav_menus()) as $name) {
            $this->config->set(
                "~theme.menu.positions.{$name}.menu",
                !empty($locations[$name]) ? $locations[$name] : '',
            );
        }
    }
}
