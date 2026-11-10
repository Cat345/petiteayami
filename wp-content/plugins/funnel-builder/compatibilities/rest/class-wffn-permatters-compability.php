<?php

/**
 * Perfmatters
 * https://perfmatters.io/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WFFN_Compatibility_With_Perfmatters' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Compatibility_With_Perfmatters {

		public function __construct() {
			add_filter( 'rest_jsonp_enabled', array( $this, 'allow_rest_apis_with_perfmatters' ), 100 );
			add_action( 'wfacp_after_template_found', array( $this, 'keep_google_maps_on_checkout' ) );
		}

		public function is_enable() {
			if ( defined( 'PERFMATTERS_VERSION' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Allow WooFunnels endpoints in rest calls
		 *
		 * @param $status
		 *
		 * @return mixed
		 */
		public function allow_rest_apis_with_perfmatters( $status ) {

			if ( true !== $this->is_enable() ) {
				return $status;
			}

			if ( ! is_array( $GLOBALS['wp']->query_vars ) || ! isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
				return $status;
			}
			$rest_route = $GLOBALS['wp']->query_vars['rest_route'];

			if ( ! is_null( $rest_route ) && ( strpos( $rest_route, 'woofunnel' ) !== false || 0 === strpos( ltrim( (string) $rest_route, '/' ), 'funnelkit/' ) ) ) {
				remove_filter( 'rest_authentication_errors', 'perfmatters_rest_authentication_errors', 20 );
			}

			return $status;
		}

		/**
		 * Perfmatters "Disable Google Maps" strips every Google Maps script from the page HTML,
		 * which also removes the checkout's Google address autocomplete.
		 * Add the current checkout page to Perfmatters' own "Exclude Locations" for this request.
		 *
		 * @return void
		 */
		public function keep_google_maps_on_checkout() {
			if ( ! class_exists( '\Perfmatters\Config' ) || ! is_array( \Perfmatters\Config::$options ) || empty( \Perfmatters\Config::$options['disable_google_maps'] ) ) {
				return;
			}

			$page_settings = WFACP_Common::get_page_settings( WFACP_Common::get_id() );
			if ( ! isset( $page_settings['enable_google_autocomplete'] ) || ! wc_string_to_bool( $page_settings['enable_google_autocomplete'] ) ) {
				return;
			}

			$page_id = (string) get_queried_object_id();
			if ( '0' === $page_id ) {
				return;
			}

			$exclusions = isset( \Perfmatters\Config::$options['disable_google_maps_exclusions'] ) ? \Perfmatters\Config::$options['disable_google_maps_exclusions'] : '';
			if ( is_array( $exclusions ) ) {
				$exclusions[] = $page_id;
			} else {
				$exclusions = trim( $exclusions . ',' . $page_id, ',' );
			}

			\Perfmatters\Config::$options['disable_google_maps_exclusions'] = $exclusions;
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Compatibility_With_Perfmatters(), 'rest_perfmatters' );
}
