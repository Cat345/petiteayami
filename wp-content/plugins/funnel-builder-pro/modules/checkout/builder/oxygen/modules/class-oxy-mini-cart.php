<?php
if ( ! class_exists( 'WFACP_OXY_Summary' ) ) {
	#[AllowDynamicProperties]
	class WFACP_OXY_Summary extends WFACP_OXY_HTML_BLOCK {


		public $slug = 'wfacp_checkout_form_summary';
		protected $id = 'wfacp_order_summary_widget';
		protected $get_local_slug = 'order_summary';

		public function __construct() {
			$this->name = __( 'Mini Cart', 'woofunnels-aero-checkout' );
			parent::__construct();

		}

		function name() {
			return $this->name;
		}


		/**
		 * @param $template WFACP_Template_Common;
		 */
		public function setup_data( $template ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$this->mini_cart();
		}


		protected function mini_cart() {

			/* ------------------------------------ Cart Heading------------------------------------ */
			$tab_id = $this->add_tab( __( 'Heading', 'woofunnels-aero-checkout' ) );
			$this->add_text( $tab_id, 'mini_cart_heading', __( 'Title', 'woofunnels-aero-checkout' ), __( 'Order Summary', 'woofunnels-aero-checkout' ) );
			$this->add_typography( $tab_id, 'mini_cart_section_typo', '.wfacp_mini_cart_start_h .wfacp-order-summary-label', __( 'Heading Typography', 'woofunnels-aero-checkout' ) );


			/* ------------------------------------ Products Start------------------------------------ */
			$cart_id = $this->add_tab( __( 'Products', 'woofunnels-aero-checkout' ) );
			$this->add_switcher( $cart_id, 'enable_product_image', __( 'Image', 'woofunnels-aero-checkout' ), 'on' );
			$this->add_switcher( $cart_id, 'enable_quantity_number', __( 'Quantity Count', 'woofunnels-aero-checkout' ), 'on' );
			$this->add_switcher( $cart_id, 'enable_quantity_box', __( 'Quantity Switcher', 'woofunnels-aero-checkout' ), 'on' );
			$this->add_switcher( $cart_id, 'enable_delete_item', __( 'Allow Deletion', 'woofunnels-aero-checkout' ), 'on' );

			$this->ajax_session_settings[] = 'mini_cart_heading';
			$this->ajax_session_settings[] = 'enable_product_image';
			$this->ajax_session_settings[] = 'enable_quantity_number';
			$this->ajax_session_settings[] = 'enable_quantity_box';
			$this->ajax_session_settings[] = 'enable_delete_item';


			/**
			 * -----------------------------Strike Through Price Setting on the mini cart-------------------------------------
			 */
			$this->price_strike_through_content_settings( $cart_id, 'mini_cart' );
			$this->ajax_session_settings[] = 'mini_cart_enable_strike_through_price';
			$this->ajax_session_settings[] = 'mini_cart_enable_low_stock_trigger';
			$this->ajax_session_settings[] = 'mini_cart_low_stock_message';
			$this->ajax_session_settings[] = 'mini_cart_enable_saving_price_message';
			$this->ajax_session_settings[] = 'mini_cart_saving_price_message';

			/* ------------------------------------ Coupon------------------------------------ */

			$enable_coupon = [
				'enable_coupon' => 'on'
			];

			$coupon_tab_id = $this->add_tab( __( 'Coupon', 'woofunnels-aero-checkout' ) );
			$this->add_switcher( $coupon_tab_id, 'enable_coupon', __( 'Enable Coupon', 'woofunnels-aero-checkout' ), 'off' );
			$this->add_switcher( $coupon_tab_id, 'enable_coupon_collapsible', __( 'Collapsible Coupon', 'woofunnels-aero-checkout' ), 'off', [ 'enable_coupon' => 'on' ] );

			$this->add_text( $coupon_tab_id, 'mini_cart_coupon_button_text', __( 'Coupon Button Text', 'woofunnels-aero-checkout' ), __( 'Apply', 'woocommerce' ), [ 'enable_coupon' => 'on' ] );
			$this->ajax_session_settings[] = 'enable_coupon';
			$this->ajax_session_settings[] = 'enable_coupon_collapsible';
			$this->ajax_session_settings[] = 'mini_cart_coupon_button_text';

			$this->add_typography( $coupon_tab_id, 'mini_cart_coupon_heading_typo', '.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page .wfacp_main_showcoupon', __( 'Link Typography' ) );


			$this->add_typography( $coupon_tab_id, 'wfacp_form_mini_cart_coupon_label_typo', '.wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon p .wfacp-form-control-label', __( 'Label Typography', 'woofunnels-aero-checkout' ) );
			$this->add_typography( $coupon_tab_id, 'wfacp_form_mini_cart_coupon_input_typo', '.wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control', __( 'Coupon Field Typography' ) );
			$this->add_border_color( $coupon_tab_id, 'wfacp_form_mini_cart_coupon_focus_color', '.wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control:focus', '#61bdf7', __( 'Focus Color', 'woofunnel-aero-checkout' ), true, $enable_coupon );
			$this->add_border( $coupon_tab_id, 'wfacp_form_mini_cart_coupon_border', '.wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control', __( 'Coupon Field Border' ) );

			/* Button Typography*/

			$this->add_heading( $coupon_tab_id, __( 'Button', 'woocommerce' ) );

			$this->add_sub_heading( $coupon_tab_id, __( 'Typography', 'woocommerce' ) );
			$default = [
				'font_size' => '16',
			];
			$this->custom_typography( $coupon_tab_id, $this->slug . '_coupon_button_typo', '.wfacp_mini_cart_start_h button.wfacp-coupon-btn', '', $default );


			/* Button color setting */
			$this->add_sub_heading( $coupon_tab_id, __( 'Color', 'woocommerce' ) );

			$this->add_background_color( $coupon_tab_id, 'mini_cart_coupon_btn_color', '.wfacp_mini_cart_start_h button.wfacp-coupon-btn', '#999', __( 'Button Background', 'woofunnels-aero-checkout' ) );
			$this->add_color( $coupon_tab_id, 'mini_cart_coupon_btn_lable_color', '.wfacp_mini_cart_start_h button.wfacp-coupon-btn', __( 'Button Label Color', 'woofunnels-aero-checkout' ), '#fff' );


			$this->add_background_color( $coupon_tab_id, 'mini_cart_coupon_btn__bg_hover_color', '.wfacp_mini_cart_start_h button.wfacp-coupon-btn:hover', '#878484', __( 'Button Hover Background', 'woofunnels-aero-checkout' ) );
			$this->add_color( $coupon_tab_id, 'mini_cart_coupon_btn_hover_label_color', '.wfacp_mini_cart_start_h button.wfacp-coupon-btn:hover', __( 'Button Label Hover Color', 'woofunnels-aero-checkout' ), '#fff' );


			/* ------------------------------------ Coupon Setting------------------------------------ */

			$this->add_heading( $cart_id, __( 'Coupons', 'woofunnels-aero-checkout' ) );
			$coupon_selector = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td a',
			];

			$default = '12px';


			$this->add_font_size( $cart_id, 'mini_cart_coupon_display_font_size', implode( ',', $coupon_selector ), 'Font Size (in px)', $default );

			$coupon_selector_label_color = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th span:not(.wfacp_coupon_code)',
			];
			$this->add_color( $cart_id, 'mini_cart_coupon_display_label_color', implode( ',', $coupon_selector_label_color ), __( 'Text Color', 'woofunnel-aero-checkout' ) );
			$coupon_selector_val_color = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td a',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount th .wfacp_coupon_code',
			];
			$this->add_color( $cart_id, 'mini_cart_coupon_display_val_color', implode( ',', $coupon_selector_val_color ), __( 'Code Color', 'woofunnel-aero-checkout' ), '#24ae4e' );


			$mini_cart_product_typo = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:not(.product-total)',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > span bdi',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > ins span bdi',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > span:not(.wfacp_cart_product_name_h):not(.wfacp_delete_item_wrap)',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total ins span:not(.wfacp_cart_product_name_h):not(.wfacp_delete_item_wrap)',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total small',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dl',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dt',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dd',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dd p',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td small',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container span.subscription-details',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td p',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name span:not(.subscription-details)',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name bdi',
			];

			$this->add_typography( $cart_id, 'mini_cart_product_typo', implode( ',', $mini_cart_product_typo ), __( 'Product Typography', 'wooofunels-aero-checkout' ) );

			$mini_cart_product_price_typo = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:last-child',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:last-child p',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:last-child span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:last-child span.amount',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:last-child span bdi',

			];

			$this->add_typography( $cart_id, 'mini_cart_product_price_typo', implode( ', ', $mini_cart_product_price_typo ), __( 'Price Typography', 'woofunnels-aero-checkout' ) );


			$mini_cart_product_variant_typo = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .product-name dl',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .product-name dt',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .product-name dd',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .product-name dd p',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .subscription-details',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .wfacp_product_subs_details span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .wfacp_product_subs_details span bdi',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .subscription-details span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .subscription-details span.amount ',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .subscription-details span.amount bdi',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:first-child .subscription-details span p',
			];

			$this->add_typography( $cart_id, 'mini_cart_product_variant_typo', implode( ', ', $mini_cart_product_variant_typo ), __( 'Variant Typography', 'woofunnels-aero-checkout' ) );


			$this->add_border_color( $cart_id, 'mini_cart_product_image_border_color', '.wfacp_mini_cart_start_h .wfacp_order_sum .product-image img', '', __( 'Image Border Color', 'woofunnel-aero-checkout' ) );
			$this->add_border_radius_preset( $cart_id, 'mini_cart_product_image_border_radius', '.wfacp_mini_cart_start_h .wfacp_order_sum .product-image img' );


			/* Strike Through Style Setting Order Summary Field */
			$this->price_strike_through_style_settings( $cart_id, 'mini_cart', '.wfacp_mini_cart_start_h .wfacp_order_summary_container' );

			/* ------------------------------------ Cart Total Start------------------------------------ */

			$subtotal_id = $this->add_tab( __( 'Cart total', 'woocommerce' ) );


			$mini_cart_subtotal_typo = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount)',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) th',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) th span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span bdi',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td small',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span.amount',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td a',
			];


			$this->add_heading( $subtotal_id, __( 'Coupon code', 'woocommerce' ) );

			$coupon_selector = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount th',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount th span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount td a',
			];


			$this->add_font_size( $subtotal_id, 'mini_cart_coupon_display_font_size', implode( ',', $coupon_selector ), 'Font Size (in px)', $default );
			$coupon_selector_label_color = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th span:not(.wfacp_coupon_code)',
			];
			$this->add_color( $subtotal_id, 'mini_cart_coupon_display_label_color', implode( ',', $coupon_selector_label_color ), __( 'Text Color', 'woofunnel-aero-checkout' ) );

			$coupon_selector_val_color = [
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td a',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span bdi',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount th .wfacp_coupon_code',
			];
			$this->add_color( $subtotal_id, 'mini_cart_coupon_display_val_color', implode( ',', $coupon_selector_val_color ), __( 'Code Color', 'woofunnel-aero-checkout' ), '#24ae4e' );
			$this->add_typography( $subtotal_id, $this->slug . '_subtotal_price_label_typo', implode( ', ', $mini_cart_subtotal_typo ), __( 'Subtotal Typography', 'woofunnels-aero-checkout' ) );

			$total_price_label_typo = [
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total td:first-child',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total th:first-child',
			];

			$total_price_typo = [
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total td span *',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total td span bdi',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total td span.amount',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total td small',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total td:last-child',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total th:last-child',
			];

			$this->add_typography( $subtotal_id, $this->slug . '_total_price_label_typo', implode( ', ', $total_price_label_typo ), __( 'Total Label Typography', 'woofunnels-aero-checkout' ) );
			$this->add_typography( $subtotal_id, $this->slug . '_total_price_typo', implode( ', ', $total_price_typo ), __( 'Total Price Typography', 'woofunnels-aero-checkout' ) );


			/* ------------------------------------ Setting------------------------------------ */

			$settings_tab_id = $this->add_tab( __( 'Settings', 'woofunnels-aero-checkout' ), 2 );


			$wfacp_mini_cart_font_family = [
				'.wfacp_mini_cart_start_h *',
				'.wfacp_mini_cart_start_h tr.order-total td span.woocommerce-Price-amount.amount',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total small',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dl',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dt',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dd',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dd p',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total)',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) th',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td small',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td a',
				'.wfacp_mini_cart_start_h span.wfacp_coupon_code',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr.order-total td span.woocommerce-Price-amount.amount',
				'.wfacp_mini_cart_start_h table.shop_table .order-total td',
				'.wfacp_mini_cart_start_h table.shop_table .order-total th',
				'.wfacp_mini_cart_start_h table.shop_table .order-total td span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item .product-name',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td small',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td p',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td .product-name span',
				'.wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td .product-name',
				'.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp_main_showcoupon',
				'.wfacp_mini_cart_start_h .shop_table tr.order-total td',
				'.wfacp_mini_cart_start_h .shop_table tr.order-total th',
				'.wfacp_mini_cart_start_h .shop_table tr.order-total td span',
				'.wfacp_mini_cart_start_h .shop_table tr.order-total td small',
				'.wfacp_mini_cart_start_h .checkout_coupon.woocommerce-form-coupon .wfacp-form-control-label',
				'.wfacp_mini_cart_start_h .checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
				'.wfacp_mini_cart_start_h .wfacp-coupon-btn',
			];


			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];
			$this->add_font_family( $settings_tab_id, 'wfacp_mini_cart_font_family', implode( ',', $wfacp_mini_cart_font_family ), '', '', [], $font_side_default );


			$this->add_heading( $settings_tab_id, __( 'Divider', 'woocommerce' ) );
			$border_color = [
				'.wfacp_mini_cart_start_h .wfacp_mini_cart_divi .cart_item',
				'.wfacp_mini_cart_start_h table.shop_table tr.cart-subtotal',
				'.wfacp_mini_cart_start_h table.shop_table tr.order-total',
				'.wfacp_mini_cart_start_h table.shop_table tr.wfacp_ps_error_state td',
				'.wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
				'.wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp_mini_cart_elementor .cart_item',
				'.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
			];
			$this->add_border_color( $settings_tab_id, 'mini_cart_divider_color', implode( ',', $border_color ), '', __( 'Color', 'woofunnel-aero-checkout' ), false );
			/* ------------------------------------ End ------------------------------------ */

		}

		public function generate_id_css( $styles, $states, $selector, $class_obj, $defaults ) {
			$slug = 'oxy-' . $this->slug();
			if ( $class_obj->options['tag'] !== $slug ) {
				return $styles;
			}
			global $oxygen_vsb_components;

			$params             = $states['original'];
			$params['selector'] = $selector;
			if ( ! is_null( $oxygen_vsb_components[ $slug ] ) ) {
				$selector_id = "#" . $params["selector"];
			}

			if ( isset( $params['oxy-wfacp_checkout_form_summary_wfacp_form_mini_cart_coupon_focus_color'] ) && ! empty( $params['oxy-wfacp_checkout_form_summary_wfacp_form_mini_cart_coupon_focus_color'] ) ) {
				$focus_color = $params['oxy-wfacp_checkout_form_summary_wfacp_form_mini_cart_coupon_focus_color'];
				$styles      = $styles . $selector_id . " .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control:focus{box-shadow:0 0 0 1px $focus_color}";
			}

			return $styles;
		}

		public function html( $setting, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$template = wfacp_template();
			if ( is_null( $template ) ) {
				return '';
			}

			if ( isset( $setting['enable_quantity_number'] ) && "off" === $setting['enable_quantity_number'] ) {

				echo "<style>";
				echo ".wfacp_mini_cart_start_h .wfacp-qty-ball{display: none;}";
				echo ".wfacp_mini_cart_start_h strong.product-quantity{display: none;}";
				echo "</style>";
			}

			$this->save_ajax_settings();
			$key     = 'wfacp_mini_cart_widgets_' . $template->get_template_type();
			$widgets = WFACP_Common::get_session( $key );
			if ( ! in_array( $key, $widgets ) ) {//phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				$widgets[] = $this->get_id();
			}
			WFACP_Common::set_session( $key, $widgets );
			$template->get_mini_cart_widget( $this->get_id() );
		}

		protected function preview_shortcode() {
			echo '[Mini Cart]';
		}

	}

	new WFACP_OXY_Summary;
}