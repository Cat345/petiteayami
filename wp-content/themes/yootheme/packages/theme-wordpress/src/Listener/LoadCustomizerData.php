<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;

class LoadCustomizerData
{
    public Config $config;
    public Metadata $metadata;

    public function __construct(Config $config, Metadata $metadata)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        if ($data = $this->config->get('customizer')) {
            $this->metadata->set(
                'script:customizer-data',
                sprintf(
                    'window.yootheme = window.yootheme || {}; var $customizer = yootheme.customizer = JSON.parse(atob("%s"));',
                    base64_encode(json_encode($data)),
                ),
                ['id' => 'customizer-data'],
            );
        }
    }
}
