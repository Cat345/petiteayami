<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\Config;
use YOOtheme\View;

return [
    'routes' => [
        ['post', '/builder/encode', BuilderController::class . '@encodeLayout'],
        ['get', '/builder/library', BuilderController::class . '@index'],
        ['post', '/builder/library', BuilderController::class . '@addElement'],
        ['delete', '/builder/library', BuilderController::class . '@removeElement'],
    ],

    'events' => [
        'customizer.init' => [
            Listener\LoadBuilderData::class => ['@handle', -10],
        ],
    ],

    'extend' => [
        View::class => function (View $view, $app) {
            $builder = function ($node, $params = []) use ($app) {
                // support old builder arguments
                if (!is_string($node)) {
                    $node = json_encode($node);
                }

                if (is_string($params)) {
                    $params = ['prefix' => $params];
                }

                return $app(Builder::class)->render($node, $params);
            };

            $view->addFunction('builder', $builder);
        },
    ],

    'services' => [
        Builder::class => function (View $view, Config $config, UpdateTransform $update) {
            $config->addFile('builder', __DIR__ . '/config/builder.json');

            // Deprecated: BC support e.g. `${builder:margin}` config interpolation in element json files.
            $config->addFilter('builder', fn($value) => $config->get("builder.{$value}"));

            $builder = new Builder([$config, 'loadFile'], [$view, 'render']);
            $builder->addTransform('preload', $update);
            $builder->addTransform('preload', new DefaultTransform());
            if ($config('app.isCustomizer')) {
                $builder->addTransform('preload', new IndexTransform());
            }
            $builder->addTransform('preload', [CollapseTransform::class, 'preload']);
            $builder->addTransform('presave', new OptimizeTransform());
            $builder->addTransform('prerender', new NormalizeTransform());
            $builder->addTransform('precontent', new NormalizeTransform());
            $builder->addTransform('prerender', new DisabledTransform());
            $builder->addTransform('precontent', new DisabledTransform());
            $builder->addTransform('prerender', new PlaceholderTransform());
            $builder->addTransform('render', new ElementTransform($view));
            $builder->addTransform('render', [CollapseTransform::class, 'render']);
            $builder->addTransform('render', new VisibilityTransform());
            $builder->addTypePath(__DIR__ . '/elements/*/element.json');

            return $builder;
        },

        BuilderConfig::class => '',

        UpdateTransform::class => function (Config $config) {
            $update = new UpdateTransform($config('theme.version', ''));
            $update->addGlobals(require __DIR__ . '/updates.php');

            return $update;
        },
    ],
];
