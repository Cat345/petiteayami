<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Listener;

use YOOtheme\Builder\Wordpress\Woocommerce\Helper;

class FilterTaxonomies
{
    public static function handle(array $taxonomies, $object): array
    {
        if ($object === 'product') {
            $taxonomies['product_visibility'] = get_taxonomy('product_visibility');

            $taxonomies += Helper::getAttributeTaxonomies();
        }

        return $taxonomies;
    }
}
