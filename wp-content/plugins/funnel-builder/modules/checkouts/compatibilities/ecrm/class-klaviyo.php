<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * plugin name Klaviyo by Klaviyo, Inc. (3.3.5)
 * Plugin URI: https://wordpress.org/plugins/klaviyo/
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Klaviyo' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Klaviyo {

		private $billing_new_fields = array(
			'kl_newsletter_checkbox',
			'billing_kl_newsletter_checkbox',
			'billing_kl_sms_consent_checkbox',
			'kl_sms_consent_checkbox',

		);

		private $sms_consent_text = false;

		private $klavio_settings = array();


		public function __construct() {

			if ( WFACP_Common::is_funnel_builder_3() ) {
				add_action( 'wffn_rest_checkout_form_actions', array( $this, 'add_field' ) );
			} else {
				add_action( 'init', array( $this, 'add_field' ), 20 );
			}

			add_filter( 'wfacp_advanced_fields', array( $this, 'add_fields' ) );
			add_action( 'wfacp_after_template_found', array( $this, 'setup' ) );
			add_filter( 'wfacp_checkout_data', array( $this, 'prepare_checkout_data' ), 10, 2 );

			/**
			 * Prevent to display registered field
			 */
			add_filter( 'wfacp_html_fields_billing_kl_newsletter_checkbox', '__return_false' );
			add_filter( 'wfacp_html_fields_billing_kl_sms_consent_checkbox', '__return_false' );

			/**
			 * Process the field to display klavio field
			 */

			add_action( 'process_wfacp_html', array( $this, 'display_field' ), 10, 3 );

			/* prevent third party fields and wrapper*/
			add_filter( 'wfacp_third_party_billing_fields', array( $this, 'disabled_third_party_billing_fields' ), 9999 );

			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_other_action' ) );

			// Klaviyo prints its compliance disclaimer on woocommerce_after_checkout_billing_form, which
			// Aero fires inside the Billing block. remove_other_action() also removes it, but runs on a
			// hook that doesn't fire on every template (e.g. Divi); priority 9 here runs just before
			// Klaviyo's priority 10 on every template. We render it next to the checkbox instead. FB #9191.
			add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'remove_kl_billing_compliance' ), 9 );

			add_action( 'wfacp_after_kl_sms_consent_checkbox_field', array( $this, 'kl_sms_consent' ) );
		}

		public function remove_kl_billing_compliance() {
			// Remove both the legacy (kl_sms_compliance_text) and current (kl_mobile_compliance_text)
			// callback names — Klaviyo renamed it across versions.
			remove_filter( 'woocommerce_after_checkout_billing_form', 'kl_sms_compliance_text' );
			remove_filter( 'woocommerce_after_checkout_billing_form', 'kl_mobile_compliance_text' );
		}

		public function add_field() {

			if ( ! version_compare( WooCommerceKlaviyo::getVersion(), '2.4.0', '>' ) ) {
				return;
			}

			$klaviyo_settings = get_option( 'klaviyo_settings' );
			new WFACP_Add_Address_Field(
				'kl_newsletter_checkbox',
				array(
					'label' => __( 'Klaviyo Newsletter', 'woocommerce-klaviyo' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- reusing Klaviyo's own label/domain.
				'type'      => 'wfacp_html',
				'cssready'  => array( 'wfacp-col-left-half', 'kl_newsletter_checkbox_field' ),
				'required'  => false,
				)
			);

			/**
			 * Register the SMS consent checkbox as a placeable field whenever the merchant has
			 * enabled the Klaviyo SMS subscribe checkbox. The list id is only required for the
			 * compliance text to render (see kl_sms_consent()), so gating field availability on it
			 * left merchants unable to place the field while still configuring Klaviyo. FB #9191.
			 */
			if ( isset( $klaviyo_settings['klaviyo_sms_subscribe_checkbox'] ) && wc_string_to_bool( $klaviyo_settings['klaviyo_sms_subscribe_checkbox'] ) ) {
				new WFACP_Add_Address_Field(
					'kl_sms_consent_checkbox',
					array(
						'label' => __( 'Klaviyo SMS Consent', 'woocommerce-klaviyo' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- reusing Klaviyo's own label/domain.
					'type'      => 'wfacp_html',
					'cssready'  => array( 'wfacp-col-left-half', 'kl_sms_consent_checkbox_field' ),
					'required'  => false,
					)
				);
			}
		}


		public function add_fields( $fields ) {

			$fields['kl_newsletter_checkbox'] = array(
				'type'          => 'checkbox',
				'default'       => 0,
				'label'         => __( 'Klaviyo', 'woocommerce-klaviyo' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- reusing Klaviyo's own label/domain.
				'validate'      => array(),
				'id'            => 'kl_newsletter_checkbox',
				'required'      => false,
				'wrapper_class' => array(),
				'class'         => array( 'kl_newsletter_checkbox_field' ),
			);

			$settings = get_option( 'klaviyo_settings' );
			if ( ! ( isset( $settings['klaviyo_sms_subscribe_checkbox'] ) && wc_string_to_bool( $settings['klaviyo_sms_subscribe_checkbox'] ) ) ) {
				return $fields;
			}
			$fields['kl_sms_consent_checkbox'] = array(
				'type'          => 'checkbox',
				'default'       => 0,
				'label'         => __( 'Subscribe to SMS updates', 'woocommerce-klaviyo' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- reusing Klaviyo's own label/domain.
				'validate'      => array(),
				'id'            => 'kl_sms_consent_checkbox',
				'required'      => false,
				'wrapper_class' => array(),
				'class'         => array( 'kl_sms_consent_checkbox_field' ),
			);

			return $fields;
		}

		public function setup() {
			add_filter( 'woocommerce_form_field_args', array( $this, 'add_default_wfacp_styling' ), 10, 2 );
			add_action( 'wfacp_internal_css', array( $this, 'js_event' ), 100 );
			$this->remove_actions();
		}

		public function remove_actions() {
			if ( ! function_exists( 'checkout_additional_checkboxes' ) ) {
				return;
			}
			$klaviyo_settings = get_option( 'klaviyo_settings' );

			if ( ! empty( $klaviyo_settings['klaviyo_newsletter_list_id'] ) ) {
				remove_action( 'woocommerce_checkout_before_terms_and_conditions', 'checkout_additional_checkboxes' );
			}
		}

		public function add_default_wfacp_styling( $args, $key ) {
			if ( ! in_array( $key, array( 'kl_newsletter_checkbox', 'kl_sms_consent_checkbox' ) ) ) {
				return $args;
			}

			$klaviyo_settings = get_option( 'klaviyo_settings' );
			if ( $key == 'kl_newsletter_checkbox' && ! empty( $klaviyo_settings['klaviyo_newsletter_text'] ) ) {
				$args['label'] = $klaviyo_settings['klaviyo_newsletter_text'];
			}

			if ( $key == 'kl_sms_consent_checkbox' ) {
				$args['label'] = ! empty( $klaviyo_settings['klaviyo_sms_consent_text'] ) ? $klaviyo_settings['klaviyo_sms_consent_text'] : $args['label'];
				if ( isset( $args['description'] ) ) {
					$args['description'] = '';
				}
			}

			return $args;
		}

		/**
		 * @param $checkout_data
		 * @param $cart WC_Cart;
		 *
		 * @return mixed
		 */
		public function prepare_checkout_data( $checkout_data, $cart ) {
			$items = $cart->get_cart_contents();
			if ( empty( $items ) ) {
				return $checkout_data;
			}
			$event_data = array(
				'$service' => 'woocommerce',
				'$value'   => $cart->total,
				'$extra'   => array(
					'Items'         => array(),
					'SubTotal'      => $cart->subtotal,
					'ShippingTotal' => $cart->shipping_total,
					'TaxTotal'      => $cart->tax_total,
					'GrandTotal'    => $cart->total,
				),
			);

			foreach ( $cart->get_cart() as $cart_item_key => $values ) {
				/**
				 * @var $product WC_Product;
				 */
				$product = $values['data'];

				$event_data['$extra']['Items'] [] = array(
					'Quantity'     => $values['quantity'],
					'ProductID'    => $product->get_id(),
					'Name'         => $product->get_title(),
					'URL'          => $product->get_permalink(),
					'Images'       => array(
						array(
							'URL' => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
						),
					),
					'Categories'   => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
					'Description'  => $product->get_description(),
					'Variation'    => $values['variation'],
					'SubTotal'     => $values['line_subtotal'],
					'Total'        => $values['line_subtotal_tax'],
					'LineTotal'    => $values['line_total'],
					'Tax'          => $values['line_tax'],
					'TotalWithTax' => $values['line_total'] + $values['line_tax'],
				);
			}
			$checkout_data['klaviyo'] = $event_data;

			return $checkout_data;
		}

		public function display_field( $field, $key, $args ) {

			if ( empty( $key ) && in_array( $key, array( 'billing_kl_sms_consent_checkbox' ) ) ) {
				return '';
			}

			if ( ! is_array( $this->klavio_settings ) || count( $this->klavio_settings ) == 0 ) {
				return '';
			}

			$kl_fields = array();

			if ( $key == 'billing_kl_newsletter_checkbox' && function_exists( 'kl_checkbox_custom_checkout_field' ) ) {
				$tmpfield = kl_checkbox_custom_checkout_field( array() );

				if ( isset( $tmpfield['billing']['kl_newsletter_checkbox'] ) ) {
					$kl_fields['kl_newsletter_checkbox'] = $tmpfield['billing']['kl_newsletter_checkbox'];
				}
			} elseif ( $key == 'billing_kl_sms_consent_checkbox' && function_exists( 'kl_sms_consent_checkout_field' ) ) {
				$tmpfield = kl_sms_consent_checkout_field( array() );
				if ( isset( $tmpfield['billing']['kl_sms_consent_checkbox'] ) ) {
					$kl_fields['kl_sms_consent_checkbox'] = $tmpfield['billing']['kl_sms_consent_checkbox'];
				}
			}
			$template = wfacp_template();
			if ( ! $template instanceof WFACP_Template_Common ) {
				return $field;
			}

			$checkout = WC()->checkout();
			if ( count( $kl_fields ) > 0 ) {
				$kl_sms_consent_checkbox = false;
				foreach ( $kl_fields as $i => $kl_field ) {
					if ( ! isset( $template->already_printed_fields[ $i ] ) ) {

						if ( $i === 'kl_sms_consent_checkbox' ) {
							$kl_sms_consent_checkbox = true;
						}
						$field_value = $checkout->get_value( $i );
						wfacp_form_field( $i, $kl_field, $field_value );
						$template->already_printed_fields[ $i ] = 'yes';
					}
				}
				if ( $kl_sms_consent_checkbox ) {
					/**
					 * Render the SMS compliance text immediately after the consent checkbox so it
					 * travels with the field wherever the merchant places it. Previously this was
					 * silently hooked to `wfacp_divider_billing_end`, which anchored the legally
					 * sensitive disclaimer at the tail of the billing block — under the
					 * "use a different billing address" toggle — with no merchant intent. FB #9191.
					 */
					$this->kl_sms_consent();
				}
			}
		}

		public function remove_other_action() {

			$this->klavio_settings = get_option( 'klaviyo_settings' );

			remove_filter( 'woocommerce_after_checkout_billing_form', 'kl_sms_compliance_text' );
			remove_filter( 'woocommerce_after_checkout_billing_form', 'kl_mobile_compliance_text' );
		}

		public function kl_sms_consent() {

			if ( ! is_array( $this->klavio_settings ) || count( $this->klavio_settings ) <= 0 ) {
				// remove_other_action() (which populates this) runs on a hook that doesn't fire on every
				// template, e.g. Divi — so lazy-load here, otherwise the disclaimer never renders. FB #9191.
				$this->klavio_settings = get_option( 'klaviyo_settings', array() );
			}

			if ( ! is_array( $this->klavio_settings ) || count( $this->klavio_settings ) <= 0 ) {
				return;
			}
			if ( $this->sms_consent_text == true ) {
				return;
			}

			// Klaviyo renamed its compliance function across versions
			// (kl_sms_compliance_text -> kl_mobile_compliance_text); use whichever exists, else fall back
			// to the configured text. Needs the SMS subscribe checkbox enabled + text set (no list id).
			$disclosure  = isset( $this->klavio_settings['klaviyo_sms_consent_disclosure_text'] ) ? trim( (string) $this->klavio_settings['klaviyo_sms_consent_disclosure_text'] ) : '';
			$sms_enabled = ! empty( $this->klavio_settings['klaviyo_sms_subscribe_checkbox'] );

			if ( $sms_enabled && '' !== $disclosure ) {
				$this->sms_consent_text = true;
				echo '<p class="kl_sms_compliance_text form-row wfacp-form-control-wrapper wfacp-col-full kl_sms_consent_checkbox_field ">';
				if ( function_exists( 'kl_sms_compliance_text' ) ) {
					kl_sms_compliance_text();
				} elseif ( function_exists( 'kl_mobile_compliance_text' ) ) {
					kl_mobile_compliance_text();
				} else {
					echo esc_html( $disclosure );
				}
				echo '</p>';
			}
		}

		public function disabled_third_party_billing_fields( $fields ) {
			if ( is_array( $this->billing_new_fields ) && count( $this->billing_new_fields ) ) {
				foreach ( $this->billing_new_fields as $i => $key ) {
					if ( isset( $fields[ $key ] ) ) {

						unset( $fields[ $key ] );
					}
				}
			}

			return $fields;
		}


		public function js_event() {
			?>
			<style>
				#kl_sms_consent_checkbox-description {
					display: block !important;
				}
			</style>
			<script>
				window.addEventListener('bwf_checkout_load', function () {
					try {
						(function ($) {

							let _learnq = window.klaviyo || window._learnq;
							if (typeof _learnq == "undefined") {
								return;
							}

							$(document.body).on('change', '#billing_email', function () {
								if (typeof wfacp_storage.klaviyo != 'undefined') {
									_learnq.push(["track", "$started_checkout", wfacp_storage.klaviyo])
								}
							});
							$(document.body).on('wfacp_checkout_data', function (e, v) {
								if (typeof v !== "object") {
									return;
								}
								if (!v.hasOwnProperty('checkout')) {
									return;
								}

								if (!v.checkout.hasOwnProperty('klaviyo')) {
									return;
								}
								wfacp_storage.klaviyo = v.checkout.klaviyo;
								_learnq.push(["track", "$started_checkout", v.checkout.klaviyo])
							});
						})(jQuery);
					} catch (e) {
					}
				});
			</script>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Klaviyo(), 'klaviyo' );
}