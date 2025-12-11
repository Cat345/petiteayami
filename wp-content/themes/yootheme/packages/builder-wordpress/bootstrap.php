<?php

namespace YOOtheme\Builder\Wordpress;

use YOOtheme\Builder;
use YOOtheme\View;

return [
    'routes' => [
        ['post', '/page', [PageController::class, 'savePage']],
        ['get', '/builder/pages', [PageController::class, 'getPages']],
        ['post', '/builder/image', [BuilderController::class, 'loadImage']],
    ],

    'actions' => [
        'wp_body_open' => [Listener\RenderBuilder::class => '@handle'],
    ],

    'filters' => [
        'pre_post_content' => [Listener\RemoveContentFilter::class => 'handle'],
        'builder_content' => [Listener\ApplyContentFilter::class => ['handle', 10, 2]],
        'template_include' => [
            Listener\RenderBuilderPage::class => '@handle',
            Listener\RenderBuilderTemplate::class => ['@handle', 50],
        ],
    ],

    'extend' => [
        View::class => function (View $view) {
            $view->addLoader(function ($name, $parameters, callable $next) {
                $content = $next($name, $parameters);

                // `i` parameter is not set on root node
                return !isset($parameters['i']) &&
                    (empty($parameters['context']) || $parameters['context'] !== 'content')
                    ? apply_filters('builder_content', $content, $parameters)
                    : $content;
            }, '*/builder/elements/{layout,fragment}/templates/template.php');
        },

        Builder::class => function (Builder $builder, $app) {
            $builder->addTypePath(__DIR__ . '/elements/*/element.json');

            if ($childDir = $app->config->get('theme.childDir')) {
                $builder->addTypePath("{$childDir}/builder/*/element.json");
            }
        },
    ],
];
