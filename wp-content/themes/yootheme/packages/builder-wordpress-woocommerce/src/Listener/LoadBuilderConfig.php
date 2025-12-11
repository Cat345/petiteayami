<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Listener;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Wordpress\Source\Listener\LoadBuilderConfig as Listener;
use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use function YOOtheme\trans;

class LoadBuilderConfig
{
    /**
     * @param BuilderConfig $config
     */
    public static function handle($config): void
    {
        $taxonomy = get_taxonomy('product_visibility');

        $terms = get_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false]);

        $mapping = [
            'featured' => __('Featured', 'woocommerce'),
            'outofstock' => __('Out of Stock', 'woocommerce'),
            'rated-1' => sprintf(__('Rated %s out of 5', 'woocommerce'), 1),
            'rated-2' => sprintf(__('Rated %s out of 5', 'woocommerce'), 2),
            'rated-3' => sprintf(__('Rated %s out of 5', 'woocommerce'), 3),
            'rated-4' => sprintf(__('Rated %s out of 5', 'woocommerce'), 4),
            'rated-5' => sprintf(__('Rated %s out of 5', 'woocommerce'), 5),
        ];

        $config->merge([
            'taxonomies' => [
                'product_visibility' => [
                    'label' => $taxonomy->label,
                    'options' => array_values(
                        array_filter(
                            array_map(function ($term) use ($mapping) {
                                if (isset($mapping[$term->name])) {
                                    return [
                                        'value' => $term->term_id,
                                        'text' => $mapping[$term->name],
                                    ];
                                }
                            }, $terms),
                        ),
                    ),
                ],
            ],

            'templates' => [
                'taxonomy-product_cat' => ['label' => trans('Product Category Archive')],
                'taxonomy-product_tag' => ['label' => trans('Product Tag Archive')],
            ],
        ]);

        foreach (Helper::getAttributeTaxonomies() as $name => $taxonomy) {
            if ($taxonomy->public) {
                $config->merge([
                    'templates' => [
                        "taxonomy-{$name}" => Listener::getTaxonomyArchive($taxonomy),
                    ],
                ]);
            }

            if ($terms = Listener::getTaxonomyTerms($taxonomy)) {
                $config->merge([
                    'taxonomies' => [
                        $name => ['label' => $taxonomy->label, 'options' => $terms],
                    ],
                ]);
            }
        }

        $config->push('sources.productOrderOptions', [
            'label' => 'WooCommerce',
            'options' => [
                ['text' => __('Price', 'woocommerce'), 'value' => 'field:_price'],
                ['text' => __('Rating', 'woocommerce'), 'value' => 'field:_wc_average_rating'],
                ['text' => __('Purchases', 'woocommerce'), 'value' => 'field:total_sales'],
                ['text' => __('SKU', 'woocommerce'), 'value' => 'field:_sku'],
            ],
        ]);
    }
}
