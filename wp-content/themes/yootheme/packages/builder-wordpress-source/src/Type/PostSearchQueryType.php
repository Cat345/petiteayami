<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Str;
use function YOOtheme\trans;

class PostSearchQueryType
{
    /**
     * @param \WP_Post_Type $type
     *
     * @return array
     */
    public static function config(\WP_Post_Type $type)
    {
        $name = Str::camelCase($type->name, true);
        $field = Str::camelCase(['search', $type->name]);

        return [
            'fields' => [
                Str::camelCase([$field, 'Single']) => [
                    'type' => Str::camelCase($name, true),

                    'args' => [
                        'post_type_filter' => [
                            'type' => [
                                'listOf' => 'String',
                            ],
                        ],
                        'offset' => [
                            'type' => 'Int',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Item (%post_type% fields)', [
                            '%post_type%' => $type->labels->singular_name,
                        ]),
                        'group' => trans('Page'),
                        'view' => ['search', '_search'],
                        'fields' => [
                            'post_type_filter' => [
                                'label' => trans('Filter by Post Types'),
                                'description' => trans(
                                    'Filter items visually by the selected post types.',
                                ),
                                'type' => 'select',
                                'default' => [],
                                'options' => [
                                    [
                                        'evaluate' =>
                                            'yootheme.builder.postTypesWithSearch.map((type) => yootheme.builder.postTypes.find((postType) => postType.value === type))',
                                    ],
                                ],
                                'attrs' => [
                                    'multiple' => true,
                                ],
                            ],
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
                        'call' => [
                            'func' => __CLASS__ . '::resolveSingle',
                            'args' => ['post_type' => $type->name],
                        ],
                    ],
                ],
                $field => [
                    'type' => [
                        'listOf' => $name,
                    ],

                    'args' => [
                        'post_type_filter' => [
                            'type' => [
                                'listOf' => 'String',
                            ],
                        ],
                        'offset' => [
                            'type' => 'Int',
                        ],
                        'limit' => [
                            'type' => 'Int',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Items (%post_type% fields)', [
                            '%post_type%' => $type->labels->singular_name,
                        ]),
                        'group' => trans('Page'),
                        'view' => ['search', '_search'],
                        'fields' => [
                            'post_type_filter' => [
                                'label' => trans('Filter by Post Types'),
                                'description' => trans(
                                    'Filter items visually by the selected post types.',
                                ),
                                'type' => 'select',
                                'default' => [],
                                'options' => [
                                    [
                                        'evaluate' =>
                                            'yootheme.builder.postTypesWithSearch.map((type) => yootheme.builder.postTypes.find((postType) => postType.value === type))',
                                    ],
                                ],
                                'attrs' => [
                                    'multiple' => true,
                                ],
                            ],
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
                                            'placeholder' => 'No limit',
                                            'min' => 0,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolve',
                            'args' => ['post_type' => $type->name],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function resolve($root, array $args)
    {
        global $wp_query;

        $args += [
            'offset' => 0,
            'limit' => null,
            'post_type_filter' => [],
        ];

        if ((is_search() || ($root['template'] ?? '') === '_search') && !get_search_query()) {
            return [];
        }

        $posts = $wp_query->posts;

        if ($args['post_type_filter']) {
            $posts = array_filter(
                $posts,
                fn($post) => in_array($post->post_type, $args['post_type_filter']),
            );
        }

        if ($args['offset'] || $args['limit']) {
            return array_slice($posts, (int) $args['offset'], (int) $args['limit'] ?: null);
        }

        return $posts;
    }

    public static function resolveSingle($root, array $args)
    {
        return self::resolve($root, ['limit' => 1] + $args)[0] ?? null;
    }
}
