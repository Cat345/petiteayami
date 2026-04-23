<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFACP_Template_Divi5' ) ) {
	/**
	 * Class WFACP_Template_Divi5
	 * This class used as wrapper class for the Divi 5 JSON templates during the rendering of the template
	 * In woofunnels template design structure every template inherits WFACP_Template_Common so we need Divi 5 templates to follow the same structure
	 *
	 * CRITICAL: This extends WFACP_Divi_Template which contains all the necessary filters and actions
	 * including header/footer handling, content wrapping, and other Divi-specific functionality.
	 */
	class WFACP_Template_Divi5 extends WFACP_Divi_Template {

		private static $ins = null;

		protected function __construct() {
			parent::__construct();

			// CRITICAL: Remove hooks registered by the grandparent constructor.
			// This class is instantiated as a side effect of loading the class file (require_once),
			// but the ACTUAL template used for rendering is WFACP_Template_Divi (D4).
			// Both instances register the same hooks via WFACP_Template_Common's constructor,
			// causing duplicate output (e.g. double asterisks, double undo messages).
			// The active template (WFACP_Template_Divi) will handle these correctly.
			remove_filter( 'wfacp_forms_field', array( $this, 'modern_label' ), 20 );
			remove_action( 'wfacp_before_mini_cart_html', array( $this, 'display_mini_cart_undo_message' ) );
			remove_action( 'wfacp_before_order_summary', array( $this, 'display_order_summary_undo_message' ) );
			remove_action( 'wfacp_before_product_switcher_html', array( $this, 'display_undo_message' ) );
			remove_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_checkout_fragments' ), 99 );
			remove_filter( 'woocommerce_order_button_html', array( $this, 'add_class_change_place_order' ), 11 );
			remove_action( 'wfacp_form_single_step_start', array( $this, 'preview_field_generate' ) );
			remove_action( 'wfacp_form_two_step_start', array( $this, 'preview_field_generate' ) );
			remove_action( 'wfacp_form_third_step_start', array( $this, 'preview_field_generate' ) );
		}

		/**
		 * Override get_localize_data to sanitize widget IDs in exchange_keys
		 * This prevents jQuery selector errors when widget IDs contain forward slashes
		 *
		 * @return array Localized data with sanitized widget IDs
		 */
		public function get_localize_data() {
			$data = parent::get_localize_data();

			// CRITICAL: Sanitize widget IDs in exchange_keys for JavaScript use
			// This ensures JavaScript receives sanitized widget IDs that can be used in CSS selectors
			if ( isset( $data['exchange_keys'] ) && isset( $data['exchange_keys']['divi'] ) ) {
				$divi_keys      = $data['exchange_keys']['divi'];
				$sanitized_keys = array();

				foreach ( $divi_keys as $key => $value ) {
					if ( is_string( $value ) ) {
						// Sanitize widget ID values (replace forward slashes and invalid CSS chars)
						$sanitized_keys[ $key ] = str_replace( array( '/', '\\', ' ', '.', ':', '[', ']', '(', ')', '{', '}' ), '_', $value );
					} else {
						$sanitized_keys[ $key ] = $value;
					}
				}

				$data['exchange_keys']['divi'] = $sanitized_keys;
			}

			return $data;
		}

		/**
		 * Helper method to get payment text value from form_data
		 * Returns empty string when blank (instead of defaults)
		 *
		 * @param string $key Form data key
		 * @return string Text value or empty string
		 */
		private function get_payment_text_value( $key ) {
			if ( isset( $this->form_data[ $key ] ) ) {
				$value = trim( $this->form_data[ $key ] );
				return $value !== '' ? $value : '';
			}
			return '';
		}

		/**
		 * Override payment_heading for Divi 5
		 * Returns empty string when blank (instead of parent's empty string or default "Payment")
		 *
		 * @return string Payment heading text
		 */
		public function payment_heading() {
			return $this->get_payment_text_value( 'wfacp_payment_method_heading_text' );
		}

		/**
		 * Override payment_sub_heading for Divi 5
		 * Returns empty string when blank (instead of parent's default text)
		 *
		 * @return string Payment subheading text
		 */
		public function payment_sub_heading() {
			return $this->get_payment_text_value( 'wfacp_payment_method_subheading' );
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}
	}

	if ( ! class_exists( '\WFACP_Common' ) ) {
		return WFACP_Template_Divi5::get_instance();
	}
}
