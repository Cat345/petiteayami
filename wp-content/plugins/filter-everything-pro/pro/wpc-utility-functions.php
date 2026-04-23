<?php

if ( ! defined('ABSPATH') ) {
    exit;
}

use FilterEverything\Filter\Container;
use FilterEverything\Filter\Pro\Admin\SeoRules;
use FilterEverything\Filter\WP_Query_Source_Detector;

function flrt_get_seo_rules_fields( $post_id )
{
    $seoRules = new SeoRules();
    return $seoRules->getRuleInputs( $post_id );
}

function flrt_create_seo_rules_nonce()
{
    return SeoRules::createNonce();
}

function flrt_is_first_order_clause( $query ) {
    return isset( $query['key'] ) || isset( $query['value'] );
}

function flrt_build_variations_meta_query( $parent_ids, $meta_query = [] ) {
    global $wpdb;
    $variations_sql = [];

    if( empty( $parent_ids ) ){
        return $variations_sql;
    }

    // Remove empty, null, or whitespace-only values from parent_ids
    $parent_ids = array_filter($parent_ids, function($value) {
        return !empty($value) && $value !== null;
    });

    // Make array unique
    $parent_ids = array_unique($parent_ids);

    $variations_sql[]       = " OR ("; //$all_not_exists ? " AND (" : " OR (";
    $variations_sql[]       = "{$wpdb->posts}.ID IN( ". implode( ",", $parent_ids ) ." )";

    if( ! empty( $meta_query ) ){
        $side_meta_query = new \WP_Meta_Query( $meta_query );
        $clauses = $side_meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
        if( $clauses['where'] ){
            $variations_sql[] = $clauses['where'];
        }
    }

    $variations_sql[]       = ")";

    return $variations_sql;
}


/**
 * Extracts from meta_queries only those which can be related with variations
 * @param $queries array meta_queries
 * @return array
 */
function flrt_sanitize_variations_meta_query( $queries, $queried_filters ) {
    $clean_queries           = [];
    $separated_queries       = [ 'for_variations' => [], 'for_products' => [] ];
    $filter_keys             = [ 'keys_variations' => [], 'keys_products' => [] ];

    if( ! $queried_filters ){
        return $separated_queries;
    }

    // Collect only post meta filter keys.
    foreach ( $queried_filters as $slug => $filter ) {
        if( isset( $filter['e_name'] ) && in_array( $filter['entity'], array( 'post_meta', 'post_meta_num', 'post_meta_exists', 'post_meta_date' ) ) ){

            if( $filter['used_for_variations'] === 'yes' ){
                $filter_keys['keys_variations'][] = $filter['e_name'];
            }else{
                $filter_keys['keys_products'][] = $filter['e_name'];
            }

        }
    }

    if ( ! is_array( $queries ) ) {
        return $separated_queries;
    }

    foreach ( $queries as $key => $query ) {
        if ( 'relation' === $key ) {
            $relation = $query;

        } elseif ( ! is_array( $query ) ) {
            continue;

            // First-order clause.
        } elseif ( flrt_is_first_order_clause( $query ) ) {
            if ( isset( $query['value'] ) && array() === $query['value'] ) {
                unset( $query['value'] );
            }

            if( isset( $query['key'] ) ){
                if( in_array( $query['key'], $filter_keys['keys_variations'] ) ){
                    $separated_queries['for_variations'][ $key ] = $query;
                }else{
                    $separated_queries['for_products'][ $key ] = $query;
                }
            }

            // Otherwise, it's a nested query, so we recurse.
        } else {
            $sub_queries = flrt_sanitize_variations_meta_query( $query, $queried_filters );

            if ( ! empty( $sub_queries['for_variations'] ) ) {
                $separated_queries['for_variations'][ $key ] = $sub_queries['for_variations'];
            }

            if( ! empty( $sub_queries['for_products'] ) ){
                $separated_queries['for_products'][ $key ] = $sub_queries['for_products'];
            }
        }
    }

    if ( empty( $separated_queries['for_variations'] ) ) {
        return $separated_queries;
    }

    // Sanitize the 'relation' key provided in the query.
    if ( isset( $relation ) && 'OR' === strtoupper( $relation ) ) {
        $separated_queries['for_variations']['relation'] = 'OR';

        /*
        * If there is only a single clause, call the relation 'OR'.
        * This value will not actually be used to join clauses, but it
        * simplifies the logic around combining key-only queries.
        */
    } elseif ( 1 === count( $clean_queries ) ) {
        $separated_queries['for_variations']['relation'] = 'OR';

        // Default to AND.
    } else {
        $separated_queries['for_variations']['relation'] = 'AND';
    }

    return $separated_queries;
}

