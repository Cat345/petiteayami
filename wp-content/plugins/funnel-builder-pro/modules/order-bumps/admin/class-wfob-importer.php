<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFOB_Importer' ) ) {
	/**
	 * Class WFOB_Importer
	 * Handles Importing of Order Bumbs Export JSON file
	 */
	class WFOB_Importer {

		private static $ins = null;
		public $is_imported = false;

		public function __construct() {
			if ( isset( $_POST['wfob-action'] ) && 'import' === $_POST['wfob-action'] ) {
				add_action( 'admin_init', [ $this, 'maybe_import' ] );
			}
		}

		/**
		 * @return WFFN_Admin|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Import our exported file
		 *
		 * @since 1.1.4
		 */
		function maybe_import() {

			if ( ! wp_verify_nonce( $_POST['wfob-action-nonce'], 'wfob-action-nonce' ) ) {
				return;
			}

			$user = WFOB_Core()->role->user_access( 'bump', 'write' );
			if ( false === $user ) {
				return;
			}

			$filename  = $_FILES['file']['name'];
			$file_info = explode( '.', $filename );
			$extension = end( $file_info );

			if ( 'json' != $extension ) {
				wp_die( __( 'Please upload a valid .json file', 'woofunnels-order-bump' ) );
			}

			$file = $_FILES['file']['tmp_name'];

			if ( empty( $file ) ) {
				wp_die( __( 'Please upload a file to import', 'woofunnels-order-bump' ) );
			}

			// Retrieve the settings from the file and convert the JSON object to an array.
			$bumps = json_decode( file_get_contents( $file ), true );

			$this->import_from_json_data( $bumps );

			$this->is_imported = true;
		}

		public function import_from_json_data( $bumps ) {
			$imported_bumps = [];

			foreach ( $bumps as $bump ) {
				$bump_id = 0;
				if ( isset( $bump['id'] ) && ! empty( $bump['id'] ) ) {
					$bump_id = $bump['id'];
				}

				$bump_post = null;
				if ( $bump_id !== 0 ) {
					$bump_post = get_post( $bump_id );
				}


				$bump_title = $bump['title'];
				if ( null !== $bump_post && $bump_title === $bump_post->post_title ) {
					$bump_title = $bump_title . ' Copy';
				}

				$settings       = $bump['settings'];
				$bump_post_args = array(
					'post_title'  => $bump_title,
					'post_type'   => WFOB_Common::get_bump_post_type_slug(),
					'post_status' => isset( $bump['post_status'] ) ? $bump['post_status'] : 'published',
					'menu_order'  => isset( $settings['priority'] ) ? $settings['priority'] : 0
				);
				$bump_id        = wp_insert_post( $bump_post_args );
				$products       = isset( $bump['products'] ) ? $bump['products'] : '';


				/**
				 * Here we are validating products to check if a product existing in the current site
				 */
				if ( ! empty( $products ) ) {
					foreach ( $products as $key => $product ) {
						$product_obj = wc_get_product( $product['id'] );
						if ( ! $product_obj instanceof WC_Product ) {
							unset( $products[ $key ] );
						}
					}
				}

				if ( is_array( $bump['design_data'] ) && count( $bump['design_data'] ) > 0 ) {

					$bump['design_data']=WFOB_Common::check_default_bump_keys($bump['design_data']);

				}


				if ( $bump_id !== 0 ) {
					update_post_meta( $bump_id, '_wfob_settings', $bump['settings'] );
					update_post_meta( $bump_id, '_wfob_rules', $bump['rules'] );
					update_post_meta( $bump_id, '_wfob_is_rules_saved', 'yes' );
					update_post_meta( $bump_id, '_wfob_design_data', $bump['design_data'] );
					update_post_meta( $bump_id, '_wfob_selected_products', $products );

					$imported_bumps[] = $bump_id;
				}
			}

			return $imported_bumps;
		}


	}


	if ( class_exists( 'WFOB_Core' ) ) {
		WFOB_Core::register( 'import', 'WFOB_Importer' );
	}
}