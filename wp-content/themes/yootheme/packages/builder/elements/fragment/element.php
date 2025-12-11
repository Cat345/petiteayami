<?php

namespace YOOtheme;

return [
    'transforms' => [
        'prerender' => function ($node, $params) {

            $node->props['isPartial'] = !$params['parent'] && str_starts_with($params['template'] ?? '', '_');

            if ($node->props['isPartial']) {
                $metadata = app(Metadata::class);
                $node->props['metadata'] = $metadata->all();
            }
        },

        'render' => function ($node, $params) {
            $node->props['root'] = !$params['parent'];

            if ($node->props['isPartial']) {
                $metadata = app(Metadata::class);
                $node->props['metadata'] = array_diff($metadata->all(), $node->props['metadata']);
                array_walk($node->props['metadata'], function ($metadata) {
                    if ($metadata->src) {
                        $metadata->attributes['src'] = Url::to($metadata->src);
                    }

                    if ($metadata->href) {
                        $metadata->attributes['href'] = Url::to($metadata->href);
                    }
                });
            }
        },
    ],
];