function flrt_is_all_not_exists( $queries ) {
    $all_not_exists = true;

    if ( ! is_array( $queries ) ) {
        return false;
    }

    foreach ( $queries as $key => $query ) {
        if ( 'relation' === $key ) {
            continue;

        } elseif ( ! is_array( $query ) ) {
            continue;

            // First-order clause.
        } elseif ( flrt_is_first_order_clause( $query ) ) {
            if( isset( $query['compare'] ) ){
                if( ! in_array( $query['compare'], array( 'NOT EXISTS' /*, 'NOT IN'*/ ) ) ){
                    $all_not_exists = false;
                    break;
                }
            }

            // Otherwise, it's a nested query, so we recurse.
        } else {
            $all_not_exists = flrt_is_all_not_exists( $query );
        }
    }

    return $all_not_exists;
}

function flrt_get_terms_ids_by_tax_query( $query ){
    if( ! isset( $query['terms'] ) || empty( $query['terms'] ) ){
        return false;
    }

    $args       = [ 'slug' => $query['terms'] ];
    $term_query = new WP_Term_Query();
    $term_list  = $term_query->query( $args );

    $term_list = wp_list_pluck( $term_list, 'term_id' );
    return '(' . implode( ",", $term_list ) . ')';
}

function flrt_get_location_permalink( $set = [] )
{
    $postType          = isset( $set['post_type']['value'] ) ? $set['post_type']['value'] : 'post';
    $location          = isset( $set['post_name']['value'] ) ? $set['post_name']['value'] : '';
    $applyBtnPageType  = isset( $set['apply_button_page_type']['value'] ) ? $set['apply_button_page_type']['value'] : '';

    if ( isset( $set['wp_page_type']['value']  ) ) {
        $wpPageType = $set['wp_page_type']['value'];
    }

    $permalink    = '';
    $wpPageType   = $wpPageType ? $wpPageType : 'common___common';

    $pageTypeVars = explode('___', $wpPageType);
    $locTypeVars  = explode( '___', $location );
    $locType      = isset( $locTypeVars[1] ) ? $locTypeVars[1] : false;
    $typeKey      = $pageTypeVars[0];
    $typeValue    = isset( $pageTypeVars[1] ) ? $pageTypeVars[1] : false;

    // @todo No posts, No tags what to show in Dropdown?
    switch ( $typeKey ){
        case 'common':
            if ( $location === '1' && $applyBtnPageType === 'no_page___no_page' ){
                return $permalink;
            }
            $common_terms = flrt_get_common_location_terms( $postType );
            if( isset( $common_terms[$location]['data-link'] ) ){
                $permalink = $common_terms[$location]['data-link'];
            }
            break;
        case 'post_type':
            if ( $locType === '-1' && $applyBtnPageType === 'no_page___no_page' ){
                return $permalink;
            }
            $post_terms = flrt_get_post_type_location_terms( $typeValue, false );
            if( isset( $post_terms[$location]['data-link'] ) ){
                $permalink = $post_terms[$location]['data-link'];
            }
            break;
        case 'taxonomy':
            if ( /*$locType === '-1' && */ $applyBtnPageType === 'no_page___no_page' ){
                // This also should work in situations, when it is sub-taxonomy page
                return $permalink;
            }
            $taxonomy_terms = flrt_get_taxonomy_location_terms( $typeValue );
            if( isset( $taxonomy_terms[$location]['data-link'] ) ){
                $permalink = $taxonomy_terms[$location]['data-link'];
            }
            break;
        case 'author':
            if ( $locType === '-1' && $applyBtnPageType === 'no_page___no_page' ){
                return $permalink;
            }
            $author_terms = flrt_get_author_location_terms();
            if( isset( $author_terms[$location]['data-link'] ) ){
                $permalink = $author_terms[$location]['data-link'];
            }
            break;
    }

    return apply_filters( 'wpc_set_location_permalink', $permalink, $typeKey, $location, $applyBtnPageType, $set );
}

