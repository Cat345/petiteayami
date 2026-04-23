<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_WP_Grid_Builder_Multilingual' ) ) {
	class WFOCU_Compatibility_With_WP_Grid_Builder_Multilingual {

		public function __construct() {
			// Only initialize if both plugins are active
			if ( ! $this->is_enable() ) {
				return;
			}

			// Hook into upsell template load to disable wp-grid-builder-multilingual
			add_action( 'wfocu_front_init_funnel_hooks', [ $this, 'disable_wp_grid_builder_multilingual' ], 1 );
		
		}

		/**
		 * Check if both wp-grid-builder-multilingual and WPML are active
		 */
		public function is_enable() {
			return defined( 'ICL_SITEPRESS_VERSION' ) && 
				   class_exists( 'WP_Grid_Builder_Multilingual\Includes\Translate' );
		}

		/**
		 * Disable wp-grid-builder-multilingual during upsell processing
		 */
		public function disable_wp_grid_builder_multilingual() {
			// Remove ALL filters for this hook completely
			remove_all_filters( 'wp_grid_builder/indexer/term_query_args' );
			
		}

	}

	// Register the compatibility class
	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WP_Grid_Builder_Multilingual(), 'wp_grid_builder_multilingual' );
}
