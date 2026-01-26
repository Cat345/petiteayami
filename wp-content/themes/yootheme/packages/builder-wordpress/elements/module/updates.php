<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.14' => function ($node) {
        if (($node->props['menu_icon_width'] ?? '') && !($node->props['menu_image_width'] ?? '')) {
            $node->props['menu_image_width'] = $node->props['menu_icon_width'];
        }
        unset($node->props['menu_icon_width']);
    },

    '3.0.0-beta.1.5' => function ($node) {
        Arr::updateKeys($node->props, ['menu_style' => 'menu_type']);
    },
];
