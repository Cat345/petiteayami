<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Http\Request;
use YOOtheme\Url;

class AddBuilderAction
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(array $actions, \WP_Post $post): array
    {
        if (empty($post->builder)) {
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
            __('YOOtheme Builder', 'yootheme'),
        );

        unset($actions['classic']);

        return $actions;
    }
}
