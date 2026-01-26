<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Post;
use WP_Post_Type;
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
class TaxonomyType
{
    /**
     * @param WP_Taxonomy $taxonomy
     *
     * @return ObjectConfig
     */
    public static function config(WP_Taxonomy $taxonomy): array
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

                    'active' => [
                        'type' => 'Boolean',
                        'metadata' => [
                            'label' => trans('Active'),
                        ],
                        'extensions' => [
                            'call' => __CLASS__ . '::active',
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
            ],
        ];
    }

    /**
     * @param WP_Taxonomy $taxonomy
     *
     * @return array<string, FieldConfig>
     */
    public static function configHierarchicalFields(WP_Taxonomy $taxonomy): array
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

    /**
     * @return array<string, FieldConfig>
     */
    public static function configPostType(WP_Post_Type $type, WP_Taxonomy $taxonomy): array
    {
        return [
            Helper::getBase($type) => [
                'type' => [
                    'listOf' => Str::camelCase($type->name, true),
                ],

                'args' => [
                    'date_column' => [
                        'type' => 'String',
                    ],
                    'date_range' => [
                        'type' => 'String',
                    ],
                    'date_relative' => [
                        'type' => 'String',
                    ],
                    'date_relative_value' => [
                        'type' => 'Int',
                    ],
                    'date_relative_unit' => [
                        'type' => 'String',
                    ],
                    'date_relative_unit_this' => [
                        'type' => 'String',
                    ],
                    'date_relative_start_today' => [
                        'type' => 'Boolean',
                    ],
                    'date_start' => [
                        'type' => 'String',
                    ],
                    'date_end' => [
                        'type' => 'String',
                    ],
                    'date_start_custom' => [
                        'type' => 'String',
                    ],
                    'date_end_custom' => [
                        'type' => 'String',
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
                    'order_alphanum' => [
                        'type' => 'Boolean',
                    ],
                    'order_reverse' => [
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
                        '_date' => [
                            'label' => trans('Filter by Date'),
                            'description' =>
                                'Filter posts by a range relative to the current date or by a fixed start and end date.',
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => [
                                'date_column' => [
                                    'type' => 'select',
                                    'options' => [
                                        ['value' => '', 'text' => trans('None')],
                                        [
                                            'evaluate' =>
                                                'yootheme.builder.sources.postTypeDateFilterOptions',
                                        ],
                                        [
                                            'evaluate' => "yootheme.builder.sources['{$type->name}DateFilterOptions']",
                                        ],
                                    ],
                                ],
                                'date_range' => [
                                    'type' => 'select',
                                    'default' => 'relative',
                                    'options' => [
                                        trans('Relative Range') => 'relative',
                                        trans('Fixed Range') => 'fixed',
                                        trans('Custom Format Range') => 'custom',
                                    ],
                                    'enable' => 'date_column',
                                ],
                            ],
                        ],
                        '_date_range_relative' => [
                            'label' => trans('Date Range'),
                            'type' => 'grid',
                            'width' => 'expand,auto,expand',
                            'fields' => [
                                'date_relative' => [
                                    'type' => 'select',
                                    'default' => 'next',
                                    'options' => [
                                        trans('Is in the next') => 'next',
                                        trans('Is in this') => 'this',
                                        trans('Is in the last') => 'last',
                                    ],
                                ],
                                'date_relative_value' => [
                                    'type' => 'limit',
                                    'attrs' => [
                                        'min' => 0,
                                        'class' => 'uk-form-width-xsmall',
                                        'placeholder' => '∞',
                                    ],
                                    'show' => 'date_relative !== \'this\'',
                                ],
                                'date_relative_unit' => [
                                    'type' => 'select',
                                    'default' => 'day',
                                    'options' => [
                                        trans('Days') => 'day',
                                        trans('Weeks') => 'week',
                                        trans('Months') => 'month',
                                        trans('Years') => 'year',
                                        trans('Calendar Weeks') => 'week_calendar',
                                        trans('Calendar Months') => 'month_calendar',
                                        trans('Calendar Years') => 'year_calendar',
                                    ],
                                    'show' => 'date_relative !== \'this\'',
                                ],
                                'date_relative_unit_this' => [
                                    'type' => 'select',
                                    'default' => 'day',
                                    'options' => [
                                        trans('Day') => 'day',
                                        trans('Week') => 'week',
                                        trans('Month') => 'month',
                                        trans('Year') => 'year',
                                    ],
                                    'show' => 'date_relative === \'this\'',
                                ],
                            ],
                            'show' => 'date_column && date_range === \'relative\'',
                        ],
                        'date_relative_start_today' => [
                            'type' => 'checkbox',
                            'text' => trans('Start today'),
                            'description' =>
                                'Set a range starting tomorrow or the next full calendar period. Optionally, start today, which includes the current partial period for calendar ranges. Today refers to the full calendar day.',
                            'enable' => 'date_relative !== \'this\'',
                            'show' => 'date_column && date_range === \'relative\'',
                        ],
                        '_date_range_fixed' => [
                            'type' => 'grid',
                            'description' =>
                                'Set only one date to load all posts either before or after that date.',
                            'width' => '1-2',
                            'fields' => [
                                'date_start' => [
                                    'label' => trans('Start Date'),
                                    'type' => 'datetime',
                                ],
                                'date_end' => [
                                    'label' => trans('End Date'),
                                    'type' => 'datetime',
                                ],
                            ],
                            'show' => 'date_column && date_range === \'fixed\'',
                        ],
                        '_date_range_custom' => [
                            'type' => 'grid',
                            'description' =>
                                'Use the <a href="https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative" target="_blank">PHP relative date formats</a> in a BNF-like syntax. Set only one date to load all articles either before or after that date.',
                            'width' => '1-2',
                            'fields' => [
                                'date_start_custom' => [
                                    'label' => trans('Start Date'),
                                    'type' => 'data-list',
                                    'options' => [
                                        'This month' => 'first day of +0 month 00:00:00',
                                        'Next month' => 'first day of +1 month 00:00:00',
                                        'Month after next month' =>
                                            'first day of +2 month 00:00:00',
                                    ],
                                ],
                                'date_end_custom' => [
                                    'label' => trans('End Date'),
                                    'type' => 'data-list',
                                    'options' => [
                                        'This month' => 'last day of +0 month 23:59:59',
                                        'Next month' => 'last day of +1 month 23:59:59',
                                        'Month after next month' => 'last day of +2 month 23:59:59',
                                    ],
                                ],
                            ],
                            'show' => 'date_column && date_range === \'custom\'',
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
                        'order_reverse' => [
                            'text' => trans('Reverse Results'),
                            'type' => 'checkbox',
                            '@order' => 80,
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

    /**
     * @return string
     */
    public static function resolveLink(WP_Term $term)
    {
        return get_term_link($term);
    }

    /**
     * @param array<string, mixed> $args
     * @return ?WP_Term
     */
    public static function resolveParent(WP_Term $term, array $args)
    {
        return $term->parent
            ? get_term($term->parent)
            : new WP_Term((object) (['id' => 0] + $args));
    }

    /**
     * @param array<string, mixed> $args
     * @return array<WP_Term>
     */
    public static function resolveChildren(WP_Term $term, array $args)
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

    /**
     * @param array<string, mixed> $args
     * @return array<WP_Post>
     */
    public static function resolvePosts(WP_Term $term, array $args)
    {
        $args['terms'] = [$term->term_id];
        $args[strtr($term->taxonomy, '-', '_') . '_include_children'] =
            $args['include_children'] ?? false;
        unset($args['include_children']);

        return Helper::getPosts($args);
    }

    public static function active(WP_Term $term): bool
    {
        $object = get_queried_object();

        if ($object instanceof WP_Post && in_array($term->taxonomy, get_post_taxonomies($object))) {
            foreach (
                wp_get_post_terms($object->ID, $term->taxonomy, ['fields' => 'ids'])
                as $term_id
            ) {
                if (
                    $term_id === $term->term_id ||
                    term_is_ancestor_of($term, $term_id, $term->taxonomy)
                ) {
                    return true;
                }
            }
        }

        return $object instanceof WP_Term &&
            ($object->term_id === $term->term_id ||
                term_is_ancestor_of($term, $object, $term->taxonomy));
    }
}
