<?php

namespace YOOtheme;

return [
    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['widths']);
    },

    '4.3.0-beta.0.3' => function ($node, $params) {
        Arr::updateKeys($node->props, ['image_focal_point' => 'media_focal_point']);
    },

    '4.0.0-beta.9.1' => function ($node) {
        $style = Arr::get($node->props, 'style') ?: '';
        if (preg_match('/^tile-(tile|card)-/', $style)) {
            $node->props['style'] = substr($style, 5);
        }
    },

    '4.0.0-beta.9' => function ($node) {
        if (Arr::get($node->props, 'style')) {
            $node->props['style'] = "tile-{$node->props['style']}";
        }
    },

    '3.0.5.1' => function ($node) {
        if (
            Arr::get($node->props, 'image_effect') == 'parallax' &&
            !is_numeric(Arr::get($node->props, 'image_parallax_easing'))
        ) {
            Arr::set($node->props, 'image_parallax_easing', '1');
        }
    },

    '2.8.0-beta.0.3' => function ($node) {
        foreach (['bgx', 'bgy'] as $prop) {
            $key = "image_parallax_{$prop}";
            $start = Arr::get($node->props, "{$key}_start", '');
            $end = Arr::get($node->props, "{$key}_end", '');
            if ($start != '' || $end != '') {
                Arr::set(
                    $node->props,
                    $key,
                    implode(',', [$start != '' ? $start : 0, $end != '' ? $end : 0]),
                );
            }
            Arr::del($node->props, "{$key}_start");
            Arr::del($node->props, "{$key}_end");
        }
    },

    '2.8.0-beta.0.1' => function ($node) {
        if (isset($node->props['position_sticky'])) {
            $node->props['position_sticky'] = 'column';
        }
    },

    '2.4.0-beta.0.2' => function ($node) {
        Arr::updateKeys($node->props, ['image_visibility' => 'media_visibility']);
    },

    '2.1.0-beta.2.1' => function ($node) {
        if (in_array(Arr::get($node->props, 'style'), ['primary', 'secondary'])) {
            $node->props['text_color'] = '';
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        unset($node->props['widths']);
    },

    '1.18.0' => function ($node) {
        if (
            !isset($node->props['vertical_align']) &&
            Arr::get($node->props, 'vertical_align') === true
        ) {
            $node->props['vertical_align'] = 'middle';
        }
    },
];
