<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.2' => function ($node) {
        if (
            (($node->props['icon'] ?? '') || !empty($node->source->props->icon)) &&
            !($node->props['image'] ?? '') && empty($node->source->props->image) &&
            ($node->props['icon_color'] ?? '')
        ) {
            $node->props['image_svg_color'] = $node->props['icon_color'];
        }
        unset($node->props['icon_color']);
    },
];
