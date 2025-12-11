<?php

namespace YOOtheme;

return [
    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['inline_align']);
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'gutter' => fn($value) => ['grid_column_gap' => $value, 'grid_row_gap' => $value],
        ]);
    },

    '1.20.0-beta.4' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },
];
