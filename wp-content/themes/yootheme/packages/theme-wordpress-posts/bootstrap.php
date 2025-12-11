<?php

namespace YOOtheme\Theme\Wordpress;

return [
    'actions' => [
        'load-post.php' => [Listener\LoadPostScript::class => '@handle'],
        'load-post-new.php' => [Listener\LoadPostScript::class => 'handle'],
    ],

    'filters' => [
        'page_row_actions' => [Listener\AddBuilderAction::class => ['@handle', 15, 2]],
        'post_row_actions' => [Listener\AddBuilderAction::class => ['@handle', 15, 2]],
        'display_post_states' => [Listener\FilterPostStates::class => ['@handle', 15, 2]],
        'gutenberg_can_edit_post_type' => [Listener\DisableBlockEditor::class => 'handle'],
        'use_block_editor_for_post_type' => [Listener\DisableBlockEditor::class => 'handle'],
    ],

    'services' => [
        Listener\FilterPostStates::class => '',
    ],
];
