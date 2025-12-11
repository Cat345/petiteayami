<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node) {
            $node->tags = [];

            // Filter tags
            if (!empty($node->props['filter'])) {
                foreach ($node->children as $child) {
                    $child->tags = [];

                    foreach (explode(',', $child->props['tags'] ?? '') as $tag) {
                        // Strip tags as precaution if tags are mapped dynamically
                        $tag = strip_tags($tag);

                        if ($key = str_replace(' ', '-', trim($tag))) {
                            $child->tags[$key] = trim($tag);
                        }
                    }

                    $node->tags += $child->tags;
                }

                if (
                    $node->props['filter_order'] === 'manual' &&
                    $node->props['filter_order_manual']
                ) {
                    $order = array_map(
                        'strtolower',
                        array_map('trim', explode(',', $node->props['filter_order_manual'])),
                    );
                    uasort($node->tags, function ($a, $b) use ($order) {
                        $iA = array_search(strtolower($a), $order);
                        $iB = array_search(strtolower($b), $order);
                        return $iA !== false && $iB !== false
                            ? $iA - $iB
                            : ($iA !== false
                                ? -1
                                : ($iB !== false
                                    ? 1
                                    : strnatcmp($a, $b)));
                    });
                } else {
                    natsort($node->tags);
                }

                if ($node->props['filter_reverse']) {
                    $node->tags = array_reverse($node->tags, true);
                }
            }

            if ($node->props['panel_style'] === 'tile-checked') {
                app(Metadata::class)->set('script:builder-grid', [
                    'src' => Path::get('./app/grid.min.js', __DIR__),
                    'defer' => true,
                ]);
            }
        },
    ],
];
