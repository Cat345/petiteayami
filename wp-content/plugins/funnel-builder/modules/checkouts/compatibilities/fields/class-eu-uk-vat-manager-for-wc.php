<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_EU_UK_Vat_Manager_For_WC' ) ) {
	/**
	 * EU/UK VAT for WooCommerce by WPWhale
	 * Plugin URI: https://wpfactory.com/author/wpwhale/
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_EU_UK_Vat_Manager_For_WC {

		private $instance = null;
		private $keys     = array();

		public function __construct() {

			/* checkout page */

			add_filter( 'wfacp_advanced_fields', array( $this, 'add_fields' ) );
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ) );
			add_action( 'process_wfacp_html', array( $this, 'call_to_action' ), 10, 3 );
			add_filter( 'wfacp_html_fields_wfacp_eu_vat_manager', '__return_false' );

			add_filter( 'woocommerce_form_field_args', array( $this, 'add_default_styling' ), 10, 2 );

			/* prevent third party fields and wrapper*/

			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );
		}

		public function add_fields( $field ) {
			$field['wfacp_eu_vat_manager'] = array(
				'type'       => 'wfacp_html',
				'class'      => array( 'form-row-wide' ),
				'id'         => 'wfacp_eu_vat_manager',
				'field_type' => 'advanced',
				'label'      => __( 'EU/UK VAT Manager', 'funnel-builder' ),

			);

			return $field;
		}

		public function action() {
			if ( ! $this->is_enable() ) {
				return;
			}
			$this->instance = WFACP_Common::remove_actions( 'woocommerce_checkout_fields', $this->get_core_class_name(), 'add_eu_vat_checkout_field_to_frontend' );

			// Print JS on the Aero footer script hook (fires after jQuery/FunnelKit JS is loaded),
			// not on the internal-CSS hook.
			add_action( 'wfacp_footer_after_print_scripts', array( $this, 'internal_js' ) );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
		}

		/**
		 * Resolve the EU VAT core class name.
		 *
		 * WPFactory EU VAT 4.7+ renamed Alg_WC_EU_VAT_Core to WPFactory_WC_EU_VAT_Core.
		 * Prefer the new name, fall back to the legacy one for older versions.
		 *
		 * @return string
		 */
		private function get_core_class_name() {
			if ( class_exists( 'WPFactory_WC_EU_VAT_Core' ) ) {
				return 'WPFactory_WC_EU_VAT_Core';
			}
			if ( class_exists( 'Alg_WC_EU_VAT_Core' ) ) {
				return 'Alg_WC_EU_VAT_Core';
			}

			return '';
		}

		public function is_enable() {
			return '' !== $this->get_core_class_name();
		}

		public function call_to_action( $field, $key, $args ) {

			if ( empty( $key ) || $key !== 'wfacp_eu_vat_manager' || ! is_a( $this->instance, $this->get_core_class_name() ) ) {
				return '';
			}

			$new_fields = $this->instance->add_eu_vat_checkout_field_to_frontend( array() );

			if ( ! isset( $new_fields['billing'] ) || ! is_array( $new_fields['billing'] ) ) {
				return;
			}

			$show_countries = $this->get_show_in_countries();
			$wrapper_style  = '';

			if ( ! empty( $show_countries ) ) {
				$billing_country = '';
				if ( WC()->checkout() ) {
					$billing_country = WC()->checkout()->get_value( 'billing_country' );
				}
				if ( ! empty( $billing_country ) && ! in_array( strtoupper( $billing_country ), $show_countries, true ) ) {
					$wrapper_style = ' style="display:none;"';
				}
			}

			echo '<div id="wfacp_eu_vat_wrapper"' . $wrapper_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( $new_fields['billing'] as $key => $field ) {
				// WPFactory returns internal/state fields alongside the input (e.g. the "VAT exempt"
				// boolean); rendering those leaks raw "VAT exempt: false/true" text to the shopper.
				// Whitelist the VAT-number input only — its validation UI is repainted by WPFactory's
				// own frontend JS through the update_checkout round-trip.
				if ( 'billing_eu_vat_number' !== $key ) {
					continue;
				}
				$this->keys[] = $key;
				woocommerce_form_field( $key, $field );
			}
			echo '</div>';
		}


		private function get_show_in_countries() {
			$countries = apply_filters( 'alg_wc_eu_vat_show_in_countries', '' );

			if ( empty( $countries ) || ! is_string( $countries ) ) {
				return array();
			}

			return array_filter( array_map( 'strtoupper', array_map( 'trim', explode( ',', $countries ) ) ) );
		}

		/**
		 * Print the frontend VAT bridge: country show/hide + update_checkout re-validation.
		 *
		 * Re-validation rides the standard update_checkout AJAX round-trip (exactly like the
		 * official-WC EU VAT compat), so WPFactory re-validates server-side and repaints its
		 * own response fragments. It is never gated on a blocking async promise or the multistep
		 * Next button (wfacp_next_button_trigger), which is what deadlocked the Next button.
		 *
		 * @since 3.15.0.8
		 *
		 * @return void
		 */
		public function internal_js() {
			if ( ! $this->is_enable() ) {
				return;
			}
			$show_countries = $this->get_show_in_countries();
			?>
			<script id="wfacp-eu-vat-compat">
				(function ($) {
					'use strict';
					// Dedupe cache shared by the country toggle and the re-validation bind,
					// declared at IIFE top so both reference the same map unambiguously.
					var last = {};
					<?php if ( ! empty( $show_countries ) ) : ?>
					// Show/hide the relocated VAT wrapper based on the billing country.
					var wfacp_eu_vat_countries = <?php echo wp_json_encode( array_values( $show_countries ) ); ?>;

					function wfacp_toggle_eu_vat_field() {
						var country  = $( '#billing_country' ).val();
						var $wrapper = $( '#wfacp_eu_vat_wrapper' );
						if ( ! country || ! $wrapper.length ) {
							return;
						}
						if ( wfacp_eu_vat_countries.indexOf( country.toUpperCase() ) !== -1 ) {
							$wrapper.show();
						} else {
							// Hidden for a non-listed country: clear the input so the stale value
							// (and WPFactory's response inside the wrapper) can't gate the Next button.
							$wrapper.hide();
							$wrapper.find( 'input[type="text"]' ).each( function () {
								// Keep the dedupe cache in sync with the programmatic clear: a .val('')
								// fires no 'change', so last[id] would otherwise retain the old VAT string
								// and short-circuit re-validation when the same number is re-entered.
								delete last[ this.id || 'billing_eu_vat_number' ];
							} ).val( '' );
						}
					}

					$( function () {
						$( '#billing_country' ).on( 'change', wfacp_toggle_eu_vat_field );
						$( document.body ).on( 'country_to_state_changed updated_checkout', wfacp_toggle_eu_vat_field );
						wfacp_toggle_eu_vat_field();
					} );
					<?php endif; ?>

					// Re-validate the VAT number through the standard update_checkout round-trip.
					// Delegated to the form and re-applied on each fragment refresh so it survives
					// FunnelKit re-emitting the field on step transitions (a direct bind would die
					// after the first transition). Never touches wfacp_next_button_trigger.
					var formSel = 'form.checkout, form#order_review';
					var vatWrap = '#billing_eu_vat_number_field';

					function wfacp_bind_eu_vat() {
						setTimeout( function () {
							$( formSel ).off( 'change', vatWrap ).on( 'change', vatWrap, function () {
								var $input = $( this ).find( 'input' ).first();
								if ( ! $input.length ) {
									return;
								}
								var id  = $input.attr( 'id' ) || 'billing_eu_vat_number';
								var val = ( $input.val() || '' ).trim().toUpperCase();
								// Dedupe: only re-validate when the uppercased value actually changed.
								if ( last[ id ] === val ) {
									return;
								}
								last[ id ] = val;
								$( 'body' ).trigger( 'update_checkout' );
							} );
						}, 0 );
					}

					$( function () {
						wfacp_bind_eu_vat();
						$( document.body ).on( 'updated_checkout', wfacp_bind_eu_vat );
					} );
				})( jQuery );
			</script>
			<?php
		}

		/**
		 * Print inline CSS so the relocated VAT field reads like native WooCommerce checkout.
		 *
		 * Only positions the field label/input and its description; the validation-result
		 * markup and strings are owned and repainted by WPFactory's own frontend response.
		 *
		 * @since 3.15.0.8
		 *
		 * @return void
		 */
		public function internal_css() {
			if ( ! $this->is_enable() ) {
				return;
			}
			?>
			<style>
				body .wfacp_main_form.woocommerce #billing_eu_vat_number_field:not(.wfacp-anim-wrap) label {
					top: 20px;
					margin: 0;
					bottom: auto;
					line-height: 1;
				}

				body .wfacp_main_form.woocommerce #billing_eu_vat_number {
					margin-bottom: 6px;
				}

				body .wfacp_main_form.woocommerce #billing_eu_vat_number_field span#billing_eu_vat_number-description {
					font-size: 13px;
					line-height: 18px;
					color: #777777;
				}
			</style>
			<?php
		}

		public function add_default_styling( $args, $key ) {

			if ( ! $this->is_enable() || ! in_array( $key, $this->keys, true ) ) {
				return $args;
			}

			if ( isset( $args['type'] ) && 'checkbox' !== $args['type'] ) {

				$args['input_class'] = array_merge( array( 'wfacp-form-control' ), $args['input_class'] );
				$args['label_class'] = array_merge( array( 'wfacp-form-control-label' ), $args['label_class'] );
				$args['class']       = array_merge( array( 'wfacp-form-control-wrapper wfacp-col-full' ), $args['class'] );
				$args['cssready']    = array( 'wfacp-col-full' );

			} else {
				$args['class']    = array_merge( array( 'wfacp-form-control-wrapper wfacp-col-full ' ), $args['class'] );
				$args['cssready'] = array( 'wfacp-col-full' );
			}

			return $args;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_EU_UK_Vat_Manager_For_WC(), 'wfacp-wc-eu-vat' );
}


