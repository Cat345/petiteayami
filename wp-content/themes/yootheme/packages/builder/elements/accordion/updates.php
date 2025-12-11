<?php

namespace YOOtheme;

return [
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['title_element']);
    },

    '2.1.0-beta.0.1' => function ($node) {
        if (Arr::get($node->props, 'image_grid_width') === 'xxlarge') {
            $node->props['image_grid_width'] = '2xlarge';
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'image_breakpoint' => 'image_grid_breakpoint',
            'image_gutter' => fn($value) => [
                'image_grid_column_gap' => $value,
                'image_grid_row_gap' => $value,
            ],
        ]);
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.18.10.1' => function ($node) {
        Arr::updateKeys($node->props, ['image_inline_svg' => 'image_svg_inline']);
    },
];
