<?php

namespace YOOtheme\Builder\Wordpress\Listener;

use YOOtheme\Builder;
use YOOtheme\Builder\Wordpress\PostHelper;
use YOOtheme\Config;
use YOOtheme\View;

class RenderBuilderPage
{
    public View $view;
    public Config $config;
    public Builder $builder;

    public function __construct(Config $config, Builder $builder, View $view)
    {
        $this->view = $view;
        $this->config = $config;
        $this->builder = $builder;
    }

    public function handle($template)
    {
        if (!is_singular() || post_password_required()) {
            return $template;
        }

        global $post;

        $content = isset($post->post_content)
            ? PostHelper::matchContent($post->post_content)
            : false;

        if ($this->config->get('app.isCustomizer')) {
            if ($page = $this->config->get('req.customizer.page')) {
                $content = !empty($page['content']) ? json_encode($page['content']) : null;
            }

            $this->config->add('customizer.page', [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $content ? $this->builder->load($content) : $content,
                'preview' => !empty($page),
                'collision' => PostHelper::getCollision($post),
            ]);
        }

        // Render builder output
        if ($content) {
            // Delay rendering to ensure regular WordPress content flow (wp_enqueue_scripts hook before shortcode hooks)
            $this->view['sections']->set(
                'builder',
                fn() => $this->builder->render($content, ['prefix' => 'page', 'post' => $post]),
            );
        }

        return $template;
    }
}
