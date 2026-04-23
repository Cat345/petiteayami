<?php

/**
 * WooCommerce Address Validation by SkyVerge
 * Plugin URI: http://www.woocommerce.com/products/postcodeaddress-validation/
 * Version: 2.11.4+
 */
if ( ! class_exists( 'WFACP_Compatibility_WooCommerce_Address_Validation' ) ) {

	/**
	 * Adds compatibility support for WooCommerce Address Validation plugin
	 * Ensures address validation fields (postcode lookup, house number) work seamlessly
	 * with FunnelKit checkout pages by applying proper styling and field visibility management
	 *
	 * @since 1.0.0
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_WooCommerce_Address_Validation {

		/**
		 * Instance of WC_Address_Validation class
		 *
		 * @var WC_Address_Validation|null
		 */
		public $instance = null;

		/**
		 * Constructor - Sets up compatibility hooks
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			// Register hooks when FunnelKit checkout page is loaded
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ) );
		}

		/**
		 * Checks if WooCommerce Address Validation plugin is active
		 *
		 * @return bool True if plugin is active, false otherwise
		 * @since 1.0.0
		 */
		public function is_enable() {
			return class_exists( 'WC_Address_Validation' );
		}

		/**
		 * Sets up compatibility actions after FunnelKit checkout page is found
		 * Adds custom JavaScript and CSS to style address validation fields
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function action() {
			if ( ! $this->is_enable() ) {
				return;
			}

			// Add custom JavaScript for field styling and visibility management
			add_action( 'wp_footer', array( $this, 'add_js' ) );

			// Add custom CSS for address validation fields
			add_action( 'wfacp_internal_css', array( $this, 'add_css' ) );
		}

		/**
		 * Adds JavaScript to handle address validation field styling
		 * Applies FunnelKit CSS classes to address validation fields and manages field visibility
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function add_js() {
			?>
			<script>
			(function($) {
				'use strict';

				/**
				 * Adds FunnelKit specific CSS classes to address validation fields
				 * - Adds form control wrapper classes
				 * - Sets field width (full width for results, half width for inputs)
				 * - Adds form control classes to input fields and labels
				 */
				function addWFACPClasses() {
					// Process billing and shipping fields
					$('.wc-address-validation-billing-field, .wc-address-validation-shipping-field').each(function() {
						var $wrapper = $(this);

						// Add wrapper classes: wfacp-form-control-wrapper
						$wrapper.addClass('wfacp-form-control-wrapper');

						// Check if this is the results field - if so, use full width, otherwise half
						if ($wrapper.find('select[name*="postcode_lookup_postcode_results"]').length > 0 ||
							$wrapper.find('[name="wc_address_validation_postcode_lookup_postcode_results"]').length > 0) {
							$wrapper.addClass('wfacp-col-full');
						} else {
							$wrapper.addClass('wfacp-col-left-half');
						}

						// Add input classes: wfacp-form-control
						$wrapper.find('input[type="text"], select').addClass('wfacp-form-control');

						// Add label classes: wfacp-form-control-label
						$wrapper.find('label').addClass('wfacp-form-control-label');
					});
				}

				/**
				 * Handles field visibility based on checkout configuration
				 * Shows/hides billing and shipping fields based on
				 * "same as shipping/billing" checkbox state
				 */
				function handleFieldVisibility() {
					// Handle billing fields visibility
					if ($('.wc-address-validation-billing-field').length > 0) {
						var $billingFields = $('.wc-address-validation-billing-field');

						// If billing_same_as_shipping_field exists, add hide classes
						if ($('#billing_same_as_shipping_field').length > 0 || $('#billing_same_as_shipping').length > 0) {
							// Only add hide class if show class doesn't exist
							if (!$billingFields.hasClass('wfacp_billing_field_show')) {
								$billingFields.addClass('wfacp_billing_fields wfacp_billing_field_hide');

								// Also hide the button wrapper
								$billingFields.parent('.wc-address-validation-field-group').find('.button').parent('p').addClass('wfacp_billing_fields wfacp_billing_field_hide');
							}
						} else {
							$billingFields.addClass('wfacp_billing_fields wfacp_billing_field_show').removeClass('wfacp_billing_field_hide');
						}
					}

					// Handle shipping fields visibility
					if ($('.wc-address-validation-shipping-field').length > 0) {
						var $shippingFields = $('.wc-address-validation-shipping-field');

						// If shipping_same_as_billing exists, add hide classes
						if ($('#shipping_same_as_billing').length > 0) {
							// Only add hide class if show class doesn't exist
							if (!$shippingFields.hasClass('wfacp_shipping_field_show')) {
								$shippingFields.addClass('wfacp_shipping_fields wfacp_shipping_field_hide');

								// Also hide the button wrapper
								$shippingFields.parent('.wc-address-validation-field-group').find('.button').parent('p').addClass('wfacp_shipping_fields wfacp_shipping_field_hide');
							}
						} else {
							$shippingFields.addClass('wfacp_shipping_fields wfacp_shipping_field_show').removeClass('wfacp_shipping_field_hide');
						}
					}
				}

				/**
				 * Initialize address validation compatibility
				 * Applies classes and handles initial visibility state
				 */
				function initAddressValidation() {
					addWFACPClasses();
					handleFieldVisibility();
				}

				// Run on page load with delays to ensure fields are rendered
				$(document).ready(function() {
					setTimeout(initAddressValidation, 100);
					setTimeout(initAddressValidation, 500);
				});

				// Re-run on checkout update (AJAX)
				$(document.body).on('updated_checkout', function() {
					setTimeout(initAddressValidation, 100);
				});

				// Watch for ship to different address checkbox change
				$(document).on('change', '#ship-to-different-address-checkbox, #shipping_same_as_billing, #billing_same_as_shipping', function() {
					setTimeout(handleFieldVisibility, 50);
				});

			})(jQuery);
			</script>
			<?php
		}

		/**
		 * Adds custom CSS styling for address validation fields
		 * Ensures buttons and input fields match FunnelKit checkout styling
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function add_css() {
			if ( ! $this->is_enable() ) {
				return;
			}
			?>
			<style>
			/* FunnelKit button styling for Find Address button */
			#wfacp-sec-wrapper .wc-address-validation-field-group .button,
			#wfacp-sec-wrapper .wc-address-validation-field-group a.button {
				display: inline-block;
				width: 100%;
				padding: 12px 24px;
				font-size: 16px;
				font-weight: 600;
				line-height: 1.5;
				text-align: center;
				text-decoration: none;
				background-color: var(--wfacp-primary-color, #3182ce);
				color: #ffffff !important;
				border: none;
				border-radius: 4px;
				cursor: pointer;
				transition: all 0.3s ease;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}

			#wfacp-sec-wrapper .wc-address-validation-field-group .button:hover,
			#wfacp-sec-wrapper .wc-address-validation-field-group a.button:hover {
				background-color: var(--wfacp-primary-hover-color, #2c5aa0);
				box-shadow: 0 4px 8px rgba(0,0,0,0.15);
				transform: translateY(-1px);
			}

			/* Container styling for address validation field group */
			#wfacp-sec-wrapper .wc-address-validation-field-group {
				margin-bottom: 20px;
				padding: 15px;
				background: #f9f9f9;
				border-radius: 4px;
			}

			#wfacp-sec-wrapper .wc-address-validation-field-group .form-row {
				margin-bottom: 15px;
			}

			/* Alternative button styling for specific layout */
			#wfacp-sec-wrapper .wfacp_main_form.woocommerce .wc-address-validation-field a.button {
				border-color: #c8c8c8;
				background-color: #c8c8c8;
				min-height: 48px;
				color: #fff !important;
				padding: 17px 12px;
				font-size: 14px;
				line-height: 1;
			}

			/* Placeholder styling for address validation input fields - Specific targeting */
			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode"]::-webkit-input-placeholder,
			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode_house_number"]::-webkit-input-placeholder {
				color: #999 !important;
				opacity: 1 !important;
				font-size: 14px;
			}

			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode"]::-moz-placeholder,
			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode_house_number"]::-moz-placeholder {
				color: #999 !important;
				opacity: 1 !important;
				font-size: 14px;
			}

			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode"]:-ms-input-placeholder,
			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode_house_number"]:-ms-input-placeholder {
				color: #999 !important;
				opacity: 1 !important;
				font-size: 14px;
			}

			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode"]::placeholder,
			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control[name="wc_address_validation_postcode_lookup_postcode_house_number"]::placeholder {
				color: #999 !important;
				opacity: 1 !important;
				font-size: 14px;
			}

			/* Padding for address validation input fields */
			body #wfacp-sec-wrapper .wfacp_main_form #wfacp_checkout_form .form-row.wc-address-validation-field input.wfacp-form-control {
				padding: 12px !important;
			}
			</style>
			<?php
		}
	}

	// Register the compatibility class with FunnelKit
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_WooCommerce_Address_Validation(), 'wfacp-woocommerce-address-validation' );

}

