<?php

namespace YOOtheme\Theme\Analytics\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;
use YOOtheme\Path;

class LoadThemeHead
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
        $keys = ['google_analytics', 'google_analytics_anonymize'];

        if ($this->config->get("~theme.{$keys[0]}")) {
            foreach ($keys as $key) {
                $this->config->set(
                    "theme.data.{$key}",
                    trim($this->config->get("~theme.{$key}", '')),
                );
            }

            $this->metadata->set('script:analytics', [
                'src' => Path::get('../../app/analytics.min.js', __DIR__),
                'defer' => true,
            ]);
        }
    }
}
