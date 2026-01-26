<?php

namespace YOOtheme\Theme\Consent\Listener;

use YOOtheme\Config;

class LoadDefaultScripts
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        $this->config->update(
            '~theme.scripts',
            fn($scripts): array => $scripts ?? [['type' => 'script-maps-openstreetmap']],
        );
    }
}
