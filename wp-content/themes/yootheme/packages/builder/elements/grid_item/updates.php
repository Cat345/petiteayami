<?php

namespace YOOtheme;

return [
    '5.0.0-beta.8.1' => function ($node) {
        unset($node->props['video_title']);
    },

    '2.5.0-beta.1.3' => function ($node) {
        if (!empty($node->props['tags'])) {
            $node->props['tags'] = ucwords($node->props['tags']);
        }
    },
];
