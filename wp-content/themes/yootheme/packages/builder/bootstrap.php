<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\Config;
use YOOtheme\Theme\ThemeConfig;
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
            Listener\LoadLeafletScript::class => '@handle',
            Listener\LoadGoogleMapsScript::class => '@handle',
            Listener\LoadYoutubeScript::class => '@handle',
            Listener\LoadVimeoScript::class => '@handle',
        ],

        ThemeConfig::class => [
            Listener\LoadLeafletScript::class => 'config',
            Listener\LoadGoogleMapsScript::class => 'config',
            Listener\LoadYoutubeScript::class => 'config',
            Listener\LoadVimeoScript::class => 'config',
        ],
    ],

    'extend' => [
        View::class => function (View $view, $app) {
            $builder = function ($node, $params = []) use ($app) {
                // Deprecated: support old builder arguments
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
            $config->addFile('builder', __DIR__ . '/config/builder.php');

            // Deprecated: BC support e.g. `${builder:margin}` config interpolation in element json files.
            $config->addFilter('builder', fn($value) => $config->get("builder.{$value}"));

            $loader = function ($file) use ($config) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if ($extension === 'php') {
                    $value = @include $file;

                    if (is_array($value)) {
                        return ['file' => $file] + $value;
                    }

                    return $value;
                }

                return $config->loadFile($file);
            };

            $builder = new Builder($loader, [$view, 'render']);
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

            $elements = [
                'accordion',
                'accordion_item',
                'alert',
                'button',
                'button_item',
                'code',
                'column',
                'countdown',
                'description_list',
                'description_list_item',
                'divider',
                'fragment',
                'gallery',
                'gallery_item',
                'grid',
                'grid_item',
                'headline',
                'html',
                'icon',
                'image',
                'layout',
                'list',
                'list_item',
                'map',
                'map_item',
                'nav',
                'nav_item',
                'overlay-slider',
                'overlay-slider_item',
                'overlay',
                'panel-slider',
                'panel-slider_item',
                'panel',
                'popover',
                'popover_item',
                'quotation',
                'row',
                'section',
                'slideshow',
                'slideshow_item',
                'social',
                'social_item',
                'subnav',
                'subnav_item',
                'switcher',
                'switcher_item',
                'table',
                'table_item',
                'text',
                'totop',
                'video',
            ];

            foreach ($elements as $element) {
                $builder->addType($element, __DIR__ . "/elements/{$element}/element.php");
            }

            return $builder;
        },

        UpdateTransform::class => function (Config $config) {
            $update = new UpdateTransform($config('theme.version', ''));
            $update->addGlobals(require __DIR__ . '/updates.php');

            return $update;
        },
    ],
];
