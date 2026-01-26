<?php

namespace YOOtheme\Builder\Source\Filesystem;

return [
    'events' => [
        // -5 to show the 'External' Group after the 'Custom' Group
        'source.init' => [Listener\LoadSourceTypes::class => ['@handle', -5]],
    ],
];
