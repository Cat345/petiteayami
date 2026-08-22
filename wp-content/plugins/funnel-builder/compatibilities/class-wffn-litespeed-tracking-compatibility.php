<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFFN_LiteSpeed_Tracking_Compatibility' ) ) {
	class WFFN_LiteSpeed_Tracking_Compatibility {
		public function __construct() {
			add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'exclude_tracking_scripts' ) );
			add_filter( 'litespeed_optimize_js_excludes', array( $this, 'exclude_tracking_scripts' ) );
		}

		/**
		 * Exclude FunnelKit tracking scripts from LiteSpeed JS defer
		 *
		 * @param array $excludes
		 *
		 * @return array
		 */
		public function exclude_tracking_scripts( $excludes ) {
			if ( ! is_array( $excludes ) ) {
				$excludes = array();
			}

			$tracking_scripts = array(
				'wffnEvents',
				'wffn-public',
				'wffn-tracking',
				'funnel-builder',
			);

			foreach ( $tracking_scripts as $script ) {
				if ( ! in_array( $script, $excludes, true ) ) {
					$excludes[] = $script;
				}
			}

			return $excludes;
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_LiteSpeed_Tracking_Compatibility(), 'wffn_litespeed_tracking' );
}
