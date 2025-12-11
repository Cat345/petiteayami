<?php

namespace DgoraWcas\Integrations\Plugins\FilterEverything;

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration with FilterEverything
 *
 * Plugin URL: https://wordpress.org/plugins/filter-everything/
 * Author: Stepasyuk
 */
class FilterEverything {
	public function init() {

		if ( ! class_exists( 'FlrtFilter' ) ) {
			return;
		}

		add_filter( 'dgwt/wcas/helpers/is_search_query', array( $this, 'allow_to_process_search_query' ), 10, 2 );
	}

	/**
	 *  Allow to process search query
	 *
	 * @param bool $enable
	 * @param \WP_Query $query
	 *
	 */
	public function allow_to_process_search_query( $enable, $query ) {
		if (
			is_object( $query ) &&
			is_a( $query, 'WP_Query' ) &&
			! empty( $query->query_vars['s'] ) &&
			Helpers::is_running_inside_class( 'FilterEverything\Filter\EntityManager', 20 ) &&
			Helpers::isRunningInsideFunction( 'getAllSetWpQueriedPostIds', 20 )
		) {
			$enable = true;
		}

		return $enable;
	}
}
