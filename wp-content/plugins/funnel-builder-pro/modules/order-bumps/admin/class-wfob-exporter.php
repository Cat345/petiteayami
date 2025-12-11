<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFOB_Exporter' ) ) {
	/**
	 * Class WFOB_Exporter
	 * Handles Exporting of Order Bumbs into JSON Downloadable File
	 */
	class WFOB_Exporter {

		private static $ins = null;

		public function __construct() {
			add_action( 'admin_init', [ $this, 'maybe_export' ] );
			add_action( 'admin_init', [ $this, 'maybe_export_single' ] );
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

		public function maybe_export( $posted_data = null ) {
			$skip_nonce = false;

			if ( null == $posted_data ) {
				$posted_data = bwf_clean( $_POST );
			} else {
				$skip_nonce = true;
			}

			if ( empty( $posted_data['wfob-action'] ) || 'export' != $posted_data['wfob-action'] ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['wfob-action-nonce'], 'wfob-action-nonce' ) && false === $skip_nonce ) {
				return;
			}

			$user = WFOB_Core()->role->user_access( 'bump', 'write' );
			if ( false === $user ) {
				return;
			}

			$args = array(
				'post_type'      => WFOB_Common::get_bump_post_type_slug(),
				'post_status'    => 'any',
				'posts_per_page' => - 1,
			);

			$query_result = new WP_Query( $args );
			$bump_posts   = [];
			if ( $query_result instanceof WP_Query && $query_result->have_posts() ) {
				$bump_posts = $query_result->posts;
			}

			$bumps_to_export = [];
			foreach ( $bump_posts as $post_key => $post ) {
				$bumps_to_export[ $post_key ] = $this->get_bump_array_for_json( $post->ID );
			}

			$bumps_to_export = apply_filters( 'wfob_export_data', $bumps_to_export );

			nocache_headers();

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=wfob-funnels-export-' . date( 'm-d-Y' ) . '.json' );
			header( 'Expires: 0' );

			echo wp_json_encode( $bumps_to_export );
			exit;
		}

		public function get_bump_array_for_json( $id ) {
			$id   = absint( $id );
			$post = get_post( $id );
			if ( is_null( $post ) ) {
				return;
			}

			$bump_json                = [];
			$bump_json['id']          = $id;
			$bump_json['title']       = get_the_title( $id );
			$bump_json['post_status'] = $post->post_status;

			$selected_products        = WFOB_Common::get_bump_products( $id );
			$bump_json['design_data'] = WFOB_Common::get_design_data_meta( $id );
			if ( empty( $bump_json['design_data'] ) ) {
				$bump_json['design_data'] = [];
			}

			$design_json_data = json_encode( $bump_json['design_data'] );
			if ( is_array( $selected_products ) && count( $selected_products ) > 0 ) {
				$products = [];
				foreach ( $selected_products as $key => $values ) {
					$unique_key = uniqid( 'wfob_' );
					if ( ! empty( $design_json_data ) ) {
						$design_json_data = str_replace( $key, $unique_key, $design_json_data );
					}
					$products[ $unique_key ] = $values;
				}
				$bump_json['products'] = $products;
			}

			$bump_json['design_data'] = json_decode( $design_json_data, true );

			$bump_json['settings'] = get_post_meta( $id, '_wfob_settings', true );
			if ( ! is_array( $bump_json['settings'] ) ) {
				$bump_json['settings'] = [];
			}


			$bump_json['rules'] = get_post_meta( $id, '_wfob_rules', true );
			if ( ! is_array( $bump_json['rules'] ) || empty( $bump_json['rules'] ) ) {
				$bump_json['rules'] = [];
			}

			return $bump_json;
		}

		public function maybe_export_single( $posted_data = null ) {
			$skip_nonce = false;

			if ( null == $posted_data ) {
				$posted_data = bwf_clean( $_POST );
			} else {
				$skip_nonce = true;
			}

			if ( empty( $posted_data['action'] ) || 'wfob-export' != $posted_data['action'] ) {
				return;
			}

			if ( ! wp_verify_nonce( $posted_data['_wpnonce'], 'wfob-export' ) && false === $skip_nonce ) {
				return;
			}

			$user = WFOB_Core()->role->user_access( 'bump', 'write' );
			if ( false === $user ) {
				return;
			}

			$bumps_to_export    = [];
			$bumps_to_export[0] = $this->get_bump_array_for_json( $posted_data['id'] );
			$bumps_to_export    = apply_filters( 'wfob_export_data', $bumps_to_export );

			nocache_headers();

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=wfob-funnels-export-' . date( 'm-d-Y' ) . '.json' );
			header( 'Expires: 0' );

			echo wp_json_encode( $bumps_to_export );
			exit;


		}
	}


	if ( class_exists( 'WFOB_Core' ) ) {
		WFOB_Core::register( 'export', 'WFOB_Exporter' );
	}
}