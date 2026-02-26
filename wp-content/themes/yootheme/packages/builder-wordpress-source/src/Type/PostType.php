<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;
use WP_Term;
use WP_User;
use YOOtheme\Arr;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Path;
use YOOtheme\Str;
use YOOtheme\View;
use function YOOtheme\app;
use function YOOtheme\link_pages;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 * @phpstan-import-type FieldConfig from Source
 */
class PostType
{
    /**
     * @param WP_Post_Type $type
     *
     * @return ObjectConfig
     */
    public static function config(WP_Post_Type $type): array
    {
        $taxonomies = Helper::getObjectTaxonomies($type->name);

        $fields = array_merge(
            [
                'title' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Title'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::title',
                    ],
                ],

                'content' => [
                    'type' => 'String',
                    'args' => [
                        'show_intro_text' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Content'),
                        'arguments' => [
                            'show_intro_text' => [
                                'label' => trans('Intro Text'),
                                'description' => trans('Show or hide the intro text.'),
                                'type' => 'checkbox',
                                'default' => true,
                                'text' => trans('Show intro text'),
                            ],
                        ],
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::content',
                    ],
                ],

                'teaser' => [
                    'type' => 'String',
                    'args' => [
                        'show_excerpt' => [
                            'type' => 'Boolean',
                        ],
                        'show_content' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Teaser'),
                        'arguments' => [
                            'show_excerpt' => [
                                'label' => trans('Intro Text'),
                                'description' => trans(
                                    'Render the intro text if the read more tag is present. Otherwise, fall back to the full content. Optionally, prefer the excerpt field over the intro text.',
                                ),
                                'type' => 'checkbox',
                                'default' => true,
                                'text' => trans('Prefer excerpt over intro text'),
                            ],
                            'show_content' => [
                                'type' => 'checkbox',
                                'default' => true,
                                'text' => trans('Fall back to content'),
                            ],
                        ],
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::teaser',
                    ],
                ],

                'excerpt' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Excerpt'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::excerpt',
                    ],
                ],

                'date' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::date',
                    ],
                ],

                'modified' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Modified Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::modified',
                    ],
                ],

                'metaString' => [
                    'type' => 'String',
                    'args' => [
                        'format' => [
                            'type' => 'String',
                        ],
                        'separator' => [
                            'type' => 'String',
                        ],
                        'link_style' => [
                            'type' => 'String',
                        ],
                        'show_publish_date' => [
                            'type' => 'Boolean',
                        ],
                        'show_author' => [
                            'type' => 'Boolean',
                        ],
                        'show_comments' => [
                            'type' => 'Boolean',
                        ],
                        'show_taxonomy' => [
                            'type' => 'String',
                        ],
                        'date_format' => [
                            'type' => 'String',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Meta'),
                        'arguments' => [
                            'format' => [
                                'label' => trans('Format'),
                                'description' => trans(
                                    'Display the meta text in a sentence or a horizontal list.',
                                ),
                                'type' => 'select',
                                'default' => 'list',
                                'options' => [
                                    trans('List') => 'list',
                                    trans('Sentence') => 'sentence',
                                ],
                            ],
                            'separator' => [
                                'label' => trans('Separator'),
                                'description' => trans('Set the separator between fields.'),
                                'default' => '|',
                                'enable' => 'arguments.format === "list"',
                            ],
                            'link_style' => [
                                'label' => trans('Link Style'),
                                'description' => trans('Set the link style.'),
                                'type' => 'select',
                                'default' => '',
                                'options' => [
                                    trans('Default') => '',
                                    'Muted' => 'link-muted',
                                    'Text' => 'link-text',
                                    'Heading' => 'link-heading',
                                    'Reset' => 'link-reset',
                                ],
                            ],
                            'show_publish_date' => [
                                'label' => trans('Display'),
                                'description' => trans('Show or hide fields in the meta text.'),
                                'type' => 'checkbox',
                                'default' => true,
                                'text' => trans('Show date'),
                            ],
                            'show_author' => [
                                'type' => 'checkbox',
                                'default' => true,
                                'text' => trans('Show author'),
                            ],
                            'show_comments' => [
                                'type' => 'checkbox',
                                'default' => true,
                                'text' => trans('Show comment count'),
                            ],
                            'show_taxonomy' => [
                                'type' => 'select',
                                'default' => $type->name === 'post' ? 'category' : '',
                                'show' => (bool) $taxonomies,
                                'options' =>
                                    [
                                        trans('Hide Term List') => '',
                                    ] +
                                    array_combine(
                                        array_map(
                                            fn($taxonomy) => trans('Show %taxonomy%', [
                                                '%taxonomy%' => $taxonomy->label,
                                            ]),
                                            $taxonomies,
                                        ),
                                        array_keys($taxonomies),
                                    ),
                            ],
                            'date_format' => [
                                'label' => trans('Date Format'),
                                'description' => trans(
                                    'Select a predefined date format or enter a custom format.',
                                ),
                                'type' => 'data-list',
                                'default' => '',
                                'options' => [
                                    'Aug 6, 1999 (M j, Y)' => 'M j, Y',
                                    'August 06, 1999 (F d, Y)' => 'F d, Y',
                                    '08/06/1999 (m/d/Y)' => 'm/d/Y',
                                    '08.06.1999 (m.d.Y)' => 'm.d.Y',
                                    '6 Aug, 1999 (j M, Y)' => 'j M, Y',
                                    'Tuesday, Aug 06 (l, M d)' => 'l, M d',
                                ],
                                'enable' => 'arguments.show_publish_date',
                                'attrs' => [
                                    'placeholder' => 'Default',
                                ],
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::metaString',
                    ],
                ],
            ],

            static::configTaxonomyFields($taxonomies),

            [
                'featuredImage' => [
                    'type' => 'Attachment',
                    'metadata' => [
                        'label' => trans('Featured Image'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::featuredImage',
                    ],
                ],

                'link' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Link'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::link',
                    ],
                ],

                'author' => [
                    'type' => 'User',
                    'metadata' => [
                        'label' => trans('Author'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::author',
                    ],
                ],

                'commentCount' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Comment Count'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::commentCount',
                    ],
                ],

                'post_name' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Slug'),
                    ],
                ],

                'id' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('ID'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::id',
                    ],
                ],
            ],

            $type->name === 'post'
                ? [
                    'sticky' => [
                        'type' => 'Boolean',
                        'metadata' => [
                            'label' => trans('Sticky'),
                            'condition' => true,
                        ],
                        'extensions' => [
                            'call' => __CLASS__ . '::isSticky',
                        ],
                    ],
                ]
                : [],
        );

        $metadata = [
            'type' => true,
        ];

        $features = [
            'title' => 'title',
            'author' => 'author',
            'editor' => 'content',
            'excerpt' => 'excerpt',
            'thumbnail' => 'featuredImage',
            'comments' => 'commentCount',
        ];

        // omit unsupported static features
        if ($values = array_diff_key($features, get_all_post_type_supports($type->name))) {
            $fields = Arr::omit($fields, $values);
        }

        $fields += static::configRelatedPostsField($type, $taxonomies);
        $fields += static::configHierarchicalFields($type);

        return compact('fields', 'metadata');
    }

    /**
     * @param WP_Post_Type $type
     * @return array<FieldConfig>
     */
    public static function configHierarchicalFields($type)
    {
        if (!$type->hierarchical) {
            return [];
        }

        $name = Str::camelCase($type->name, true);

        return [
            'parent' => [
                'type' => $name,
                'metadata' => [
                    'label' => trans('Parent %type%', [
                        '%type%' => $type->labels->singular_name,
                    ]),
                ],
                'extensions' => [
                    'call' => [static::class, 'resolveParent'],
                ],
            ],
            'children' => [
                'type' => [
                    'listOf' => $name,
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
                ],
                'metadata' => [
                    'label' => trans('Child %type%', [
                        '%type%' => $type->labels->name,
                    ]),
                    'arguments' => [
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
                                    'default' => 'menu_order',
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
                        ],
                    ],
                    'directives' => [],
                ],
                'extensions' => [
                    'call' => [static::class, 'resolveChildren'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, WP_Taxonomy> $taxonomies
     * @return array<string, FieldConfig>
     */
    protected static function configTaxonomyFields(array $taxonomies)
    {
        $fields = [];

        foreach (wp_filter_object_list($taxonomies, ['public' => true]) as $taxonomy) {
            $fields[Helper::getBase($taxonomy)] = [
                'type' => [
                    'listOf' => Str::camelCase($taxonomy->name, true),
                ],

                'metadata' => [
                    'label' => $taxonomy->label,
                ],

                'extensions' => [
                    'call' => [
                        'func' => __CLASS__ . '::resolveTerms',
                        'args' => ['taxonomy' => $taxonomy->name],
                    ],
                ],
            ];

            $fields[strtr($taxonomy->name, '-', '_') . 'String'] = [
                'type' => 'String',

                'args' => [
                    'separator' => [
                        'type' => 'String',
                    ],
                    'show_link' => [
                        'type' => 'Boolean',
                    ],
                    'link_style' => [
                        'type' => 'String',
                    ],
                ],

                'metadata' => [
                    'label' => $taxonomy->label,
                    'arguments' => [
                        'separator' => [
                            'label' => trans('Separator'),
                            'description' => trans('Set the separator between terms.'),
                            'default' => ', ',
                        ],
                        'show_link' => [
                            'label' => trans('Link'),
                            'type' => 'checkbox',
                            'default' => true,
                            'text' => trans('Show link'),
                        ],
                        'link_style' => [
                            'label' => trans('Link Style'),
                            'description' => trans('Set the link style.'),
                            'type' => 'select',
                            'default' => '',
                            'options' => [
                                'Default' => '',
                                'Muted' => 'link-muted',
                                'Text' => 'link-text',
                                'Heading' => 'link-heading',
                                'Reset' => 'link-reset',
                            ],
                            'enable' => 'arguments.show_link',
                        ],
                    ],
                ],

                'extensions' => [
                    'call' => [
                        'func' => __CLASS__ . '::resolveTermString',
                        'args' => ['taxonomy' => $taxonomy->name],
                    ],
                ],
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, WP_Taxonomy> $taxonomies
     * @return array<FieldConfig>
     */
    protected static function configRelatedPostsField(WP_Post_Type $type, array $taxonomies): array
    {
        if (!$taxonomies && !post_type_supports($type->name, 'author')) {
            return [];
        }

        $base = Helper::getBase($type);

        $arguments = [];
        foreach ($taxonomies as $id => $taxonomy) {
            $arguments[strtr($id, '-', '_')] = [
                'label' => array_first($taxonomies) === $taxonomy ? 'Relationship' : '',
                'type' => 'select',
                'default' => '',
                'options' => [
                    trans('Ignore %taxonomy%', [
                        '%taxonomy%' => mb_strtolower($taxonomy->labels->singular_name),
                    ]) => '',
                    trans('Match one %taxonomy% (OR)', [
                        '%taxonomy%' => mb_strtolower($taxonomy->labels->singular_name),
                    ]) => 'IN',
                    trans('Match all %taxonomies% (AND)', [
                        '%taxonomies%' => mb_strtolower($taxonomy->label),
                    ]) => 'AND',
                    trans('Don\'t match %taxonomies% (NOR)', [
                        '%taxonomies%' => mb_strtolower($taxonomy->label),
                    ]) => 'NOT IN',
                ],
            ];
        }

        $taxonomyList = implode(', ', array_map(fn($taxonomy) => $taxonomy->label, $taxonomies));

        return [
            Str::camelCase(['related', $base]) => [
                'type' => [
                    'listOf' => Str::camelCase($type->name, true),
                ],

                'args' => array_map(fn() => ['type' => 'String'], $arguments) + [
                    'author' => [
                        'type' => 'String',
                    ],
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
                ],

                'metadata' => [
                    'label' => trans('Related %post_type%', ['%post_type%' => $type->labels->name]),
                    'arguments' => $arguments + [
                        'author' => [
                            'label' => !$taxonomies ? 'Relationship' : '',
                            'description' => $taxonomyList
                                ? trans(
                                    'Set the logical operators for how the %post_type% relate to %taxonomy_list% and author. Choose between matching at least one term, all terms or none of the terms.',
                                    [
                                        '%post_type%' => $type->labels->name,
                                        '%taxonomy_list%' => $taxonomyList,
                                    ],
                                )
                                : trans(
                                    'Set the logical operator for how the %post_type% relate to the author. Choose between matching at least one term, all terms or none of the terms.',
                                    ['%post_type%' => $type->labels->name],
                                ),
                            'type' => 'select',
                            'options' => [
                                trans('Ignore author') => '',
                                trans('Match author (OR)') => 'IN',
                                trans('Don\'t match author (NOR)') => 'NOT IN',
                            ],
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
                    'call' => __CLASS__ . '::relatedPosts',
                ],
            ],
        ];
    }

    /**
     * @param WP_Post $post
     * @return int
     */
    public static function id($post)
    {
        return $post->ID;
    }

    /**
     * @param WP_Post $post
     * @return string
     */
    public static function title($post)
    {
        return get_the_title($post);
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return string
     */
    public static function content($post, $args)
    {
        global $page, $numpages, $multipage;

        $args += ['show_intro_text' => true];

        // Hint: this returns different results depending on the current view (archive vs. single page)
        $content = get_the_content('', !$args['show_intro_text'], $post);
        $content = str_replace("<span id=\"more-{$post->ID}\"></span>", '', $content);

        if ($multipage && Helper::isPageSource($post)) {
            $title = sprintf(__('Page %s of %s', 'yootheme'), $page, $numpages);
            $content =
                '<p class="uk-text-meta tm-page-break' .
                ($page == '1' ? ' tm-page-break-first-page' : '') .
                "\">{$title}</p>{$content}" .
                link_pages();
        }

        if (!has_blocks($content)) {
            $content = Helper::applyAutoP($content);
        }

        return apply_filters('yootheme_source_post_content', $content, $post, $args);
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return string
     */
    public static function teaser($post, $args)
    {
        $args += ['show_excerpt' => true, 'show_content' => true];

        if ($args['show_excerpt'] && has_excerpt($post)) {
            return static::excerpt($post);
        }

        $extended = get_extended($post->post_content);
        $teaser = $extended['main'];

        if (!$args['show_content'] && $extended['extended'] == '') {
            return '';
        }

        // Having multiple `<!-- wp:more -->` blocks confuses the parse_blocks function
        if (has_blocks($teaser)) {
            $teaser = do_blocks($teaser);
        }

        return apply_filters('yootheme_source_post_teaser', $teaser, $post, $args);
    }

    /**
     * @param WP_Post $post
     * @return string
     */
    public static function excerpt($post)
    {
        return Helper::applyAutoP(get_the_excerpt($post));
    }

    /**
     * @param WP_Post $post
     * @return string
     */
    public static function date($post)
    {
        return $post->post_date_gmt;
    }

    /**
     * @param WP_Post $post
     */
    public static function isSticky($post): bool
    {
        return is_sticky($post->ID);
    }

    /**
     * @param WP_Post $post
     * @return string
     */
    public static function modified($post)
    {
        return $post->post_modified_gmt;
    }

    /**
     * @param WP_Post $post
     * @return int|string
     */
    public static function commentCount($post)
    {
        return $post->comment_count;
    }

    /**
     * @param WP_Post $post
     * @return ?string
     */
    public static function link($post)
    {
        $link = get_permalink($post);

        return is_string($link) ? $link : null;
    }

    /**
     * @param WP_Post $post
     * @return ?int
     */
    public static function featuredImage($post)
    {
        return get_post_thumbnail_id($post) ?: null;
    }

    /**
     * @param WP_Post $post
     * @return ?WP_User
     */
    public static function author($post)
    {
        return get_userdata((int) $post->post_author) ?: null;
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     */
    public static function metaString($post, array $args): string
    {
        $args += [
            'format' => 'list',
            'separator' => '|',
            'link_style' => '',
            'show_publish_date' => true,
            'show_author' => true,
            'show_comments' => true,
            'show_taxonomy' => $post->post_type === 'post' ? 'category' : '',
            'date_format' => '',
        ];

        return app(View::class)->render(
            Path::join(__DIR__, '../../templates/meta'),
            compact('post', 'args'),
        );
    }

    /**
     * @param WP_Post $post
     * @return ?WP_Post
     */
    public static function resolveParent($post)
    {
        return get_post_parent($post);
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return array<WP_Post>
     */
    public static function resolveChildren($post, array $args)
    {
        $args += ['post_parent' => $post->ID, 'post_type' => $post->post_type];

        return Helper::getPosts($args);
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return array<WP_Term>
     */
    public static function resolveTerms($post, array $args)
    {
        return wp_get_post_terms($post->ID, $args['taxonomy']);
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return string
     */
    public static function resolveTermString($post, array $args)
    {
        $args += ['separator' => ', ', 'show_link' => true, 'link_style' => ''];
        $before = $args['link_style'] ? "<span class=\"uk-{$args['link_style']}\">" : '';
        $after = $args['link_style'] ? '</span>' : '';

        if ($args['show_link']) {
            return get_the_term_list(
                $post->ID,
                $args['taxonomy'],
                $before,
                $args['separator'],
                $after,
            ) ?:
                null;
        }

        $terms = get_the_terms($post->ID, $args['taxonomy']);

        return $terms
            ? implode($args['separator'], array_map(fn($term) => $term->name, $terms))
            : null;
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return ?array<WP_Post>
     */
    public static function relatedPosts($post, array $args)
    {
        $args += ['post_type' => $post->post_type, 'terms' => []];

        $args['exclude'] = [$post->ID, ...$args['exclude'] ?? []];

        $taxonomyNames = get_taxonomies();
        $taxonomies = Arr::pick($args, fn($arg, $name) => in_array($name, $taxonomyNames));

        foreach (array_filter($taxonomies) as $taxonomy => $operator) {
            $args['terms'] = array_merge(
                $args['terms'],
                $terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']) ?: [],
            );

            if (!$terms && $operator === 'IN') {
                return null;
            }

            $args["{$taxonomy}_operator"] = $operator;
        }

        if (!empty($args['author'])) {
            $args['users'] = [$post->post_author];
            $args['users_operator'] = $args['author'];
        }

        return Helper::getPosts($args);
    }
}
