<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node) {
            $config = app(Config::class);

            // Force reload, otherwise Modal/Offcanvas might be orphaned in the DOM
            if ($config('app.isCustomizer') && (
                ($node->props['link'] && $node->props['link_target'] == 'modal') ||
                (!$node->props['link'] && $node->props['dialog'] && in_array($node->props['dialog_layout'], ['modal', 'offcanvas']))
            )) {
                $node->attrs['data-preview'] = 'reload';
            }

            // Don't render element if content fields are empty
            return ($node->props['link'] || $node->props['dialog']) && ($node->props['content'] != '' || $node->props['icon']);
        },
    ],
];
