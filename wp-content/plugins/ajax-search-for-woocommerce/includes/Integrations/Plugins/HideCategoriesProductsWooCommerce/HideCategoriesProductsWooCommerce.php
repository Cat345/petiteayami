<?php

namespace DgoraWcas\Integrations\Plugins\HideCategoriesProductsWooCommerce;

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Integration with Hide Categories and Products for Woocommerce
 *
 * Plugin URL: https://wordpress.org/plugins/hide-categories-products-woocommerce/
 * Author: N.O.U.S. Open Useful and Simple
 */
class HideCategoriesProductsWooCommerce {
    public function init() {
        if ( !function_exists( 'Hide_Categories_Products_WC' ) ) {
            return;
        }
        add_filter( 'dgwt/wcas/search_query/args', array($this, 'excludeHiddenProducts') );
    }

    /**
     * Exclude hidden products (native search)
     */
    public function excludeHiddenProducts( $args ) {
        $hiddenCategories = Hide_Categories_Products_WC()->get_exluded_cats();
        if ( !empty( $hiddenCategories ) ) {
            if ( !isset( $args['tax_query'] ) ) {
                $args['tax_query'] = [];
            }
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $hiddenCategories,
                'operator' => 'NOT IN',
            ];
        }
        return $args;
    }

}
