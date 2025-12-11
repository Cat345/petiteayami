<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;

class SetBodyClass
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        $this->config->set('~theme.body_class', [$this->config->get('~theme.page_class')]);
    }
}
