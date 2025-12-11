<?php

namespace YOOtheme\Theme\Wordpress;

return [
    'events' => [
        'customizer.init' => [Listener\LoadEditor::class => 'handle'],
    ],

    'actions' => [
        'wp_tiny_mce_init' => [Listener\LoadEditorScript::class => 'handle'],
    ],
];
