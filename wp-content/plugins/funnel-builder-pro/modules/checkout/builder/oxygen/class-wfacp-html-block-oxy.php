<?php
if ( ! class_exists( 'WFACP_OXY_HTML_BLOCK' ) ) {
	#[AllowDynamicProperties]
	abstract class WFACP_OXY_HTML_BLOCK extends WFACP_OXY_Field {


		public function __construct() {
			parent::__construct();

			WFACP_OXY::set_locals( $this->get_local_slug(), $this->get_id() );
			add_filter( 'pre_do_shortcode_tag', [ $this, 'pick_data' ], 10, 3 );
		}

		public function options() {
			return [ 'rebuild_on_dom_change' => true ];
		}

		final public function render( $setting, $defaults, $content ) {

			if ( ! wp_doing_ajax() && is_admin() ) {
				return;
			}

			if ( apply_filters( 'wfacp_print_oxy_widget', true, $this->get_id(), $this ) ) {
				if ( WFACP_OXY::is_template_editor() ) {
					$this->preview_shortcode();

					return;
				}
				$this->parse_render_settings();

				$this->settings = $setting;


				$this->html( $setting, $defaults, $content );


				if ( isset( $_REQUEST['action'] ) && false !== strpos( $_REQUEST['action'], 'oxy_render' ) ) {//phpcs:ignore
					exit;
				}
			}

		}

		protected function preview_shortcode() {
			echo '';
		}

		protected function parse_ajax_settings( $settings, $ajax_keys ) {
			if ( empty( $ajax_keys ) || empty( $this->ajax_session_settings ) ) {
				return $settings;
			}

			$output_settings = [];
			foreach ( $this->ajax_session_settings as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$output_settings[ $key ] = $settings[ $key ];
				}
			}


			return $output_settings;
		}

		protected function save_ajax_settings() {
			$id            = $this->get_id();
			$ajax_settings = $this->parse_ajax_settings( $this->settings, $this->ajax_session_settings );
			WFACP_Common::set_session( $id, $ajax_settings );
		}

		protected function html( $setting, $defaults, $content ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

		}


		protected function available_html_block() {
			$block = [ 'product_switching', 'order_total' ];

			return apply_filters( 'wfacp_html_block_elements', $block );
		}

		public function get_title() {
			return __( 'Checkout Form', 'woofunnels-aero-checkout' );
		}

		protected function order_summary( $field_key ) {


			$tab_id = $this->add_tab( __( 'Order summary', 'woocommerce' ) );


			$this->add_heading( $tab_id, 'Product' );


			$this->add_switcher( $tab_id, 'order_summary_enable_product_image', __( 'Enable Image', 'woofunnels-aero-checkout' ), 'on' );
			$this->ajax_session_settings[] = 'order_summary_enable_product_image';

			$this->price_strike_through_content_settings( $tab_id, 'order_summary_field' );
			$this->ajax_session_settings[] = 'order_summary_field_enable_strike_through_price';
			$this->ajax_session_settings[] = 'order_summary_field_enable_low_stock_trigger';
			$this->ajax_session_settings[] = 'order_summary_field_low_stock_message';
			$this->ajax_session_settings[] = 'order_summary_field_enable_saving_price_message';
			$this->ajax_session_settings[] = 'order_summary_field_saving_price_message';

			$cart_item_color = [
				'#wfacp-e-form  table.shop_table tbody .wfacp_order_summary_item_name',
				'#wfacp-e-form  table.shop_table tbody .product-name .product-quantity',
				'#wfacp-e-form  table.shop_table tbody td.product-total',
				'#wfacp-e-form  table.shop_table tbody .cart_item .product-total span',
				'#wfacp-e-form  table.shop_table tbody .cart_item .product-total span.amount',
				'#wfacp-e-form  table.shop_table tbody .cart_item .product-total span.amount bdi',
				'#wfacp-e-form  table.shop_table tbody .cart_item .product-total small',
				'#wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container dl',
				'#wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container dd',
				'#wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container dt',
				'#wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container p',
				'#wfacp-e-form  table.shop_table tbody tr span.amount',
				'#wfacp-e-form  table.shop_table tbody tr span.amount bdi',
				'#wfacp-e-form  table.shop_table tbody dl',
				'#wfacp-e-form  table.shop_table tbody dd',
				'#wfacp-e-form  table.shop_table tbody dt',
				'#wfacp-e-form  table.shop_table tbody p',
				'#wfacp-e-form  table.shop_table tbody tr td span:not(.wfacp-pro-count)',
				'#wfacp-e-form  .wfacp_show_icon_wrap',
				'#wfacp-e-form  .wfacp_show_icon_wrap *',
				'#wfacp-e-form  .wfacp_show_price_wrap *',
				'#wfacp-e-form  .wfacp_show_price_wrap span bdi',
			];


			$this->add_heading( $tab_id, __( 'Product Typography', 'woofunnel-aero-checkout' ) );
			$this->custom_typography( $tab_id, $field_key . '_cart_item_typo', implode( ',', $cart_item_color ), __( 'Product Typography', 'woofunnel-aero-checkout' ) );
			$this->add_color( $tab_id, $field_key . '_cart_item_color', implode( ',', $cart_item_color ), 'Text Color', '' );


			$border_image_color = [ '#wfacp-e-form table.shop_table tr.cart_item .product-image img' ];

			$this->add_border_color( $tab_id, 'mini_product_image_border_color', implode( ',', $border_image_color ), '', __( 'Image Border Color', 'woofunnels-aero-checkout' ), false, [ 'order_summary_enable_product_image' => 'on' ] );
			$this->add_border_radius_preset( $tab_id, $field_key . '_cart_item_image_border_radius', implode( ',', $border_image_color ) );

			$cart_subtotal_color_option = [
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount)',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) th',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) th span',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td span',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td small',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td bdi',
				'#wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td a',
			];

			/* Strike Through Style Setting Order Summary Field */
			$this->price_strike_through_style_settings( $tab_id, $field_key, '#wfacp-e-form #wfacp-sec-wrapper' );


			$this->add_heading( $tab_id, __( 'Subtotal Typography', 'woofunnel-aero-checkout' ) );
			$this->custom_typography( $tab_id, 'order_summary_product_meta_typo', implode( ',', $cart_subtotal_color_option ), __( 'Subtotal Typography', 'woofunnel-aero-checkout' ) );
			$this->add_color( $tab_id, 'order_summary_product_meta_color', implode( ',', $cart_subtotal_color_option ), 'Text Color', '' );

			/* ------------------------------------ Coupon Start------------------------------------ */
			$this->add_heading( $tab_id, __( 'Coupon code', 'woocommerce' ) );
			$coupon_selector = [
				'#wfacp-e-form  table.shop_table tfoot tr.cart-discount th',
				'#wfacp-e-form  table.shop_table tfoot tr.cart-discount th span',
				'#wfacp-e-form  table.shop_table tfoot tr.cart-discount td',
				'#wfacp-e-form  table.shop_table tfoot tr.cart-discount td span',
				'#wfacp-e-form  table.shop_table tfoot tr.cart-discount td a',
			];

			$default = 14;

			$this->add_font_size( $tab_id, $field_key . '_coupon_typo', implode( ',', $coupon_selector ), 'Font Size (in px)', $default, [] );
			$coupon_selector_label_color = [
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount th',
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount th span:not(.wfacp_coupon_code)',
			];

			$this->add_color( $tab_id, $field_key . '_display_label_color', implode( ',', $coupon_selector_label_color ), 'Text Color', '' );

			$coupon_selector_val_color = [
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount td',
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount td span',
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount td a',
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount td span',
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount td span bdi',
				'#wfacp-e-form table.shop_table tfoot tr.cart-discount th .wfacp_coupon_code',
			];

			$this->add_color( $tab_id, $field_key . '_display_val_color', implode( ',', $coupon_selector_val_color ), 'Code Color', '#24ae4e' );


			/* ------------------------------------ Order Total------------------------------------ */


			$cart_total_color_option = [
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td p',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td small',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td a',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td p',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th span',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th small',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th a',
			];

			$cart_total_label_typo_option = [
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th span',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th small',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total th a',
			];
			$cart_total_value_typo_option = [
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td p',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td small',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td a',
				'#wfacp-e-form  table.shop_table tfoot tr.order-total td p',
			];
			$this->add_heading( $tab_id, __( 'Label Typography', 'woofunnel-aero-checkout' ) );
			$this->custom_typography( $tab_id, $field_key . '_cart_total_label_typo', implode( ',', $cart_total_label_typo_option ), __( 'Label Typography', 'woofunnel-aero-checkout' ) );
			$this->add_heading( $tab_id, __( 'Price Typography', 'woofunnel-aero-checkout' ) );
			$this->custom_typography( $tab_id, $field_key . '_cart_subtotal_heading_typo', implode( ',', $cart_total_value_typo_option ), __( 'Price Typography', 'woofunnel-aero-checkout' ) );

			$this->add_color( $tab_id, $field_key . '_cart_subtotal_heading_color', implode( ',', $cart_total_color_option ) );

			/* ---------------------------------------- Divider Color---------------------------------------- */

			$this->add_heading( $tab_id, __( 'Divider', 'woocommerce' ) );
			$divider_line_color = [
				'#wfacp-e-form .wfacp_main_form.woocommerce table.shop_table tbody .wfacp_order_summary_item_name',
				'#wfacp-e-form table.shop_table.woocommerce-checkout-review-order-table tr.cart_item',
				'#wfacp-e-form table.shop_table.woocommerce-checkout-review-order-table tr.cart-subtotal',
				'#wfacp-e-form table.shop_table.woocommerce-checkout-review-order-table tr.order-total',
			];
			$this->add_border_color( $tab_id, $field_key . '_divider_line_color', implode( ',', $divider_line_color ), '' );

		}


		/**
		 * @param $field STring
		 * @param \Elementor\Widget_Base
		 */
		protected function product_switching( $field_key ) {

			$tab_id = $this->add_tab( __( 'Product Switcher', 'woofunnels-aero-checkout' ) );

			/*  Selected Items Setting */

			$this->add_heading( $tab_id, __( 'Selected Items Typography', 'woofunnels-aero-checkout' ) );

			/* Typography  */

			$default = [
				'font_size' => '16',
			];


			$product_switcher_typo_option = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_name_inner *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_attributes .wfacp_selected_attributes  *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_quantity_selector input',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_switcher_col_2 .wfacp_product_subs_details > span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_subs_details span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_subs_details *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_select_options .wfacp_qv-button',

				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec > span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec > span *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec > span bdi',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec ins span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec ins span bdi',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec del',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec del *',
			];


			$this->custom_typography( $tab_id, $field_key . '_selected_item_typography', implode( ',', $product_switcher_typo_option ), 'Selected Items Typography', $default );

			$this->add_heading( $tab_id, __( 'Color', 'woofunnels-aero-checkout' ) );

			/* Items Color */
			$selector = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item',
				'#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .product-name .wfacp_product_switcher_item',
				'#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_row_quantity',
			];

			$this->add_color( $tab_id, $field_key . '_label_color', implode( ',', $selector ), 'Item Color', '' );

			/* Items Price Color */

			$itemPriceColorOpt = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp-selected-product .product-price',
				'#wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp-selected-product .product-price span',
			];
			$this->add_color( $tab_id, $field_key . '_price_color', implode( ',', $itemPriceColorOpt ), 'Item Price Color ', "" );

			$variant_color = [
				'#wfacp-e-form .wfacp_main_form .wfacp_selected_attributes .wfacp_pro_attr_single span',
				'#wfacp-e-form .wfacp_main_form .wfacp_selected_attributes .wfacp_pro_attr_single span:last-child',
				'#wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_subs_details',
				'#wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_subs_details span',
			];
			$this->add_color( $tab_id, $field_key . '_variant_color', implode( ',', $variant_color ), 'Variant Color', '#666666' );

			/* Background Color */
			$itemBgColor = '#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product';
			$this->add_background_color( $tab_id, $field_key . '_item_background', $itemBgColor, "", 'Background Color' );


			$fields_options = '#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product';

			$this->add_border( $tab_id, $field_key . '_border', $fields_options, 'Selected Items Border' );


			$this->add_heading( $tab_id, __( 'Non-selected Items', 'woofunnels-aero-checkout' ) );

			/* Optional Item Setting */


			$product_switcher_typo_optional = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_name_inner *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_attributes .wfacp_selected_attributes  *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_quantity_selector input',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_switcher_col_2 .wfacp_product_subs_details > span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_subs_details span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_subs_details *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_select_options .wfacp_qv-button',

				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec > span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec > span *',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec > span bdi',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec ins span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec ins span bdi',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec del',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec del *',
			];


			$this->custom_typography( $tab_id, $field_key . '_optional_item_typography', implode( ',', $product_switcher_typo_optional ), 'Non Selected Items', $default );


			$this->add_heading( $tab_id, __( 'Non-selected Color', 'woofunnels-aero-checkout' ) );
			/* Label Color Setting */
			$optionalLabelColorOpt = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item',
				'#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .product-name .wfacp_product_switcher_item',
				'#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_row_quantity'
			];

			$this->add_color( $tab_id, $field_key . '_optional_label_color', implode( ',', $optionalLabelColorOpt ), esc_attr__( 'Item Color', 'woofunnels-aero-checkout' ) );


			/* Items Price Color */

			$optional_price_color_option = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .product-price',
				'#wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp_product_price_sec span',
			];
			$this->add_color( $tab_id, $field_key . '_optional_price_color', implode( ',', $optional_price_color_option ), 'Item Price Color' );
			/* Background Color */

			$this->add_background_color( $tab_id, $field_key . '_optional_background', '.woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product)', "#ffffff", 'Background Color' );
			$this->add_background_color( $tab_id, $field_key . '_optional_background_hover', '.wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product):hover', "#fbfbfb", 'Background Hover Color' );


			/* Non Selected border*/
			$fields_options = '#wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product)';
			$this->add_border( $tab_id, $field_key . '_border_non_selected', $fields_options, 'Non-selected Items Border' );

			/* Non Selected border End */

			$typography              = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span bdi',
			];
			$non_selected_typography = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span bdi',
			];

			$this->add_typography( $tab_id, $field_key . '_you_save_typo', implode( ',', $typography ), 'Selected Saving Text Typography' );

			$this->add_typography( $tab_id, $field_key . 'non_selected_you_save_typo', implode( ',', $non_selected_typography ), 'Non Selected Saving Text Typography' );


			//Best value Controls

			if ( true === WFACP_Common::is_best_value_available() ) {
				$selector = [
					'#wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value',
					'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_best_value.wfacp_top_left_corner',
					'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_best_value.wfacp_top_right_corner',
				];
				$selector = implode( ',', $selector );
				/* Best Value: Color Setting */
				$this->add_heading( $tab_id, __( 'Best Value Typography', 'woofunnels-aero-checkout' ) );
				$this->add_typography( $tab_id, $field_key . '_best_value_typography', $selector, 'Best Value Typography' );
				$this->custom_typography( $tab_id, $field_key . '_best_value_typography', $selector, '', $default );


				$this->add_heading( $tab_id, __( 'Best Value Color', 'woofunnels-aero-checkout' ) );
				$this->add_background_color( $tab_id, $field_key . '_best_value_bg_color', $selector, "#b22323", 'Best Value Background Color' );
				$this->add_border( $tab_id, $field_key . '_best_value_border', $selector, 'Best Value Border' );
			}


			if ( true === WFACP_Common::is_what_included_available() ) {


				$this->add_heading( $tab_id, __( "Custom Product Description", 'woofunnels-aero-checkout' ) );


				/* Section Heading Setting */
				$what_included_heading_opt = '#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included h3';

				$this->add_typography( $tab_id, $field_key . '_what_included_heading', $what_included_heading_opt, 'Whats Included Heading typography' );

				/* Product Title Setting */

				$this->add_typography( $tab_id, $field_key . '_what_included_product_title', ' #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description h4', 'Whats Included Product title typography' );


				/* Product Description Setting */

				$description_typo = [
					' #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description .wfacp_description p',
					' #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description .wfacp_description',
				];
				$description_typo = implode( ',', $description_typo );
				$this->add_typography( $tab_id, $field_key . '_what_included_product_description', $description_typo, 'Whats Included Product Description Typography' );

				$advance_typo = '#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included';
				$this->add_background_color( $tab_id, $field_key . '_what_included_bg', $advance_typo, "", 'Whats Included Background Color' );
				$this->add_border( $tab_id, $field_key . '_what_included_border', '#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included', 'Whats Included Border' );
				$description = __( 'Note: Add this CSS class <strong>"wfacp_for_mb_style"</strong> here if your checkout page width is less than 375px on desktop browser', 'woofunnels-aero-checkout' );

				$this->add_text( $tab_id, 'product_switcher_mobile_style', __( 'Whats Included CSS Class', 'woofunnels-aero-checkout' ), '', [], '', $description );

				$this->ajax_session_settings[] = 'product_switcher_mobile_style';

			}
		}

		/**
		 * @param $field STring
		 * @param $this \Elementor\Widget_Base
		 */
		protected function order_total( $field_key ) {

			$tab_id = $this->add_tab( __( 'Order Total', 'woocommerce' ) );

			$order_total_text_color_opt = [
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td strong > span',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td strong > span bdi',
				'#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td strong > span span.woocommerce-Price-currencySymbol',
			];

			$order_total_text_color_opt = implode( ',', $order_total_text_color_opt );
			$this->add_typography( $tab_id, $field_key . '_typography', $order_total_text_color_opt );
			$order_total_bg_color_opt = '#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total .wfacp_order_total_wrap';
			$this->add_background_color( $tab_id, $field_key . '_order_total_bg_color', $order_total_bg_color_opt, "", 'Background Color' );
			$this->add_border( $tab_id, $field_key . '_order_total_border_sec', $order_total_bg_color_opt );

		}

		protected function order_coupon( $field_key ) {

			$tab_id = $this->add_tab( __( 'Coupon', 'woocommerce' ) );
			$this->add_heading( $tab_id, __( 'Field', 'woofunnels-aero-checkout' ), '' );

			$coupon_typography_opt = [
				'#wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .woocommerce-info > a',
				'#wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .woocommerce-info > a:not(.wfacp_close_icon):not(.button-social-login):not(.wfob_btn_add):not(.ywcmas_shipping_address_button_new):not(.wfob_qv-button):not(.wfob_read_more_link):not(.wfacp_step_text_have ):not(.wfacp_cart_link)',
			];


			$this->add_typography( $tab_id, $field_key . '_coupon_typography', implode( ',', $coupon_typography_opt ), 'Link Typography' );
			$form_fields_label_typo = '#wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper label.wfacp-form-control-label';
			$this->add_typography( $tab_id, $field_key . '_label_typo', $form_fields_label_typo, __( 'Label Typography', 'woofunnels-aero-checkout' ) );
			$fields_options = ' #wfacp-e-form .wfacp_main_form .wfacp_coupon_field_box p.wfacp-form-control-wrapper .wfacp-form-control';
			$this->add_typography( $tab_id, $field_key . '_input_typo', $fields_options, __( 'Input Typography' ) );
			$this->add_border_color( $tab_id, $field_key . '_focus_color', '#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_coupon_field_box p.wfacp-form-control-wrapper .wfacp-form-control:focus', '#61bdf7', __( 'Focus Color', 'woofunnels-aero-checkout' ), true );
			$this->add_border( $tab_id, $field_key . '_coupon_border', $fields_options, __( 'Input Border' ) );
			$this->add_heading( $tab_id, __( 'Button Normal', 'woofunnels-aero-checkout' ) );
			/* Button color setting */

			$btnkey       = '#wfacp-e-form .wfacp_main_form .wfacp_woocommerce_form_coupon .wfacp-coupon-section .wfacp_coupon_field_box .wfacp-coupon-field-btn';
			$btnkey_hover = '#wfacp-e-form .wfacp_main_form .wfacp_woocommerce_form_coupon .wfacp-coupon-section .wfacp_coupon_field_box .wfacp-coupon-field-btn:hover';

			$this->add_background_color( $tab_id, $field_key . '_btn_bg_color_1', $btnkey, '#999', __( 'Background', 'woofunnels-aero-checkout' ) );
			$this->add_color( $tab_id, $field_key . '_btn_text_color_1', $btnkey, __( 'Label', 'woofunnels-aero-checkout' ) );

			$this->add_heading( $tab_id, __( 'Button Hover', 'woofunnels-aero-checkout' ) );
			$this->add_background_color( $tab_id, $field_key . '_btn_bg_hover_color', $btnkey_hover, '#878484', __( 'Background', 'woofunnels-aero-checkout' ) );
			$this->add_color( $tab_id, $field_key . '_btn_bg_hover_text_color', $btnkey_hover, __( 'Label', 'woofunnels-aero-checkout' ) );

			$this->add_heading( $tab_id, __( 'Button Text', 'woofunnels-aero-checkout' ) );
			$this->add_text( $tab_id, 'form_coupon_button_text', __( 'Coupon Button Text', 'woofunnels-aero-checkout' ), __( 'Apply', 'woocommerce' ) );

			$this->add_heading( $tab_id, __( 'Button Typography', 'woofunnels-aero-checkout' ) );
			$this->custom_typography( $tab_id, $field_key . '_btn_typo', $btnkey, '' );

			/* Button color setting End*/
		}


		/**
		 * @param $field STring
		 * @param $this \Elementor\Widget_Base
		 */
		protected function generate_html_block( $field_key ) {
			if ( method_exists( $this, $field_key ) ) {
				$this->{$field_key}( $field_key );
			}
		}

		protected function divider_field() {
			return [
				'wfacp_start_divider_billing',
				'wfacp_start_divider_shipping',
				'wfacp_end_divider_billing',
				'wfacp_end_divider_shipping'
			];
		}

		public function pick_data( $status, $tag, $attr ) {

			if ( ( $tag === 'oxy-' . $this->slug() ) && ! empty( $attr ) && ! empty( $attr['ct_options'] ) ) {
				$ct_options = json_decode( $attr['ct_options'], true );
				if ( is_array( $ct_options ) && isset( $ct_options['media'] ) ) {
					$this->media_settings = $ct_options['media'];
				}
			}

			return $status;
		}

		public function parse_render_settings() {
			if ( ! defined( 'OXY_ELEMENTS_API_AJAX' ) ) {
				return;
			}

			oxygen_vsb_ajax_request_header_check();

			$component_json      = file_get_contents( 'php://input' );//phpcs:ignore
			$component           = json_decode( $component_json, true );
			$options             = $component['options']['original'];
			$options['selector'] = $component['options']['selector'];

			if ( is_array( $component['options']['media'] ) && count( $component['options']['media'] ) > 0 ) {
				$this->media_settings = $component['options']['media'];
			}

		}


	}
}