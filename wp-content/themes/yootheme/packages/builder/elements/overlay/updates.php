<?php

namespace YOOtheme;

return [
    '5.0.0-beta.8.1' => function ($node) {
        unset($node->props['video_title']);
    },

    '5.0.0-beta.0.11' => function ($node) {
         if ($node->props['content_expand'] ?? '') {
            if (!($node->props['content'] ?? '') && (!($node->props['meta'] ?? '') || ($node->props['meta_align'] ?? '') == 'above-title')) {
                $node->props['title_margin_auto'] = true;
            }
            if (($node->props['meta_align'] ?? '') == 'below-content' || (!($node->props['content'] ?? '') && (($node->props['meta_align'] ?? '') == 'above-content' || (($node->props['meta_align'] ?? '') == 'below-title' )))) {
                $node->props['meta_margin_auto'] = true;
            }
            if (!(($node->props['meta'] ?? '') && ($node->props['meta_align'] ?? '') == 'below-content')) {
                $node->props['content_margin_auto'] = true;
            }
         }
        unset($node->props['content_expand']);
    },

    '4.5.0-beta.0.1' => function ($node) {
        if (($node->props['image'] ?? '') && ($node->props['video'] ?? '')) {
            unset($node->props['video']);
        }
    },

    '4.0.0-beta.9' => function ($node) {
        if (($node->props['overlay_link'] ?? '') && ($node->props['css'] ?? '')) {
            $node->props['css'] = str_replace(
                '.el-element',
                '.el-element > *',
                $node->props['css'],
            );
        }
    },

    '3.0.0-beta.5.1' => function ($node) {
        if (($node->props['image_box_decoration'] ?? '') === 'border-hover') {
            $node->props['image_transition_border'] = true;
            unset($node->props['image_box_decoration']);
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        foreach (['title_style', 'meta_style', 'content_style'] as $prop) {
            if (in_array($node->props[$prop] ?? '', ['meta', 'lead'])) {
                $node->props[$prop] = "text-{$node->props[$prop]}";
            }
        }
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
        if (!isset($node->props['overlay_image']) && ($node->props['image2'] ?? '')) {
            $node->props['overlay_image'] = $node->props['image2'] ?? '';
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
