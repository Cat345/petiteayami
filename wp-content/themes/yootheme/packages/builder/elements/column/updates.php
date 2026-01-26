<?php

namespace YOOtheme;

return [
    '5.0.0-beta.8.1' => function ($node) {
        unset($node->props['video_title']);
    },

    '5.0.0-beta.0.3' => function ($node) {
        if (
            !($node->props['image'] ?? '') &&
            empty($node->source->props->image) &&
            (($node->props['video'] ?? '') || !empty($node->source->props->video))
        ) {
            $node->props['image_width'] = $node->props['video_width'] ?? '';
            $node->props['image_height'] = $node->props['video_height'] ?? '';
        }
        unset($node->props['video_width'], $node->props['video_height']);
    },

    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['widths']);
    },

    '4.3.0-beta.0.3' => function ($node) {
        Arr::updateKeys($node->props, ['image_focal_point' => 'media_focal_point']);
    },

    '4.0.0-beta.9.1' => function ($node) {
        $style = $node->props['style'] ?? '';
        if (preg_match('/^tile-(tile|card)-/', $style)) {
            $node->props['style'] = substr($style, 5);
        }
    },

    '4.0.0-beta.9' => function ($node) {
        if ($node->props['style'] ?? '') {
            $node->props['style'] = "tile-{$node->props['style']}";
        }
    },

    '3.0.5.1' => function ($node) {
        if (
            ($node->props['image_effect'] ?? '') == 'parallax' &&
            !is_numeric($node->props['image_parallax_easing'] ?? '')
        ) {
            $node->props['image_parallax_easing'] = '1';
        }
    },

    '2.8.0-beta.0.3' => function ($node) {
        foreach (['bgx', 'bgy'] as $prop) {
            $key = "image_parallax_{$prop}";
            $start = $node->props["{$key}_start"] ?? '';
            $end = $node->props["{$key}_end"] ?? '';
            if ($start != '' || $end != '') {
                $node->props[$key] = implode(',', [
                    $start != '' ? $start : '0',
                    $end != '' ? $end : '0',
                ]);
            }
            unset($node->props["{$key}_start"], $node->props["{$key}_end"]);
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
        if (in_array($node->props['style'] ?? '', ['primary', 'secondary'])) {
            $node->props['text_color'] = '';
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        unset($node->props['widths']);
    },

    '1.18.0' => function ($node) {
        if (
            !isset($node->props['vertical_align']) &&
            ($node->props['vertical_align'] ?? '') === true
        ) {
            $node->props['vertical_align'] = 'middle';
        }
    },
];
