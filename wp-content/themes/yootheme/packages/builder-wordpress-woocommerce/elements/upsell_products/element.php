<?php

namespace YOOtheme;

use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

return [
    'transforms' => [
        'render' => function ($node) {
            if (!is_product()) {
                return false;
            }

            $content = Helper::renderTemplate(function () use ($node) {
                $filters = [];

                if (empty($node->props['show_headline'])) {
                    $filters[] = Helper::addFilter(
                        'woocommerce_product_upsells_products_heading',
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
                    'woocommerce_upsells_columns',
                    fn() => $node->props['columns'],
                );

                $filters[] = Helper::addFilter(
                    'woocommerce_upsells_orderby',
                    fn() => $node->props['orderby'],
                );

                $filters[] = Helper::addFilter(
                    'woocommerce_upsells_order',
                    fn() => $node->props['order'],
                );

                $filters[] = Helper::addFilter(
                    'woocommerce_upsells_total',
                    fn() => $node->props['posts_per_page'],
                );

                /**
                 * Hook: woocommerce_after_single_product_summary.
                 *
                 * @hooked woocommerce_upsell_display - 15
                 */
                Hook::doAction('woocommerce_after_single_product_summary', [
                    'start' => 15,
                    'end' => 19,
                ]);

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
];
