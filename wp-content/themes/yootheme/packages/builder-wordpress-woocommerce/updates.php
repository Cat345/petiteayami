<?php

namespace YOOtheme;

return [
    '2.5.0-beta.1.1' => function ($node) {
        $rename = [
            'products' => 'woo_products',
            'pages' => 'woo_pages',
        ];

        if (($node->type ?? '') === 'layout') {
            $apply = function ($node) use (&$apply, $rename) {
                if (isset($node->type) && !empty($rename[$node->type])) {
                    $node->type = $rename[$node->type];
                }

                if (!empty($node->children)) {
                    array_map($apply, $node->children);
                }
            };
            $apply($node);
        }
    },
];
