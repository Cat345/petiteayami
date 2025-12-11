<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node) {
            $content = wc_print_notices(true);

            if (empty($content)) {
                return false;
            }

            $node->props['content'] = $content;
        },
    ],
];
