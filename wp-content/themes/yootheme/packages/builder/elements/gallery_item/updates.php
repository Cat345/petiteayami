<?php

namespace YOOtheme;

return [
    '5.0.0-beta.8.1' => function ($node) {
        unset($node->props['video_title']);
    },

    '4.5.0-beta.0.1' => function ($node) {
        if (($node->props['image'] ?? '') && ($node->props['video'] ?? '')) {
            unset($node->props['video']);
        }
    },

    '2.5.0-beta.1.3' => function ($node) {
        if (!empty($node->props['tags'])) {
            $node->props['tags'] = ucwords($node->props['tags']);
        }
    },

    '1.18.0' => function ($node) {
        $node->props['hover_image'] ??= $node->props['image2'] ?? '';
    },
];
