<?php

namespace YOOtheme;

return [
    '5.0.0-beta.8.1' => function ($node) {
        unset($node->props['video_title']);
    },

    '5.0.0-beta.0.16' => function ($node) {
        Arr::updateKeys($node->props, ['padding' => 'padding_top']);
        if ($node->props['padding_top'] ?? '') {
            $node->props['padding_bottom'] = $node->props['padding_top'];
        }
        if ($node->props['padding_remove_top'] ?? '') {
            $node->props['padding_top'] = 'none';
        }
        if ($node->props['padding_remove_bottom'] ?? '') {
            $node->props['padding_bottom'] = 'none';
        }
        unset(
            $node->props['padding_remove_top'],
            $node->props['padding_remove_bottom'],
        );
    },

    '5.0.0-beta.0.15' => function ($node) {
        Arr::updateKeys($node->props, [
            'height_offset_top' => 'height_viewport_offset',
        ]);
    },

    '5.0.0-beta.0.3' => function ($node) {
        if (!($node->props['image'] ?? '') && empty($node->source->props->image) &&
            (($node->props['video'] ?? '') || !empty($node->source->props->video))
        ) {
            $node->props['image_width'] = $node->props['video_width'] ?? '';
            $node->props['image_height'] = $node->props['video_height'] ?? '';
        }
        unset($node->props['video_width'], $node->props['video_height']);
    },

    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset(
            $node->props['header_overlay'],
            $node->props['image_fixed'],
            $node->props['media'],
            $node->props['media_advanced'],
            $node->props['media_size'],
        );
    },

    '4.4.0-beta.0.3' => function ($node) {
        if (
            ($node->props['vertical_align'] ?? '') &&
            !(
                (($node->props['height'] ?? '') == 'viewport' &&
                    ($node->props['height_viewport'] ?? '') <= 100) ||
                in_array($node->props['height'] ?? '', ['section', 'pixels'])
            )
        ) {
            $node->props['vertical_align'] = '';
        }
    },

    '4.3.1' => function ($node) {
        if (
            ($node->props['header_transparent_noplaceholder'] ?? '') &&
            ($node->props['header_transparent_text_color'] ?? '')
        ) {
            $element = $node->children[0]->children[0]->children[0] ?? null;

            if (
                $element &&
                in_array($element->type ?? '', ['slideshow', 'slider']) &&
                ($element->props->text_color ?? '') !==
                    $node->props['header_transparent_text_color']
            ) {
                $element->props->css = trim(
                    str_replace(
                        "\n\n.el-item { --uk-inverse: dark !important; }",
                        '',
                        $element->props->css ?? '',
                    ),
                );
            }
        }
    },

    '4.3.0-beta.0.5' => function ($node, $params) {
        if ($height = $node->props['height'] ?? '') {
            $rename = [
                'full' => 'viewport',
                'percent' => 'viewport',
                'section' => 'section',
                'expand' => 'page',
            ];
            if (isset($rename[$height])) {
                $node->props['height'] = $rename[$height];

                if (
                    $height !== 'expand' &&
                    ($params['i'] ?? 0) < 2 &&
                    empty($params['updateContext']['height'])
                ) {
                    $node->props['height_offset_top'] = true;
                }

                if ($height === 'percent') {
                    $node->props['height_viewport'] = 80;
                }
            } elseif (preg_match('/viewport-([2-4])/', $height, $match)) {
                $node->props['height'] = 'viewport';
                $node->props['height_viewport'] = ((int) $match[1]) * 100;
            }

            $params['updateContext']['height'] = true;
        }
        $params['updateContext']['sectionIndex'] = $params['i'] ?? 0;
    },

    '4.3.0-beta.0.3' => function ($node) {
        if ($node->props['header_transparent'] ?? '') {
            if (
                ($node->props['text_color'] ?? '') != ($node->props['header_transparent'] ?? '') ||
                !(($node->props['image'] ?? '') || ($node->props['video'] ?? ''))
            ) {
                $node->props['header_transparent_text_color'] = $node->props['header_transparent'];
            }

            $node->props['header_transparent'] = true;
        }

        Arr::updateKeys($node->props, ['image_focal_point' => 'media_focal_point']);
    },

    '3.0.5.1' => function ($node) {
        if (
            ($node->props['image_effect'] ?? '') == 'parallax' &&
            !is_numeric($node->props['image_parallax_easing'] ?? '')
        ) {
            $node->props['image_parallax_easing'] = '1';
        }
    },

    '2.8.0-beta.0.12' => function ($node) {
        if (($node->props['image_position'] ?? '') === '') {
            $node->props['image_position'] = 'center-center';
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

    '2.8.0-beta.0.2' => function ($node) {
        if (isset($node->props['sticky'])) {
            $node->props['sticky'] = 'cover';
        }
    },

    '2.4.12.1' => function ($node) {
        if (($node->props['animation_delay'] ?? '') === true) {
            $node->props['animation_delay'] = '200';
        }
    },

    '2.4.0-beta.0.2' => function ($node) {
        Arr::updateKeys($node->props, ['image_visibility' => 'media_visibility']);
    },

    '2.3.0-beta.1.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if ($style == 'fjord') {
            if (($node->props['width'] ?? '') === 'default') {
                $node->props['width'] = 'large';
            }
        }
    },

    '2.1.0-beta.2.1' => function ($node) {
        if (in_array($node->props['style'] ?? '', ['primary', 'secondary'])) {
            $node->props['text_color'] = '';
        }
    },

    '2.0.0-beta.5.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if (!in_array($style, ['jack-baker', 'morgan-consulting', 'vibe'])) {
            if (($node->props['width'] ?? '') === 'large') {
                $node->props['width'] = 'xlarge';
            }
        }

        if (
            in_array($style, [
                'craft',
                'district',
                'florence',
                'makai',
                'matthew-taylor',
                'pinewood-lake',
                'summit',
                'tomsen-brody',
                'trek',
                'vision',
                'yard',
            ])
        ) {
            if (($node->props['width'] ?? '') === 'default') {
                $node->props['width'] = 'large';
            }
        }
    },

    '1.18.10.2' => function ($node) {
        if (!empty($node->props['image']) && !empty($node->props['video'])) {
            unset($node->props['video']);
        }
    },

    '1.18.0' => function ($node) {
        $node->props['image_effect'] ??= $node->props['image_fixed'] ?? '' ? 'fixed' : '';

        if (
            !isset($node->props['vertical_align']) &&
            in_array($node->props['height'] ?? '', ['full', 'percent', 'section'])
        ) {
            $node->props['vertical_align'] = 'middle';
        }

        if (($node->props['style'] ?? '') === 'video') {
            $node->props['style'] = 'default';
        }

        if (($node->props['width'] ?? '') === 0) {
            $node->props['width'] = 'default';
        } elseif (($node->props['width'] ?? '') === 2) {
            $node->props['width'] = 'small';
        } elseif (($node->props['width'] ?? '') === 3) {
            $node->props['width'] = 'expand';
        }
    },
];
