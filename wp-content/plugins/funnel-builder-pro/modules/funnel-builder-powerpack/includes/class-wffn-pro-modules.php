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
				if ( self::is_bump_posts_exists() ) {
					$modules['bump'] = 'yes';
				}
				if ( self::is_checkout_posts_exists() ) {
					$modules['checkout'] = 'yes';
				}
				if ( self::is_upsell_posts_exists() ) {
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

		public static function is_bump_posts_exists() {

			$get_posts               = array( 'post_type' => 'wfob_bump', 'posts_per_page' => 1 );
			$get_posts['meta_query'] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_bwf_in_funnel',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
			);
			$query_res               = new WP_Query( $get_posts );

			if ( is_object( $query_res ) && 0 < $query_res->found_posts ) {
				return true;
			}

			return false;
		}

		public static function is_checkout_posts_exists() {

			$get_posts               = array( 'post_type' => 'wfacp_checkout', 'posts_per_page' => 1 );
			$get_posts['meta_query'] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_bwf_in_funnel',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
			);
			$query_res               = new WP_Query( $get_posts );
			if ( is_object( $query_res ) && 0 < $query_res->found_posts ) {
				return true;
			}

			return false;
		}

		public static function is_upsell_posts_exists() {

			$get_posts               = array( 'post_type' => 'wfocu_funnel', 'posts_per_page' => 1 );
			$get_posts['meta_query'] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_bwf_in_funnel',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
			);
			$query_res               = new WP_Query( $get_posts );
			if ( is_object( $query_res ) && 0 < $query_res->found_posts ) {
				return true;
			}

			return false;
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