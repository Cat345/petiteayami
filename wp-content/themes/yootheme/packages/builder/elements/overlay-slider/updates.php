<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.15' => function ($node) {
        Arr::updateKeys($node->props, [
            'slider_height' => 'height_viewport',
            'slider_height_viewport' => 'height_viewport_height',
            'slider_height_offset_top' => 'height_viewport_offset',
        ]);
    },

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

    '5.0.0-beta.0.10' => function ($node) {
        if (($node->props['overlay_border'] ?? '')) {
            $node->props['image_border'] = 'rounded';
            unset($node->props['overlay_border']);
        }
    },

    '4.5.0-beta.0.1' => function ($node) {
        if (!($node->props['text_color'] ?? '') && ($node->props['slidenav_color'] ?? '')) {
            $node->props['text_color'] = $node->props['slidenav_color'] ?? '';
        }
        unset(
            $node->props['nav_color'],
            $node->props['slidenav_color'],
            $node->props['slidenav_outside_color'],
            $node->props['overlay_hover'],
        );
    },

    '4.3.0-beta.0.5' => function ($node, $params) {
        if ($height = $node->props['slider_height'] ?? '') {
            if ($height === 'full' || $height === 'percent') {
                $node->props['slider_height'] = 'viewport';
            }

            if ($height === 'percent') {
                $node->props['slider_height_viewport'] = 80;
            }

            if (
                ($params['updateContext']['sectionIndex'] ?? 0) < 2 &&
                empty($params['updateContext']['height'])
            ) {
                $node->props['slider_height_offset_top'] = true;
            }

            $params['updateContext']['height'] = true;
        }
    },

    '4.3.0-beta.0.4' => function ($node) {
        if ($node->props['overlay_hover'] ?? '') {
            $node->props['overlay_display'] = 'hover';
        }
        unset($node->props['overlay_hover']);
    },

    '4.0.0-beta.9' => function ($node) {
        if (($node->props['overlay_link'] ?? '') && ($node->props['css'] ?? '')) {
            $node->props['css'] = str_replace('.el-item', ':has(> .el-item)', $node->props['css']);
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        foreach (['title_style', 'meta_style', 'content_style'] as $prop) {
            if (in_array($node->props[$prop] ?? '', ['meta', 'lead'])) {
                $node->props[$prop] = "text-{$node->props[$prop]}";
            }
        }
    },

    '2.7.0-beta.0.4' => function ($node) {
        if (empty($node->props['slider_width']) || empty($node->props['slider_height'])) {
            unset($node->props['slider_min_height']);
        }
    },

    '2.2.2.1' => function ($node) {
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

    '2.1.0-beta.0.1' => function ($node) {
        if (($node->props['overlay_maxwidth'] ?? '') === 'xxlarge') {
            $node->props['overlay_maxwidth'] = '2xlarge';
        }
    },

    '1.22.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, ['slider_gutter' => 'slider_gap']);
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.20.0-beta.0.1' => function ($node) {
        if (($node->props['title_style'] ?? '') === 'heading-hero') {
            $node->props['title_style'] = 'heading-xlarge';
        }

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

        if (in_array($style, ['juno', 'vibe', 'yard'])) {
            if (($node->props['title_style'] ?? '') === 'heading-xlarge') {
                $node->props['title_style'] = 'heading-medium';
            }
        }

        if (in_array($style, ['district', 'florence', 'flow', 'nioh-studio', 'summit', 'vision'])) {
            if (($node->props['title_style'] ?? '') === 'heading-xlarge') {
                $node->props['title_style'] = 'heading-large';
            }
        }

        if ($style == 'lilian') {
            if (($node->props['title_style'] ?? '') === 'heading-xlarge') {
                $node->props['title_style'] = 'heading-2xlarge';
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
        if (!isset($node->props['meta_color']) && ($node->props['meta_style'] ?? '') === 'muted') {
            $node->props['meta_color'] = 'muted';
            $node->props['meta_style'] = '';
        }
    },
];
