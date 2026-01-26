<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Listener;

use WP_Taxonomy;
use YOOtheme\Builder\Wordpress\Woocommerce\Helper;

class FilterTaxonomies
{
    /**
     * @param array<string, WP_Taxonomy> $taxonomies
     * @param string $object
     * @return array<string, WP_Taxonomy>
     */
    public static function handle(array $taxonomies, $object): array
    {
        if ($object === 'product') {
            $taxonomies['product_visibility'] = get_taxonomy('product_visibility');

            $taxonomies += Helper::getAttributeTaxonomies();
        }

        return $taxonomies;
    }
}
