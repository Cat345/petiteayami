<?php
/**
 * Installation & Configuration Tracking
 *
 * Collects 29 keys related to plugin installation, environment, and configuration
 *
 * @package FunnelKit Funnel Builder
 * @since 3.13.1.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Installation_Config' ) ) {

	/**
	 * Class WFFN_Installation_Config
	 */
	class WFFN_Installation_Config {

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
		 * Collect installation and configuration data
		 *
		 * @return array
		 */
		public function collect() {
			$data = array();

			// Product & Timing (2 keys - version removed as it's tracked in active_plugins)
			$data['install_date']              = $this->get_install_date();
			$data['first_funnel_created_date'] = $this->get_first_funnel_date();

			// Global Settings
			// is already collected at root level, so we don't duplicate it here
			$data = array_merge( $data, $this->get_global_settings() );

			// Pixel Settings - Group all pixel/platform settings together
			$data['pixel_settings'] = $this->get_platform_settings();

			// Theme Settings - Group all theme CSS/JS settings together
			$data['theme_settings'] = $this->get_advanced_settings();

			// Notification Settings - Group all email notification settings together
			$data['notification_settings'] = $this->get_notification_settings();

			// Module-specific settings (checkout, optin, ab_test)
			$data = array_merge( $data, $this->get_additional_settings() );

			return $data;
		}

		/**
		 * Get install date
		 * Uses fk_fb_active_date option which stores activation timestamps
		 *
		 * @return string Date in Y-m-d H:i:s format or empty string
		 */
		private function get_install_date() {
			$active_date = get_option( 'fk_fb_active_date', array() );

			// Check for Lite activation date first
			if ( is_array( $active_date ) && isset( $active_date['lite'] ) && ! empty( $active_date['lite'] ) ) {
				// Convert timestamp to date string (Y-m-d H:i:s format)
				return date( 'Y-m-d H:i:s', $active_date['lite'] ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			}

			// If Lite date not found, return empty string
			return '';
		}

		/**
		 * Get first funnel created date
		 *
		 * @return string
		 */
		private function get_first_funnel_date() {
			global $wpdb;

			try {
				// Suppress errors and check if query succeeds
				$date = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(date_added) FROM {$wpdb->prefix}bwf_funnels WHERE date_added != %s AND date_added IS NOT NULL", '0000-00-00 00:00:00' ) );
				return $date ? $date : '';
			} catch ( Exception $e ) {
				return '';
			} catch ( Error $e ) {
				// Handle PHP 7+ errors
				return '';
			}
		}

		/**
		 * Get global settings (4 keys)
		 *
		 * @return array
		 */
		private function get_global_settings() {
			$page_builder = $this->get_setting( 'default_selected_builder', '' );
			// Correct option key for Google Maps
			$maps_key = $this->get_setting( 'funnelkit_google_map_key', '' );

			// If no builder is set, try to detect one
			if ( empty( $page_builder ) ) {
				$page_builder = $this->detect_page_builder();
			}

			return array(
				'default_page_builder'         => false !== $page_builder ? $page_builder : '',
				'funnel_as_homepage'           => $this->is_funnel_homepage(),
				'custom_permalinks_configured' => $this->get_custom_permalinks(),
				'google_maps_api_configured'   => ! empty( $maps_key ),
			);
		}

		/**
		 * Check if funnel is set as homepage
		 *
		 * @return bool
		 */
		private function is_funnel_homepage() {
			$page_on_front = get_option( 'page_on_front', 0 );
			if ( empty( $page_on_front ) ) {
				return false;
			}

			// Check if the page is a funnel step
			$funnel_id = get_post_meta( $page_on_front, '_bwf_in_funnel', true );

			return ! empty( $funnel_id );
		}

		/**
		 * Get all custom permalink slugs configured for different post types
		 * Returns an array with post type keys and their configured slugs
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

		/**
		 * Get theme settings (all theme CSS/JS options grouped)
		 *
		 * @return array
		 */
		private function get_advanced_settings() {
			// Correct option key: allow_theme_css is an array
			$theme_settings = $this->get_setting( 'allow_theme_css', array() );

			if ( ! is_array( $theme_settings ) ) {
				$theme_settings = array();
			}

			return array(
				'checkout'           => in_array( 'wfacp_checkout', $theme_settings, true ),
				'sales'              => in_array( 'wffn_landing', $theme_settings, true ),
				'upsell_offer'       => in_array( 'wfocu_offer', $theme_settings, true ),
				'thankyou'           => in_array( 'wffn_ty', $theme_settings, true ),
				'optin'              => in_array( 'wffn_optin', $theme_settings, true ),
				'optin_confirmation' => in_array( 'wffn_oty', $theme_settings, true ),
			);
		}

		/**
		 * Get notification settings (4 keys)
		 *
		 * @return array
		 */
		private function get_notification_settings() {
			// Correct option keys with fallback support
			$enable_notification = $this->get_setting( 'bwf_enable_notification', false );
			$frequency           = $this->get_setting( 'bwf_notification_frequency', array() );
			$notification_time   = $this->get_setting( 'bwf_notification_time', array() );

			// Convert frequency array to string (daily/weekly/monthly)
			$frequency_str = '';
			if ( is_array( $frequency ) && ! empty( $frequency ) ) {
				// If both weekly and monthly are selected, prioritize monthly
				if ( in_array( 'monthly', $frequency, true ) ) {
					$frequency_str = 'monthly';
				} elseif ( in_array( 'weekly', $frequency, true ) ) {
					$frequency_str = 'weekly';
				} elseif ( in_array( 'daily', $frequency, true ) ) {
					$frequency_str = 'daily';
				} else {
					$frequency_str = ! empty( $frequency[0] ) ? $frequency[0] : '';
				}
			}

			// Format notification time (hours:minutes AM/PM)
			$time_str = '';
			if ( is_array( $notification_time ) && ! empty( $notification_time ) ) {
				$hours    = isset( $notification_time['hours'] ) ? $notification_time['hours'] : '00';
				$minutes  = isset( $notification_time['minutes'] ) ? $notification_time['minutes'] : '00';
				$ampm     = isset( $notification_time['ampm'] ) ? $notification_time['ampm'] : 'am';
				$time_str = sprintf( '%s:%s %s', str_pad( $hours, 2, '0', STR_PAD_LEFT ), str_pad( $minutes, 2, '0', STR_PAD_LEFT ), $ampm );
			}

			// Get email notification last sent/updated times
			$email_notification_updated = get_option(
				'wffn_email_notification_updated',
				array(
					'weekly'  => '',
					'monthly' => '',
				)
			);

			// Format: return the most recent timestamp, or empty string if none
			$last_sent_str = '';
			if ( is_array( $email_notification_updated ) && ! empty( $email_notification_updated ) ) {
				// Get the most recent timestamp from weekly or monthly
				$timestamps = array_filter( array_values( $email_notification_updated ) );
				if ( ! empty( $timestamps ) ) {
					// Sort to get the most recent
					usort(
						$timestamps,
						function ( $a, $b ) {
							return strtotime( $b ) - strtotime( $a );
						}
					);
					$last_sent_str = $timestamps[0];
				}
			}

			return array(
				'email_performance_summary' => true === $enable_notification || '1' === $enable_notification || 'yes' === $enable_notification,
				'email_summary_frequency'   => $frequency_str,
				'email_send_time'           => $time_str,
				'email_last_sent'           => $last_sent_str,
			);
		}

		/**
		 * Get all platform settings (not just enabled events, but all configuration)
		 * Collects comprehensive settings for Facebook, Google Analytics, Google Ads, Pinterest, TikTok, Snapchat
		 *
		 * @return array
		 */
		private function get_platform_settings() {
			$platforms = array();

			// Facebook Pixel - All settings
			$platforms['facebook'] = $this->get_facebook_settings();

			// Google Analytics - All settings
			$platforms['google_analytics'] = $this->get_google_analytics_settings();

			// Google Ads - All settings
			$platforms['google_ads'] = $this->get_google_ads_settings();

			// Pinterest - All settings
			$platforms['pinterest'] = $this->get_pinterest_settings();

			// TikTok - All settings
			$platforms['tiktok'] = $this->get_tiktok_settings();

			// Snapchat - All settings
			$platforms['snapchat'] = $this->get_snapchat_settings();

			return $platforms;
		}

		/**
		 * Get Facebook Pixel settings
		 *
		 * @return array
		 */
		private function get_facebook_settings() {
			$settings = array();

			// Pixel ID - Only collect if configured and count (not the actual ID)
			$pixel_key                    = $this->get_setting( 'fb_pixel_key', '' );
			$settings['pixel_configured'] = ! empty( $pixel_key );
			// Count number of pixel IDs (Facebook supports multiple pixels separated by comma)
			$pixel_ids               = ! empty( $pixel_key ) ? explode( ',', $pixel_key ) : array();
			$settings['pixel_count'] = count( array_filter( array_map( 'trim', $pixel_ids ) ) );

			// Conversion API - Only collect configuration status, not tokens or codes
			$settings['conversion_api_configured'] = ! empty( $this->get_setting( 'conversion_api_access_token', '' ) );
			$settings['conversion_api_test_mode']  = $this->is_checklist_enabled( 'is_fb_conv_enable_test' );
			$settings['purchase_logs_enabled']     = is_array( $this->get_setting( 'is_fb_conversion_api_log', array() ) ) && in_array( 'yes', $this->get_setting( 'is_fb_conversion_api_log', array() ), true );

			// Site Wide Events
			$settings['page_view_global']    = $this->is_setting_enabled( 'is_fb_page_view_global' );
			$settings['add_to_cart_global']  = $this->is_setting_enabled( 'is_fb_add_to_cart_global' );
			$settings['view_content_global'] = $this->is_setting_enabled( 'is_fb_page_product_content_global' );

			// Sales Page Events
			$settings['page_view_sales'] = $this->is_checklist_enabled( 'is_fb_page_view_lp' );

			// Optin Page Events
			$settings['page_view_optin'] = $this->is_checklist_enabled( 'is_fb_page_view_op' );
			$settings['lead_optin']      = $this->is_checklist_enabled( 'is_fb_lead_op' );

			// Order Bump Events
			$settings['add_to_cart_bump'] = $this->is_setting_enabled( 'is_fb_add_to_cart_bump' );
			$settings['custom_bump']      = $this->is_setting_enabled( 'is_fb_custom_bump' );

			// Checkout Events
			$settings['checkout_page_view']    = $this->is_setting_enabled( 'pixel_is_page_view' );
			$settings['checkout_initiate']     = $this->is_setting_enabled( 'pixel_initiate_checkout_event' );
			$settings['checkout_add_to_cart']  = $this->is_setting_enabled( 'pixel_add_to_cart_event' );
			$settings['checkout_payment_info'] = $this->is_setting_enabled( 'pixel_add_payment_info_event' );

			// Purchase Events
			$settings['purchase_page_view'] = $this->is_checklist_enabled( 'is_fb_purchase_page_view' );
			$settings['purchase_event']     = $this->is_checklist_enabled( 'is_fb_purchase_event' );
			$settings['general_event']      = $this->is_checklist_enabled( 'enable_general_event' );
			// Don't collect actual event name, just if it's configured
			$settings['general_event_configured'] = ! empty( $this->get_setting( 'general_event_name', '' ) );

			// Track Steps
			$settings['custom_step_events'] = $this->is_setting_enabled( 'is_fb_custom_events' );

			// Advanced
			$settings['content_for_dynamic_ads'] = $this->is_checklist_enabled( 'is_fb_enable_content' );
			$settings['variable_as_simple']      = $this->is_setting_enabled( 'pixel_variable_as_simple' );
			// Don't collect actual content ID configuration values, just if configured
			$settings['content_id_type_configured']   = ! empty( $this->get_setting( 'pixel_content_id_type', '' ) );
			$settings['content_id_prefix_configured'] = ! empty( $this->get_setting( 'pixel_content_id_prefix', '' ) );
			$settings['content_id_suffix_configured'] = ! empty( $this->get_setting( 'pixel_content_id_suffix', '' ) );
			$settings['exclude_shipping']             = is_array( $this->get_setting( 'exclude_from_total', array() ) ) && in_array( 'is_disable_shipping', $this->get_setting( 'exclude_from_total', array() ), true );
			$settings['exclude_taxes']                = is_array( $this->get_setting( 'exclude_from_total', array() ) ) && in_array( 'is_disable_taxes', $this->get_setting( 'exclude_from_total', array() ), true );
			$settings['advanced_matching']            = $this->is_checklist_enabled( 'is_fb_advanced_event' );

			return $settings;
		}

		/**
		 * Get Google Analytics settings
		 *
		 * @return array
		 */
		private function get_google_analytics_settings() {
			$settings = array();

			// Analytics ID - Only collect if configured (not the actual ID)
			$ga_key                           = $this->get_setting( 'ga_key', '' );
			$settings['analytics_configured'] = ! empty( $ga_key );
			$settings['ga4_tracking']         = $this->is_setting_enabled( 'is_ga4_tracking' );

			// Site Wide Events
			$settings['page_view_global']   = $this->is_setting_enabled( 'is_ga_page_view_global' );
			$settings['add_to_cart_global'] = $this->is_setting_enabled( 'is_ga_add_to_cart_global' );
			$settings['view_item_global']   = $this->is_setting_enabled( 'is_ga_view_item_global' );

			// Sales Page Events
			$settings['page_view_sales'] = $this->is_checklist_enabled( 'is_ga_page_view_lp' );

			// Optin Page Events
			$settings['page_view_optin'] = $this->is_checklist_enabled( 'is_ga_page_view_op' );
			$settings['lead_optin']      = $this->is_checklist_enabled( 'is_ga_lead_op' );

			// Order Bump Events
			$settings['add_to_cart_bump'] = $this->is_setting_enabled( 'is_ga_add_to_cart_bump' );
			$settings['custom_bump']      = $this->is_setting_enabled( 'is_ga_custom_bump' );

			// Checkout Events
			$settings['checkout_page_view']    = $this->is_setting_enabled( 'google_ua_is_page_view' );
			$settings['checkout_add_to_cart']  = $this->is_setting_enabled( 'google_ua_add_to_cart_event' );
			$settings['checkout_initiate']     = $this->is_setting_enabled( 'google_ua_initiate_checkout_event' );
			$settings['checkout_payment_info'] = $this->is_setting_enabled( 'google_ua_add_payment_info_event' );

			// Purchase Events
			$settings['purchase_page_view'] = $this->is_checklist_enabled( 'is_ga_purchase_page_view' );
			$settings['purchase_event']     = $this->is_checklist_enabled( 'is_ga_purchase_event' );

			// Track Steps
			$settings['custom_step_events'] = $this->is_setting_enabled( 'is_ga_custom_events' );

			// Advanced
			$settings['variable_as_simple'] = $this->is_setting_enabled( 'google_ua_variable_as_simple' );

			return $settings;
		}

		/**
		 * Get Google Ads settings
		 *
		 * @return array
		 */
		private function get_google_ads_settings() {
			$settings = array();

			// Ads ID - Only collect if configured (not the actual ID)
			$gad_key                    = $this->get_setting( 'gad_key', '' );
			$settings['ads_configured'] = ! empty( $gad_key );

			// Site Wide Events
			$settings['page_view_global']   = $this->is_setting_enabled( 'is_gad_page_view_global' );
			$settings['add_to_cart_global'] = $this->is_setting_enabled( 'is_gad_add_to_cart_global' );
			$settings['view_item_global']   = $this->is_setting_enabled( 'is_gad_view_item_global' );

			// Sales Page Events
			$settings['page_view_sales'] = $this->is_checklist_enabled( 'is_gad_page_view_lp' );

			// Optin Page Events
			$settings['page_view_optin'] = $this->is_checklist_enabled( 'is_gad_page_view_op' );
			$settings['lead_optin']      = $this->is_checklist_enabled( 'is_gad_lead_op' );

			// Order Bump Events
			$settings['add_to_cart_bump'] = $this->is_setting_enabled( 'is_gad_add_to_cart_bump' );
			$settings['custom_bump']      = $this->is_setting_enabled( 'is_gad_custom_bump' );

			// Checkout Events
			$settings['checkout_page_view']   = $this->is_setting_enabled( 'google_ads_is_page_view' );
			$settings['checkout_add_to_cart'] = $this->is_setting_enabled( 'google_ads_add_to_cart_event' );
			$settings['checkout_initiate']    = $this->is_setting_enabled( 'google_ads_initiate_checkout_event' );

			// Purchase Events
			$settings['purchase_page_view'] = $this->is_checklist_enabled( 'is_gad_pageview_event' );
			$settings['purchase_event']     = $this->is_checklist_enabled( 'is_gad_purchase_event' );

			// Track Steps
			$settings['custom_step_events'] = $this->is_setting_enabled( 'is_gad_custom_events' );

			// Advanced
			$settings['variable_as_simple'] = $this->is_setting_enabled( 'google_ads_variable_as_simple' );

			return $settings;
		}

		/**
		 * Get Pinterest settings
		 *
		 * @return array
		 */
		private function get_pinterest_settings() {
			$settings = array();

			// Pinterest Tag ID - Only collect if configured (not the actual ID)
			$pint_key                   = $this->get_setting( 'pint_key', '' );
			$settings['tag_configured'] = ! empty( $pint_key );

			// Site Wide Events
			$settings['page_view_global']   = $this->is_setting_enabled( 'is_pint_page_view_global' );
			$settings['page_visit_global']  = $this->is_setting_enabled( 'is_pint_page_visit_global' );
			$settings['add_to_cart_global'] = $this->is_setting_enabled( 'is_pint_add_to_cart_global' );

			// Sales Page Events
			$settings['page_view_sales'] = $this->is_checklist_enabled( 'is_pint_page_view_lp' );

			// Optin Page Events
			$settings['page_view_optin'] = $this->is_checklist_enabled( 'is_pint_page_view_op' );
			$settings['lead_optin']      = $this->is_checklist_enabled( 'is_pint_lead_op' );

			// Order Bump Events
			$settings['add_to_cart_bump'] = $this->is_setting_enabled( 'is_pint_add_to_cart_bump' );
			$settings['custom_bump']      = $this->is_setting_enabled( 'is_pint_custom_bump' );

			// Checkout Events
			$settings['checkout_page_view']   = $this->is_setting_enabled( 'pint_is_page_view' );
			$settings['checkout_add_to_cart'] = $this->is_setting_enabled( 'pint_add_to_cart_event' );
			$settings['checkout_initiate']    = $this->is_setting_enabled( 'pint_initiate_checkout_event' );

			// Purchase Events
			$settings['purchase_page_view'] = $this->is_checklist_enabled( 'is_pint_pageview_event' );
			$settings['purchase_event']     = $this->is_checklist_enabled( 'is_pint_purchase_event' );

			// Track Steps
			$settings['custom_step_events'] = $this->is_setting_enabled( 'is_pint_custom_events' );

			// Advanced
			$settings['variable_as_simple'] = $this->is_setting_enabled( 'pint_variable_as_simple' );

			return $settings;
		}

		/**
		 * Get TikTok settings
		 *
		 * @return array
		 */
		private function get_tiktok_settings() {
			$settings = array();

			// TikTok Pixel ID - Only collect if configured (not the actual ID)
			$tiktok_pixel                 = $this->get_setting( 'tiktok_pixel', '' );
			$settings['pixel_configured'] = ! empty( $tiktok_pixel );

			// Site Wide Events
			$settings['page_view_global']    = $this->is_setting_enabled( 'is_tiktok_page_view_global' );
			$settings['add_to_cart_global']  = $this->is_setting_enabled( 'is_tiktok_add_to_cart_global' );
			$settings['view_content_global'] = $this->is_setting_enabled( 'is_tiktok_page_product_content_global' );

			// Sales Page Events
			$settings['page_view_sales'] = $this->is_checklist_enabled( 'is_tiktok_page_view_lp' );

			// Optin Page Events
			$settings['page_view_optin'] = $this->is_checklist_enabled( 'is_tiktok_page_view_op' );

			// Order Bump Events
			$settings['add_to_cart_bump'] = $this->is_setting_enabled( 'is_tiktok_add_to_cart_bump' );

			// Checkout Events
			$settings['checkout_page_view']   = $this->is_setting_enabled( 'tiktok_is_page_view' );
			$settings['checkout_add_to_cart'] = $this->is_setting_enabled( 'tiktok_add_to_cart_event' );
			$settings['checkout_initiate']    = $this->is_setting_enabled( 'tiktok_initiate_checkout_event' );

			// Purchase Events
			$settings['purchase_page_view'] = $this->is_checklist_enabled( 'is_tiktok_pageview_event' );
			$settings['purchase_event']     = $this->is_checklist_enabled( 'is_tiktok_purchase_event' );

			// Advanced
			$settings['variable_as_simple'] = $this->is_setting_enabled( 'tiktok_variable_as_simple' );

			return $settings;
		}

		/**
		 * Get Snapchat settings
		 *
		 * @return array
		 */
		private function get_snapchat_settings() {
			$settings = array();

			// Snapchat Pixel ID - Only collect if configured (not the actual ID)
			$snapchat_pixel               = $this->get_setting( 'snapchat_pixel', '' );
			$settings['pixel_configured'] = ! empty( $snapchat_pixel );

			// Site Wide Events
			$settings['page_view_global']   = $this->is_setting_enabled( 'is_snapchat_page_view_global' );
			$settings['add_to_cart_global'] = $this->is_setting_enabled( 'is_snapchat_add_to_cart_global' );

			// Sales Page Events
			$settings['page_view_sales'] = $this->is_checklist_enabled( 'is_snapchat_page_view_lp' );

			// Optin Page Events
			$settings['page_view_optin'] = $this->is_checklist_enabled( 'is_snapchat_page_view_op' );

			// Order Bump Events
			$settings['add_to_cart_bump'] = $this->is_setting_enabled( 'is_snapchat_add_to_cart_bump' );

			// Checkout Events
			$settings['checkout_page_view']   = $this->is_setting_enabled( 'snapchat_is_page_view' );
			$settings['checkout_add_to_cart'] = $this->is_setting_enabled( 'snapchat_add_to_cart_event' );
			$settings['checkout_initiate']    = $this->is_setting_enabled( 'snapchat_initiate_checkout_event' );

			// Purchase Events
			$settings['purchase_event'] = $this->is_checklist_enabled( 'is_snapchat_purchase_event' );

			// Advanced
			$settings['variable_as_simple'] = $this->is_setting_enabled( 'snapchat_variable_as_simple' );

			return $settings;
		}

		/**
		 * Check if a setting is enabled (for checkbox/string values)
		 *
		 * @param string $key Setting key
		 *
		 * @return bool
		 */
		private function is_setting_enabled( $key ) {
			$value = $this->get_setting( $key, '' );
			return ! empty( $value ) && ( '1' === $value || 'yes' === $value || true === $value || 'true' === $value );
		}

		/**
		 * Check if a checklist setting is enabled (for array/checklist values)
		 *
		 * @param string $key Setting key
		 *
		 * @return bool
		 */
		private function is_checklist_enabled( $key ) {
			$value = $this->get_setting( $key, array() );
			return is_array( $value ) && in_array( 'yes', $value, true );
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
		 * Get additional settings (only essential non-textual settings)
		 * Includes Lite module settings (Checkout, Optin) to ensure consistency with Pro
		 *
		 * @return array
		 */
		private function get_additional_settings() {
			$settings = array();

			// A/B Test Settings - Only essential boolean (non-textual)
			$settings['ab_test_override_permalink'] = $this->is_setting_enabled( 'ab_test_override_permalink' );

			// Checkout Settings (_wfacp_global_settings) - Available in both Lite and Pro
			$wfacp_settings    = $this->get_option( '_wfacp_global_settings', array() );
			$checkout_settings = array();
			if ( ! empty( $wfacp_settings ) ) {
				// Shipping method ascending order - Correct key: wfacp_set_shipping_method
				$checkout_settings['shipping_method_ascending'] = isset( $wfacp_settings['wfacp_set_shipping_method'] ) ? $this->is_enabled( $wfacp_settings['wfacp_set_shipping_method'] ) : false;
			}
			if ( ! empty( $checkout_settings ) ) {
				$settings['checkout_settings'] = $checkout_settings;
			}

			// Optin Settings - Lite uses 'wffn_op_settings', Pro uses 'wfop_global_settings'
			// Check Lite first (wffn_op_settings)
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
				$settings['optin_settings'] = $optin_settings;
			}

			return $settings;
		}

		/**
		 * Detect active page builder
		 *
		 * @return string Page builder slug or empty string
		 */
		private function detect_page_builder() {
			// Check Elementor
			if ( class_exists( '\Elementor\Plugin' ) ) {
				return 'elementor';
			}

			// Check Divi and Bricks (requires WFFN_Core)
			if ( class_exists( 'WFFN_Core' ) ) {
				try {
					$wffn_core = WFFN_Core();
					if ( $wffn_core && method_exists( $wffn_core, 'page_builders' ) && is_object( $wffn_core->page_builders ) ) {
						if ( method_exists( $wffn_core->page_builders, 'is_divi_theme_enabled' ) && $wffn_core->page_builders->is_divi_theme_enabled() ) {
							return 'divi';
						}
						if ( method_exists( $wffn_core->page_builders, 'is_bricks_theme_enabled' ) && $wffn_core->page_builders->is_bricks_theme_enabled() ) {
							return 'bricks';
						}
					}
				} catch ( Exception $e ) {
					// Continue to next check
				}
			}

			// Check Oxygen
			if ( class_exists( 'OxygenElement' ) ) {
				return 'oxy';
			}

			// Check Gutenberg (SlingBlocks) - requires WFFN_Common
			if ( class_exists( 'WFFN_Common' ) && method_exists( 'WFFN_Common', 'get_plugin_status' ) ) {
				try {
					if ( WFFN_Common::get_plugin_status( 'slingblocks/slingblocks.php' ) === 'activated' ) {
						return 'gutenberg';
					}
				} catch ( Exception $e ) {
					// Continue
				}
			}

			return '';
		}
	}
}
