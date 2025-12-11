<?php

namespace YOOtheme\Builder\Wordpress\Source;

use YOOtheme\Builder;
use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Source\SourceTransform;
use YOOtheme\Builder\UpdateTransform;

return [
    'config' => [
        'source' => [
            'id' => get_current_blog_id(),
        ],

        BuilderConfig::class => __DIR__ . '/config/customizer.json',
    ],

    'routes' => [
        ['get', '/wordpress/posts', [SourceController::class, 'posts']],
        ['get', '/wordpress/users', [SourceController::class, 'users']],
    ],

    'events' => [
        'source.init' => [Listener\LoadSourceTypes::class => 'handle'],
        'builder.template' => [Listener\MatchTemplate::class => '@handle'],
        'builder.template.load' => [Listener\LoadTemplateUrl::class => 'handle'],
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => ['handle', 10]],
    ],

    'filters' =>
        [
            'template_include' => [Listener\LoadTemplate::class => ['@include', 20]],
            'wp_link_query_args' => [Listener\AddPostType::class => '@handle'],
        ] +
        (!is_admin() && !wp_doing_cron()
            ? ['pre_get_posts' => [Listener\LoadTemplate::class => ['@match', 30]]]
            : []),

    'extend' => [
        Builder::class => function (Builder $builder) {
            $builder->addTypePath(__DIR__ . '/elements/*/element.json');
        },

        UpdateTransform::class => function (UpdateTransform $update) {
            $update->addGlobals(require __DIR__ . '/updates.php');
        },

        SourceTransform::class => function (SourceTransform $transform) {
            $transform->addFilter('date', function ($value, $format) {
                if (!$value) {
                    return $value;
                }

                if (is_string($value) && !is_numeric($value)) {
                    $value = strtotime($value);
                }

                return wp_date(
                    $format ?: get_option('date_format', 'd/m/Y'),
                    $value,
                    wp_timezone(),
                );
            });

            $transform->addFilter(
                'limit',
                fn($value, $limit, array $filters) => $transform->applyLimit(
                    $limit ? strip_shortcodes($value) : $value,
                    $limit,
                    $filters,
                ),
            );
        },
    ],

    'services' => [
        Listener\LoadTemplate::class => '',
        Listener\MatchTemplate::class => '',
    ],
];
