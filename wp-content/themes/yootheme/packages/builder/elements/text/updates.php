<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.1' => function ($node) {
        if ($node->props['height_expand'] ?? '') {
            $node->props['push'] = true;
            unset($node->props['height_expand']);
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        if (($node->props['text_size'] ?? '') && !($node->props['text_style'] ?? '')) {
            $node->props['text_style'] = $node->props['text_size'];
        }
        unset($node->props['text_size']);
    },

    '1.20.0-beta.4' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },
];
