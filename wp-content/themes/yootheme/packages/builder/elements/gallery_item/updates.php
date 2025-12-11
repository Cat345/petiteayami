<?php

namespace YOOtheme;

return [
    '4.5.0-beta.0.1' => function ($node) {
        if (Arr::get($node->props, 'image') && Arr::get($node->props, 'video')) {
            unset($node->props['video']);
        }
    },

    '2.5.0-beta.1.3' => function ($node) {
        if (!empty($node->props['tags'])) {
            $node->props['tags'] = ucwords($node->props['tags']);
        }
    },

    '1.18.0' => function ($node) {
        if (!isset($node->props['hover_image'])) {
            $node->props['hover_image'] = Arr::get($node->props, 'image2');
        }
    },
];
