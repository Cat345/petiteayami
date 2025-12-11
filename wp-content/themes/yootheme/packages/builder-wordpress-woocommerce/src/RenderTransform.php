<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use YOOtheme\Arr;
use YOOtheme\Builder;

class RenderTransform
{
    /**
     * Transform callback.
     *
     * @param object $node
     * @param array  $params
     */
    public function __invoke($node, array $params)
    {
        /** @var Builder $builder */
        $builder = $params['builder'];
        $path = $params['path'];

        $types = [
            'woo_add_to_cart',
            'woo_additional_information_tab',
            'woo_description',
            'woo_images',
            'woo_meta',
            'woo_notices',
            'woo_price',
            'woo_rating',
            'woo_related_products',
            'woo_reviews',
            'woo_stock',
            'woo_tabs',
            'woo_title',
            'woo_upsell_products',
        ];

        global $post, $product;

        // setup global $product variable
        if (!is_object($product)) {
            wc_setup_product_data($post);
        }

        // check product elements
        if (in_array($node->type, $types) && ($section = $builder->parent($path, 'section'))) {
            // section needs product classes for styling and product variation images script
            if (empty($section->attrs['class']) || !in_array('product', $section->attrs['class'])) {
                Arr::update($section->attrs, 'class', function ($class) {
                    global $product;

                    return array_merge($class ?: [], wc_get_product_class('', $product));
                });
            }
        }
    }
}
