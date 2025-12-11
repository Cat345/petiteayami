<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class LoadjQueryScript
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Fires when scripts and styles are enqueued.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
     */
    public function handle(): void
    {
        if (
            $this->config->get('~theme.jquery') ||
            str_contains($this->config->get('~theme.custom_js', ''), 'jQuery')
        ) {
            wp_enqueue_script('jquery');
        }
    }
}
