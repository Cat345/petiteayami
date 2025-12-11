<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager as Control_Manager;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! class_exists( 'El_WFACP_Form_Summary' ) ) {
	#[AllowDynamicProperties]
	class El_WFACP_Form_Summary extends WFACP_Elementor_HTML_BLOCK {

		public function get_name() {
			return 'wfacp_form_summary';
		}

		public function get_title() {
			return __( 'Mini Cart', 'woofunnels-aero-checkout' );
		}

		public function get_icon() {
			return 'wfacp-icon-icon_minicart';
		}

		public function get_categories() {
			return [ 'woofunnels-aero-checkout' ];
		}

		/**
		 * _register_controls function DEPRECATED in 3.1.0 version of elementor 24-01-2021
		 * @return void
		 */
		protected function register_controls() {
			$this->mini_cart();

		}

		protected function mini_cart() {
			$this->add_tab( __( 'Heading', 'woofunnels-aero-checkout' ) );
			$this->add_text( 'mini_cart_heading', __( 'Title', 'woofunnels-aero-checkout' ), __( 'Order Summary', 'woofunnels-aero-checkout' ) );
			$this->end_tab();

			$this->add_tab( __( 'Products', 'woofunnels-aero-checkout' ) );

			$this->add_switcher( 'enable_product_image', __( 'Image', 'woofunnels-aero-checkout' ), '', '', 'yes', 'yes', [], 'yes', 'yes', 'wfacp_elementor_device_hide' );
			$this->add_switcher( 'enable_quantity_box', __( 'Quantity Switcher', 'woofunnels-aero-checkout' ), '', '', 'no', 'yes', [], 'no', 'no', 'wfacp_elementor_device_hide' );
			$this->add_switcher( 'enable_delete_item', __( 'Allow Deletion', 'woofunnels-aero-checkout' ), '', '', 'no', 'yes', [], 'no', 'no', 'wfacp_elementor_device_hide' );

			/**
			 * -----------------------------Strike Through Price Setting on the mini cart-------------------------------------
			 */
			$this->price_strike_through_content_settings( 'mini_cart' );

			/*-------------------------------------------------------- End -----------------------------------------------------*/


			$this->end_tab();


			$this->add_tab( __( 'Coupon', 'woofunnels-aero-checkout' ) );
			$this->add_switcher_without_responsive( 'enable_coupon', __( 'Enable', 'woofunnels-aero-checkout' ), '', '', 'no', 'yes', [] );
			$this->add_switcher_without_responsive( 'enable_coupon_collapsible', __( 'Collapsible', 'woofunnels-aero-checkout' ), '', '', 'false', 'true', [ 'enable_coupon' => 'yes' ] );

			$this->add_text( 'mini_cart_coupon_button_text', __( 'Coupon Button Text', 'woofunnels-aero-checkout' ), __( 'Apply', 'woocommerce' ), [ 'enable_coupon' => 'yes' ] );
			$this->end_tab();

			/**
			 * Style Tab
			 */
			/* Section */
			$this->add_tab( __( 'Heading', 'woofunnels-aero-checkout' ), 2 );
			$this->add_typography( 'mini_cart_section_typo', '{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp-order-summary-label' );
			$this->add_color( 'mini_cart_section_text_color', [ '{{WRAPPER}} .wfacp-order-summary-label' ], '#000000' );
			$this->add_text_alignments( 'mini_cart_section_typo_alignment', [ '{{WRAPPER}} .wfacp-order-summary-label' ] );
			$this->end_tab();

			$this->add_tab( __( 'Products', 'woocommerce' ), 2 );
			/* ------------------------------------ Products Start------------------------------------ */

			$mini_cart_product_typo = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:not(.product-total)',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > span bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > ins span bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > span:not(.wfacp_cart_product_name_h):not(.wfacp_delete_item_wrap)',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total ins span:not(.wfacp_cart_product_name_h):not(.wfacp_delete_item_wrap)',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dl',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dt',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dd',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dd p',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name',

				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container span.subscription-details',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td p',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name span:not(.subscription-details)',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name bdi',
			];


			$this->add_typography( 'mini_cart_product_typo', implode( ',', $mini_cart_product_typo ) );
			$this->add_color( 'mini_cart_product_color', $mini_cart_product_typo );
			$this->add_border_color( 'mini_cart_product_image_border_color', [ '{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_sum .product-image .wfacp-pro-thumb img' ], '', __( 'Image Border Color', 'woofunnel-aero-checkout' ), false );

			$label = __( 'Border Radius', 'elementor' );
			$this->add_border_radius( 'mini_cart_product_image_border_radius', '{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_sum .product-image .wfacp-pro-thumb img', [], '', '', $label );


			/* Strike Through Style Setting Order Summary Field */
			$this->price_strike_through_style_settings( 'mini_cart', '{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container.wfacp_min_cart_widget' );

			/* ------------------------------------ End ------------------------------------ */


			$this->end_tab();


			/* ------------------------------------ Coupon Fields Start ------------------------------------ */

			$this->add_tab( __( 'Coupon', 'woocommerce' ), 2, [ 'enable_coupon' => 'yes' ] );

			$this->add_heading( __( 'Link', 'woofunnel-aero-checkout' ), '', [ 'enable_coupon_collapsible' => 'true' ] );

			$this->add_typography( 'mini_cart_coupon_heading_typo', '{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page .wfacp_main_showcoupon', [], [ 'enable_coupon_collapsible' => 'true' ] );
			$this->add_color( 'mini_cart_coupon_label_text_color', [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .woocommerce-info',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp-coupon-page .woocommerce-info a'
			], '', '', [ 'enable_coupon_collapsible' => 'true' ] );


			$this->add_heading( __( 'Field', 'woofunnel-aero-checkout' ), 'none' );
			$form_fields_label_typo = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control-label',
			];
			$fields_options         = [
				'font_weight' => [
					'default' => '400',
				],
			];

			$this->add_typography( 'wfacp_form_mini_cart_coupon_label_typo', implode( ',', $form_fields_label_typo ), $fields_options, [], __( 'Label Typography', 'woofunnels-aero-checkout' ) );

			$form_fields_label_color_opt = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control-label',
			];
			$this->add_color( 'wfacp_form_fields_label_color', $form_fields_label_color_opt, '', __( 'Label Color', 'woofunnels-aero-checkout' ) );


			$fields_options = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
			];

			$optionString = implode( ',', $fields_options );
			$this->add_typography( 'wfacp_form_mini_cart_coupon_input_typo', $optionString, [], [], __( 'Coupon Typography' ) );


			$inputColorOption = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
			];
			$this->add_color( 'wfacp_form_mini_cart_coupon_input_color', $inputColorOption, '', __( 'Coupon Color', 'woofunnels-aero-checkout' ) );
			$this->add_border_color( 'wfacp_form_mini_cart_coupon_focus_color', [ '{{WRAPPER}} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control:focus' ], '#61bdf7', __( 'Focus Color', 'woofunnel-aero-checkout' ), true );
			$fields_options = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
			];
			$default        = [ 'top' => 4, 'right' => 4, 'bottom' => 4, 'left' => 4, 'unit' => 'px' ];
			$this->add_border( 'wfacp_form_mini_cart_coupon_border', implode( ',', $fields_options ), [], $default );


			$this->add_heading( __( 'Button', 'woofunnel-aero-checkout' ) );
			/* Button color setting */
			$this->add_controls_tabs( "wfacp_mini_cart_button_style" );
			$this->add_controls_tab( "wfacp_mini_cart_button_normal_tab", 'Normal' );
			$this->add_background_color( 'mini_cart_coupon_btn_color', [ '{{WRAPPER}} .wfacp_mini_cart_start_h button.wfacp-coupon-btn' ], '', __( 'Background', 'woofunnels-aero-checkout' ) );
			$this->add_color( 'mini_cart_coupon_btn_lable_color', [ '{{WRAPPER}} .wfacp_mini_cart_start_h button.wfacp-coupon-btn' ], '', __( 'Label', 'woofunnels-aero-checkout' ) );
			$this->close_controls_tab();

			$this->add_controls_tab( "wfacp_mini_cart_hover_button_normal_tab", 'Hover' );
			$this->add_background_color( 'mini_cart_coupon_btn_lable_hover_color', [ '{{WRAPPER}} .wfacp_mini_cart_start_h button.wfacp-coupon-btn:hover' ], '', __( 'Background', 'woofunnels-aero-checkout' ) );
			$this->add_color( 'mini_cart_coupon_btn_hover_label_color', [ '{{WRAPPER}} .wfacp_mini_cart_start_h button.wfacp-coupon-btn:hover' ], '', __( 'Label', 'woofunnels-aero-checkout' ) );
			$this->close_controls_tab();
			$this->close_controls_tabs();

			$this->add_typography( 'wfacp_form_mini_cart_coupon_button_typo', '{{WRAPPER}} .wfacp_mini_cart_start_h button.wfacp-coupon-btn', [], [], __( 'Button Typography' ) );
			/* Button color setting End*/
			$this->end_tab();

			/* ------------------------------------ End ------------------------------------ */

			$this->add_tab( __( 'Cart Total', 'woocommerce' ), 2 );


			/* ------------------------------------ Subtotal Start------------------------------------ */
			$this->add_heading( __( 'Subtotal', 'woocommerce' ) );

			$mini_cart_product_meta_typo = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount)',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) th span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td a',
			];

			$this->add_typography( 'mini_cart_product_meta_typo', implode( ',', $mini_cart_product_meta_typo ) );
			$this->add_color( 'mini_cart_product_meta_color', $mini_cart_product_meta_typo );
			/* ------------------------------------ End ------------------------------------ */

			/* ------------------------------------ Coupon Start------------------------------------ */

			$this->add_heading( __( 'Coupon code', 'woocommerce' ) );
			$coupon_selector = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td a',
			];

			$default = [
				'unit' => 'px',
				'size' => 14,
			];

			$this->add_font_size( 'mini_cart_coupon_display_font_size', implode( ',', $coupon_selector ), 'Font Size (in px)', $default, [], [ 'px' ], $default, $default );

			$coupon_selector_label_color = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th span:not(.wfacp_coupon_code)',
			];
			$this->add_color( 'mini_cart_coupon_display_label_color', $coupon_selector_label_color, '', __( 'Text Color', 'woofunnel-aero-checkout' ) );
			$coupon_selector_val_color = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td a',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount th .wfacp_coupon_code',
			];
			$this->add_color( 'mini_cart_coupon_display_val_color', $coupon_selector_val_color, '#24ae4e', __( 'Code Color', 'woofunnel-aero-checkout' ) );

			/* ------------------------------------ End ------------------------------------ */


			/* ------------------------------------ Total Start------------------------------------ */

			$this->add_heading( __( 'Total', 'woocommerce' ) );
			$cart_total_color_option = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total td span.amount',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total td span.amount bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total th span',
			];

			$cart_total_label_typo_option = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th a',
			];
			$cart_total_value_typo_option = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span.woocommerce-Price-amount.amount',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td p',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td a',
				'{{WRAPPER}} .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td p',
			];


			$this->add_typography( 'mini_cart_total_label_typo', implode( ', ', $cart_total_label_typo_option ), [], [], __( 'Label Typography', 'woofunnel-aero-checkout' ) );
			$this->add_typography( 'mini_cart_total_typo', implode( ', ', $cart_total_value_typo_option ), [], [], __( 'Price Typography', 'woofunnel-aero-checkout' ) );
			$this->add_color( 'mini_cart_total_color', $cart_total_color_option );


			/* ------------------------------------ End ------------------------------------ */

			$this->end_tab();


			/* ------------------------------------ Mini Cart Global Settings  ------------------------------------ */
			$this->add_tab( __( 'Settings', 'woofunnels-aero-checkout' ), 2 );

			$this->add_heading( __( 'Default Font', 'woocommerce' ) );

			$wfacp_mini_cart_font_family = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h *',
				'{{WRAPPER}} .wfacp_mini_cart_start_h tr.order-total td span.woocommerce-Price-amount.amount',
				'{{WRAPPER}} .wfacp_mini_cart_start_h tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dl',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dt',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dd',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dd p',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total)',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td a',
				'{{WRAPPER}} .wfacp_mini_cart_start_h span.wfacp_coupon_code',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr.order-total td span.woocommerce-Price-amount.amount',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table .order-total td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table .order-total th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table .order-total td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item .product-name',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td p',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td .product-name span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td .product-name',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp_main_showcoupon',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .shop_table tr.order-total td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .shop_table tr.order-total th',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .shop_table tr.order-total td span',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .shop_table tr.order-total td small',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .checkout_coupon.woocommerce-form-coupon .wfacp-form-control-label',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp-coupon-btn',
			];

			$this->add_font_family( 'wfacp_mini_cart_font_family', $wfacp_mini_cart_font_family, 'Font family', 'Open Sans' );
			$this->add_heading( __( 'Divider', 'woocommerce' ) );

			$this->add_border_color( 'mini_cart_divider_color', [
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp_mini_cart_elementor .cart_item',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.cart-subtotal',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.order-total',
				'{{WRAPPER}} .wfacp_mini_cart_start_h table.shop_table tr.wfacp_ps_error_state td',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
				'{{WRAPPER}} .wfacp_mini_cart_start_h .wfob_bump_wrapper.wfacp_below_mini_cart_items:empty',
			], '', __( 'Color', 'woofunnel-aero-checkout' ), false );

			$this->end_tab();

			/* ------------------------------------ End ------------------------------------ */


			$this->ajax_session_settings[] = 'mini_cart_heading';
			$this->ajax_session_settings[] = 'enable_product_image';
			$this->ajax_session_settings[] = 'enable_quantity_number';
			$this->ajax_session_settings[] = 'enable_quantity_box';
			$this->ajax_session_settings[] = 'enable_delete_item';
			$this->ajax_session_settings[] = 'enable_coupon';
			$this->ajax_session_settings[] = 'enable_coupon_collapsible';
			$this->ajax_session_settings[] = 'mini_cart_coupon_button_text';
		}

		private function mini_section_typo_settings() {

			$this->add_tab( __( 'Section', 'woofunnel-aero-checkout' ), 2 );


			$form_section_bg_color = [
				'{{WRAPPER}} .wfacp_mini_cart_start_h',
			];

			$this->add_background_color( 'mini_form_section_bg_color', $form_section_bg_color, '', __( 'Background Color', 'woofunnels-aero-checkout' ) );
			$this->add_divider( "none" );
			$this->add_border( 'mini_form_section_border', implode( ',', $form_section_bg_color ) );
			$this->add_divider( "none" );
			$this->add_border_shadow( 'mini_form_section_box_shadow', implode( ', ', $form_section_bg_color ) );
			$this->add_divider( "none" );
			$this->add_padding( 'mini_form_section_padding', implode( ', ', $form_section_bg_color ) );
			$this->add_margin( 'mini_form_section_margin', implode( ', ', $form_section_bg_color ) );
			$this->end_tab();

		}

		protected function html() {
			echo '<div style="height: 1px"></div>';

			/**
			 * @var $template WFACP_Elementor_Template;
			 */
			$template = wfacp_template();
			if ( null === $template ) {
				return;
			}

			$key = 'wfacp_mini_cart_widgets_' . $template->get_template_type();
			if ( WFACP_Common::is_theme_builder() ) {
				do_action( 'wfacp_mini_cart_widgets_elementor_editor', $this );
			}
			$widgets   = WFACP_Common::get_session( $key );
			$widgets[] = $this->get_id();
			WFACP_Common::set_session( $key, $widgets );
			$template->get_mini_cart_widget( $this->get_id() );
		}
	}

	if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
		\Elementor\Plugin::instance()->widgets_manager->register( new \El_WFACP_Form_Summary() );
	} else {
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \El_WFACP_Form_Summary() );
	}
}