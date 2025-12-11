<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFOCU_Session_Handler
 * Works as a Backbone for the Running data during the lifecycle of the user
 * Stores a unique key in the cookie, and then use transients to save data for the same unique key called transient key
 * So in this way that key supposed to be unique for each user session.
 *
 * @uses WooFunnels_Transient
 * @package UpStroke
 * @author UpStroke
 */
if ( ! class_exists( 'WFOCU_Session_Handler' ) ) {
	class WFOCU_Session_Handler {

		private static $ins = null;
		/**
		 * @var null $transient_key
		 */
		public $transient_key = null;
		public $transient_object = null;
		private $default_group = 'funnel';
		private $groups = array( 'funnel', 'orders', 'rules', 'track', 'paypal', 'variations', 'gateway' );
		private $data_groups = array( '_orders' );
		private $_data = array();

		/**
		 * Constructor for the session class.
		 */
		public function __construct() {

			$this->transient_object = WooFunnels_Transient::get_instance();

			add_action( 'init', array( $this, 'load_transient_from_cookie' ), 2 );
			add_action( 'wp', array( $this, 'maybe_pass_no_cache_header' ) );
			if ( null === $this->get_transient_key() ) {
				add_action( 'init', array( $this, 'load_funnel_from_session' ), 6 );
			}
		}


		public static function get_instance() {
			if ( self::$ins === null ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function set_up_orders( $order_id ) {

			return wc_get_order( $order_id );
		}

		public function load_funnel_from_session() {
			/**
			 * If we do not have transient key, we should not setup the data
			 */
			if ( null === $this->get_transient_key() ) {

				return;
			}
			$get_key = $this->get_transient_key();

			$data = $this->transient_object->get_transient( $get_key, 'upstroke-funnel' );

			if ( false === $data ) {
				return;
			}

			foreach ( $this->groups as $group ) {
				$cookie_value = isset( $data[ $group ] ) ? wp_unslash( $data[ $group ] ) : [];

				$cookie_value          = maybe_unserialize( $cookie_value );
				$this->_data[ $group ] = apply_filters( 'wfocu_front_funnel_data', $cookie_value, $group );

				if ( in_array( '_' . $group, $this->data_groups, true ) && $cookie_value && is_array( $cookie_value ) && count( $cookie_value ) > 0 ) {

					foreach ( $cookie_value as $key => $value ) { //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable

						if ( ! is_array( $key ) ) {

							if ( is_callable( array( $this, 'set_up_' . $group ) ) ) {
								$this->_data[ '_' . $group ][ $key ] = call_user_func( array( $this, 'set_up_' . $group ), $this->_data[ $group ][ $key ] );

							}
						}
					}
				}
			}

			do_action( 'wfocu_session_loaded' );

		}

		public function get_transient_key() {
			return $this->transient_key;
		}

		public function set_transient_key() {

			if ( null === $this->transient_key ) {

				$get_hash = $this->generate_transient_key();

				$this->transient_key = $get_hash;
				/**
				 * Serve the transient from the wc_session if exists
				 */

				if ( ! is_null( WC()->session ) && WC()->session->has_session() ) {
					WC()->session->set( '_wfocu_session_id', $get_hash );
				}
				if ( defined( 'DOING_CRON' ) && true === DOING_CRON ) {
					return;
				}
				wc_setcookie( 'wfocu_si', $get_hash, time() + ( DAY_IN_SECONDS * 1 ) );
			}
		}

		public function get_all() {

			return $this->_data;
		}

		/**
		 * Set a session variable.
		 *
		 * @param string $key Key to set.
		 * @param mixed $value Value to set.
		 * @param mixed $group Value to set.
		 */
		public function set( $key, $value, $group = null ) {
			if ( null === $group ) {
				$group = $this->default_group;
			}

			if ( ! isset( $this->_data[ $group ] ) || ! is_array( $this->_data[ $group ] ) ) {

				$this->_data[ $group ] = [];
			}
			if ( $value !== $this->get( $key, null, $group ) ) {

				if ( 0 === strpos( $key, '_' ) ) {
					$this->_data[ $group ][ sanitize_key( $key ) ] = $value;
				} else {
					$this->_data[ $group ][ sanitize_key( $key ) ] = maybe_serialize( $value );

				}
			}

		}

		/**
		 * Get a session variable.
		 *
		 * @param string $key Key to get.
		 * @param mixed $default used if the session variable isn't set.
		 *
		 * @return array|string value of session variable
		 */
		public function get( $key, $default = false, $group = null ) {

			if ( null === $group ) {
				$group = $this->default_group;
			}
			$key = sanitize_key( $key );
			if ( 0 === strpos( $key, '_' ) ) {
				return isset( $this->_data[ $group ][ $key ] ) ? $this->_data[ $group ][ $key ] : $default;

			} else {
				return isset( $this->_data[ $group ][ $key ] ) ? maybe_unserialize( $this->_data[ $group ][ $key ] ) : $default;

			}
		}

		/**
		 * Destroy all session data.
		 */
		public function destroy_session() {

			$get_key = $this->get_transient_key();

			/**
			 * destroying the session means delete the respective transient.
			 * reset the value of transient key in the class object
			 * Unset the cookie in the current environment
			 */
			$this->transient_object->delete_transient( $get_key, 'upstroke-funnel' );

			$this->transient_key = null;

			if ( ! is_null( WC()->session ) && WC()->session->has_session() ) {
				WC()->session->set( '_wfocu_session_id', '' );
			}
			if ( ! empty( $get_key ) ) {
				WFOCU_Core()->log->log( 'Clearing session for ' . $get_key );
			}

			if ( defined( 'DOING_CRON' ) && true === DOING_CRON ) {
				return;
			}
			wc_setcookie( 'wfocu_si', '', time() - DAY_IN_SECONDS );

		}

		public function save( $group = null ) {

			if ( null === $group ) {
				$group = $this->default_group;
			}
			$clean_data = array();
			if ( ! is_array( $this->_data[ $group ] ) ) {
				return;
			}
			foreach ( $this->_data[ $group ] as $key => $data ) {

				if ( 0 === strpos( $key, '_' ) ) {
					continue;
				}
				$clean_data[ $key ] = $data;
			}

			$this->set_transient_key();

			$existing = $this->transient_object->get_transient( $this->transient_key, 'upstroke-funnel' );

			if ( false === is_array( $existing ) ) {
				$existing = array();
			}

			$existing[ $group ] = $clean_data;

			$this->transient_object->set_transient( $this->transient_key, $existing, HOUR_IN_SECONDS * 24, 'upstroke-funnel' );

		}

		public function maybe_load_from_transient() {

			if ( is_array( $this->_data ) && count( $this->_data ) > 0 ) {
				foreach ( $this->_data as $group => $data ) { //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable

					if ( isset( $this->_data[ $group ] ) && is_array( $this->_data[ $group ] ) && isset( $this->_data[ $group ]['transient_key'] ) ) {

						$get_transient_instance = WooFunnels_Transient::get_instance();

						$get_transient_data    = $get_transient_instance->get_transient( $this->_data[ $group ]['transient_key'], 'upstroke' );
						$this->_data[ $group ] = wp_parse_args( $get_transient_data, $this->_data[ $group ] );
					}
				}
			}

		}

		public function load_transient_from_cookie() {
			$cookie_value = '';
			if ( isset( $_GET['wfocu-si'] ) ) {   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$cookie_value = wc_clean( $_GET['wfocu-si'] );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			} elseif ( WFOCU_AJAX_Controller::is_wfocu_front_ajax() ) {
				$cookie_value = isset( $_POST['wfocu-si'] ) ? wc_clean( $_POST['wfocu-si'] ) : false;  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			/**
			 * Serve the transient from the wc_session if exists
			 */

			if ( empty( $cookie_value ) && ! is_null( WC()->session ) && WC()->session->has_session() ) {

				$cookie_value = WC()->session->get( '_wfocu_session_id', '' );
			}

			if ( empty( $cookie_value ) ) {
				$cookie_value = isset( $_COOKIE['wfocu_si'] ) ? wc_clean( $_COOKIE['wfocu_si'] ) : false; //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			}
			if ( $cookie_value && $cookie_value !== false && '' !== $cookie_value ) {
				$this->transient_key = $cookie_value;
			}
		}

		public function generate_transient_key() {
			if ( function_exists( 'bwf_generate_random_bytes' ) ) {
				return md5( bwf_generate_random_bytes( 32 ) );
			} else {
				require_once ABSPATH . 'wp-includes/class-phpass.php';
				$hasher = new PasswordHash( 8, false );

				return md5( $hasher->get_random_bytes( 32 ) );
			}

		}

		public function maybe_pass_no_cache_header() {
			global $post;
			$maybe_offer = WFOCU_Core()->offers->get_offer_from_post( $post );

			if ( $maybe_offer ) {
				add_action( 'wp_head', array( $this, 'no_index_offers' ) );
				$this->set_nocache_constants();
				nocache_headers();
			}
		}

		/**
		 * @param $value
		 *
		 * @return mixed
		 */
		public function set_nocache_constants() {
			$this->maybe_define_constant( 'DONOTCACHEPAGE', true );
			$this->maybe_define_constant( 'DONOTCACHEOBJECT', true );
			$this->maybe_define_constant( 'DONOTCACHEDB', true );

			return null;
		}

		function maybe_define_constant( $name, $value ) {
			if ( ! defined( $name ) ) { //phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantName
				define( $name, $value ); //phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantName
			}
		}

		public function no_index_offers() {

			if ( true === apply_filters( 'wfocu_no_index_pages_by_upstroke', true ) ) {
				echo '<meta name="robots" content="noindex,nofollow">';
			}

		}

		/**
		 * detect whether we have a valid session running
		 * @return bool
		 * @since 2.0
		 */
		public function has_valid_session() {
			/**
			 * if called before init then we might not have any valid session
			 */

			if ( 0 < did_action( 'wfocu_session_loaded' ) ) {
				return true;
			}

			return false;

		}


	}

}
