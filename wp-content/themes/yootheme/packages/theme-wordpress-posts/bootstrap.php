<?php

namespace YOOtheme\Theme\Wordpress;

return [
    'actions' => [
        'load-post.php' => [Listener\LoadClassicEditor::class => 'handle'],
        'load-post-new.php' => [Listener\LoadClassicEditor::class => 'handle'],
        'enqueue_block_assets' => [Listener\LoadBlockEditor::class => 'handle'],
    ],

    'filters' => [
        'page_row_actions' => [Listener\AddBuilderAction::class => ['@handle', 15, 2]],
        'post_row_actions' => [Listener\AddBuilderAction::class => ['@handle', 15, 2]],
        'display_post_states' => [Listener\FilterPostStates::class => ['@handle', 15, 2]],
    ],

    'services' => [
        Listener\FilterPostStates::class => '',
    ],
];
