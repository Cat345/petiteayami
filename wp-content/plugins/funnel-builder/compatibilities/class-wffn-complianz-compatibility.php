<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Complianz GDPR/CCPA Cookie Consent Compatibility
 * Adds FunnelKit UTM tracking script to Complianz known script tags so cookies are blocked until consent.
 *
 * Note: We do NOT register via cmplz_integrations - Complianz already has a built-in "funnelkit"
 * integration (constant_or_function: WFFN_VERSION, firstparty_marketing: false) that handles
 * pixel tracking and UTM cookies. Overriding it would break their integration.
 *
 * @since 3.13.2
 */
if ( ! class_exists( 'WFFN_Complianz_Compatibility' ) ) {

	#[\AllowDynamicProperties]
	class WFFN_Complianz_Compatibility {

		public function __construct() {
			add_filter( 'cmplz_known_script_tags', array( $this, 'add_script_tags' ) );
		}

		/**
		 * @return bool
		 */
		public function is_enable() {
			return class_exists( 'COMPLIANZ' );
		}

		/**
		 * Add UTM tracker script to Complianz known script tags for blocking until consent.
		 *
		 * @param array $tags
		 *
		 * @return array
		 */
		public function add_script_tags( $tags ) {
			if ( ! is_array( $tags ) ) {
				$tags = array();
			}
			$tags[] = array(
				'name'     => 'funnelkit-utm',
				'category' => 'marketing',
				'urls'     => array(
					'utm-tracker',
				),
			);

			return $tags;
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Complianz_Compatibility(), 'complianz' );
}
