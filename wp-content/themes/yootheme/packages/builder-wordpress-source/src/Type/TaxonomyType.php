<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

class TaxonomyType
{
    /**
     * @param \WP_Taxonomy $taxonomy
     *
     * @return array
     */
    public static function config(\WP_Taxonomy $taxonomy)
    {
        return [
            'fields' => array_merge(
                [
                    'name' => [
                        'type' => 'String',
                        'metadata' => [
                            'label' => trans('Name'),
                            'filters' => ['limit', 'preserve'],
                        ],
                    ],

                    'description' => [
                        'type' => 'String',
                        'metadata' => [
                            'label' => trans('Description'),
                            'filters' => ['limit', 'preserve'],
                        ],
                    ],

                    'link' => [
                        'type' => 'String',
                        'metadata' => [
                            'label' => trans('Link'),
                        ],
                        'extensions' => [
                            'call' => __CLASS__ . '::resolveLink',
                        ],
                    ],
                ],
                static::configHierarchicalFields($taxonomy),
                [
                    'count' => [
                        'type' => 'String',
                        'metadata' => [
                            'label' => trans('Item Count'),
                        ],
                    ],
                    'slug' => [
                        'type' => 'String',
                        'metadata' => [
                            'label' => trans('Slug'),
                        ],
                    ],
                    'term_id' => [
                        'type' => 'String',
                        'metadata' => [
                            'label' => trans('Term ID'),
                        ],
                    ],
                ],
                ...array_values(
                    array_map(
                        fn($type) => static::configPostType($type, $taxonomy),
                        Helper::getTaxonomyPostTypes($taxonomy),
                    ),
                ),
            ),

            'metadata' => [
                'type' => true,
                'label' => $taxonomy->labels->singular_name,
            ],
        ];
    }

    public static function configHierarchicalFields($taxonomy)
    {
        if (!$taxonomy->hierarchical) {
            return [];
        }

        $name = Str::camelCase($taxonomy->name, true);

        return [
            'parent' => [
                'type' => $name,
                'metadata' => [
                    'label' => trans('Parent %taxonomy%', [
                        '%taxonomy%' => $taxonomy->labels->singular_name,
                    ]),
                ],
                'extensions' => [
                    'call' => [
                        'func' => __CLASS__ . '::resolveParent',
                        'args' => ['taxonomy' => $taxonomy->name],
                    ],
                ],
            ],

            'children' => [
                'type' => [
                    'listOf' => $name,
                ],
                'args' => [
                    'order' => [
                        'type' => 'String',
                    ],
                    'order_direction' => [
                        'type' => 'String',
                    ],
                ],
                'metadata' => [
                    'label' => trans('Child %taxonomies%', [
                        '%taxonomies%' => $taxonomy->labels->name,
                    ]),
                    'fields' => [
                        '_order' => [
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => [
                                'order' => [
                                    'label' => trans('Order'),
                                    'type' => 'select',
                                    'default' => 'term_order',
                                    'options' => [
                                        trans('Term Order') => 'term_order',
                                        trans('Alphabetical') => 'name',
                                    ],
                                ],
                                'order_direction' => [
                                    'label' => trans('Direction'),
                                    'type' => 'select',
                                    'default' => 'ASC',
                                    'options' => [
                                        trans('Ascending') => 'ASC',
                                        trans('Descending') => 'DESC',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'extensions' => [
                    'call' => [
                        'func' => __CLASS__ . '::resolveChildren',
                        'args' => ['taxonomy' => $taxonomy->name],
                    ],
                ],
            ],
        ];
    }

    public static function configPostType($type, $taxonomy)
    {
        return [
            Helper::getBase($type) => [
                'type' => [
                    'listOf' => Str::camelCase($type->name, true),
                ],

                'args' => [
                    'offset' => [
                        'type' => 'Int',
                    ],
                    'limit' => [
                        'type' => 'Int',
                    ],
                    'order' => [
                        'type' => 'String',
                    ],
                    'order_direction' => [
                        'type' => 'String',
                    ],
                    'order_alphanum' => [
                        'type' => 'Boolean',
                    ],
                    'include_children' => [
                        'type' => 'Boolean',
                    ],
                ],

                'metadata' => [
                    'label' => $type->label,
                    'arguments' => [
                        'include_children' => [
                            'label' => trans('Filter'),
                            'text' => trans('Include %post_types% from child %taxonomies%', [
                                '%post_types%' => Str::lower($type->label),
                                '%taxonomies%' => Str::lower($taxonomy->labels->name),
                            ]),
                            'type' => 'checkbox',
                            'show' => $taxonomy->hierarchical,
                        ],
                        '_offset' => [
                            'description' => trans(
                                'Set the starting point and limit the number of %post_types%.',
                                ['%post_types%' => Str::lower($type->label)],
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
                                    'default' => 10,
                                    'attrs' => [
                                        'min' => 1,
                                    ],
                                ],
                            ],
                            '@order' => 50,
                        ],
                        '_order' => [
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => [
                                'order' => [
                                    'label' => trans('Order'),
                                    'type' => 'select',
                                    'default' => 'date',
                                    'options' => [
                                        [
                                            'evaluate' =>
                                                'yootheme.builder.sources.postTypeOrderOptions',
                                        ],
                                        [
                                            'evaluate' => "yootheme.builder.sources['{$type->name}OrderOptions']",
                                        ],
                                    ],
                                ],
                                'order_direction' => [
                                    'label' => trans('Direction'),
                                    'type' => 'select',
                                    'default' => 'DESC',
                                    'options' => [
                                        ['text' => trans('Ascending'), 'value' => 'ASC'],
                                        ['text' => trans('Descending'), 'value' => 'DESC'],
                                        [
                                            'evaluate' => "yootheme.builder.sources['{$type->name}OrderDirectionOptions']",
                                        ],
                                    ],
                                ],
                            ],
                            '@order' => 60,
                        ],
                        'order_alphanum' => [
                            'text' => trans('Alphanumeric Ordering'),
                            'type' => 'checkbox',
                            '@order' => 70,
                        ],
                    ],
                    'directives' => [],
                ],

                'extensions' => [
                    'call' => [
                        'func' => __CLASS__ . '::resolvePosts',
                        'args' => ['post_type' => $type->name],
                    ],
                ],
            ],
        ];
    }

    public static function resolveLink(\WP_Term $term)
    {
        return get_term_link($term);
    }

    public static function resolveParent(\WP_Term $term, array $args)
    {
        return $term->parent
            ? get_term($term->parent)
            : new \WP_Term((object) (['id' => 0] + $args));
    }

    public static function resolveChildren(\WP_Term $term, array $args)
    {
        $args += [
            'order' => 'term_order',
            'order_direction' => 'ASC',
        ];

        $query = [
            'taxonomy' => $args['taxonomy'],
            'orderby' => $args['order'],
            'order' => $args['order_direction'],
            'parent' => $term->term_id,
        ];

        return get_terms($query);
    }

    public static function resolvePosts(\WP_Term $term, array $args)
    {
        $args['terms'] = [$term->term_id];
        $args[strtr($term->taxonomy, '-', '_') . '_include_children'] =
            $args['include_children'] ?? false;
        unset($args['include_children']);

        return Helper::getPosts($args);
    }
}
