<?php

namespace YOOtheme\Theme\Wordpress\WPML\Listener;

use YOOtheme\Config;

class LoadCustomizer
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        if (!class_exists('SitePress', false)) {
            return;
        }

        $this->config->addFile('customizer', __DIR__ . '/../../config/customizer.php');
    }
}
