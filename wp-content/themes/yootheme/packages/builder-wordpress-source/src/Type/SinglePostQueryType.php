<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

class SinglePostQueryType
{
    /**
     * @param \WP_Post_Type $type
     *
     * @return array
     */
    public static function config(\WP_Post_Type $type)
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

        $args = $taxonomies
            ? [
                'taxonomy' => [
                    'type' => 'String',
                ],
            ]
            : [];

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

    public static function resolve()
    {
        global $post, $wp_query;

        $wp_query->setup_postdata($post);

        return $post;
    }

    public static function resolvePreviousPost($root, $args)
    {
        $args += ['taxonomy' => ''];
        return get_previous_post((bool) $args['taxonomy'], '', $args['taxonomy'] ?: 'category') ?:
            null;
    }

    public static function resolveNextPost($root, $args)
    {
        $args += ['taxonomy' => ''];
        return get_next_post((bool) $args['taxonomy'], '', $args['taxonomy'] ?: 'category') ?: null;
    }
}
