<?php

namespace YOOtheme\Builder\Templates;

use YOOtheme\Builder\BuilderConfig;

return [
    'routes' => [
        ['get', '/builder/template', [TemplateController::class, 'index']],
        ['post', '/builder/template', [TemplateController::class, 'saveTemplate']],
        ['delete', '/builder/template', [TemplateController::class, 'deleteTemplate']],
        ['post', '/builder/template/reorder', [TemplateController::class, 'reorderTemplates']],
    ],

    'events' => [
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => ['handle', -20]],
    ],

    'services' => [
        TemplateHelper::class => '',
    ],
];
