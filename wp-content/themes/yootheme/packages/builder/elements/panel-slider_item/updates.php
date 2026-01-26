<?php

namespace YOOtheme;

return [
    '5.0.0-beta.8.1' => function ($node) {
        unset($node->props['video_title']);
    },
];
