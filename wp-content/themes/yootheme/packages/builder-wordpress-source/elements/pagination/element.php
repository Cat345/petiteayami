<?php

namespace YOOtheme;

return [
    'name' => 'pagination',
    'title' => 'Pagination',
    'group' => 'system',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'pagination_type' => 'previous/next',
        'text_align' => 'center',
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
    ],
    'transforms' => [
        'render' => function ($node) {
            $getItem = fn($text, $link, $active = true) => (object) compact(
                'active',
                'text',
                'link',
            );

            if (is_singular()) {
                $node->props['pagination_type'] = 'previous/next';
                $taxonomy = $node->props['pagination_taxonomy'] ?: '';

                $previous = get_previous_post((bool) $taxonomy, '', $taxonomy ?: 'category');
                $next = get_next_post((bool) $taxonomy, '', $taxonomy ?: 'category');

                $node->props['pagination'] = [
                    'previous' => $previous
                        ? $getItem(
                            _x('Previous', 'previous set of posts'),
                            get_permalink($previous),
                        )
                        : null,
                    'next' => $next
                        ? $getItem(_x('Next', 'next set of posts'), get_permalink($next))
                        : null,
                ];

                return true;
            }

            global $wp_query;

            if ($wp_query->max_num_pages <= 1) {
                return false;
            }

            $total = $wp_query->max_num_pages ?? 1;
            $current = (int) get_query_var('paged') ?: 1;
            $endSize = 1;
            $midSize = 3;
            $dots = false;
            $pagination = [];

            if ($current > 1 && ($previous = previous_posts(false))) {
                $pagination['previous'] = $getItem(
                    _x('Previous', 'previous set of posts'),
                    $previous,
                    false,
                );
            }

            for ($n = 1; $n <= $total; $n++) {
                $active =
                    $n <= $endSize ||
                    ($n >= $current - $midSize && $n <= $current + $midSize) ||
                    $n > $total - $endSize;

                if ($active || $dots) {
                    $pagination[$n] = $getItem(
                        $active ? number_format_i18n($n) : __('&hellip;'),
                        $active ? get_pagenum_link($n, false) : '',
                        $n === $current,
                    );
                    $dots = $active;
                }
            }

            if ($current < $total && ($next = next_posts($wp_query->max_num_pages, false))) {
                $pagination['next'] = $getItem(_x('Next', 'next set of posts'), $next, false);
            }

            $node->props['pagination'] = $pagination;
        },
    ],
    'fields' => [
        'pagination_type' => [
            'label' => 'Pagination',
            'description' =>
                'Choose between the previous/next or numeric pagination. The numeric pagination is not available for single posts.',
            'type' => 'select',
            'options' => [
                'Previous/Next' => 'previous/next',
                'Numeric' => 'numeric',
            ],
        ],
        'pagination_space_between' => [
            'type' => 'checkbox',
            'text' => 'Show space between links',
            'enable' => 'pagination_type == \'previous/next\'',
        ],
        'pagination_taxonomy' => [
            'label' => 'Filter by Term',
            'description' => 'Select a taxonomy to only navigate between posts from the same term.',
            'type' => 'select',
            'options' => [
                [
                    'text' => 'None',
                    'value' => '',
                ],
                [
                    'evaluate' => 'yootheme.builder.allTaxonomies',
                ],
            ],
            'enable' => 'pagination_type == \'previous/next\'',
        ],
        'position' => '${builder.position}',
        'position_left' => '${builder.position_left}',
        'position_right' => '${builder.position_right}',
        'position_top' => '${builder.position_top}',
        'position_bottom' => '${builder.position_bottom}',
        'position_z_index' => '${builder.position_z_index}',
        'blend' => '${builder.blend}',
        'margin_top' => '${builder.margin_top}',
        'margin_bottom' => '${builder.margin_bottom}',
        'maxwidth' => '${builder.maxwidth}',
        'maxwidth_breakpoint' => '${builder.maxwidth_breakpoint}',
        'block_align' => '${builder.block_align}',
        'block_align_breakpoint' => '${builder.block_align_breakpoint}',
        'block_align_fallback' => '${builder.block_align_fallback}',
        'text_align' => '${builder.text_align}',
        'text_align_breakpoint' => '${builder.text_align_breakpoint}',
        'text_align_fallback' => '${builder.text_align_fallback}',
        'animation' => '${builder.animation}',
        '_parallax_button' => '${builder._parallax_button}',
        'visibility' => '${builder.visibility}',
        'name' => '${builder.name}',
        'status' => '${builder.status}',
        'id' => '${builder.id}',
        'class' => '${builder.cls}',
        'attributes' => '${builder.attrs}',
        'css' => [
            'label' => 'CSS',
            'description' =>
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element'],
            ],
            'source' => true,
        ],
        'transform' => '${builder.transform}',
    ],
    'fieldset' => [
        'default' => [
            'type' => 'tabs',
            'fields' => [
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Pagination',
                            'type' => 'group',
                            'fields' => ['pagination_type', 'pagination_space_between'],
                        ],
                        [
                            'label' => 'Single Post Pages',
                            'type' => 'group',
                            'fields' => ['pagination_taxonomy'],
                        ],
                        [
                            'label' => 'General',
                            'type' => 'group',
                            'fields' => [
                                'position',
                                'position_left',
                                'position_right',
                                'position_top',
                                'position_bottom',
                                'position_z_index',
                                'blend',
                                'margin_top',
                                'margin_bottom',
                                'maxwidth',
                                'maxwidth_breakpoint',
                                'block_align',
                                'block_align_breakpoint',
                                'block_align_fallback',
                                'text_align',
                                'text_align_breakpoint',
                                'text_align_fallback',
                                'animation',
                                '_parallax_button',
                                'visibility',
                            ],
                        ],
                    ],
                ],
                '${builder.advanced}',
            ],
        ],
    ],
];
