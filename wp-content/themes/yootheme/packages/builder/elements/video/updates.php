<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.15' => function ($node) {
        Arr::updateKeys($node->props, [
            'video_viewport_height' => 'height_viewport',
        ]);
    },

    '5.0.0-beta.0.4' => function ($node) {
        Arr::updateKeys($node->props, [
            'video_poster' => 'poster',
            'video_poster_focal_point' => 'video_focal_point',
        ]);

        if (!empty($node->source->props->video_poster)) {
            $node->source->props->poster = $node->source->props->video_poster;
            unset($node->source->props->video_poster);
        }

        if ($node->props['video_lazyload'] ?? '') {
            $node->props['video_loading'] = 'lazy';
        }
        unset($node->props['video_lazyload']);
    },

    '1.20.0-beta.1.1' => function ($node) {
        Arr::updateKeys($node->props, ['maxwidth_align' => 'block_align']);
    },

    '1.18.0' => function ($node) {
        if (
            !isset($node->props['video_box_decoration']) &&
            ($node->props['video_box_shadow_bottom'] ?? '') === true
        ) {
            $node->props['video_box_decoration'] = 'shadow';
        }
    },
];
