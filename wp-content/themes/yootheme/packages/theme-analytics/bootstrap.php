<?php

namespace YOOtheme\Theme\Analytics;

return [
    'events' => ['theme.head' => [Listener\LoadThemeHead::class => '@handle']],
];
