<?php

namespace YOOtheme;

return [
     '5.0.0-beta.0.11' => function ($node) {
        if ($node->props['content_expand'] ?? '') {
            $hasProp = fn($prop) => array_any($node->children ?? [], fn($child) => !empty($child->props->$prop) || !empty($child->source->props->$prop));
            $content = $hasProp('content');
            $meta = $hasProp('meta');

            if (!$content && (!$meta || ($node->props['meta_align'] ?? '') == 'above-title')) {
                $node->props['title_margin_auto'] = true;
            }
            if (($node->props['meta_align'] ?? '') == 'below-content' || (!$content && (($node->props['meta_align'] ?? '') == 'above-content' || (($node->props['meta_align'] ?? '') == 'below-title' )))) {
                $node->props['meta_margin_auto'] = true;
            }
            if (!($meta && ($node->props['meta_align'] ?? '') == 'below-content')) {
                $node->props['content_margin_auto'] = true;
            }
        }
        unset($node->props['content_expand']);
    },

    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['grid_mode'], $node->props['image_box_shadow_bottom']);
    },

    '4.1.0-beta.0.1' => function ($node) {
        if ($node->props['grid_masonry'] ?? '') {
            $node->props['grid_masonry'] = 'next';
        }
    },

    '4.0.0-beta.9' => function ($node) {
        if (($node->props['overlay_link'] ?? '') && ($node->props['css'] ?? '')) {
            $node->props['css'] = str_replace('.el-item', '.el-item > *', $node->props['css']);
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        foreach (['title_style', 'meta_style', 'content_style'] as $prop) {
            if (in_array($node->props[$prop] ?? '', ['meta', 'lead'])) {
                $node->props[$prop] = "text-{$node->props[$prop]}";
            }
        }
    },

    '2.4.14.2' => function ($node) {
        $node->props['animation'] = $node->props['item_animation'] ?? '';
        $node->props['item_animation'] = true;
    },

    '2.1.0-beta.0.1' => function ($node) {
        if (($node->props['item_maxwidth'] ?? '') === 'xxlarge') {
            $node->props['item_maxwidth'] = '2xlarge';
        }
    },

    '2.0.0-beta.8.1' => function ($node) {
        Arr::updateKeys($node->props, ['grid_align' => 'grid_column_align']);
    },

    '2.0.0-beta.5.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'link_type' => function ($value) {
                if ($value === 'content') {
                    return [
                        'title_link' => true,
                        'link_text' => '',
                    ];
                } elseif ($value === 'element') {
                    return [
                        'overlay_link' => true,
                        'link_text' => '',
                    ];
                }
            },
        ]);
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'divider' => 'grid_divider',
            'filter_breakpoint' => 'filter_grid_breakpoint',
            'gutter' => fn($value) => ['grid_column_gap' => $value, 'grid_row_gap' => $value],
            'filter_gutter' => fn($value) => [
                'filter_grid_column_gap' => $value,
                'filter_grid_row_gap' => $value,
            ],
        ]);
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.20.0-beta.0.1' => function ($node) {
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
    },

    '1.19.0-beta.0.1' => function ($node) {
        if (($node->props['meta_align'] ?? '') === 'top') {
            $node->props['meta_align'] = 'above-title';
        }

        if (($node->props['meta_align'] ?? '') === 'bottom') {
            $node->props['meta_align'] = 'below-title';
        }

        $node->props['link_type'] = 'element';
    },

    '1.18.10.3' => function ($node) {
        if (($node->props['meta_align'] ?? '') === 'top') {
            if (!empty($node->props['meta_margin'])) {
                $node->props['title_margin'] = $node->props['meta_margin'];
            }
            $node->props['meta_margin'] = '';
        }
    },

    '1.18.0' => function ($node) {
        if (
            !isset($node->props['grid_parallax']) &&
            ($node->props['grid_mode'] ?? '') === 'parallax'
        ) {
            $node->props['grid_parallax'] = $node->props['grid_parallax_y'] ?? '';
        }

        if (!isset($node->props['show_hover_image'])) {
            $node->props['show_hover_image'] = $node->props['show_image2'] ?? '';
        }

        if (
            !isset($node->props['image_box_decoration']) &&
            ($node->props['image_box_shadow_bottom'] ?? '') === true
        ) {
            $node->props['image_box_decoration'] = 'shadow';
        }

        if (!isset($node->props['meta_color']) && ($node->props['meta_style'] ?? '') === 'muted') {
            $node->props['meta_color'] = 'muted';
            $node->props['meta_style'] = '';
        }
    },
];
