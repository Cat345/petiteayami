<?php

namespace YOOtheme;

return [
    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset(
            $node->props['navigation'],
            $node->props['pagination_start_end']
        );
    },
];
