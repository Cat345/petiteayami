<?php

namespace YOOtheme\Theme\Wordpress\Listener;

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
        // add config
        $this->config->addFile('customizer', __DIR__ . '/../../config/customizer.php');
    }
}
