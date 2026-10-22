<?php

namespace YOOtheme\Builder\Wordpress\Listener;

use YOOtheme\Builder;
use YOOtheme\Builder\Wordpress\PostHelper;
use YOOtheme\Config;
use YOOtheme\View;
use function YOOtheme\app;

class RenderBuilderPage
{
    public View $view;
    public Config $config;

    public function __construct(Config $config, View $view)
    {
        $this->view = $view;
        $this->config = $config;
    }

    /**
     * @param string $template
     * @return string
     */
    public function handle($template)
    {
        if (!is_singular() || post_password_required()) {
            return $template;
        }

        global $post;

        $content = PostHelper::matchContent($post->post_content ?? '');

        if ($this->config->get('app.isCustomizer')) {
            if ($page = $this->config->get('req.customizer.page')) {
                $content = !empty($page['content']) ? json_encode($page['content']) : null;
            }

            /** @var Builder $builder */
            $builder = app(Builder::class);

            $this->config->add('customizer.page', [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $content ? $builder->load($content) : $content,
                'preview' => !empty($page),
                'collision' => PostHelper::getCollision($post),
            ]);
        }

        // Render builder output
        if ($content) {
            $builder ??= app(Builder::class);

            // Delay rendering to ensure regular WordPress content flow (wp_enqueue_scripts hook before shortcode hooks)
            $this->view['sections']->set(
                'builder',
                fn() => $builder->render($content, ['prefix' => 'page', 'post' => $post]),
            );
        }

        return $template;
    }
}
