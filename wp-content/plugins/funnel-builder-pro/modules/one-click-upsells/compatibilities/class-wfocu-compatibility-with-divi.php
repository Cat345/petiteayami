<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Divi' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Divi
	 */
	class WFOCU_Compatibility_With_Divi {

		public function __construct() {

			add_filter( 'et_builder_enabled_builder_post_type_options', function ( $options ) {
				if ( ! is_array( $options ) ) {
					$options = [];
				}
				$options[ WFOCU_Common::get_offer_post_type_slug() ] = 'on';

				return $options;

			} );
			add_filter( 'wfocu_should_render_script_jquery', array( $this, 'should_prevent_jq_on_editor' ), 10 );
			add_action( 'plugins_loaded', array( $this, 'initialize_deep_integration' ), 2 );
			add_filter( 'wfocu_container_attrs', array( $this, 'add_id_for_wfocu_container' ) );
			add_filter( 'et_builder_add_outer_content_wrap', array( $this, 'maybe_filter' ), 999 );
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_handle_jquery_defer' ), 1 );
		}

		public function is_enable() {
			if ( defined( 'ET_CORE_VERSION' ) ) {
				return true;
			}

			return false;
		}

		public function should_prevent_jq_on_editor( $bool ) {
			if ( isset( $_GET['et_fb'] ) ) {
				return false;
			}

			return $bool;
		}

		public function initialize_deep_integration() {
			/**
			 * Include UpStroke template group for the elementor
			 */
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi/wfocu-template-group-divi.php';
		}

		/**
		 * @param $attrs
		 *
		 * @return mixed
		 */
		public function add_id_for_wfocu_container( $attrs ) {

			$attrs['id'] = 'page-container';

			return $attrs;
		}

		public function maybe_filter( $add_outer_wrap ) {

			global $post;

			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfocu_offer' ) {
				return true;
			}

			return $add_outer_wrap;
		}

		public function maybe_handle_jquery_defer() {
			global $post;

			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfocu_offer' ) {
				add_filter( 'et_builder_enable_jquery_body', '__return_false' );
			}

		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Divi(), 'divi' );
}