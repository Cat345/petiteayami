<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function () {
            if (!is_product()) {
                return false;
            }
        },
    ],
];
