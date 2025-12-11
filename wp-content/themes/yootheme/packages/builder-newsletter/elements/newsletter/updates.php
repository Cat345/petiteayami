<?php

namespace YOOtheme;

return [
    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, ['gutter' => 'gap']);
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },
];
