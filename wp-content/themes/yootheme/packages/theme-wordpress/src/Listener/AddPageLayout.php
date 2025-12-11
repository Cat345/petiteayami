<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class AddPageLayout
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Filters the path of the current template before including it.
     *
     * @link https://developer.wordpress.org/reference/hooks/template_include/
     */
    public function handle(string $template): string
    {
        if (is_home() || is_category() || is_tag()) {
            $this->config->set('~theme.page_layout', 'blog');
        } elseif (is_singular('post')) {
            $this->config->set('~theme.page_layout', 'post');
        }

        return $template;
    }
}
