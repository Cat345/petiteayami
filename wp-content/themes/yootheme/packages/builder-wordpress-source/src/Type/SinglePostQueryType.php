<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Post;
use WP_Post_Type;
use WP_Query;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class SinglePostQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(WP_Post_Type $type): array
    {
        $name = Str::camelCase($type->name, true);
        $field = Str::camelCase(['single', $type->name]);

        $taxonomies = Helper::getObjectTaxonomies($type->name);

        ksort($taxonomies);

        $fields = $taxonomies
            ? [
                'taxonomy' => [
                    'label' => trans('Filter by Term'),
                    'description' => trans(
                        'Select a taxonomy to only load posts from the same term.',
                    ),
                    'type' => 'select',
                    'options' =>
                        [trans('None') => ''] +
                        array_combine(
                            array_map(fn($taxonomy) => $taxonomy->label, $taxonomies),
                            array_keys($taxonomies),
                        ),
                ],
            ]
            : [];

        $args = [
            'taxonomy' => [
                'type' => 'String',
                'defaultValue' => '',
            ],
        ];

        return [
            'fields' => [
                $field => [
                    'type' => $name,
                    'metadata' => [
                        'label' => $type->labels->singular_name,
                        'view' => ["single-{$type->name}"],
                        'group' => trans('Page'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
                Str::camelCase(['singlePrevious', $type->name]) => [
                    'type' => $name,
                    'args' => $args,
                    'metadata' => [
                        'label' => trans('Previous %post_type%', [
                            '%post_type%' => $type->labels->singular_name,
                        ]),
                        'view' => ["single-{$type->name}"],
                        'group' => trans('Page'),
                        'fields' => $fields,
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePreviousPost',
                    ],
                ],
                Str::camelCase(['singleNext', $type->name]) => [
                    'type' => $name,
                    'args' => $args,
                    'metadata' => [
                        'label' => trans('Next %post_type%', [
                            '%post_type%' => $type->labels->singular_name,
                        ]),
                        'view' => ["single-{$type->name}"],
                        'group' => trans('Page'),
                        'fields' => $fields,
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveNextPost',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return WP_Post
     */
    public static function resolve()
    {
        /**
         * @var WP_Post $post
         * @var WP_Query $wp_query
         */
        global $post, $wp_query;

        $wp_query->setup_postdata($post);

        return $post;
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return ?WP_Post
     */
    public static function resolvePreviousPost($root, $args)
    {
        return get_previous_post((bool) $args['taxonomy'], '', $args['taxonomy'] ?: 'category') ?:
            null;
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return ?WP_Post
     */
    public static function resolveNextPost($root, $args)
    {
        return get_next_post((bool) $args['taxonomy'], '', $args['taxonomy'] ?: 'category') ?: null;
    }
}
