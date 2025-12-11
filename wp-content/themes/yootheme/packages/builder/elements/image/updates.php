<?php

namespace YOOtheme;

return [
    // Remove obsolete props
    '4.5.0-beta.0.4' => function ($node) {
        unset($node->props['inline_align']);
    },

    '4.3.0-beta.0.2' => function ($node, $params) {
        $search = 'uk-height-match="target: !.tm-height-min-1-1, * > img; row: false"';
        if (str_contains(Arr::get($node->props, 'attributes', ''), $search)) {
            $node->props['height_expand'] = true;
            $node->props['attributes'] = str_replace($search, '', $node->props['attributes']);

            $pattern = '/.el-image\s*\{\s*width: 100%;\s*object-fit: cover;\s*}/';
            if (preg_match($pattern, Arr::get($node->props, 'css', ''), $matches)) {
                $node->props['css'] = str_replace($matches[0], '', $node->props['css']);
            }

            $row = $params['builder']->parent($params['path'], 'row');
            if ($row) {
                $row->props['class'] = trim(($row->props['class'] ?? '') . ' uk-height-1-1');
            }
        }
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.18.10.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'image_inline_svg' => 'image_svg_inline',
            'image_animate_svg' => 'image_svg_animate',
        ]);
    },

    '1.18.0' => function ($node) {
        if (Arr::get($node->props, 'link_target') === true) {
            $node->props['link_target'] = 'blank';
        }

        if (
            !isset($node->props['image_box_decoration']) &&
            Arr::get($node->props, 'image_box_shadow_bottom') === true
        ) {
            $node->props['image_box_decoration'] = 'shadow';
        }
    },
];
