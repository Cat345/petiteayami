<?php

namespace YOOtheme;

return [
    '4.5.0-beta.0.1' => function ($node) {
        if (Arr::get($node->props, 'image') && Arr::get($node->props, 'video')) {
            unset($node->props['video']);
        }
    },
];
