<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.15' => function ($node) {
        Arr::updateKeys($node->props, [
            'slideshow_height' => 'height_viewport',
            'slideshow_height_viewport' => 'height_viewport_height',
            'slideshow_height_offset_top' => 'height_viewport_offset',
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

    '5.0.0-beta.0.9' => function ($node) {
        Arr::updateKeys($node->props, ['thumbnav_nowrap' => 'thumbnav_shrink']);
    },

    '4.5.0-beta.0.1' => function ($node) {
        unset($node->props['nav_color'], $node->props['slidenav_outside_color']);
    },

    '4.3.0-beta.0.5' => function ($node, $params) {
        if ($height = $node->props['slideshow_height'] ?? '') {
            if ($height === 'full' || $height === 'percent') {
                $node->props['slideshow_height'] = 'viewport';
            }

            if ($height === 'percent') {
                $node->props['slideshow_height_viewport'] = 80;
            }

            if (
                ($params['updateContext']['sectionIndex'] ?? 0) < 2 &&
                empty($params['updateContext']['height'])
            ) {
                $node->props['slideshow_height_offset_top'] = true;
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

    '2.8.0-beta.0.3' => function ($node) {
        foreach (['overlay', 'title', 'meta', 'content', 'link'] as $prefix) {
            foreach (['x', 'y', 'scale', 'rotate', 'opacity'] as $prop) {
                $key = "{$prefix}_parallax_{$prop}";
                $start = preg_replace('/\s*,\s*/', ',', $node->props["{$key}_start"] ?? '');
                $end = preg_replace('/\s*,\s*/', ',', $node->props["{$key}_end"] ?? '');

                if ($start !== '' || $end !== '') {
                    $default = in_array($prop, ['scale', 'opacity']) ? '1' : '0';
                    $node->props[$key] = implode(',', [
                        $start !== '' ? $start : $default,
                        $default,
                        $end !== '' ? $end : $default,
                    ]);
                }
                unset($node->props["{$key}_start"], $node->props["{$key}_end"]);
            }
        }
    },

    '2.3.0-beta.1.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if ($style == 'fjord') {
            if (($node->props['overlay_container'] ?? '') === 'default') {
                $node->props['overlay_container'] = 'large';
            }
        }
    },

    '2.1.0-beta.0.1' => function ($node) {
        if (($node->props['overlay_width'] ?? '') === 'xxlarge') {
            $node->props['overlay_width'] = '2xlarge';
        }
    },

    '2.0.0-beta.5.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if (!in_array($style, ['jack-baker', 'morgan-consulting', 'vibe'])) {
            if (($node->props['overlay_container'] ?? '') === 'large') {
                $node->props['overlay_container'] = 'xlarge';
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
            if (($node->props['overlay_container'] ?? '') === 'default') {
                $node->props['overlay_container'] = 'large';
            }
        }
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
    },

    '1.18.10.3' => function ($node) {
        if (($node->props['meta_align'] ?? '') === 'top') {
            if (!empty($node->props['meta_margin'])) {
                $node->props['title_margin'] = $node->props['meta_margin'];
            }
            $node->props['meta_margin'] = '';
        }
    },

    '1.18.10.1' => function ($node) {
        Arr::updateKeys($node->props, ['thumbnav_inline_svg' => 'thumbnav_svg_inline']);
    },

    '1.18.0' => function ($node) {
        if (
            !isset($node->props['slideshow_box_decoration']) &&
            ($node->props['slideshow_box_shadow_bottom'] ?? '') === true
        ) {
            $node->props['slideshow_box_decoration'] = 'shadow';
        }

        if (!isset($node->props['meta_color']) && ($node->props['meta_style'] ?? '') === 'muted') {
            $node->props['meta_color'] = 'muted';
            $node->props['meta_style'] = '';
        }
    },
];
