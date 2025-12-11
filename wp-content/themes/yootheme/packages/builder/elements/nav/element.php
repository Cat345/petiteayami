<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node, $params) {
            $node->props['scrollspy_nav'] = array_any(
                    $params['path'] ?? [],
                    fn($parent) => $parent->type === 'column' && $parent->props['position_sticky']
                ) && array_any(
                    $node->children ?? [],
                    fn($child) => str_contains((string) $child->props['link'], '#')
                );
        },
    ],
];
