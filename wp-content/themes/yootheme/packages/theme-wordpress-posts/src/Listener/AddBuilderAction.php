<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use WP_Post;
use YOOtheme\Builder\Wordpress\PostHelper;
use YOOtheme\Http\Request;
use YOOtheme\Url;

class AddBuilderAction
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param array<string, mixed> $actions
     * @param WP_Post              $post
     * @return array<string, mixed>
     */
    public function handle(array $actions, $post): array
    {
        if (
            !PostHelper::matchContent($post->post_content) ||
            !current_user_can('edit_theme_options')
        ) {
            return $actions;
        }

        $link = Url::route('customizer', [
            'site' => get_permalink($post->ID),
            'return' => (string) $this->request->getUri(),
            'section' => 'builder',
        ]);

        $actions['yootheme'] = sprintf(
            '<a href="%s" class="tm-button">%s</a>',
            $link,
            __('Edit with YOOtheme', 'yootheme'),
        );

        unset($actions['classic']);

        return $actions;
    }
}
