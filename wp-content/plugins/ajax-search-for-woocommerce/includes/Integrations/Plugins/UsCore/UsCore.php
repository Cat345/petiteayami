<?php

namespace DgoraWcas\Integrations\Plugins\UsCore;

use DgoraWcas\Helpers;
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Integration with UpSolution Core addon by UpSolution
 *
 * Page URL: https://us-themes.com/
 * Author: UpSolution
 */
class UsCore {
    public function init() {
        if ( !defined( 'US_CORE_VERSION' ) ) {
            return;
        }
        add_action( 'pre_get_posts', array($this, 'pre_get_posts') );
    }

    /**
     *
     * Narrow the list of products in the AJAX search to those returned by our search engine
     *
     * @param \WP_Query $query
     */
    public function pre_get_posts( $query ) {
        if ( !$this->isRelevantProductAjaxQuery() ) {
            return;
        }
        $search_term = $query->get( 's' );
        if ( !dgoraAsfwFs()->is_premium() ) {
            $post_ids = Helpers::searchProducts( $search_term );
        }
        if ( !empty( $post_ids ) ) {
            $query->set( 'post__in', $post_ids );
            $query->set( 'orderby', 'post__in' );
            $query->set( 's', '' );
        }
    }

    /**
     * Check whether the current AJAX query is for the product grid in us_ajax_grid
     *
     * @return bool
     */
    private function isRelevantProductAjaxQuery() : bool {
        if ( !defined( 'DOING_AJAX' ) ) {
            return false;
        }
        if ( !isset( $_POST['action'] ) || $_POST['action'] !== 'us_ajax_grid' ) {
            return false;
        }
        $template_vars = ( function_exists( 'us_get_HTTP_POST_json' ) ? us_get_HTTP_POST_json( 'template_vars' ) : array() );
        $post_type = $template_vars['query_args']['post_type'] ?? null;
        if ( empty( $post_type ) || is_array( $post_type ) && !in_array( 'product', $post_type, true ) || is_string( $post_type ) && $post_type !== 'product' ) {
            return false;
        }
        return true;
    }

}
