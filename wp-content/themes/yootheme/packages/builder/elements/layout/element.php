<?php

namespace YOOtheme;

return [
    'name' => 'layout',
    'title' => 'Layout',
    'container' => true,
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
];
