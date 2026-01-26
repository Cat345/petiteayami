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
];
