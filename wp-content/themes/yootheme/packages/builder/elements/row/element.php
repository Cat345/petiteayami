<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node, $params) {
            $node->props['parent'] = $params['parent']->type;
        },
    ],
];
