<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node, $params) {
            foreach (['height', 'height_viewport', 'height_offset_top', 'parallax'] as $prop) {
                $node->props["row_{$prop}"] = $params['parent']->props[$prop] ?? null;
            }

            foreach ($node->children as $child) {
                if (
                    !empty($child->props['height_expand']) &&
                    (!$node->props['position_sticky'] ||
                        (in_array($node->props['position_sticky'], ['row', 'section']) &&
                            $node->props['row_height']))
                ) {
                    $node->props['child_height_expand'] = true;
                    $node->props['vertical_align'] = '';
                    break;
                }
            }
        },
    ],
];
