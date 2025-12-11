<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Theme\Wordpress\MenuConfig;

class LoadMenuData
{
    public Config $config;
    public MenuConfig $menu;

    public function __construct(Config $config, MenuConfig $menu)
    {
        $this->menu = $menu;
        $this->config = $config;
    }

    public function handle()
    {
        $this->config->add('customizer', ['menu' => $this->menu->getArrayCopy()]);
        $this->config->addFile('customizer', Path::get('../../config/customizer.json'));
    }
}