function flrt_init_common()
{
    add_filter( 'site_transient_update_plugins', 'flrt_increase_count' );

    // modify plugin data visible in the 'View details' popup
    add_filter( 'plugins_api', 'flrt_plugin_details', 10, 3 );

    if ( is_admin() ) {
        add_action( 'in_plugin_update_message-' . FLRT_PLUGIN_BASENAME, 'flrt_plugin_update_message', 10, 2 );
        add_action( 'upgrader_process_complete', 'flrt_after_increase_count', 10, 2 );

        $license_data = get_option( FLRT_LICENSE_KEY );
        $license_key  = false;
        $parts        = false;
        $hare         = true;

        if ( $license_data && isset( $license_data[ 'license_key' ] ) ) {
            $decoded = maybe_unserialize( base64_decode( $license_data[ 'license_key' ] ) );

            if ( $decoded[ 'key' ] ) {
                $license_key = $decoded[ 'key' ];
            }

            $parts = explode( "|", base64_decode( $license_key ) );

            if ( count( $parts ) === 3  ) {
                $hare = false;
            }
        }

        if ( ! $license_key || $hare || count( $parts ) !== 3 ) {
            $the_trident = get_option( 'wpc_trident' );
            if ( ! $the_trident ) {
                flrt_set_the_trident();
            } else {
                if ( isset( $the_trident[ 'first_install' ] ) && isset( $the_trident[ 'last_message' ] ) && isset( $the_trident[ 'messages_count' ] ) ) {
                    $instt = $the_trident[ 'first_install' ];
                    $lastm = $the_trident[ 'last_message' ];
                    $msgc  = $the_trident[ 'messages_count' ];
                    $tnow  = time();

                    // One month after installation date
                    if ( ( $instt + MONTH_IN_SECONDS ) < $tnow ) {
                        if( ( $instt + MONTH_IN_SECONDS * 2 ) < $tnow ) {
                            add_filter( 'wpc_validate_filter_fields', 'flrt_notify_hare', 10, 2 );
                            add_filter( 'wpc_validate_seo_rules', 'flrt_notify_hare', 10, 2 );
                        } else {
                            if ( ( $lastm + DAY_IN_SECONDS * 7 ) < $tnow && $msgc < 4 ) {
                                add_action( 'all_admin_notices', 'flrt_show_license_notice' );
                            }
                        }
                    }
                }
            }
        }
    }
}

function flrt_init_common_multisite()
{
    if( ! is_multisite() || is_main_site() ) {
        return;
    }

    if ( is_admin() ) {
        $main_site_id = get_main_site_id();
        $license_data = get_blog_option( $main_site_id, FLRT_LICENSE_KEY );
        $license_key  = false;
        $parts        = false;
        $hare         = true;

        if ( $license_data && isset( $license_data[ 'license_key' ] ) ) {
            $decoded = maybe_unserialize( base64_decode( $license_data[ 'license_key' ] ) );

            if ( $decoded[ 'key' ] ) {
                $license_key = $decoded[ 'key' ];
            }

            $parts = explode( "|", base64_decode( $license_key ) );

            if ( count( $parts ) === 3  ) {
                $hare = false;
            }
        }

        if ( ! $license_key || $hare || count( $parts ) !== 3 ) {
            $the_trident = get_blog_option( $main_site_id,'wpc_trident' );

            if ( isset( $the_trident[ 'first_install' ] ) && isset( $the_trident[ 'last_message' ] ) && isset( $the_trident[ 'messages_count' ] ) ) {
                $instt = $the_trident[ 'first_install' ];
                $tnow  = time();

                // One month after installation date
                if( ( $instt + MONTH_IN_SECONDS * 2 ) < $tnow ) {
                    add_filter( 'wpc_validate_filter_fields', 'flrt_notify_hare_multisite', 10, 2 );
                    add_filter( 'wpc_validate_seo_rules', 'flrt_notify_hare_multisite', 10, 2 );
                }
            }
        }
    }
}

function flrt_get_stock_status_filter_emulation()
{
    return array(
        "ID" => "-1",
        "parent" => "-1",
        "entity" => "post_meta",
        "e_name" => "_stock_status",
        "slug" => "status",
        "logic" => "or",
        "orderby" => "default",
        "used_for_variations" => "yes",
        "values" => array( "instock" )
    );
}

