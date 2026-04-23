<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- Compatibility class uses simplified file name convention
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_WC_Germanized' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WC_Germanized {
		public function __construct() {
			/* checkout page */
			add_action( 'wfacp_form_widgets_elementor_editor', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_mini_cart_widgets_elementor_editor', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_actions' ) );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'update_order_review' ) );
			add_action( 'wfacp_get_fragments', array( $this, 'wfacp_get_fragments' ) );
			add_action( 'wfob_before_add_to_cart', array( $this, 'removed_Germanized_action' ) );
			add_action( 'wfob_before_remove_bump_from_cart', array( $this, 'removed_Germanized_action' ) );
			add_action( 'wfacp_woocommerce_review_order_before_submit', array( $this, 'remove_order_button_html_filter' ), 30 );
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'germanized_terms' ) );

			/* Remove the place order button text  */
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_place_order_button_text' ), 11 );
			add_action( 'wfacp_checkout_page_found', array( $this, 'remove_place_order_button_text' ), 11 );
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'remove_place_order_button_text' ), 11 );

			add_action( 'wfacp_template_load', array( $this, 'remove_action_for_shipping' ) );
			add_action( 'init', array( $this, 'setup_fields_billing' ), 20 );

			/* Fix Terms and Conditions compatibility - only adjust the filter, don't move hooks */
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'fix_terms_filter' ], 12 );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'wfacp_germanized_terms_on_ajax' ), 0 );

			/*--------------------------Add Internal Css----------------------------------------*/
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
		}

		/**
		 * Add backend notice when Germanized Pro multistep checkout is enabled.
		 *
		 * @param array $messages Existing dependency messages.
		 * @return array
		 */
		public function add_germanized_multistep_notice( $messages ) {
			if ( ! class_exists( 'WC_GZDP_Multistep_Checkout' ) ) {
				return $messages;
			}
			if ( 'yes' !== get_option( 'woocommerce_gzdp_checkout_enable', 'no' ) ) {
				return $messages;
			}
			$messages[] = array(
				'message'     => __( 'Germanized Pro Multistep Checkout is enabled. This may interrupt the checkout flow. We recommend disabling this feature.', 'woofunnels-aero-checkout' ),
				'id'          => '',
				'show'        => 'yes',
				'dismissible' => true,
				'is_global'   => true,
				'type'        => 'wfacp_warning',
			);
			return $messages;
		}

		public function germanized_terms() {

			if ( ! function_exists( 'woocommerce_gzd_template_render_checkout_checkboxes' ) ) {
				return;
			}

			// Remove Germanized checkboxes from incompatible locations
			if ( class_exists( 'WC_GZD_Compatibility_Elementor_Pro' ) ) {
				remove_action( 'woocommerce_checkout_order_review', 'woocommerce_gzd_template_render_checkout_checkboxes', 19 );
			}

			// Keep legal checkboxes in submit section and before Place Order button.
			// Use FunnelKit submit hook because Germanized rewires callbacks on
			// woocommerce_review_order_before_submit at runtime.
			
			remove_action( 'woocommerce_review_order_after_payment', 'woocommerce_gzd_template_render_checkout_checkboxes', 10 );
			remove_action( 'woocommerce_review_order_after_payment', 'woocommerce_gzd_template_render_checkout_checkboxes', 99 );
			remove_action( 'woocommerce_review_order_before_submit', 'woocommerce_gzd_template_render_checkout_checkboxes', 9 );
			add_action( 'wfacp_woocommerce_review_order_before_submit', 'woocommerce_gzd_template_render_checkout_checkboxes', 9 );
		}

		public function remove_actions() {
			if ( class_exists( 'WooCommerce_Germanized' ) && WFACP_Common::get_id() > 0 ) {
				add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'refresh_order_submit' ), 11, 1 );
				$this->actions();
				add_action( 'wp_enqueue_scripts', array( $this, 'remove_css' ), 99 );
				WFACP_Common::remove_actions( 'woocommerce_review_order_before_shipping', 'WC_GZD_Checkout', 'remove_shipping_rates' );
				add_filter( 'wfacp_display_place_order_buttons', array( $this, 'do_not_display_native_submit_button' ) );

				remove_action( 'woocommerce_review_order_before_payment', 'woocommerce_gzd_template_checkout_payment_title' );
				add_filter( 'woocommerce_gzd_checkout_table_needs_thumbnail', '__return_false' );
				$this->remove_button_hiding_filters();
			}
		}

		private function actions() {
			if ( class_exists( 'WooCommerce_Germanized' ) && function_exists( 'wc_gzd_get_hook_priority' ) ) {
				remove_action( 'woocommerce_review_order_after_order_total', 'woocommerce_gzd_template_cart_total_tax', 1 );
				remove_action( 'woocommerce_review_order_before_cart_contents', 'woocommerce_gzd_template_checkout_remove_cart_name_filter' );
				remove_action( 'woocommerce_review_order_before_cart_contents', 'woocommerce_gzd_template_checkout_table_content_replacement' );
				remove_action( 'woocommerce_review_order_after_cart_contents', 'woocommerce_gzd_template_checkout_table_product_hide_filter_removal' );
				remove_filter( 'woocommerce_checkout_cart_item_quantity', 'wc_gzd_cart_product_units', wc_gzd_get_hook_priority( 'checkout_product_units' ) );
				remove_filter( 'woocommerce_checkout_cart_item_quantity', 'wc_gzd_cart_product_delivery_time', wc_gzd_get_hook_priority( 'checkout_product_delivery_time' ) );
				remove_filter( 'woocommerce_checkout_cart_item_quantity', 'wc_gzd_cart_product_item_desc', wc_gzd_get_hook_priority( 'checkout_product_item_desc' ) );
				remove_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', wc_gzd_get_hook_priority( 'checkout_order_review' ) );
				remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', wc_gzd_get_hook_priority( 'checkout_payment' ) );
				// FunnelKit renders its own submit button in payment templates.
				// Remove Germanized's custom submit injection to avoid duplicate place order buttons.
				remove_action( 'woocommerce_checkout_order_review', 'woocommerce_gzd_template_order_submit', wc_gzd_get_hook_priority( 'checkout_order_submit' ) );
				remove_action( 'woocommerce_checkout_after_order_review', 'woocommerce_gzd_template_order_submit_fallback', 50 );
				if ( ! is_null( WC()->session ) ) {
					$paypal_express_checkout_angle_eye = WC()->session->get( 'paypal_express_checkout', null );
					$paypal_express_checkout           = WC()->session->get( 'paypal', null );
					if ( ! is_null( $paypal_express_checkout_angle_eye ) || ! is_null( $paypal_express_checkout ) ) {
						add_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
					}
				}
			}
		}

		public function refresh_order_submit( $fragments ) {
			if ( isset( $fragments['.wc-gzd-order-submit'] ) ) {
				unset( $fragments['.wc-gzd-order-submit'] );
			}

			return $fragments;
		}

		private function remove_button_hiding_filters() {
			remove_action( 'woocommerce_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_remove_filter', 1500 );
			remove_action( 'woocommerce_review_order_after_submit', 'woocommerce_gzd_template_set_order_button_show_filter', 1500 );
			remove_action( 'woocommerce_gzd_review_order_before_submit', 'woocommerce_gzd_template_set_order_button_show_filter', 1500 );
			remove_filter( 'woocommerce_order_button_html', 'woocommerce_gzd_template_button_temporary_hide', 1500 );
		}

		public function update_order_review( $postdata ) {
			$post_data = array();
			parse_str( $postdata, $post_data );
			if ( isset( $post_data['_wfacp_post_id'] ) ) {
				$this->actions();
				$this->remove_button_hiding_filters();
			}
		}

		public function wfacp_get_fragments( $wfacp_id ) {
			if ( $wfacp_id > 0 ) {
				$this->actions();
				$this->remove_button_hiding_filters();
			}
		}

		public function removed_Germanized_action() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Read-only check for admin page identification parameter, not a state-changing form submission
			if ( isset( $_REQUEST['wfacp_post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Read-only check for request parameter
				$this->actions();
			}
		}

		public function remove_css() {
			wp_dequeue_style( 'woocommerce-gzd-layout' );
		}

		public function do_not_display_native_submit_button( $status ) {
			if ( WFACP_Core()->public->is_checkout_override() ) {
				return $status;
			}

			if ( class_exists( 'WooCommerce_Germanized' ) && WFACP_Common::get_id() > 0 ) {
				return true;
			}

			if ( ! WFACP_Common::is_theme_builder() ) {
				$status = false;
			}

			return $status;
		}

		public function remove_order_button_html_filter() {
			if ( function_exists( 'wc_gzd_checkout_adjustments_disabled' ) && wc_gzd_checkout_adjustments_disabled() ) {
				return;
			}

			$this->remove_button_hiding_filters();
		}

		public function remove_place_order_button_text() {
			if ( ! function_exists( 'woocommerce_gzd_template_order_button_text' ) ) {
				return;
			}
			remove_filter( 'woocommerce_order_button_text', 'woocommerce_gzd_template_order_button_text', 9999 );
			add_filter( 'woocommerce_gzd_order_button_payment_gateway_text', '__return_empty_string' );
		}

		public function remove_action_for_shipping() {
			// Check for both possible class namespaces (legacy and current)
			$parcel_services_class = null;
			if ( class_exists( 'Vendidero\Germanized\DHL\ParcelServices' ) ) {
				$parcel_services_class = 'Vendidero\Germanized\DHL\ParcelServices';
			} elseif ( class_exists( 'Vendidero\Shiptastic\DHL\ParcelServices' ) ) {
				$parcel_services_class = 'Vendidero\Shiptastic\DHL\ParcelServices';
			}

			if ( ! $parcel_services_class ) {
				return;
			}

			// Prevent duplicate action registration if method is called multiple times
			static $already_processed = false;
			if ( $already_processed ) {
				return;
			}
			$already_processed = true;

			// Remove actions from hooks where ParcelServices attaches
			// ParcelServices hooks maybe_output_fields to both payment hooks, which then fires
			// woocommerce_shiptastic_dhl_preferred_service_fields action that calls add_fields
			WFACP_Common::remove_actions( 'woocommerce_review_order_after_payment', $parcel_services_class, 'maybe_output_fields' );
			WFACP_Common::remove_actions( 'woocommerce_review_order_before_payment', $parcel_services_class, 'maybe_output_fields' );

			// Also remove add_fields from the action hook in case it was added directly
			WFACP_Common::remove_actions( 'woocommerce_shiptastic_dhl_preferred_service_fields', $parcel_services_class, 'add_fields' );

			// Add to WFACP custom hook only if not already added
			if ( ! has_action( 'wfacp_woocommerce_review_order_after_shipping', array( $parcel_services_class, 'add_fields' ) ) ) {
				add_action(
					'wfacp_woocommerce_review_order_after_shipping',
					array(
						$parcel_services_class,
						'add_fields',
					),
					100
				);
			}
		}

		public function setup_fields_billing() {
			if ( ! class_exists( 'WooCommerce_Germanized_Pro' ) || get_option( 'woocommerce_gzdp_enable_vat_check' ) === 'no' ) {
				return;
			}
			new WFACP_Add_Address_Field(
				'vat_id',
				array(
					'type'        => 'text',
					'label'       => __( 'VAT ID', 'woocommerce-germanized-pro' ),
					'placeholder' => __( 'VAT ID', 'woocommerce-germanized-pro' ),
					'cssready'    => array( 'wfacp-col-left-third' ),
					'class'       => array( 'form-row-third first', 'wfacp-col-full' ),
					'required'    => false,
					'priority'    => 60,
				)
			);
			new WFACP_Add_Address_Field(
				'vat_id',
				array(
					'type'        => 'text',
					'label'       => __( 'VAT ID', 'woocommerce-germanized-pro' ),
					'placeholder' => __( 'VAT ID', 'woocommerce-germanized-pro' ),
					'cssready'    => array( 'wfacp-col-left-third' ),
					'class'       => array( 'form-row-third first', 'wfacp-col-full' ),
					'required'    => false,
					'priority'    => 60,
				),
				'shipping'
			);
		}

		public function internal_css( $selected_template_slug ) {

			if ( function_exists( 'wfacp_template' ) ) {
				$instance = wfacp_template();
			}
			if ( is_null( $instance ) ) {
				return;
			}
		$px = $instance->get_template_type_px();
		if ( ! isset( $px ) || $px === '' ) {
			return;
		}

		$body_class = 'body';
		if ( 'pre_built' !== $instance->get_template_type() ) {
			$body_class = 'body #wfacp-e-form ';
		}

		echo '<style>';
		echo 'body .wfacp_main_form .form-row.checkbox-legal .woocommerce-form__label-for-checkbox span.woocommerce-gzd-legal-checkbox-text{padding-left: 0;}';
		if ( $selected_template_slug === 'layout_9' || $selected_template_slug === 'layout_1' ) {
			echo 'body .wfacp_main_form .wc-gzd-checkbox-placeholder.wc-gzd-checkbox-placeholder-legal{padding: 0;}';
		}
		echo '#wfacp-e-form .wc-gzd-checkbox-placeholder.wc-gzd-checkbox-placeholder-legal {margin-top: 15px;}';
		echo $body_class . ' .woocommerce form .wc-gzd-checkbox-placeholder {float: none;}';
		echo $body_class . '.wc-gzd-order-submit {margin-bottom: 25px;}';
		echo '</style>';

		if ( WFACP_Common::is_customizer() ) {
			echo '<style>';
			echo '#payment button#place_order {display: none;}';
			echo '</style>';
		}
		}

		/**
		 * Fix Terms and Conditions filter conflict
		 *
		 * Germanized hides ALL WC terms with a blanket filter. We need to restore
		 * FunnelKit terms when Germanized checkboxes are NOT active.
		 * This only touches the FILTER, not the rendering hooks (preventing duplication).
		 *
		 * @return void
		 */
		public function fix_terms_filter() {
			if ( ! function_exists( 'woocommerce_gzd_template_set_wc_terms_hide' ) ) {
				return;
			}

			// Remove Germanized's blanket filter
			remove_filter( 'woocommerce_checkout_show_terms', 'woocommerce_gzd_template_set_wc_terms_hide', 100 );

			if ( false === has_filter( 'woocommerce_checkout_show_terms', array( $this, 'conditional_terms_display' ) ) ) {
				add_filter( 'woocommerce_checkout_show_terms', [ $this, 'conditional_terms_display' ], 100 );
			}
		}

		/**
		 * @param string $posted_data Posted checkout fields.
		 */
		public function wfacp_germanized_terms_on_ajax( $posted_data ) {
			if ( 0 !== did_action( 'wfacp_after_checkout_page_found' ) ) {
				return;
			}
			$post_data = array();
			parse_str( (string) $posted_data, $post_data );
			if ( empty( $post_data['_wfacp_post_id'] ) ) {
				return;
			}
			$this->germanized_terms();
			$this->fix_terms_filter();
		}

		/**
		 * Conditionally show/hide WC terms based on Germanized's actual state
		 *
		 * @param bool $show Whether to show terms.
		 * @return bool
		 */
		public function conditional_terms_display( $show ) {
			// If Germanized adjustments are disabled globally, show FunnelKit terms
			if ( function_exists( 'wc_gzd_checkout_adjustments_disabled' ) && wc_gzd_checkout_adjustments_disabled() ) {
				return $show;
			}

			if ( has_action( 'woocommerce_review_order_after_payment', 'woocommerce_gzd_template_render_checkout_checkboxes' ) ) {
				return false;
			}
			$gzd_terms = function_exists( 'wc_gzd_get_legal_checkbox' ) ? wc_gzd_get_legal_checkbox( 'terms' ) : false;
			if ( $gzd_terms && $gzd_terms->is_enabled() && in_array( 'checkout', (array) $gzd_terms->get_locations(), true ) ) {
				return false;
			}

			return $show;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WC_Germanized(), 'wc_germanized' );
}
