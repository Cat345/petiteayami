<?php

namespace YOOtheme;

return [
    '3.0.0-beta.1.5' => function ($node) {
        Arr::updateKeys($node->props, ['menu_style' => 'menu_type']);
    },
];
