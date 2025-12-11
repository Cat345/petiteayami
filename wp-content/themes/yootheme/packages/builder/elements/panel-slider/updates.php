<?php

namespace YOOtheme;

return [
    '4.5.0-beta.0.3' => function ($node) {
        if (Arr::get($node->props, 'show_link') && Arr::get($node->props, 'panel_link') && preg_match('/^card-(default|primary|secondary)|tile-/', Arr::get($node->props, 'panel_style', ''))) {
            $node->props['panel_link_hover'] = 'true';
        }

        // Remove obsolete props
        unset($node->props['panel_card_size']);
    },

    '4.5.0-beta.0.1' => function ($node) {
        if (!Arr::get($node->props, 'image_text_color')) {
            $node->props['image_text_color'] = Arr::get($node->props, 'slidenav_color');
        }

        unset(
            $node->props['nav_color'],
            $node->props['slidenav_color'],
            $node->props['slidenav_outside_color'],
        );
    },

    '4.4.0-beta.0.2' => function ($node) {
        if (str_starts_with(Arr::get($node->props, 'panel_style', ''), 'card-') && Arr::get($node->props, 'panel_image_no_padding') && in_array(Arr::get($node->props, 'image_align'), ['left', 'right'])) {
            $node->props['panel_expand'] = 'image';
        }
    },

    '4.3.4.1' => function ($node) {
        if (Arr::get($node->props, 'panel_expand')) {
            $node->props['panel_expand'] = 'content';
        }
    },

    '4.3.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, ['panel_card_match' => 'panel_match']);
    },

    '4.0.0-beta.9' => function ($node) {
        if (Arr::get($node->props, 'panel_link') && Arr::get($node->props, 'css')) {
            $node->props['css'] = str_replace('.el-item', '.el-item > *', $node->props['css']);
        }
    },

    '2.8.0-beta.0.13' => function ($node) {
        foreach (['title_style', 'meta_style', 'content_style'] as $prop) {
            if (in_array(Arr::get($node->props, $prop), ['meta', 'lead'])) {
                $node->props[$prop] = 'text-' . Arr::get($node->props, $prop);
            }
        }
    },

    '2.7.3.1' => function ($node) {
        if (empty($node->props['panel_style']) && empty($node->props['panel_padding'])) {
            foreach ($node->children as $child) {
                if (str_starts_with($child->props->panel_style ?? '', 'card-')) {
                    $node->props['panel_padding'] = 'default';
                    break;
                }
            }
        }
    },

    '2.7.0-beta.0.5' => function ($node) {
        if (str_starts_with($node->props['panel_style'] ?? '', 'card-')) {
            if (empty($node->props['panel_card_size'])) {
                $node->props['panel_card_size'] = 'default';
            }
            $node->props['panel_padding'] = $node->props['panel_card_size'];
        }
        unset($node->props['panel_card_size']);
    },

    '2.7.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'panel_content_padding' => 'panel_padding',
            'panel_size' => 'panel_card_size',
            'panel_card_image' => 'panel_image_no_padding',
        ]);
    },
];
