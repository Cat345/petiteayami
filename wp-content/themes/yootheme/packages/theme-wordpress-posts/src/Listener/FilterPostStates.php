<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use WP_Post;
use YOOtheme\Builder\Wordpress\PostHelper;
use YOOtheme\Config;

class FilterPostStates
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param array<string, mixed> $post_states
     * @param WP_Post              $post
     * @return array<string, mixed>
     */
    public function handle($post_states, $post)
    {
        if (!PostHelper::matchContent($post->post_content)) {
            return $post_states;
        }

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

        return $post_states;
    }
}
