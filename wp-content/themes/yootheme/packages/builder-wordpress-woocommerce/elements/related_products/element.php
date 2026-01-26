<?php

namespace YOOtheme;

use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

return [
    'name' => 'woo_related_products',
    'title' => 'Related Products',
    'group' => 'woocommerce',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'defaults' => [
        'posts_per_page' => 4,
        'columns' => 4,
        'orderby' => 'rand',
        'order' => 'desc',
        'show_headline' => true,
        'show_title' => true,
        'show_rating' => true,
    ],
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
    ],
    'transforms' => [
        'render' => function ($node) {
            if (!is_product()) {
                return false;
            }

            $content = Helper::renderTemplate(function () use ($node) {
                $filters = [];

                if (empty($node->props['show_headline'])) {
                    $filters[] = Helper::addFilter(
                        'woocommerce_product_related_products_heading',
                        function () {},
                        100,
                    );
                }

                if (empty($node->props['show_title'])) {
                    $filters[] = Helper::removeFilter('woocommerce_shop_loop_item_title');
                }

                if (empty($node->props['show_rating'])) {
                    $filters[] = Helper::removeFilter('woocommerce_after_shop_loop_item_title', 5);
                }

                $filters[] = Helper::addFilter(
                    'woocommerce_output_related_products_args',
                    fn($args) => array_merge(
                        $args,
                        Arr::pick($node->props, ['posts_per_page', 'columns', 'orderby', 'order']),
                    ),
                );

                /**
                 * Hook: woocommerce_after_single_product_summary.
                 *
                 * @hooked woocommerce_output_related_products - 20
                 */
                Hook::doAction('woocommerce_after_single_product_summary', ['start' => 20]);

                // Restore filters
                foreach ($filters as $fn) {
                    $fn();
                }
            });

            if (empty($content)) {
                return false;
            }

            $node->props['content'] = $content;
        },
    ],
    'fields' => [
        'posts_per_page' => [
            'label' => 'Limit',
            'description' => 'Limit the number of products.',
            'type' => 'number',
        ],
        'columns' => [
            'label' => 'Columns',
            'description' =>
                'Set the number of grid columns for desktops and larger screens. On smaller viewports the columns will adapt automatically.',
            'type' => 'number',
        ],
        'orderby' => [
            'label' => 'Order',
            'description' => 'Set the product ordering.',
            'type' => 'select',
            'options' => [
                'ID' => 'id',
                'Date' => 'date',
                'Modified' => 'modified',
                'Title' => 'title',
                'Price' => 'price',
                'Menu' => 'menu_order',
                'Random' => 'rand',
            ],
        ],
        'order' => [
            'label' => 'Order Direction',
            'description' => 'Set the order direction.',
            'type' => 'select',
            'options' => [
                'Ascending' => 'asc',
                'Descending' => 'desc',
            ],
        ],
        'show_headline' => [
            'label' => 'Display',
            'type' => 'checkbox',
            'text' => 'Show headline',
        ],
        'show_title' => [
            'type' => 'checkbox',
            'text' => 'Show title',
        ],
        'show_rating' => [
            'type' => 'checkbox',
            'text' => 'Show rating',
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
        'text_align' => '${builder.text_align_justify}',
        'text_align_breakpoint' => '${builder.text_align_breakpoint}',
        'text_align_fallback' => '${builder.text_align_justify_fallback}',
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
                            'label' => 'WooCommerce',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'posts_per_page',
                                'columns',
                                'orderby',
                                'order',
                                'show_headline',
                                'show_title',
                                'show_rating',
                            ],
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
