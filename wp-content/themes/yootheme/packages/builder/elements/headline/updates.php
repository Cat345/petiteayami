<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.16' => function ($node) {
        if ($node->props['height_expand'] ?? '') {
            $node->props['push'] = true;
        }
        unset($node->props['height_expand']);
    },

    '5.0.0-beta.0.2' => function ($node) {
        if (
            (($node->props['icon'] ?? '') || !empty($node->source->props->icon)) &&
            !($node->props['image'] ?? '') && empty($node->source->props->image)
        ) {
            if ($node->props['icon_width'] ?? '') {
                $node->props['image_width'] = $node->props['icon_width'];
            }
            if ($node->props['icon_color'] ?? '') {
                $node->props['image_svg_color'] = $node->props['icon_color'];
            }
        }
        unset($node->props['icon_width'], $node->props['icon_color']);
    },

    '2.8.0-beta.0.13' => function ($node) {
        if (in_array($node->props['title_style'] ?? '', ['meta', 'lead'])) {
            $node->props['title_style'] = "text-{$node->props['title_style']}";
        }
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.20.0-beta.0.1' => function ($node) {
        /** @var Config $config */
        $config = app(Config::class);

        [$style] = explode(':', $config('~theme.style'));

        if (($node->props['title_style'] ?? '') === 'heading-hero') {
            $node->props['title_style'] = 'heading-xlarge';
        }

        if (($node->props['title_style'] ?? '') === 'heading-primary') {
            $node->props['title_style'] = 'heading-medium';
        }

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

        if (in_array($style, ['fuse', 'horizon', 'joline', 'juno', 'lilian', 'vibe', 'yard'])) {
            if (($node->props['title_style'] ?? '') === 'heading-medium') {
                $node->props['title_style'] = 'heading-small';
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
];
