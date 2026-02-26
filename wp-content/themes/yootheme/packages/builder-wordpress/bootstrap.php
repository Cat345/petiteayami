<?php

namespace YOOtheme\Builder\Wordpress;

use YOOtheme\Builder;
use YOOtheme\Builder\Listener\LoadGoogleMapsScript;
use YOOtheme\Builder\Listener\LoadLeafletScript;
use YOOtheme\Builder\Listener\LoadVimeoScript;
use YOOtheme\Builder\Listener\LoadYoutubeScript;
use YOOtheme\File;
use YOOtheme\View;

return [
    'routes' => [
        ['post', '/page', [PageController::class, 'savePage']],
        ['get', '/builder/pages', [PageController::class, 'getPages']],
        ['post', '/builder/image', [BuilderController::class, 'loadImage']],
    ],

    'actions' => [
        'wp_body_open' => [Listener\RenderBuilder::class => '@handle'],
        'wp_footer' => [
            LoadLeafletScript::class => ['@body', 10],
            LoadGoogleMapsScript::class => ['@body', 10],
            LoadYoutubeScript::class => ['@body', 10],
            LoadVimeoScript::class => ['@body', 10],
        ],
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
            $elements = ['breadcrumbs', 'menu', 'module', 'module_position', 'search'];

            foreach ($elements as $element) {
                $builder->addType($element, __DIR__ . "/elements/{$element}/element.php");
            }

            if ($childDir = $app->config->get('theme.childDir')) {
                $files = File::glob("{$childDir}/builder/*/element.{json,php}");
                $filter = fn($file) => str_ends_with($file, '.json') ||
                    !in_array(dirname($file) . '/element.json', $files);
                $builder->addTypePath(array_filter($files, $filter));
            }
        },
    ],
];
