<?php

namespace YOOtheme;

return [
    '3.0.10.1' => function ($node) {
        if (($node->props['type'] ?? '') === 'header') {
            $node->props['type'] = 'heading';
        }
    },
];
