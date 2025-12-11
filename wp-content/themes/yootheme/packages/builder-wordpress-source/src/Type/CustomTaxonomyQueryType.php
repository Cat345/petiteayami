<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Str;
use function YOOtheme\trans;

class CustomTaxonomyQueryType
{
    /**
     * @param \WP_Taxonomy $taxonomy
     *
     * @return array
     */
    public static function config(\WP_Taxonomy $taxonomy)
    {
        $name = Str::camelCase($taxonomy->name, true);
        $base = Str::camelCase(SourceHelper::getBase($taxonomy), true);

        $plural = Str::lower($taxonomy->label);
        $singular = Str::lower($taxonomy->labels->singular_name);

        return [
            'fields' => [
                "custom{$name}" => [
                    'type' => $name,

                    'args' => [
                        'id' => [
                            'type' => 'Int',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Custom %taxonomy%', [
                            '%taxonomy%' => $taxonomy->labels->singular_name,
                        ]),
                        'group' => trans('Custom'),
                        'fields' => [
                            'id' => [
                                'label' => $taxonomy->labels->singular_name,
                                'type' => 'select',
                                'defaultIndex' => 0,
                                'options' => [
                                    [
                                        'evaluate' => "yootheme.builder.taxonomies['{$taxonomy->name}'].options",
                                    ],
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolveTerm',
                    ],
                ],

                "custom{$base}" => [
                    'type' => [
                        'listOf' => $name,
                    ],

                    'args' => [
                        'id' => [
                            'type' => 'Int',
                        ],
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
                    ],

                    'metadata' => [
                        'label' => trans('Custom %taxonomies%', [
                            '%taxonomies%' => $taxonomy->label,
                        ]),
                        'group' => trans('Custom'),
                        'fields' => ($taxonomy->hierarchical
                            ? [
                                'id' => [
                                    'label' => trans('Parent %taxonomy%', [
                                        '%taxonomy%' => $taxonomy->labels->singular_name,
                                    ]),
                                    'description' => trans(
                                        '%taxonomies% are only loaded from the selected parent %taxonomy%.',
                                        [
                                            '%taxonomies%' => $taxonomy->label,
                                            '%taxonomy%' => $singular,
                                        ],
                                    ),
                                    'type' => 'select',
                                    'default' => 0,
                                    'options' => [
                                        ['value' => 0, 'text' => trans('Root')],
                                        [
                                            'evaluate' => "yootheme.builder.taxonomies['{$taxonomy->name}'].options",
                                        ],
                                    ],
                                ],
                            ]
                            : []) + [
                            '_offset' => [
                                'description' => trans(
                                    'Set the starting point and limit the number of %taxonomies%.',
                                    ['%taxonomies%' => $plural],
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
                            ],
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
                            'func' => __CLASS__ . '::resolveTerms',
                            'args' => ['taxonomy' => $taxonomy->name],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function resolveTerm($root, array $args)
    {
        $args += ['id' => 0];

        $term = get_term($args['id']);

        return $term instanceof \WP_Term ? $term : null;
    }

    public static function resolveTerms($root, array $args)
    {
        $args += [
            'order' => 'term_order',
            'order_direction' => 'ASC',
            'offset' => 0,
            'limit' => 10,
        ];

        $query = [
            'taxonomy' => $args['taxonomy'],
            'orderby' => $args['order'],
            'order' => $args['order_direction'],
            'number' => $args['limit'],
            'offset' => $args['offset'],
        ];

        if (is_taxonomy_hierarchical($args['taxonomy'])) {
            $query['parent'] = $args['id'] ?? 0;

            // There is a bug in WordPress (introduced in 6.0) where terms are added to cache without the 'hide_empty' filter being applied first
            // TODO: remove once fixed in WordPress 6+
            $query['cache_domain'] = microtime();
        }

        return get_terms($query);
    }
}
