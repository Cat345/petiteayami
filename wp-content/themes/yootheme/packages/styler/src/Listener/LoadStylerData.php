<?php

namespace YOOtheme\Theme\Styler\Listener;

use YOOtheme\Metadata;
use YOOtheme\Theme\Styler\StylerConfig;

class LoadStylerData
{
    public Metadata $metadata;
    public StylerConfig $config;

    public function __construct(Metadata $metadata, StylerConfig $config)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        $this->metadata->set(
            'script:styler-data',
            sprintf(
                'window.yootheme ||= {}; var $styler = yootheme.styler = %s;',
                json_encode($this->config),
            ),
        );
    }
}
