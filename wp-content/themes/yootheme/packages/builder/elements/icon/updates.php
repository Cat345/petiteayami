<?php

namespace YOOtheme;

return [
    '2.1.0-beta.0.1' => function ($node) {
        if (!empty($node->props['icon_ratio'])) {
            $node->props['icon_width'] = round(20 * $node->props['icon_ratio']);
            unset($node->props['icon_ratio']);
        }
    },

    '1.20.0-beta.4' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },
];
