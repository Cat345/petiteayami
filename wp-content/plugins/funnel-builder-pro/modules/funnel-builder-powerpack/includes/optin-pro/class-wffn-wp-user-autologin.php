<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_WP_User_AutoLogin' ) ) {
	#[AllowDynamicProperties]
	class WFFN_WP_User_AutoLogin {


		private static $ins = null;
		const WP_USER_AUTOLOGIN_KEY = '_bwf_wp_autologin_key';
		const WP_USER_AUTOLOGIN_URL_PARAM = '_bwf_login_key';
		const WP_USER_AUTOLOGIN_TIME = '_bwf_wp_autologin_time';
		public $user_id;

		public function __construct() {
			add_action( 'init', array( $this, 'maybe_autologin_user' ) );
			/**
			 * This shortcode no longer shown in admin area but placed for backward compatibility
			 */
			add_shortcode( 'bwf_autologin_link', array( $this, 'shortcode' ) );
			add_shortcode( 'wfop_ld_course_autologin_link', array( $this, 'shortcode' ) );
			add_shortcode( 'wfop_lifter_course_autologin_link', array( $this, 'shortcode' ) );
			add_action( 'login_head', array( $this, 'autologin_extract_login_link_error' ) );
		}

		/**
		 * @return WFFN_WP_User_AutoLogin|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function set_user_id( $user_id = 0 ) {
			if ( ! empty( $user_id ) ) {
				$this->user_id = $user_id;
			}
		}

		public function get_autologin_link( $user_id, $return_url = '' ) {

			$get_saved_hash = get_user_meta( $user_id, self::WP_USER_AUTOLOGIN_KEY, true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
			$get_time       = get_user_meta( $user_id, self::WP_USER_AUTOLOGIN_TIME, true );//phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta

			if ( empty( $get_saved_hash ) || empty( $get_time ) ) {
				$get_saved_hash = $this->generate_autologin_key();
				$this->save_autologin_link( $user_id, $get_saved_hash );
			}
			$url = home_url( '?' . self::WP_USER_AUTOLOGIN_URL_PARAM . "=$get_saved_hash" );
			if ( ! empty( $return_url ) ) {
				$url .= '&return_url=' . $return_url;
			}

			return $url;

		}

		public function save_autologin_link( $user_id, $generated_code ) {
			update_user_meta( $user_id, self::WP_USER_AUTOLOGIN_KEY, $generated_code );//phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta
			update_user_meta( $user_id, self::WP_USER_AUTOLOGIN_TIME, time() );//phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta
		}

		public function generate_autologin_key() {


			if ( function_exists( 'bwf_generate_random_bytes' ) ) {
				// Workaround: first value seems to always be zero, so we will skip the first value
				$random_ints = unpack( "L*", bwf_generate_random_bytes( 4 * ( 32 + 1 ) ) );
			} else {
				require_once ABSPATH . 'wp-includes/class-phpass.php';
				$hasher = new PasswordHash( 8, true ); // The PasswordHasher has a php-version independent "safeish" random generator

				// Workaround: first value seems to always be zero, so we will skip the first value
				$random_ints = unpack( "L*", $hasher->get_random_bytes( 4 * ( 32 + 1 ) ) );
			}


			$char_count      = strlen( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );
			$new_code        = "";
			$_str_copy_php55 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			for ( $i = 0; $i < 32; $i ++ ) {
				$new_code = $new_code . $_str_copy_php55[ $random_ints[ $i + 1 ] % $char_count ];
			}

			return $new_code;
		}

		public function maybe_autologin_user() {
			global $wpdb;

			// Check if autologin link is specified - if there is one the work begins
			if ( isset( $_GET[ self::WP_USER_AUTOLOGIN_URL_PARAM ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$autologin_code = preg_replace( '/[^a-zA-Z0-9]+/', '', wffn_clean( $_GET[ self::WP_USER_AUTOLOGIN_URL_PARAM ] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( $autologin_code ) { // Check if not empty
					// Get part left of ? of the request URI for resassembling the target url later


					$userIds = array();
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value as login_code FROM $wpdb->usermeta WHERE meta_key = %s and meta_value = %s;", self::WP_USER_AUTOLOGIN_KEY, $autologin_code ), ARRAY_A ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__usermeta
					if ( $results === null ) {
						wp_die( "Query failed!" );
					}
					foreach ( $results as $row ) {
						if ( $row["login_code"] === $autologin_code ) {
							$userIds[] = $row["user_id"];
						}
					}

					if ( count( $userIds ) > 1 ) {
						wp_die( "Please login normally - this is a statistic bug and prevents you from using login links securely!" );
					}

					// Only login if there is only ONE possible user
					if ( count( $userIds ) === 1 ) {
						$userToLogin = get_user_by( 'id', (int) $userIds[0] );

						// Check if user exists
						if ( $userToLogin ) {
							$get_time = get_user_meta( $userToLogin->ID, self::WP_USER_AUTOLOGIN_TIME, true );//phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta

							if ( ( time() - $get_time ) > DAY_IN_SECONDS ) {
								wp_redirect( home_url( 'wp-login.php?wffn_autologin_error=invalid_login_code' ) );
								exit;
							}

							wp_set_auth_cookie( $userToLogin->ID, false );
							do_action( 'wp_login', $userToLogin->name, $userToLogin );
							$get_redirect_param = filter_input( INPUT_GET, 'return_url', FILTER_UNSAFE_RAW );

							// Create redirect URL without autologin code
							$GETQuery = $this->autologin_generate_get_postfix();
							// Augment my solution with https://stackoverflow.com/questions/1907653/how-to-force-page-not-to-be-cached-in-php
							header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" );
							header( "Cache-Control: no-cache, no-store, must-revalidate, private, max-age=0, s-maxage=0" );
							header( "Cache-Control: post-check=0, pre-check=0", false );
							header( "Pragma: no-cache" );
							header( "Expires: Mon, 01 Jan 1990 01:00:00 GMT" );

							if ( empty( $get_redirect_param ) ) {
								$get_redirect_param = home_url();
							}
							wp_redirect( $get_redirect_param . $GETQuery );
							exit;
						}
					}

				}

				// If something went wrong send the user to login-page (and log the old user out if there was any)
				wp_logout();
				wp_redirect( home_url( 'wp-login.php?wffn_autologin_error=invalid_login_code' ) );
				exit;
			}
		}

		public function autologin_extract_login_link_error() {
			// Ensure the global errors object is initialized

			try {
				if ( isset( $GLOBALS['errors'] ) && ! is_null( $GLOBALS['errors'] ) ) {
					$errors = $GLOBALS['errors'];
				} else {
					$errors = new WP_Error();
				}
				if ( isset( $_GET['wffn_autologin_error'] ) ) {
					$rawMsg = sanitize_text_field( $_GET['wffn_autologin_error'] );

					// Check if valid autologin_error
					if ( in_array( $rawMsg, array( 'invalid_login_code' ), true ) ) {
						// Add error texts based on the error code
						switch ( $rawMsg ) {
							case 'invalid_login_code':
								$errors->add( "invalid_autologin_link", __( "Invalid autologin link.", 'funnel-builder-powerpack' ) );
								break;
						}
					}
				}// Set errors globally for login
				if ( ! empty( $errors->get_error_codes() ) ) {
					$GLOBALS['errors'] = $errors;
				}
			} catch ( Exception|Error $e ) {

			}
		}


		public function autologin_generate_get_postfix() {
			$GETcopy = $_GET; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $GETcopy[ self::WP_USER_AUTOLOGIN_URL_PARAM ] );
			unset( $GETcopy['return_url'] );
			$GETQuery = $this->autologin_join_get_parameters( $GETcopy );
			if ( strlen( $GETQuery ) > 0 ) {
				$GETQuery = '?' . $GETQuery;
			}

			return $GETQuery;
		}

		public function autologin_join_get_parameters( $parameters ) {
			$keys        = array_keys( $parameters );
			$assignments = array();
			foreach ( $keys as $key ) {
				$assignments[] = rawurlencode( $key ) . "=" . rawurlencode( $parameters[ $key ] );
			}

			return implode( '&', $assignments );
		}

		public function shortcode( $args = [] ) {

			if ( empty( $this->user_id ) ) {

				if ( is_user_logged_in() ) {
					$this->set_user_id( get_current_user_id() );
				} else {
					return site_url();
				}

			}
			$callback = isset( $args['callback'] ) ? $args['callback'] : apply_filters( 'bwf_auto_login_redirect', '' );
			if ( ! empty( $callback ) ) {
				$get_link = $this->get_autologin_link( $this->user_id, $callback );
			} else {

				$get_link = $this->get_autologin_link( $this->user_id );
			}

			return $get_link;

		}
	}

	WFOPP_Core()->autologin = WFFN_WP_User_AutoLogin::get_instance();
}

