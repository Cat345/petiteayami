<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node, $params) {
            // Display
            foreach (['title', 'meta', 'content', 'link', 'thumbnail'] as $key) {
                if (!$params['parent']->props["show_{$key}"]) {
                    $node->props[$key] = '';
                }
            }

            /**
             * Auto-correct media rendering for dynamic content
             *
             * @var View $view
             */
            $view = app(View::class);

            if ($node->props['image'] && $view->isVideo($node->props['image'])) {
                $node->props['video'] = $node->props["image"];
                $node->props['image'] = null;
            } elseif ($node->props['video'] && $view->isImage($node->props['video'])) {
                $node->props['image'] = $node->props['video'];
                $node->props['video'] = null;
            }

            // Don't render element if content fields are empty
            return $node->props['image'] || $node->props['video'];
        },
    ],
];
