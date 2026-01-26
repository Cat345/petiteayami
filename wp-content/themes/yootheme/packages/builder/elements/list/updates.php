<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.2' => function ($node) {
        $hasProp = fn($child, $prop) => !empty($child->props->$prop) || !empty($child->source->props->$prop);
        if (array_all($node->children ?? [], fn($child) => $hasProp($child, 'icon') && !$hasProp($child, 'image'))) {
            if ($node->props['icon_width'] ?? '') {
                $node->props['image_width'] = $node->props['icon_width'];
            }
            if ($node->props['icon_color'] ?? '') {
                $node->props['image_svg_color'] = $node->props['icon_color'];
            }
        }
        unset($node->props['icon_width'], $node->props['icon_color']);
    },

    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['image'], $node->props['icon_ratio'], $node->props['text_style']);
    },

    '4.3.2' => function ($node) {
        $separator = $node->props['list_horizontal_separator'] ?? '';
        if ($separator && !preg_match('/\h$/u', $separator)) {
            $node->props['list_horizontal_separator'] .= ' ';
        }
    },

    '4.3.0-beta.0.4' => function ($node) {
        if (($node->props['list_type'] ?? '') === 'horizontal' && !($node->props['margin'] ?? '')) {
            $node->props['margin'] = 'default';
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        if (in_array($node->props['content_style'] ?? '', ['bold', 'muted'])) {
            $node->props['content_style'] = "text-{$node->props['content_style']}";
        }
    },

    '2.1.0-beta.0.1' => function ($node) {
        if (($node->props['list_style'] ?? '') === 'bullet') {
            $node->props['list_marker'] = 'bullet';
            $node->props['list_style'] = '';
        }

        if (($node->props['list_size'] ?? '') === true) {
            $node->props['list_size'] = 'large';
        } else {
            $node->props['list_size'] = '';
        }

        if (!empty($node->props['icon_ratio'])) {
            $node->props['icon_width'] = round(20 * $node->props['icon_ratio']);
            unset($node->props['icon_ratio']);
        }
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.20.0-beta.0.1' => function ($node) {
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
            if (($node->props['content_style'] ?? '') === 'h1') {
                $node->props['content_style'] = 'heading-small';
            }
        }

        if (in_array($style, ['florence', 'max', 'nioh-studio', 'sonic', 'summit', 'trek'])) {
            if (($node->props['content_style'] ?? '') === 'h2') {
                $node->props['content_style'] = 'h1';
            }
        }

        if ($style == 'copper-hill') {
            if (($node->props['content_style'] ?? '') === 'h1') {
                $node->props['content_style'] = 'h2';
            }
        }
    },

    '1.18.10.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'image_inline_svg' => 'image_svg_inline',
            'image_animate_svg' => 'image_svg_animate',
        ]);
    },

    '1.18.0' => function ($node) {
        $node->props['content_style'] ??= $node->props['text_style'] ?? '';
    },
];
