<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Post;
use WP_Post_Type;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class CustomPostQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(WP_Post_Type $type): array
    {
        $name = Str::camelCase($type->name, true);
        $base = Str::camelCase(Helper::getBase($type), true);

        $plural = Str::lower($type->label);
        $singular = Str::lower($type->labels->singular_name);

        $taxonomies = Helper::getObjectTaxonomies($type->name);

        ksort($taxonomies);

        $terms = $taxonomies
            ? [
                'label' => trans('Filter by Terms'),
                'type' => 'select',
                'options' => array_map(
                    fn($taxonomy) => ['evaluate' => "yootheme.builder.taxonomies['{$taxonomy}']"],
                    array_keys($taxonomies),
                ),
                'attrs' => [
                    'multiple' => true,
                    'class' => 'uk-height-medium',
                ],
            ]
            : [];

        $operators = [];

        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->hierarchical) {
                $operators[strtr($taxonomy->name, '-', '_') . '_include_children'] = [
                    'description' =>
                        end($taxonomies) === $taxonomy
                            ? trans(
                                'Filter %post_types% by terms. Use the <kbd>shift</kbd> or <kbd>ctrl/cmd</kbd> key to select multiple terms. Set the logical operator to match at least one of the terms, none of the terms or all terms.',
                                ['%post_types%' => $plural],
                            )
                            : '',
                    'type' => 'select',
                    'options' => [
                        trans('Exclude child %taxonomies%', [
                            '%taxonomies%' => mb_strtolower($taxonomy->label),
                        ]) => '',
                        trans('Include child %taxonomies%', [
                            '%taxonomies%' => mb_strtolower($taxonomy->label),
                        ]) => 'include',
                        trans('Only include child %taxonomies%', [
                            '%taxonomies%' => mb_strtolower($taxonomy->label),
                        ]) => 'only',
                    ],
                ];
            }
            $operators[strtr($taxonomy->name, '-', '_') . '_operator'] = [
                'description' =>
                    end($taxonomies) === $taxonomy && !$taxonomy->hierarchical
                        ? trans(
                            'Filter %post_types% by terms. Use the <kbd>shift</kbd> or <kbd>ctrl/cmd</kbd> key to select multiple terms. Set the logical operator to match at least one of the terms, none of the terms or all terms.',
                            ['%post_types%' => $plural],
                        )
                        : '',
                'type' => 'select',
                'options' => [
                    trans('Match one %taxonomy% (OR)', [
                        '%taxonomy%' => mb_strtolower($taxonomy->labels->singular_name),
                    ]) => 'IN',
                    trans('Match all %taxonomies% (AND)', [
                        '%taxonomies%' => mb_strtolower($taxonomy->label),
                    ]) => 'AND',
                    trans("Don't match %taxonomies% (NOR)", [
                        '%taxonomies%' => mb_strtolower($taxonomy->label),
                    ]) => 'NOT IN',
                ],
            ];
        }

        return [
            'fields' => [
                "custom{$name}" => [
                    'type' => $name,

                    'args' => array_merge(
                        [
                            'id' => [
                                'type' => 'Int',
                            ],
                            'terms' => [
                                'type' => [
                                    'listOf' => 'Int',
                                ],
                            ],
                            'users' => [
                                'type' => [
                                    'listOf' => 'Int',
                                ],
                                'defaultValue' => [],
                            ],
                            'users_operator' => [
                                'type' => 'String',
                                'defaultValue' => 'IN',
                            ],
                            'date_column' => [
                                'type' => 'String',
                            ],
                            'date_range' => [
                                'type' => 'String',
                                'defaultValue' => 'relative',
                            ],
                            'date_relative' => [
                                'type' => 'String',
                                'defaultValue' => 'next',
                            ],
                            'date_relative_value' => [
                                'type' => 'Int',
                            ],
                            'date_relative_unit' => [
                                'type' => 'String',
                                'defaultValue' => 'day',
                            ],
                            'date_relative_unit_this' => [
                                'type' => 'String',
                                'defaultValue' => 'day',
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
                                'defaultValue' => 0,
                            ],
                            'order' => [
                                'type' => 'String',
                                'defaultValue' => 'date',
                            ],
                            'order_direction' => [
                                'type' => 'String',
                                'defaultValue' => 'DESC',
                            ],
                            'order_alphanum' => [
                                'type' => 'Boolean',
                            ],
                            'order_reverse' => [
                                'type' => 'Boolean',
                            ],
                        ],
                        array_map(fn() => ['type' => 'String', 'defaultValue' => 'IN'], $operators),
                    ),

                    'metadata' => [
                        'label' => trans('Custom %post_type%', [
                            '%post_type%' => $type->labels->singular_name,
                        ]),
                        'group' => trans('Custom'),
                        'fields' => array_merge(
                            [
                                'id' => [
                                    'label' => trans('Select Manually'),
                                    'description' => trans(
                                        'Pick a %post_type% manually or use filter options to specify which %post_type% should be loaded dynamically.',
                                        ['%post_type%' => $singular],
                                    ),
                                    'type' => 'select-item',
                                    'post_type' => $type->name,
                                    'labels' => [
                                        'type' => $type->labels->singular_name,
                                    ],
                                ],
                            ],
                            $terms
                                ? [
                                        'terms' => $terms + [
                                            'enable' => '!id',
                                        ],
                                    ] +
                                    array_map(
                                        fn($operator) => $operator + ['enable' => '!id'],
                                        $operators,
                                    )
                                : [],
                            [
                                'users' => [
                                    'label' => trans('Filter by Authors'),
                                    'type' => 'select',
                                    'options' => [['evaluate' => 'yootheme.builder.authors']],
                                    'attrs' => [
                                        'multiple' => true,
                                        'class' => 'uk-height-small',
                                    ],
                                    'enable' => '!id',
                                ],
                                'users_operator' => [
                                    'description' => trans(
                                        'Filter %post_types% by authors. Use the <kbd>shift</kbd> or <kbd>ctrl/cmd</kbd> key to select multiple authors. Set the logical operator to match or not match the selected authors.',
                                        ['%post_types%' => $plural],
                                    ),
                                    'type' => 'select',
                                    'options' => [
                                        trans('Match (OR)') => 'IN',
                                        trans("Don't match (NOR)") => 'NOT IN',
                                    ],
                                    'enable' => '!id',
                                ],
                                '_date' => [
                                    'label' => trans('Filter by Date'),
                                    'description' => trans(
                                        'Filter posts by a range relative to the current date or by a fixed start and end date.',
                                    ),
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
                                            'show' => "date_relative !== 'this'",
                                        ],
                                        'date_relative_unit' => [
                                            'type' => 'select',
                                            'options' => [
                                                trans('Days') => 'day',
                                                trans('Weeks') => 'week',
                                                trans('Months') => 'month',
                                                trans('Years') => 'year',
                                                trans('Calendar Weeks') => 'week_calendar',
                                                trans('Calendar Months') => 'month_calendar',
                                                trans('Calendar Years') => 'year_calendar',
                                            ],
                                            'show' => "date_relative !== 'this'",
                                        ],
                                        'date_relative_unit_this' => [
                                            'type' => 'select',
                                            'options' => [
                                                trans('Day') => 'day',
                                                trans('Week') => 'week',
                                                trans('Month') => 'month',
                                                trans('Year') => 'year',
                                            ],
                                            'show' => "date_relative === 'this'",
                                        ],
                                    ],
                                    'show' => "date_column && date_range === 'relative'",
                                ],
                                'date_relative_start_today' => [
                                    'type' => 'checkbox',
                                    'text' => trans('Start today'),
                                    'description' => trans(
                                        'Set a range starting tomorrow or the next full calendar period. Optionally, start today, which includes the current partial period for calendar ranges. Today refers to the full calendar day.',
                                    ),
                                    'enable' => "date_relative !== 'this'",
                                    'show' => "date_column && date_range === 'relative'",
                                ],
                                '_date_range_fixed' => [
                                    'type' => 'grid',
                                    'description' => trans(
                                        'Set only one date to load all posts either before or after that date.',
                                    ),
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
                                    'show' => "date_column && date_range === 'fixed'",
                                ],
                                '_date_range_custom' => [
                                    'type' => 'grid',
                                    'description' => trans(
                                        'Use the <a href="https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative" target="_blank">PHP relative date formats</a> in a BNF-like syntax. Set only one date to load all articles either before or after that date.',
                                    ),
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
                                                'Month after next month' =>
                                                    'last day of +2 month 23:59:59',
                                            ],
                                        ],
                                    ],
                                    'show' => "date_column && date_range === 'custom'",
                                ],
                                'offset' => [
                                    'label' => trans('Start'),
                                    'description' => trans(
                                        'Set the starting point to specify which %post_type% is loaded.',
                                        ['%post_type%' => $singular],
                                    ),
                                    'type' => 'number',
                                    'modifier' => 1,
                                    'attrs' => [
                                        'min' => 1,
                                        'required' => true,
                                    ],
                                    'enable' => '!id',
                                    '@order' => 50,
                                ],
                                '_order' => [
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => [
                                        'order' => [
                                            'label' => trans('Order'),
                                            'type' => 'select',
                                            'options' => [
                                                [
                                                    'evaluate' =>
                                                        'yootheme.builder.sources.postTypeOrderOptions',
                                                ],
                                                [
                                                    'evaluate' => "yootheme.builder.sources['{$type->name}OrderOptions']",
                                                ],
                                            ],
                                            'enable' => '!id',
                                        ],
                                        'order_direction' => [
                                            'label' => trans('Direction'),
                                            'type' => 'select',
                                            'options' => [
                                                ['text' => trans('Ascending'), 'value' => 'ASC'],
                                                ['text' => trans('Descending'), 'value' => 'DESC'],
                                                [
                                                    'evaluate' => "yootheme.builder.sources['{$type->name}OrderDirectionOptions']",
                                                ],
                                            ],
                                            'enable' => '!id',
                                        ],
                                    ],
                                    '@order' => 60,
                                ],
                                'order_alphanum' => [
                                    'text' => trans('Alphanumeric Ordering'),
                                    'type' => 'checkbox',
                                    'enable' => '!id',
                                    '@order' => 70,
                                ],
                                'order_reverse' => [
                                    'text' => trans('Reverse Results'),
                                    'type' => 'checkbox',
                                    '@order' => 80,
                                ],
                            ],
                        ),
                    ],

                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolvePost',
                            'args' => ['post_type' => $type->name],
                        ],
                    ],
                ],

                "custom{$base}" => [
                    'type' => [
                        'listOf' => $name,
                    ],

                    'args' => array_merge(
                        [
                            'terms' => [
                                'type' => [
                                    'listOf' => 'Int',
                                ],
                            ],
                            'users' => [
                                'type' => [
                                    'listOf' => 'Int',
                                ],
                                'defaultValue' => [],
                            ],
                            'users_operator' => [
                                'type' => 'String',
                                'defaultValue' => 'IN',
                            ],
                            'date_column' => [
                                'type' => 'String',
                            ],
                            'date_range' => [
                                'type' => 'String',
                                'defaultValue' => 'relative',
                            ],
                            'date_relative' => [
                                'type' => 'String',
                                'defaultValue' => 'next',
                            ],
                            'date_relative_value' => [
                                'type' => 'Int',
                            ],
                            'date_relative_unit' => [
                                'type' => 'String',
                                'defaultValue' => 'day',
                            ],
                            'date_relative_unit_this' => [
                                'type' => 'String',
                                'defaultValue' => 'day',
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
                                'defaultValue' => 0,
                            ],
                            'limit' => [
                                'type' => 'Int',
                                'defaultValue' => 10,
                            ],
                            'order' => [
                                'type' => 'String',
                                'defaultValue' => 'date',
                            ],
                            'order_direction' => [
                                'type' => 'String',
                                'defaultValue' => 'DESC',
                            ],
                            'order_alphanum' => [
                                'type' => 'Boolean',
                            ],
                            'order_reverse' => [
                                'type' => 'Boolean',
                            ],
                        ],
                        array_map(fn() => ['type' => 'String', 'defaultValue' => 'IN'], $operators),
                    ),
                    'metadata' => [
                        'label' => trans('Custom %post_types%', ['%post_types%' => $type->label]),
                        'group' => trans('Custom'),
                        'fields' => array_merge($terms ? ['terms' => $terms] + $operators : [], [
                            'users' => [
                                'label' => trans('Filter by Authors'),
                                'type' => 'select',
                                'options' => [['evaluate' => 'yootheme.builder.authors']],
                                'attrs' => [
                                    'multiple' => true,
                                    'class' => 'uk-height-small',
                                ],
                            ],
                            'users_operator' => [
                                'description' => trans(
                                    'Filter %post_types% by authors. Use the <kbd>shift</kbd> or <kbd>ctrl/cmd</kbd> key to select multiple authors. Set the logical operator to match or not match the selected authors.',
                                    ['%post_types%' => $plural],
                                ),
                                'type' => 'select',
                                'options' => [
                                    trans('Match (OR)') => 'IN',
                                    trans("Don't match (NOR)") => 'NOT IN',
                                ],
                            ],
                            '_date' => [
                                'label' => trans('Filter by Date'),
                                'description' => trans(
                                    'Filter posts by a range relative to the current date or by a fixed start and end date.',
                                ),
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
                                        'show' => "date_relative !== 'this'",
                                    ],
                                    'date_relative_unit' => [
                                        'type' => 'select',
                                        'options' => [
                                            trans('Days') => 'day',
                                            trans('Weeks') => 'week',
                                            trans('Months') => 'month',
                                            trans('Years') => 'year',
                                            trans('Calendar Weeks') => 'week_calendar',
                                            trans('Calendar Months') => 'month_calendar',
                                            trans('Calendar Years') => 'year_calendar',
                                        ],
                                        'show' => "date_relative !== 'this'",
                                    ],
                                    'date_relative_unit_this' => [
                                        'type' => 'select',
                                        'options' => [
                                            trans('Day') => 'day',
                                            trans('Week') => 'week',
                                            trans('Month') => 'month',
                                            trans('Year') => 'year',
                                        ],
                                        'show' => "date_relative === 'this'",
                                    ],
                                ],
                                'show' => "date_column && date_range === 'relative'",
                            ],
                            'date_relative_start_today' => [
                                'type' => 'checkbox',
                                'text' => trans('Start today'),
                                'description' => trans(
                                    'Set a range starting tomorrow or the next full calendar period. Optionally, start today, which includes the current partial period for calendar ranges. Today refers to the full calendar day.',
                                ),
                                'enable' => "date_relative !== 'this'",
                                'show' => "date_column && date_range === 'relative'",
                            ],
                            '_date_range_fixed' => [
                                'type' => 'grid',
                                'description' => trans(
                                    'Set only one date to load all posts either before or after that date.',
                                ),
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
                                'show' => "date_column && date_range === 'fixed'",
                            ],
                            '_date_range_custom' => [
                                'type' => 'grid',
                                'description' => trans(
                                    'Use the <a href="https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative" target="_blank">PHP relative date formats</a> in a BNF-like syntax. Set only one date to load all articles either before or after that date.',
                                ),
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
                                            'Month after next month' =>
                                                'last day of +2 month 23:59:59',
                                        ],
                                    ],
                                ],
                                'show' => "date_column && date_range === 'custom'",
                            ],
                            '_offset' => [
                                'description' => trans(
                                    'Set the starting point and limit the number of %post_types%.',
                                    ['%post_types%' => $plural],
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
                        ]),
                    ],

                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolvePosts',
                            'args' => ['post_type' => $type->name],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return ?WP_Post
     */
    public static function resolvePost($root, array $args)
    {
        if (!empty($args['id'])) {
            return get_post($args['id']);
        }

        return array_first(Helper::getPosts(['limit' => 1] + $args));
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return array<WP_Post>
     */
    public static function resolvePosts($root, array $args): array
    {
        return Helper::getPosts($args);
    }
}
