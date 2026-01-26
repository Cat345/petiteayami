<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.15' => function ($node) {
        Arr::updateKeys($node->props, [
            'viewport_height' => 'height_viewport',
            'viewport_height_viewport' => 'height_viewport_height',
            'viewport_height_offset_top' => 'height_viewport_offset',
        ]);
    },

    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['width_max']);
    },

    '4.3.0-beta.0.5' => function ($node, $params) {
        if ($height = $node->props['viewport_height'] ?? '') {
            if ($height === 'full' || $height === 'percent') {
                $node->props['viewport_height'] = 'viewport';
            }

            if ($height === 'percent') {
                $node->props['viewport_height_viewport'] = 80;
            }

            if (
                ($params['updateContext']['sectionIndex'] ?? 0) < 2 &&
                empty($params['updateContext']['height'])
            ) {
                $node->props['viewport_height_offset_top'] = true;
            }

            $params['updateContext']['height'] = true;
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        foreach (['title_style', 'meta_style', 'content_style'] as $prop) {
            if (in_array($node->props[$prop] ?? '', ['meta', 'lead'])) {
                $node->props[$prop] = "text-{$node->props[$prop]}";
            }
        }
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.18.0' => function ($node) {
        if (
            !isset($node->props['width_breakpoint']) &&
            ($node->props['width_max'] ?? '') === false
        ) {
            $node->props['width_breakpoint'] = true;
        }
    },
];
