<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Funnel Modules facing functionality
 * Class WFFN_Pro_Modules
 */
if ( ! class_exists( 'WFFN_Pro_Modules' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Modules {

		public static $modules = [];

		public static function init_modules() {

			add_action( 'admin_init', array( __CLASS__, 'update_modules' ) );

			foreach ( glob( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'modules/*.php' ) as $module_name ) {
				$basename = basename( $module_name );
				if ( false !== strpos( $basename, 'index.php' ) ) {
					continue;
				}
				require_once( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'modules/' . $basename );
			}
		}

		public static function update_modules() {
			$modules = get_option( '_bwf_individual_modules', [] );


			if ( empty( $modules ) ) {
				$modules = array(
					'bump'     => 'no',
					'checkout' => 'no',
					'upsells'  => 'no',
				);

				$existing_types = self::get_standalone_post_types();

				if ( ! empty( $existing_types['wfob_bump'] ) ) {
					$modules['bump'] = 'yes';
				}
				if ( ! empty( $existing_types['wfacp_checkout'] ) ) {
					$modules['checkout'] = 'yes';
				}
				if ( ! empty( $existing_types['wfocu_funnel'] ) ) {
					$modules['upsells'] = 'yes';
				}


				update_option( '_bwf_individual_modules', $modules, true );

			}

			if ( ! isset( $modules['ab_tests'] ) ) {
				$modules['ab_tests'] = 'no';
				if ( self::is_ab_experiment_exists_for_non_funnel() ) {
					$modules['ab_tests'] = 'yes';
				}
				update_option( '_bwf_individual_modules', $modules, true );
			}

		}

		/**
		 * Single consolidated query to check for standalone posts across all 3 post types.
		 * Replaces 3 separate WP_Query calls with one, reducing DB queries on admin_init.
		 *
		 * @return array Associative array keyed by post_type with boolean values.
		 */
		public static function get_standalone_post_types() {
			$post_types = array( 'wfob_bump', 'wfacp_checkout', 'wfocu_funnel' );

			$result = array(
				'wfob_bump'      => false,
				'wfacp_checkout' => false,
				'wfocu_funnel'   => false,
			);

			$query_args = array(
				'post_type'      => $post_types,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_bwf_in_funnel',
						'compare' => 'NOT EXISTS',
						'value'   => '',
					),
				),
			);

			$query = new WP_Query( $query_args );

			if ( ! empty( $query->posts ) ) {
				foreach ( $query->posts as $post_id ) {
					$post_type = get_post_type( $post_id );
					if ( isset( $result[ $post_type ] ) ) {
						$result[ $post_type ] = true;
					}
				}
			}

			return $result;
		}

		public static function is_bump_posts_exists() {
			$types = self::get_standalone_post_types();

			return ! empty( $types['wfob_bump'] );
		}

		public static function is_checkout_posts_exists() {
			$types = self::get_standalone_post_types();

			return ! empty( $types['wfacp_checkout'] );
		}

		public static function is_upsell_posts_exists() {
			$types = self::get_standalone_post_types();

			return ! empty( $types['wfocu_funnel'] );
		}

		public static function is_ab_experiment_exists_for_non_funnel() {
			global $wpdb;

			$get_all_controls = $wpdb->get_col( "SELECT control as control_id FROM " . $wpdb->prefix . "bwf_ab_experiments WHERE `type` IN ('upstroke','order_bump','aero') ORDER BY control_id ASC" );

			$funnel_controls = [];
			if ( is_array( $get_all_controls ) && $get_all_controls > 0 ) {
				foreach ( $get_all_controls as $control_id ) {
					$is_control_in_funnel = get_post_meta( $control_id, '_bwf_in_funnel', true );
					if ( $is_control_in_funnel > 0 ) {
						$funnel_controls[] = $control_id;
					}
				}
				if ( count( $get_all_controls ) === count( $funnel_controls ) ) {
					/**
					 * reaching here means we have all the experiments of the funnel steps
					 */
					return false;
				} else {

					return true;
				}
			}

			return false;
		}

		public static function register( $basename, $class ) {
			self::$modules[ $basename ] = $class;
		}

		public static function maybe_load( $basename ) {

			$module = self::get_module( $basename );

			if ( class_exists( $module ) ) {
				$module::maybe_load();
			}
		}

		public static function get_module( $basename ) {
			return self::$modules[ $basename ];
		}


	}

	add_action( 'plugins_loaded', function () {
		WFFN_Pro_Modules::init_modules();
		do_action( 'wffn_pro_modules_loaded' );
	}, - 500 );


}
