<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;
use YOOtheme\Path;

class LoadCustomizer
{
    public Config $config;
    public Metadata $metadata;

    public function __construct(Config $config, Metadata $metadata)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle()
    {
        // add config
        $this->config->addFile('customizer', Path::get('../../config/customizer.json', __DIR__));
    }
}
