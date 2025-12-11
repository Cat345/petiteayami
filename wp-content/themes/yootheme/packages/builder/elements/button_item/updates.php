<?php

namespace YOOtheme;

return [
    '4.5.0-beta.0.1' => function ($node) {
        Arr::updateKeys($node->props, [
            'lightbox_width' => 'image_width',
            'lightbox_height' => 'image_height',
            'lightbox_image_focal_point' => 'image_focal_point',
        ]);
    },

    '1.18.0' => function ($node) {
        if (Arr::get($node->props, 'link_target') === true) {
            $node->props['link_target'] = 'blank';
        }

        if (Arr::get($node->props, 'button_style') === 'muted') {
            $node->props['button_style'] = 'link-muted';
        }
    },
];
