<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Post;
use WP_Query;
use WP_Taxonomy;
use WP_Term;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 * @phpstan-import-type FieldConfig from Source
 */
class TaxonomyArchiveQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(WP_Taxonomy $taxonomy): array
    {
        $name = Str::camelCase($taxonomy->name, true);
        $field = Str::camelCase(['taxonomy', $taxonomy->name]);

        $metadata = [
            'group' => trans('Page'),
            'view' => ["taxonomy-{$taxonomy->name}"],
        ];

        return [
            'fields' => array_merge(
                [
                    $field => [
                        'type' => $name,
                        'metadata' => $metadata + [
                            'label' => $taxonomy->labels->singular_name,
                        ],
                        'extensions' => [
                            'call' => __CLASS__ . '::resolve',
                        ],
                    ],
                ],
                static::configPostTypes($taxonomy, $metadata),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, FieldConfig>
     */
    public static function configPostTypes(WP_Taxonomy $taxonomy, array $metadata): array
    {
        $fields = [];
        foreach (Helper::getTaxonomyPostTypes($taxonomy) as $name => $type) {
            $field = Str::camelCase([$taxonomy->name, $name]);

            $fields += [
                Str::camelCase([$field, 'Single']) => [
                    'type' => Str::camelCase($name, true),

                    'args' => [
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                    ],

                    'metadata' => $metadata + [
                        'label' => $type->labels->singular_name,
                        'fields' => [
                            'offset' => [
                                'label' => trans('Start'),
                                'description' => trans(
                                    'Set the starting point to specify which %post_type% is loaded.',
                                    ['%post_type%' => $type->labels->singular_name],
                                ),
                                'type' => 'number',
                                'modifier' => 1,
                                'attrs' => [
                                    'min' => 1,
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePostsSingle',
                    ],
                ],

                $field => [
                    'type' => [
                        'listOf' => Str::camelCase($name, true),
                    ],

                    'args' => [
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                        'limit' => [
                            'type' => 'Int',
                            'defaultValue' => null,
                        ],
                    ],

                    'metadata' => $metadata + [
                        'label' => $type->label,
                        'fields' => [
                            '_offset' => [
                                'description' => trans(
                                    'Set the starting point and limit the number of %post_type%.',
                                    ['%post_type%' => $type->label],
                                ),
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'offset' => [
                                        'label' => trans('Start'),
                                        'type' => 'number',
                                        'modifier' => 1,
                                        'attrs' => [
                                            'min' => 1,
                                            'required' => true,
                                        ],
                                    ],
                                    'limit' => [
                                        'label' => trans('Quantity'),
                                        'type' => 'limit',
                                        'attrs' => [
                                            'placeholder' => trans('No limit'),
                                            'min' => 0,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePosts',
                    ],
                ],
            ];
        }

        return $fields;
    }

    /**
     * @return ?WP_Term
     */
    public static function resolve()
    {
        return get_queried_object();
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     *
     * @return array<WP_Post>
     */
    public static function resolvePosts($root, array $args): array
    {
        global $wp_query;

        if ($args['offset'] || $args['limit']) {
            return array_slice(
                $wp_query->posts,
                (int) $args['offset'],
                (int) $args['limit'] ?: null,
            );
        }

        return $wp_query->posts;
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     */
    public static function resolvePostsSingle($root, array $args): ?WP_Post
    {
        /** @var WP_Query $wp_query */
        global $wp_query;

        return $wp_query->posts[$args['offset'] ?? 0] ?? null;
    }
}
