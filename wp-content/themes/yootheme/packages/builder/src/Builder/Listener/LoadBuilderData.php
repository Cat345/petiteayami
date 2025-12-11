<?php

namespace YOOtheme\Builder\Listener;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Metadata;

class LoadBuilderData
{
    public Metadata $metadata;
    public BuilderConfig $config;

    public function __construct(Metadata $metadata, BuilderConfig $config)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        $this->metadata->set(
            'script:builder-data',
            sprintf(
                'window.yootheme ||= {}; var $builder = yootheme.builder = %s;',
                json_encode($this->config),
            ),
        );
    }
}
