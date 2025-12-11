<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class FilterPostStates
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle($post_states, ?\WP_Post $post = null)
    {
        // is builder?
        if ($post && ($post->builder = preg_match('/<!--\s?{/', $post->post_content))) {
            $post_states = (array) $post_states;

            // remove gutenberg?
            $key = array_search('Gutenberg', $post_states);

            if ($key !== false) {
                unset($post_states[$key]);
            }

            $post_states[$this->config->get('theme.template', '')] = $this->config->get(
                'theme.name',
                '',
            );
        }

        return $post_states;
    }
}
