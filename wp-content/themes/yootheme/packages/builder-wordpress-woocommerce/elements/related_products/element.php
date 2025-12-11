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
];
