<?php

namespace YOOtheme;

return [
    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'gutter' => fn($value) => ['grid_column_gap' => $value, 'grid_row_gap' => $value],
        ]);
    },
];
