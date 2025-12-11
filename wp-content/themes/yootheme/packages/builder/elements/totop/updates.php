<?php

namespace YOOtheme;

return [
    '1.20.0-beta.4' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },
];
