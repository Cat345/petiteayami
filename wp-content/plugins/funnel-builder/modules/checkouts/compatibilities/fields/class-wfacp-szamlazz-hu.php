<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration for Szamlazz.hu & WooCommerce by Viszt Péter
 * Adds compatibility for the Tax number field (wc_szamlazz_adoszam) and related fields.
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Szamlazz_HU' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Szamlazz_HU {

		private $keys = array();

		public function __construct() {
			/* Register field in Advanced Fields */
			add_filter( 'wfacp_advanced_fields', array( $this, 'add_field' ) );

			/* Prevent default HTML rendering */
			add_filter( 'wfacp_html_fields_wfacp_szamlazz_vat', '__return_false' );

			/* Remove native actions */
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ) );

			/* Display Field */
			add_action( 'process_wfacp_html', array( $this, 'display_field' ), 10, 3 );

			/* Add default styling to fields */
			add_filter( 'woocommerce_form_field_args', array( $this, 'add_default_wfacp_styling' ), 10, 2 );

			/* Internal CSS */
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );

			/* Prevent third party fields and wrapper */
			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );

			/* Re-add the field to checkout fields so it's included in validation */
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_szamlazz_to_checkout_fields' ), 99999 );
		}

		/**
		 * Check if Szamlazz.hu plugin is active and VAT field class exists.
		 *
		 * @return bool
		 */
		public function is_enable() {
			return class_exists( 'WC_Szamlazz_Vat_Number_Field' );
		}

		/**
		 * Add field to Advanced Fields section
		 *
		 * @param array $fields Existing fields.
		 * @return array Modified fields.
		 */
		public function add_field( $fields ) {
			$fields['wfacp_szamlazz_vat'] = array(
				'type'       => 'wfacp_html',
				'class'      => array( 'form-row-wide' ),
				'id'         => 'wfacp_szamlazz_vat',
				'field_type' => 'advanced',
				'label'      => __( 'Szamlazz.hu Tax Number', 'woofunnels-aero-checkout' ), //phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			);

			return $fields;
		}

		/**
		 * Re-add Szamlazz fields to checkout fields for validation
		 *
		 * @param array $fields Checkout fields.
		 * @return array Modified fields.
		 */
		public function add_szamlazz_to_checkout_fields( $fields ) {
			if ( ! $this->is_enable() ) {
				return $fields;
			}

			$szamlazz_fields = WC_Szamlazz_Vat_Number_Field::add_vat_number_checkout_field( array() );

			if ( ! empty( $szamlazz_fields ) && is_array( $szamlazz_fields ) ) {
				foreach ( $szamlazz_fields as $key => $field_data ) {
					if ( ! isset( $fields['billing'][ $key ] ) ) {
						$fields['billing'][ $key ] = $field_data;
					}
				}
			}

			return $fields;
		}

		/**
		 * Remove native Szamlazz.hu actions
		 */
		public function action() {
			if ( ! $this->is_enable() ) {
				return;
			}
			WFACP_Common::remove_actions( 'woocommerce_billing_fields', 'WC_Szamlazz_Vat_Number_Field', 'add_vat_number_checkout_field' );
			WFACP_Common::remove_actions( 'woocommerce_checkout_fields', 'WC_Szamlazz_Vat_Number_Field', 'align_vat_number_checkout_field' );
		}

		/**
		 * Display the Szamlazz.hu fields
		 *
		 * @param array  $field Field data.
		 * @param string $key   Field key.
		 * @param array  $args  Field arguments.
		 */
		public function display_field( $field, $key, $args ) {
			if ( empty( $key ) || 'wfacp_szamlazz_vat' !== $key ) {
				return '';
			}

			if ( ! $this->is_enable() ) {
				return;
			}

			$szamlazz_fields = WC_Szamlazz_Vat_Number_Field::add_vat_number_checkout_field( array() );
			$szamlazz_fields = WC_Szamlazz_Vat_Number_Field::align_vat_number_checkout_field( array( 'billing' => $szamlazz_fields ) );

			if ( ! isset( $szamlazz_fields['billing'] ) || ! is_array( $szamlazz_fields['billing'] ) ) {
				return;
			}

			echo '<div class="wfacp_szamlazz_fields_wrap" id="wfacp_szamlazz_vat_wrap">';
			foreach ( $szamlazz_fields['billing'] as $field_key => $field_val ) {
				$this->keys[] = $field_key;
				woocommerce_form_field( $field_key, $field_val );
			}
			echo '</div>';
		}

		/**
		 * Add default WFACP styling to the fields
		 *
		 * @param array  $args Field arguments.
		 * @param string $key  Field key.
		 * @return array Modified field arguments.
		 */
		public function add_default_wfacp_styling( $args, $key ) {
			if ( ! $this->is_enable() || ! in_array( $key, $this->keys, true ) ) {
				return $args;
			}

			if ( isset( $args['type'] ) && 'checkbox' !== $args['type'] && 'radio' !== $args['type'] ) {
				$args['input_class'] = array_merge( array( 'wfacp-form-control' ), isset( $args['input_class'] ) ? $args['input_class'] : array() );
				$args['label_class'] = array_merge( array( 'wfacp-form-control-label' ), isset( $args['label_class'] ) ? $args['label_class'] : array() );
				$args['class']       = array_merge( array( 'wfacp-form-control-wrapper wfacp-col-full' ), isset( $args['class'] ) ? $args['class'] : array() );
				$args['cssready']    = array( 'wfacp-col-full' );
			} else {
				$args['class']    = array_merge( array( 'wfacp-form-control-wrapper wfacp-col-full' ), isset( $args['class'] ) ? $args['class'] : array() );
				$args['cssready'] = array( 'wfacp-col-full' );
			}

			return $args;
		}

		/**
		 * Add internal CSS for the Szamlazz fields
		 */
		public function internal_css() {
			if ( ! $this->is_enable() ) {
				return;
			}

			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			$body_class = 'body ';
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$body_class = 'body #wfacp-e-form ';
			}

			$css  = '<style>';
			$css .= $body_class . '.wfacp_szamlazz_fields_wrap { width: 100%; clear: both; }';
			$css .= $body_class . '.wfacp_szamlazz_fields_wrap .form-row { margin-bottom: 15px; }';
			$css .= $body_class . '.wfacp_szamlazz_fields_wrap input[type="checkbox"] { position: relative; margin: 0 10px 0 0; }';
			$css .= $body_class . '.wfacp_szamlazz_fields_wrap input[type="radio"] { position: relative; margin: 0 5px 0 0; }';
			$css .= $body_class . '.wfacp_szamlazz_fields_wrap .wc_szamlazz_company_toggle_radio label { display: inline-block; margin-right: 15px; }';
			$css .= '</style>';

			echo $css; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal CSS, no user input
		}
	}

	if ( ! function_exists( 'WC_Szamlazz' ) ) {
		return;
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Szamlazz_HU(), 'wfacp-szamlazz-hu' );
}
