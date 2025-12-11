<?php

namespace YOOtheme;

return [
    // moved from 4.0.0-beta.9 to 4.3.9 (previously missing @import)
    // moved from 4.3.9 to 4.5.0-beta.0.4 (ensure to unset prop)
    '4.5.0-beta.0.4' => function ($node) {
        if (Arr::get($node->props, 'nav_element') === 'nav') {
            $node->props['html_element'] = 'nav';
        }
        unset($node->props['nav_element']);
    },
];
