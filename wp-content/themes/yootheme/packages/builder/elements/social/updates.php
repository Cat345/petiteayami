<?php

namespace YOOtheme;

return [
    '4.4.6' => function ($node) {
        // Previous version still had grid_gap as default
        unset($node->props['grid_gap']);

        if (!Arr::has($node->props, 'grid_column_gap')) {
            $node->props['grid_column_gap'] = '';
        }

        if (!Arr::has($node->props, 'grid_row_gap')) {
            $node->props['grid_row_gap'] = '';
        }

        // Remove deprecated prop
        unset($node->props['inline_align']);
    },

    '4.4.0-beta.4' => function ($node) {
        Arr::updateKeys($node->props, [
            'grid_gap' => fn($value) => ['grid_column_gap' => $value, 'grid_row_gap' => $value],
        ]);
    },

    '4.4.0-beta.0.1' => function ($node) {
        if (!empty($node->props['icon_width'])) {
            $node->props['image_width'] = $node->props['icon_width'];
            $node->props['image_height'] = $node->props['icon_width'];
        }
    },

    '2.8.0-beta.0.3' => function ($node) {
        Arr::del($node->props, 'gap');
    },

    '2.4.14.1' => function ($node) {
        Arr::updateKeys($node->props, ['gap' => 'grid_gap']);
    },

    '2.2.0-beta.0.1' => function ($node) {
        $props = (array) ($node->source->props ?? []);

        for ($i = 1; $i <= 5; $i++) {
            if (!empty($props["link_{$i}"])) {
                $node->children[] = (object) [
                    'type' => 'social_item',
                    'props' => (object) ['link' => ''],
                    'source' => (object) [
                        'query' => $node->source->query,
                        'props' => (object) ['link' => $props["link_{$i}"]],
                    ],
                ];
            } elseif (!empty($node->props["link_{$i}"])) {
                $node->children[] = (object) [
                    'type' => 'social_item',
                    'props' => (object) ['link' => $node->props["link_{$i}"]],
                ];
            }

            unset($node->props["link_{$i}"]);
        }

        unset($node->source);
    },

    '2.1.0-beta.0.1' => function ($node) {
        if (!empty($node->props['icon_ratio'])) {
            $node->props['icon_width'] = round(20 * $node->props['icon_ratio']);
            unset($node->props['icon_ratio']);
        }
    },

    '2.0.5.1' => function ($node) {
        $links = !empty($node->props['links']) ? (array) $node->props['links'] : [];

        for ($i = 0; $i <= 4; $i++) {
            if (isset($links[$i])) {
                $node->props['link_' . ($i + 1)] = $links[$i];
            }
        }

        unset($node->props['links']);
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, ['gutter' => 'gap']);
    },

    '1.20.0-beta.4' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },
];