function flrt_display_products_in_stock_only_pro( $filtered_query )
{
    if ( $filtered_query->get('wc_query') === 'product_query' || $filtered_query->get('post_type') === 'product' ) {
        $meta_query = $filtered_query->get('meta_query');
        $add_in_stock = true;

        if ( ! empty( $meta_query ) ) {
            foreach ($meta_query as $sub_query) {
                if (isset($sub_query['key']) && $sub_query['key'] === '_stock_status') {
                    $add_in_stock = false;
                    break;
                }
            }
        }

        //@todo consider to relate with WooCommerce 'woocommerce_hide_out_of_stock_items' option value
        if ( $add_in_stock ) {

            if( ! is_array( $meta_query ) ) {
                $meta_query = [];
            }

            $meta_query[] = array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => 'IN',
            );

            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }

            $filtered_query->set('meta_query', $meta_query);
        }
    }

    return $filtered_query;
}

function flrt_display_variations_in_stock_only_pro( $separated_queries )
{
    $products_in_stock_query_exists = false;
    if (isset($separated_queries['for_products'])) {
        foreach ($separated_queries['for_products'] as $products_meta_query) {
            if (isset($products_meta_query['key']) && $products_meta_query['key'] === '_stock_status') {
                $products_in_stock_query_exists = true;
                break;
            }
        }
    }

    $variations_in_stock_query_exists = false;
    if (isset($separated_queries['for_variations'])) {
        foreach ($separated_queries['for_variations'] as $products_meta_query) {
            if (isset($products_meta_query['key']) && $products_meta_query['key'] === '_stock_status') {
                $variations_in_stock_query_exists = true;
                break;
            }
        }
    }

    //@todo consider to relate with WooCommerce 'woocommerce_hide_out_of_stock_items' option value
    if ( $products_in_stock_query_exists && ! $variations_in_stock_query_exists ) {

        $separated_queries['for_variations'][] =
            array(
                'key' => '_stock_status',
                'value' => array('instock'),
                'compare' => "IN"
            );
    }

    return $separated_queries;
}

function flrt_all_set_wp_queried_in_stock_only_pro( $set_wp_query )
{

    /**
     * Set In Stock products by default
     * For correct term count calculations
     */
    if ( $set_wp_query->get('wc_query') === 'product_query' || $set_wp_query->get('post_type') === 'product' ) {
        $meta_query = $set_wp_query->get('meta_query');
        $add_in_stock = true;

        if (!empty($meta_query)) {
            foreach ($meta_query as $sub_query) {
                if (isset($sub_query['key']) && $sub_query['key'] === '_stock_status') {
                    $add_in_stock = false;
                    break;
                }
            }
        }

        if ( $add_in_stock ) {
            if( ! is_array( $meta_query ) ) {
                $meta_query = [];
            }

            $meta_query[] = array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => 'IN',
            );

            $set_wp_query->set('meta_query', $meta_query);
        }
    }

    return $set_wp_query;
}

function flrt_add_in_stock_to_related_filters_pro($relatedFilters, $sets)
{
    $filter_by_stock_exists = false;
    $post_type = $sets[0]['filtered_post_type'];

    if (!empty($relatedFilters) && $post_type === 'product') {

        foreach ($relatedFilters as $filter) {
            if (isset($filter['e_name']) && $filter['e_name'] === '_stock_status') {
                $filter_by_stock_exists = true;
                break;
            }
        }

        if ( ! $filter_by_stock_exists ) {
            $relatedFilters["-1"] = flrt_get_stock_status_filter_emulation();
        }
    }

    return $relatedFilters;
}

function flrt_add_in_stock_to_filtered_posts_pro( $filteredAllPostsIds, $allEntities )
{
    if ( isset( $allEntities['_stock_status'] ) && isset( $allEntities['_stock_status']->items['instock'] ) ) {
        if ( is_array( $allEntities['_stock_status']->items['instock']->posts ) ) {
            $filteredAllPostsIds['_stock_status'] = array_flip( $allEntities['_stock_status']->items['instock']->posts );
        }
    }

    return $filteredAllPostsIds;
}

add_filter('wpc_get_broken_builders', function ($array){
    if(defined('FLRT_PRO_BUILDER_KEY')){
        $builder_key = FLRT_PRO_BUILDER_KEY;
        $builders = WP_Query_Source_Detector::$builders;
        $builders_keys = array_keys($builders);
        $builders_keys[] = 'main_query';
        $builders_keys[] = 'gutenberg';
        $builders_keys[] = 'custom';

        foreach ($builders_keys as $key){
            $array[] = (int) sprintf("%u", crc32($key . $builder_key));
        }
        return $array;
    }
}, 11, 1);

add_filter('wpc_builder_key_pro', function ($i, $o){
    return (int) sprintf("%u", crc32($i . $o));
}, 10, 2);