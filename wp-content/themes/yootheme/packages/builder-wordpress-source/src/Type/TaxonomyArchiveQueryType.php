<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

class TaxonomyArchiveQueryType
{
    /**
     * @param \WP_Taxonomy $taxonomy
     *
     * @return array
     */
    public static function config(\WP_Taxonomy $taxonomy)
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

    public static function configPostTypes($taxonomy, $metadata)
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
                                'default' => 0,
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
                        ],
                        'limit' => [
                            'type' => 'Int',
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
                                        'default' => 0,
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

    public static function resolve()
    {
        return get_queried_object();
    }

    public static function resolvePosts($root, array $args)
    {
        global $wp_query;

        $args += [
            'offset' => 0,
            'limit' => null,
        ];

        if ($args['offset'] || $args['limit']) {
            return array_slice(
                $wp_query->posts,
                (int) $args['offset'],
                (int) $args['limit'] ?: null,
            );
        }

        return $wp_query->posts;
    }

    public static function resolvePostsSingle($root, array $args)
    {
        global $wp_query;

        return $wp_query->posts[$args['offset'] ?? 0] ?? null;
    }
}
