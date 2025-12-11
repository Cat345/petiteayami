<?php

namespace YOOtheme;

return [
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
            Arr::get($node->props, 'vertical_align') &&
            !(
                (Arr::get($node->props, 'height') == 'viewport' &&
                    Arr::get($node->props, 'height_viewport') <= 100) ||
                in_array(Arr::get($node->props, 'height'), ['section', 'pixels'])
            )
        ) {
            Arr::set($node->props, 'vertical_align', '');
        }
    },

    '4.3.1' => function ($node) {
        if (
            Arr::get($node->props, 'header_transparent_noplaceholder') &&
            Arr::get($node->props, 'header_transparent_text_color')
        ) {
            $element = $node->children[0]->children[0]->children[0] ?? null;

            if (
                $element &&
                in_array($element->type ?? '', ['slideshow', 'slider']) &&
                ($element->props->text_color ?? '') !==
                    Arr::get($node->props, 'header_transparent_text_color')
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
        if ($height = Arr::get($node->props, 'height')) {
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

    '4.3.0-beta.0.3' => function ($node, $params) {
        if (Arr::get($node->props, 'header_transparent')) {
            if (
                Arr::get($node->props, 'text_color') !=
                    Arr::get($node->props, 'header_transparent') ||
                !(Arr::get($node->props, 'image') || Arr::get($node->props, 'video'))
            ) {
                $node->props['header_transparent_text_color'] =
                    $node->props['header_transparent'];
            }

            $node->props['header_transparent'] = true;
        }

        Arr::updateKeys($node->props, ['image_focal_point' => 'media_focal_point']);
    },

    '3.0.5.1' => function ($node) {
        if (
            Arr::get($node->props, 'image_effect') == 'parallax' &&
            !is_numeric(Arr::get($node->props, 'image_parallax_easing'))
        ) {
            Arr::set($node->props, 'image_parallax_easing', '1');
        }
    },

    '2.8.0-beta.0.12' => function ($node) {
        if (Arr::get($node->props, 'image_position') === '') {
            $node->props['image_position'] = 'center-center';
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

    '2.8.0-beta.0.2' => function ($node) {
        if (isset($node->props['sticky'])) {
            $node->props['sticky'] = 'cover';
        }
    },

    '2.4.12.1' => function ($node) {
        if (Arr::get($node->props, 'animation_delay') === true) {
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
            if (Arr::get($node->props, 'width') === 'default') {
                $node->props['width'] = 'large';
            }
        }
    },

    '2.1.0-beta.2.1' => function ($node) {
        if (in_array(Arr::get($node->props, 'style'), ['primary', 'secondary'])) {
            $node->props['text_color'] = '';
        }
    },

    '2.0.0-beta.5.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if (!in_array($style, ['jack-baker', 'morgan-consulting', 'vibe'])) {
            if (Arr::get($node->props, 'width') === 'large') {
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
            if (Arr::get($node->props, 'width') === 'default') {
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
        if (!isset($node->props['image_effect'])) {
            $node->props['image_effect'] = Arr::get($node->props, 'image_fixed') ? 'fixed' : '';
        }

        if (
            !isset($node->props['vertical_align']) &&
            in_array(Arr::get($node->props, 'height'), ['full', 'percent', 'section'])
        ) {
            $node->props['vertical_align'] = 'middle';
        }

        if (Arr::get($node->props, 'style') === 'video') {
            $node->props['style'] = 'default';
        }

        if (Arr::get($node->props, 'width') === 0) {
            $node->props['width'] = 'default';
        } elseif (Arr::get($node->props, 'width') === 2) {
            $node->props['width'] = 'small';
        } elseif (Arr::get($node->props, 'width') === 3) {
            $node->props['width'] = 'expand';
        }
    },
];
