<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.7' => function ($node) {
        if (($node->props['margin'] ?? '') === 'remove-vertical') {
            $node->props['margin'] = 'remove';
        }
        if (isset($node->props['margin'])) {
            $node->props['margin_top'] = $node->props['margin'];
            $node->props['margin_bottom'] = $node->props['margin'];
        }
        if ($node->props['margin_remove_top'] ?? '') {
            $node->props['margin_top'] = 'remove';
        }
        if ($node->props['margin_remove_bottom'] ?? '') {
            $node->props['margin_bottom'] = 'remove';
        }
        if ($node->props['push'] ?? '') {
            $node->props['margin_bottom'] = 'auto';
        }
        unset(
            $node->props['margin'],
            $node->props['margin_remove_top'],
            $node->props['margin_remove_bottom'],
            $node->props['push'],
        );
    },
    '3.1.0-beta.0.1' => function ($node) {
        if (
            ($target = $node->props['parallax_target'] ?? '') &&
            str_starts_with($target, '![uk-grid]')
        ) {
            $node->props['parallax_target'] = str_replace('[uk-grid]', '', $target);
        }
    },
    '3.0.5.1' => function ($node) {
        if (
            (($node->props['animation'] ?? '') == 'parallax' ||
                ($node->props['item_animation'] ?? '') == 'parallax') &&
            !is_numeric($node->props['parallax_easing'] ?? '')
        ) {
            $node->props['parallax_easing'] = '1';
        }
    },
    '3.0.0-beta.3.1' => function ($node) {
        if (($node->props['parallax_target'] ?? '') === false) {
            unset($node->props['parallax_target']);
        }
    },
    '3.0.0-beta.2.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);
        [$style] = explode(':', $config('~theme.style'));

        if (
            $style === 'fjord' &&
            !array_key_exists('@base-h6-font-size', $config('~theme.less', [])) &&
            ($node->props['title_style'] ?? '') === 'h6' &&
            ($node->props['title_element'] ?? '') === 'h4'
        ) {
            $node->props['title_style'] = '';
        }
    },
    '2.8.0-beta.0.3' => function ($node) {
        foreach (['x', 'y', 'scale', 'rotate', 'opacity'] as $prop) {
            $key = "parallax_{$prop}";

            // Cleanup old values from before introducing '_start' and '_end' props
            unset($node->props[$key]);

            $start = preg_replace('/\s*,\s*/', ',', $node->props["{$key}_start"] ?? '');
            $end = preg_replace('/\s*,\s*/', ',', $node->props["{$key}_end"] ?? '');

            if ($start !== '' || $end !== '') {
                $default = in_array($prop, ['scale', 'opacity']) ? '1' : '0';

                $node->props[$key] = implode(',', [
                    $start !== '' ? $start : $default,
                    $end !== '' ? $end : $default,
                ]);
            }
            unset($node->props["{$key}_start"], $node->props["{$key}_end"]);
        }
    },
    '2.8.0-beta.0.1' => function ($node) {
        if ($node->props['parallax_target'] ?? '') {
            $node->props['parallax_target'] = '!.uk-section';
        }

        Arr::updateKeys($node->props, [
            'parallax_viewport' => function ($value) {
                if (!empty($value) && ($viewport = 100 * (1 - (float) $value))) {
                    return ['parallax_end' => "{$viewport}vh + {$viewport}%"];
                }
            },
        ]);
    },

    '2.7.11.1' => function ($node) {
        unset($node->props['pointer_events']);
    },

    '2.1.1.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if ($style == 'horizon') {
            if (
                (($node->props['title_style'] ?? '') === 'h6' ||
                    (($node->props['title_element'] ?? '') === 'h6' &&
                        empty($node->props['title_style']))) &&
                empty($node->props['title_color'])
            ) {
                $node->props['title_color'] = 'primary';
            }
        }

        if ($style == 'fjord') {
            if (
                (($node->props['title_style'] ?? '') === 'h4' ||
                    (($node->props['title_element'] ?? '') === 'h4' &&
                        empty($node->props['title_style']))) &&
                empty($node->props['title_color'])
            ) {
                $node->props['title_color'] = 'primary';
            }
        }
    },

    '2.1.0-beta.0.1' => function ($node, array $params) {
        $type = $params['type'];

        if (($node->props['maxwidth'] ?? '') === 'xxlarge') {
            $node->props['maxwidth'] = '2xlarge';
        }

        // move declaration of uk-hidden class to visibility settings
        if ($type->element && empty($node->props['visibility']) && !empty($node->props['class'])) {
            $node->props['class'] = trim(
                preg_replace_callback(
                    '/(^|\s+)uk-hidden@(s|m|l|xl)/',
                    function ($match) use ($node) {
                        $node->props['visibility'] = "hidden-{$match[2]}";
                        return '';
                    },
                    $node->props['class'],
                ),
            );
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        if (in_array($node->type ?? '', ['joomla_position', 'wordpress_area'])) {
            Arr::updateKeys($node->props, [
                'grid_divider' => 'divider',
                'grid_gutter' => fn($value) => ['column_gap' => $value, 'row_gap' => $value],
            ]);
        }
    },

    '1.20.0-beta.1.1' => function ($node) {
        if (
            in_array($node->type ?? '', [
                'joomla_position',
                'wordpress_area',
                'joomla_module',
                'wordpress_widget',
            ])
        ) {
            Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
        }
    },

    '1.20.0-beta.0.1' => function ($node) {
        if (in_array($node->type ?? '', ['joomla_module', 'wordpress_widget'])) {
            if (($node->props['title_style'] ?? '') === 'heading-primary') {
                $node->props['title_style'] = 'heading-medium';
            }

            /** @var Config $config */
            $config = app(Config::class);

            [$style] = explode(':', $config('~theme.style'));

            if (
                in_array($style, [
                    'craft',
                    'district',
                    'jack-backer',
                    'tomsen-brody',
                    'vision',
                    'florence',
                    'max',
                    'nioh-studio',
                    'sonic',
                    'summit',
                    'trek',
                ])
            ) {
                if (
                    ($node->props['title_style'] ?? '') === 'h1' ||
                    (empty($node->props['title_style']) &&
                        ($node->props['title_element'] ?? '') === 'h1')
                ) {
                    $node->props['title_style'] = 'heading-small';
                }
            }

            if (in_array($style, ['florence', 'max', 'nioh-studio', 'sonic', 'summit', 'trek'])) {
                if (($node->props['title_style'] ?? '') === 'h2') {
                    $node->props['title_style'] =
                        ($node->props['title_element'] ?? '') === 'h1' ? '' : 'h1';
                } elseif (
                    empty($node->props['title_style']) &&
                    ($node->props['title_element'] ?? '') === 'h2'
                ) {
                    $node->props['title_style'] = 'h1';
                }
            }

            if (in_array($style, ['fuse', 'horizon', 'joline', 'juno', 'lilian', 'vibe', 'yard'])) {
                if (($node->props['title_style'] ?? '') === 'heading-medium') {
                    $node->props['title_style'] = 'heading-small';
                }
            }

            if ($style == 'copper-hill') {
                if (($node->props['title_style'] ?? '') === 'heading-medium') {
                    $node->props['title_style'] =
                        ($node->props['title_element'] ?? '') === 'h1' ? '' : 'h1';
                } elseif (($node->props['title_style'] ?? '') === 'h1') {
                    $node->props['title_style'] =
                        ($node->props['title_element'] ?? '') === 'h2' ? '' : 'h2';
                } elseif (
                    empty($node->props['title_style']) &&
                    ($node->props['title_element'] ?? '') === 'h1'
                ) {
                    $node->props['title_style'] = 'h2';
                }
            }

            if (in_array($style, ['trek', 'fjord'])) {
                if (($node->props['title_style'] ?? '') === 'heading-medium') {
                    $node->props['title_style'] = 'heading-large';
                }
            }
        }
    },
];
