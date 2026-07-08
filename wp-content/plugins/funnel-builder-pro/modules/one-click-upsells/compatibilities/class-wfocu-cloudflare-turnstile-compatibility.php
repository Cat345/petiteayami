<?php

/**
 * Cloudflare Turnstile compatibility for WFOCU
 *
 * @package WFOCU
 * @since   1.0.0
 */
if ( ! class_exists( 'WFOCU_Cloudflare_Turnstile_Compatibility' ) ) {
	/**
	 * WFOCU Cloudflare Turnstile Compatibility Class
	 *
	 * Handles compatibility between WFOCU and Cloudflare Turnstile plugin
	 * by bypassing Turnstile validation during WFOCU customer creation.
	 *
	 * @since 1.0.0
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Cloudflare_Turnstile_Compatibility {

		/**
		 * Flag to track if WFOCU is currently creating a customer
		 *
		 * @var bool
		 */
		private $wfocu_creating_customer = false;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			try {
				if ( $this->is_enable() ) {
					add_action( 'wfocu_before_create_new_customer', array( $this, 'set_wfocu_customer_creation_flag' ), 10, 2 );
					add_filter( 'woocommerce_registration_errors', array( $this, 'bypass_turnstile_for_wfocu_wp_registration' ), 9999, 3 );
				}
			} catch ( Throwable $e ) {
				// Silently fail to prevent breaking main functionality
			}
		}

		/**
		 * Check if Cloudflare Turnstile plugin is active
		 */
		public function is_enable() {
			try {
				$is_enabled = function_exists( 'cfturnstile_check' );

				return $is_enabled;
			} catch ( Throwable $e ) {
				return false;
			}
		}

		/**
		 * Set a flag when WFOCU is creating a new customer
		 * This allows us to bypass Turnstile validation during WFOCU customer creation
		 *
		 * @param string         $email
		 * @param WC_Order|false $order
		 */
		public function set_wfocu_customer_creation_flag( $email, $order ) {
			$this->wfocu_creating_customer = true;
		}

		/**
		 * Bypass Turnstile validation for WordPress registration when called from WFOCU
		 * This prevents the "missing-input-response" error when WFOCU creates new customers via WordPress registration
		 *
		 * @param WP_Error $errors
		 * @param string   $sanitized_user_login
		 * @param string   $user_email
		 * @return WP_Error
		 */
		public function bypass_turnstile_for_wfocu_wp_registration( $errors, $sanitized_user_login, $user_email ) {
			try {
				// Check if this is a WFOCU customer creation using our class property.
				if ( $this->wfocu_creating_customer ) {
					// Remove any existing Turnstile errors.
					$errors->remove( 'cfturnstile_error' );

					// Reset the flag.
					$this->wfocu_creating_customer = false;
				}
			} catch ( Throwable $e ) {
				// Silently fail to prevent breaking user registration.
				// This ensures WFOCU customer creation continues even if compatibility fails.
			}

			return $errors;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Cloudflare_Turnstile_Compatibility(), 'wfocu_cloudflare_turnstile_compatibility' );
}
