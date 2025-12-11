<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class AddMenus
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Register navigation menus.
     *
     * @link https://developer.wordpress.org/themes/functionality/navigation-menus
     */
    public function handle(): void
    {
        foreach ($this->config->get('theme.menus') as $id => $name) {
            register_nav_menu($id, __($name));
        }
    }
}
