<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;

class LoadUIkitScript
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
        $debug = $this->config->get('app.debug') ? '' : '.min';

        $this->metadata->set('script:uikit', [
            'src' => "~assets/uikit/dist/js/uikit{$debug}.js",
            'defer' => true,
        ]);

        $this->metadata->set('script:uikit-icons', [
            'src' => "~assets/uikit/dist/js/uikit-icons{$debug}.js",
            'defer' => true,
        ]);
    }
}
