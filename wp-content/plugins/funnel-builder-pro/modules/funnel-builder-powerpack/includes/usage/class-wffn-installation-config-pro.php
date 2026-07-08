<?php
	/**
	 * Pro Installation & Configuration Tracking
	 *
	 * Collects Pro-specific keys related to Pro installation and configuration
	 * Includes settings from One Click Upsells, Order Bumps, Optin, and Checkout modules
	 *
	 * @package FunnelKit Funnel Builder Pro
	 * @since 3.13.3
	 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Installation_Config_Pro' ) ) {

	/**
	 * Class WFFN_Installation_Config_Pro
	 */
	#[\AllowDynamicProperties]
	class WFFN_Installation_Config_Pro {

		/**
		 * Get setting value with fallback to direct option access
		 * This ensures settings can be retrieved even if BWF_Admin_General_Settings class is not available
		 *
		 * @param string $key Setting key
		 * @param mixed  $default Default value if not found
		 *
		 * @return mixed Setting value or default
		 */
		private function get_setting( $key, $default = false ) {
			// Try using BWF_Admin_General_Settings class first (preferred method)
			if ( class_exists( 'BWF_Admin_General_Settings' ) ) {
				try {
					$settings = BWF_Admin_General_Settings::get_instance();
					if ( $settings && method_exists( $settings, 'get_option' ) ) {
						$value = $settings->get_option( $key );
						if ( false !== $value ) {
							return $value;
						}
					}
				} catch ( Exception $e ) {
					// Fall through to direct option access
				}
			}

			// Fallback: Access settings directly from database
			$all_settings = get_option( 'bwf_gen_config', array() );
			if ( is_array( $all_settings ) && isset( $all_settings[ $key ] ) ) {
				return $all_settings[ $key ];
			}

			return $default;
		}

		/**
		 * Get option value directly from database (for module-specific settings)
		 *
		 * @param string $option_name Option name
		 * @param mixed  $default Default value
		 *
		 * @return mixed Option value or default
		 */
		private function get_option( $option_name, $default = array() ) {
			$value = get_option( $option_name, $default );
			return is_array( $value ) ? $value : $default;
		}

		/**
		 * Check if a setting is enabled (for checkbox/string values)
		 *
		 * @param mixed $value Setting value
		 *
		 * @return bool
		 */
		private function is_enabled( $value ) {
			return ! empty( $value ) && ( '1' === $value || 'yes' === $value || true === $value || 'true' === $value );
		}

		/**
		 * Get list of currently available WooCommerce gateways (fallback method)
		 * Returns only gateways that are enabled in WooCommerce settings
		 *
		 * @return array Array of gateway IDs
		 */
		private function get_available_wc_gateways() {
			$available_gateways = array();

			// Only proceed if WooCommerce is available
			if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
				return $available_gateways;
			}

			try {
				$wc_gateways = WC()->payment_gateways->payment_gateways();
				if ( ! empty( $wc_gateways ) && is_array( $wc_gateways ) ) {
					foreach ( $wc_gateways as $gateway_id => $gateway ) {
						// Only include gateways that are enabled
						if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
							$available_gateways[] = $gateway_id;
						}
					}
				}
			} catch ( Exception $e ) {
				// Return empty array if there's an error
				return array();
			}

			return $available_gateways;
		}

		/**
		 * Collect Pro installation and configuration data
		 *
		 * @return array
		 */
		public function collect() {
			$data = array();

			// Note: Version is not collected here as it's already tracked in active_plugins array

			// Pro Settings - Group Pro-specific settings together
			$pro_settings                                 = array();
			$track_utms                                   = $this->get_setting( 'track_utms', false );
			$pro_settings['first_party_tracking_enabled'] = $this->is_enabled( $track_utms );
			if ( ! empty( $pro_settings ) ) {
				$data['pro_settings'] = $pro_settings;
			}

			// One Click Upsells Settings (wfocu_global_settings) - Group all upsell settings together
			$wfocu_settings  = $this->get_option( 'wfocu_global_settings', array() );
			$upsell_settings = array();

			if ( ! empty( $wfocu_settings ) ) {
				// Gateways enabled (array of gateway IDs) - Only include currently available gateways
				$saved_gateways = isset( $wfocu_settings['gateways'] ) && is_array( $wfocu_settings['gateways'] ) ? $wfocu_settings['gateways'] : array();

				// Get list of currently available/supported gateways (same logic as admin interface)
				// This ensures we only track gateways that are visible on the screen
				$available_gateways = array();

				// Try to get gateways list from WFOCU (preferred method - matches admin interface exactly)
				if ( class_exists( 'WFOCU_Core' ) ) {
					try {
						$wfocu_core = WFOCU_Core();
						if ( is_object( $wfocu_core ) && isset( $wfocu_core->gateways ) && is_object( $wfocu_core->gateways ) ) {
							if ( method_exists( $wfocu_core->gateways, 'get_gateways_list' ) ) {
								$gateways_list = $wfocu_core->gateways->get_gateways_list();
								if ( ! empty( $gateways_list ) && is_array( $gateways_list ) ) {
									$available_gateways = wp_list_pluck( $gateways_list, 'value' );
								}
							}
						}
					} catch ( Exception $e ) {
						// Fall through to fallback method
					} catch ( Error $e ) {
						// Fall through to fallback method (PHP 7+)
					}
				}

				// Fallback: If WFOCU method didn't work or returned empty, check WooCommerce directly
				// This ensures we still filter even if WFOCU_Core is not available
				if ( empty( $available_gateways ) ) {
					$available_gateways = $this->get_available_wc_gateways();
				}

				// Filter saved gateways to only include those that are currently available
				// This removes gateways that were previously enabled but are no longer available (e.g., Mollie plugin deactivated)
				if ( ! empty( $available_gateways ) && ! empty( $saved_gateways ) ) {
					$saved_gateways = array_filter(
						$saved_gateways,
						function ( $gateway ) use ( $available_gateways ) {
							return in_array( $gateway, $available_gateways, true );
						}
					);
					$saved_gateways = array_values( $saved_gateways ); // Re-index array
				} elseif ( empty( $available_gateways ) ) {
					// If we can't determine available gateways, return empty array to avoid tracking stale data
					$saved_gateways = array();
				}

				$upsell_settings['gateways_enabled'] = array_values( $saved_gateways ); // Return array of currently available gateway IDs

				// Test gateway enabled
				$test_gateway                            = isset( $wfocu_settings['gateway_test'] ) && is_array( $wfocu_settings['gateway_test'] ) ? $wfocu_settings['gateway_test'] : array();
				$upsell_settings['test_gateway_enabled'] = ! empty( $test_gateway );

				// PayPal reference transactions
				$upsell_settings['paypal_ref_transactions'] = $this->is_enabled( isset( $wfocu_settings['paypal_ref_trans'] ) ? $wfocu_settings['paypal_ref_trans'] : '' );

				// SEPA gateway enabled
				$upsell_settings['sepa_gateway_enabled'] = $this->is_enabled( isset( $wfocu_settings['sepa_gateway_trans'] ) ? $wfocu_settings['sepa_gateway_trans'] : '' );

				// TTL funnel (timeout in minutes)
				$upsell_settings['ttl_funnel_minutes'] = isset( $wfocu_settings['ttl_funnel'] ) ? absint( $wfocu_settings['ttl_funnel'] ) : 0;

				// Email settings
				$upsell_settings['email_on_start']       = isset( $wfocu_settings['send_processing_mail_on'] ) && 'start' === $wfocu_settings['send_processing_mail_on'];
				$upsell_settings['email_on_end']         = isset( $wfocu_settings['send_processing_mail_on'] ) && 'end' === $wfocu_settings['send_processing_mail_on'];
				$upsell_settings['email_no_batch_start'] = isset( $wfocu_settings['send_processing_mail_on_no_batch'] ) && 'start' === $wfocu_settings['send_processing_mail_on_no_batch'];
				$upsell_settings['email_no_batch_end']   = isset( $wfocu_settings['send_processing_mail_on_no_batch_cancel'] ) && 'end' === $wfocu_settings['send_processing_mail_on_no_batch_cancel'];

				// Treat variable as simple
				$upsell_settings['treat_variable_as_simple'] = isset( $wfocu_settings['treat_variable_as_simple'] ) ? $this->is_enabled( $wfocu_settings['treat_variable_as_simple'] ) : false;

				// Enable logging
				$upsell_settings['enable_logging'] = isset( $wfocu_settings['enable_log'] ) ? $this->is_enabled( $wfocu_settings['enable_log'] ) : false;
			}

			// Group all upsell settings together
			if ( ! empty( $upsell_settings ) ) {
				$data['upsell_settings'] = $upsell_settings;
			}

			// Order Bumps Settings (_wfob_global_settings) - Group order bump settings together
			$wfob_settings       = $this->get_option( '_wfob_global_settings', array() );
			$order_bump_settings = array();
			if ( ! empty( $wfob_settings ) ) {
				// Number of bumps per checkout - Correct key: number_bump_per_checkout
				$number_bumps                               = isset( $wfob_settings['number_bump_per_checkout'] ) ? $wfob_settings['number_bump_per_checkout'] : '';
				$order_bump_settings['number_per_checkout'] = ! empty( $number_bumps ) ? absint( $number_bumps ) : 0;
			}
			if ( ! empty( $order_bump_settings ) ) {
				$data['order_bump_settings'] = $order_bump_settings;
			}

			// Optin Settings - Check both Lite (wffn_op_settings) and Pro (wfop_global_settings)
			$wffn_op_settings  = $this->get_option( 'wffn_op_settings', array() );
			$wfop_settings     = $this->get_option( 'wfop_global_settings', array() );
			$optin_settings    = array();
			$recaptcha_enabled = false;

			// Always include optin/recaptcha_enabled if either Lite or Pro settings exist
			if ( is_array( $wffn_op_settings ) && isset( $wffn_op_settings['op_recaptcha'] ) ) {
				// Lite uses 'op_recaptcha' with 'true'/'false' string values
				$recaptcha_enabled = ( 'true' === $wffn_op_settings['op_recaptcha'] || '1' === $wffn_op_settings['op_recaptcha'] || true === $wffn_op_settings['op_recaptcha'] );
			} elseif ( is_array( $wfop_settings ) && isset( $wfop_settings['enable_recaptcha'] ) ) {
				// Check Pro option (wfop_global_settings) if Lite option not found
				$recaptcha_enabled = $this->is_enabled( $wfop_settings['enable_recaptcha'] );
			}

			// Always include the key if either settings array exists (even if empty)
			if ( is_array( $wffn_op_settings ) || is_array( $wfop_settings ) ) {
				$optin_settings['recaptcha_enabled'] = $recaptcha_enabled;
			}
			if ( ! empty( $optin_settings ) ) {
				$data['optin_settings'] = $optin_settings;
			}

			// Checkout Settings (_wfacp_global_settings) - Group checkout settings together
			$wfacp_settings    = $this->get_option( '_wfacp_global_settings', array() );
			$checkout_settings = array();
			if ( ! empty( $wfacp_settings ) ) {
				// Shipping method ascending order - Correct key: wfacp_set_shipping_method
				$checkout_settings['shipping_method_ascending'] = isset( $wfacp_settings['wfacp_set_shipping_method'] ) ? $this->is_enabled( $wfacp_settings['wfacp_set_shipping_method'] ) : false;
			}
			if ( ! empty( $checkout_settings ) ) {
				$data['checkout_settings'] = $checkout_settings;
			}

			return $data;
		}

		/**
		 * Get all custom permalink slugs configured for different post types
		 * Returns an array with post type keys and their configured slugs
		 * Note: This method is available for consistency with Lite, but Pro inherits permalinks from Lite
		 *
		 * @return array Array of configured permalink slugs, e.g. ['checkout' => 'checkouts', 'landing_page' => 'sp']
		 */
		private function get_custom_permalinks() {
			$permalinks = array();

			// Get all permalink base settings
			$checkout_base = $this->get_setting( 'checkout_page_base', '' );
			$landing_base  = $this->get_setting( 'landing_page_base', '' );
			$optin_base    = $this->get_setting( 'optin_page_base', '' );
			$optin_ty_base = $this->get_setting( 'optin_ty_page_base', '' );
			$wfocu_base    = $this->get_setting( 'wfocu_page_base', '' );
			$ty_base       = $this->get_setting( 'ty_page_base', '' );

			// Only include non-empty slugs in the array
			if ( ! empty( $checkout_base ) ) {
				$permalinks['checkout'] = $checkout_base;
			}
			if ( ! empty( $landing_base ) ) {
				$permalinks['landing_page'] = $landing_base;
			}
			if ( ! empty( $optin_base ) ) {
				$permalinks['optin_page'] = $optin_base;
			}
			if ( ! empty( $optin_ty_base ) ) {
				$permalinks['optin_ty_page'] = $optin_ty_base;
			}
			if ( ! empty( $wfocu_base ) ) {
				$permalinks['upsell'] = $wfocu_base;
			}
			if ( ! empty( $ty_base ) ) {
				$permalinks['thankyou_page'] = $ty_base;
			}

			return $permalinks;
		}
	}
}
