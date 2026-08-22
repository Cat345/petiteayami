<?php

namespace YOOtheme\Theme\Wordpress\Polylang;

return [
    'events' => [
        'url.resolve' => [Listener\AddLanguageParameter::class => 'handle'],
    ],
];
