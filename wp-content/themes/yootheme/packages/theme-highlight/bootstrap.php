<?php

namespace YOOtheme\Theme\Highlight;

return [
    'filters' => [
        'builder_content' => [Listener\LoadHighlightScript::class => '@handle'],
    ],

    'actions' => [
        'onBeforeRender' => [Listener\LoadHighlightScript::class => '@beforeRender'],
    ],

    'services' => [
        Listener\LoadHighlightScript::class => '',
    ],
];
