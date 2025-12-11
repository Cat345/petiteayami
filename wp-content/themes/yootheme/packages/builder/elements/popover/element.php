<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node) {
            if (empty($node->props['background_image'])) {
                $node->props['background_image'] = Url::to(
                    '~yootheme/theme/assets/images/element-image-placeholder.png',
                );
            }
        },
    ],
];
