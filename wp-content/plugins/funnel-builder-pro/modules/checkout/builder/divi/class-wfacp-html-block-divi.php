<?php
if ( ! class_exists( 'WFACP_Divi_HTML_BLOCK' ) ) {
	#[AllowDynamicProperties]
	abstract class WFACP_Divi_HTML_BLOCK extends WFACP_Divi_Field {

		public function __construct() {
			parent::__construct();
			add_action( 'wp_footer', [ $this, 'localize_array' ] );

			WFACP_DIVI::set_locals( $this->get_local_slug(), $this->get_id() );

		}


		final public function render( $attrs, $content = null, $render_slug = '' ) {
			if ( ! wp_doing_ajax() && is_admin() ) {
				return;
			}
			$this->prepare_css( $attrs, $content, $render_slug );

			if ( apply_filters( 'wfacp_print_divi_widget', true, $this->get_id(), $this ) ) {
				$setting = $this->props;
				$id      = $this->get_id();
				WFACP_Common::set_session( $id, $setting );

				return $this->html( $attrs, $content, $render_slug );
			}

		}

		protected function html( $attrs, $content = null, $render_slug = '' ) {
			return '';
		}


		protected function available_html_block() {
			$block = [ 'product_switching', 'order_total' ];

			return apply_filters( 'wfacp_html_block_elements', $block );
		}

		public function get_title() {
			return __( 'Checkout Form', 'woofunnels-aero-checkout' );
		}

		protected function order_summary( $field_key ) {





			$tab_id = $this->add_tab( __( 'Order summary', 'woocommerce' ), 2 );
			$this->add_heading( $tab_id, 'Product' );

			$cart_item_color = [
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .wfacp_order_summary_item_name',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .product-name .product-quantity',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody td.product-total',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .cart_item .product-total span',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .cart_item .product-total span.amount',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .cart_item .product-total span.amount bdi',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .cart_item .product-total small',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container dl',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container dd',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container dt',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .wfacp_order_summary_container p',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody tr span.amount',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody tr span.amount bdi',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody dl',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody dd',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody dt',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody p',
				'%%order_class%% #wfacp-e-form  table.shop_table tbody tr td span:not(.wfacp-pro-count)',
				'%%order_class%% #wfacp-e-form  #wfacp-sec-wrapper .wfacp_show_icon_wrap',
				'%%order_class%% #wfacp-e-form  #wfacp-sec-wrapper .wfacp_show_icon_wrap *',
				'%%order_class%% #wfacp-e-form  #wfacp-sec-wrapper .wfacp_show_price_wrap *',
				'%%order_class%% #wfacp-e-form  #wfacp-sec-wrapper .wfacp_show_price_wrap span bdi',
			];


			$this->add_typography( $tab_id, $field_key . '_cart_item_typo', implode( ',', $cart_item_color ) );

			$this->add_color( $tab_id, $field_key . '_cart_item_color', $cart_item_color, '', '#666666' );


			$border_image_color = [ '%%order_class%% #wfacp-e-form table.shop_table tr.cart_item .product-image img' ];
			$this->add_border_color( $tab_id, 'mini_product_image_border_color', $border_image_color, '', __( 'Image Border Color', 'woofunnel-aero-checkout' ), false, [ 'order_summary_enable_product_image' => 'on' ] );

			$this->add_border_radius( $tab_id, $field_key . '_cart_item_image_border_radius', $border_image_color );

			/* Strike Through Style Setting Order Summary Field */
			$this->price_strike_through_style_settings( $tab_id, $field_key, '%%order_class%% #wfacp-e-form #wfacp-sec-wrapper' );


			$this->add_heading( $tab_id, __( 'Subtotal', 'woocommerce' ) );


			$cart_subtotal_color_option = [
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount)',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) th',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) th span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td small',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td bdi',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td span.amount',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr:not(.order-total):not(.cart-discount) td a',
			];

			$fields_options    = [
				'font_weight' => [
					'default' => '400',
				],
			];
			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];
			$this->add_typography( $tab_id, 'order_summary_product_meta_typo', implode( ',', $cart_subtotal_color_option ), '', '', [], $font_side_default );
			$this->add_color( $tab_id, 'order_summary_product_meta_color', $cart_subtotal_color_option, '', '' );


			$cart_total_color_option = [
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td p',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td small',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td a',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td p',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th small',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th a',
			];


			$cart_total_label_typo_option = [
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th small',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total th a',
			];
			$cart_total_value_typo_option = [
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td p',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td span',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td small',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td a',
				'%%order_class%% #wfacp-e-form  table.shop_table tfoot tr.order-total td p',
			];

			$this->add_heading( $tab_id, 'Total' );
			$font_side_default = [ 'default' => '16px', 'unit' => 'px' ];


			$this->add_heading( $tab_id, __( 'Label Typography', 'woocommerce' ) );
			$this->add_typography( $tab_id, $field_key . '_cart_total_label_typo', implode( ',', $cart_total_label_typo_option ), $fields_options, '', [], $font_side_default );
			$this->add_heading( $tab_id, __( 'Price Typography', 'woocommerce' ) );
			$this->add_typography( $tab_id, $field_key . '_cart_subtotal_heading_typo', implode( ',', $cart_total_value_typo_option ), $fields_options, '', [], $font_side_default );
			$this->add_color( $tab_id, $field_key . '_cart_subtotal_heading_color', $cart_total_color_option, '', '' );

			$this->add_heading( $tab_id, __( 'Divider', 'woocommerce' ) );


			$divider_line_color = [
				'%%order_class%% #wfacp-e-form  table.shop_table tbody .wfacp_order_summary_item_name',
				'%%order_class%% #wfacp-e-form table.shop_table tr.cart_item',
				'%%order_class%% #wfacp-e-form table.shop_table tr.cart-subtotal',
				'%%order_class%% #wfacp-e-form table.shop_table tr.order-total',
			];

			$this->add_border_color( $tab_id, $field_key . '_divider_line_color', $divider_line_color, '' );

		}


		/**
		 * @param $field STring
		 * @param \Elementor\Widget_Base
		 */
		protected function product_switching( $field_key ) {


			$tab_id = $this->add_tab( __( 'Product Switcher', 'woofunnel-aero-checkout' ), 2 );

			/*  Selected Items Setting */

			$controls_tab_id = $this->add_controls_tabs( $tab_id, "Color Settings" );


			/* Typography  */
			$field_keys = [];


			$product_switcher_typo_option = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_name_inner *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_attributes .wfacp_selected_attributes  *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_quantity_selector input',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_switcher_col_2 .wfacp_product_subs_details > span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_subs_details span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_subs_details *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_select_options .wfacp_qv-button',

				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec > span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec > span *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec > span bdi',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec ins span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec ins span bdi',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec del',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_price_sec del *',
			];

			$product_switcher_typo_string = implode( ',', $product_switcher_typo_option );

			$temp_keys  = $this->add_typography( $tab_id, 'selected_item_typography', $product_switcher_typo_string );
			$field_keys = array_merge( $field_keys, $temp_keys );
			/* Items Color */
			$selector = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .product-name .wfacp_product_switcher_item',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_row_quantity',
			];

			$field_keys[] = $this->add_color( $tab_id, $field_key . '_label_color', $selector, 'Item Color', '' );

			/* Items Price Color */

			$itemPriceColorOpt = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp-selected-product .product-price',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp-selected-product .product-price span',
			];
			$field_keys[]      = $this->add_color( $tab_id, $field_key . '_price_color', $itemPriceColorOpt, 'Item Price Color ', "" );

			$variant_color = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form .wfacp_selected_attributes .wfacp_pro_attr_single span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form .wfacp_selected_attributes .wfacp_pro_attr_single span:last-child',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_subs_details',
				'%%order_class%%  #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_subs_details span',
			];
			$field_keys[]  = $this->add_color( $tab_id, $field_key . '_variant_color', $variant_color, 'Variant Color', '#666666' );


			/* Background Color */
			$itemBgColor  = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product',
			];
			$field_keys[] = $this->add_background_color( $tab_id, $field_key . '_item_background', $itemBgColor, "", 'Background Color' );


			/* Border selected */

			$fields_options = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product'
			];
			$default_args   = [
				'border_type'          => 'solid',
				'border_width_top'     => '1',
				'border_width_bottom'  => '1',
				'border_width_left'    => '1',
				'border_width_right'   => '1',
				'border_radius_top'    => '0',
				'border_radius_bottom' => '0',
				'border_radius_left'   => '0',
				'border_radius_right'  => '0',
				'border_color'         => '#dddddd',
			];
			$border_keys    = $this->add_border( $tab_id, $field_key . '_border', implode( ',', $fields_options ), [], $default_args );

			/* Saving text Start*/
			$field_keys = $this->product_switching_saving_text( $tab_id, $field_keys, 'selected' );
			/* Saving text End*/


			$field_keys = array_merge( $field_keys, $border_keys );
			/* Border  End*/


			$this->add_controls_tab( $controls_tab_id, __( 'Selected Items', 'woofunnels-aero-checkout' ), $field_keys );

			$field_keys = [];
			/* Optional Item Setting */


			$product_switcher_typo_optional = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_name_inner *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_attributes .wfacp_selected_attributes  *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_quantity_selector input',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_switcher_col_2 .wfacp_product_subs_details > span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_subs_details span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_subs_details *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_select_options .wfacp_qv-button',

				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec > span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec > span *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec > span bdi',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec ins span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec ins span bdi',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec del',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_price_sec del *',
			];

			$product_switcher_typo_optional_string = implode( ',', $product_switcher_typo_optional );

			$temp_keys  = $this->add_typography( $tab_id, $field_key . '_optional_item_typography', $product_switcher_typo_optional_string );
			$field_keys = array_merge( $field_keys, $temp_keys );

			/* Label Color Setting */
			$optionalLabelColorOpt = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .product-name .wfacp_product_switcher_item',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_row_quantity'
			];

			$field_keys[] = $this->add_color( $tab_id, $field_key . '_optional_label_color', $optionalLabelColorOpt, esc_attr__( 'Item Color', 'woofunnels-aero-checkout' ) );


			/* Items Price Color */

			$optional_price_color_option = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .product-price',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp_product_price_sec span',
			];
			$field_keys[]                = $this->add_color( $tab_id, $field_key . '_optional_price_color', $optional_price_color_option, 'Item Price Color' );


			/* Background Color */

			$field_keys[] = $this->add_background_color( $tab_id, $field_key . '_optional_background', [ '%%order_class%% .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product)' ], "#ffffff", 'Background Color' );

			$field_keys[] = $this->add_background_color( $tab_id, $field_key . '_optional_background_hover', [ '%%order_class%% .wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product):hover' ], "#fbfbfb", 'Background Hover Color' );


			/* Non Selected border*/
			$fields_options = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product)'
			];
			$border_keys    = $this->add_border( $tab_id, $field_key . '_border_non_selected', implode( ',', $fields_options ), [], $default_args );
			/* Saving text Start*/
			$field_keys = $this->product_switching_saving_text( $tab_id, $field_keys, 'non_selected' );
			/* Saving text End*/
			$field_keys = array_merge( $field_keys, $border_keys );

			/* Non Selected border*/

			$this->add_controls_tab( $controls_tab_id, __( 'Non-selected Items', 'woofunnels-aero-checkout' ), $field_keys );

			unset( $field_keys );


			//Best value Controls

			if ( true === WFACP_Common::is_best_value_available() ) {
				$this->add_heading( $tab_id, __( 'Best Value', 'woofunnels-aero-checkout' ) );
				$selector = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_best_value.wfacp_top_left_corner',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_best_value.wfacp_top_right_corner',
				];

				/* Best Value: Color Setting */
				$this->add_typography( $tab_id, $field_key . '_best_value_typography', '%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value' );
				$this->add_color( $tab_id, $field_key . '_best_value_text_color', $selector );
				$this->add_background_color( $tab_id, $field_key . '_best_value_bg_color', $selector, "", 'Background Color' );

				$this->add_border_color( $tab_id, '_best_value_border_color', [ '%%order_class%% #wfacp-e-form .wfacp_main_form .shop_table.wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item.wfacp_best_val_wrap' ], '', __( 'Best Value Item Border Color', 'woofunnel-aero-checkout' ) );


				/* Typography */
				$this->add_border( $tab_id, $field_key . '_best_value_border', implode( ',', $selector ) );


			}


			if ( true === WFACP_Common::is_what_included_available() ) {


				$this->add_heading( $tab_id, __( "Custom Product Description", 'woofunnels-aero-checkout' ) );


				/* Section Heading Setting */
				$what_included_heading_opt = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included h3'
				];
				$this->add_heading( $tab_id, __( 'Heading', 'woofunnels-aero-checkout' ), 'none' );
				$this->add_typography( $tab_id, $field_key . '_what_included_heading', implode( ',', $what_included_heading_opt ) );
				$this->add_color( $tab_id, $field_key . '_what_included_heading_color', $what_included_heading_opt );


				/* Product Title Setting */
				$this->add_heading( $tab_id, __( 'Title', 'woofunnels-aero-checkout' ), 'none' );
				$this->add_typography( $tab_id, $field_key . '_what_included_product_title', '%%order_class%%  #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description h4' );
				$this->add_color( $tab_id, $field_key . '_what_included_product_title_color', [ '%%order_class%% #wfacp-e-form .wfacp_whats_included .wfacp_product_switcher_description h4' ], '#666666' );


				/* Product Description Setting */
				$this->add_heading( $tab_id, __( 'Description', 'woofunnels-aero-checkout' ), 'none' );
				$fields_options = [
					'font_weight' => [
						'default' => '400',
					],
				];

				$description_typo = [
					'%%order_class%%  #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description .wfacp_description p',
					'%%order_class%%  #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description .wfacp_description',
				];
				$this->add_typography( $tab_id, $field_key . '_what_included_product_description', implode( ',', $description_typo ), $fields_options );
				$this->add_color( $tab_id, $field_key . '_what_included_product_title_description', $description_typo, '#6c6c6c' );


				$this->add_heading( $tab_id, __( 'Advanced', 'woofunnels-aero-checkout' ), 'none' );
				$advance_typo = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included',
				];
				$this->add_background_color( $tab_id, $field_key . '_what_included_bg', $advance_typo, "", 'Background Color' );

				$default        = [ 'top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1, 'unit' => 'px' ];
				$fields_options = [
					'border' => [
						'default' => 'solid'
					],
					'width'  => [
						'default' => [
							'top'    => 1,
							'bottom' => 1,
							'left'   => 1,
							'right'  => 1,
						]
					],
					'color'  => [
						'default' => '#efefef'
					],

				];
				$this->add_border( $tab_id, $field_key . '_what_included_border', '%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included', [], $default, $fields_options );

				$description = __( 'Note: Add this CSS class <strong>"wfacp_for_mb_style"</strong> here if your checkout page width is less than 375px on desktop browser', 'woofunnels-aero-checkout' );
				$this->add_text( $tab_id, 'product_switcher_mobile_style', __( 'CSS Class', 'woofunnels-aero-checkout' ), '', [], '', $description );

			}
		}

		/**
		 * @param $field STring
		 * @param $this \Elementor\Widget_Base
		 */
		protected function order_total( $field_key ) {

			$tab_id = $this->add_tab( __( 'Order Total', 'woocommerce' ), 2 );

			$order_total_text_color_opt = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table tr td',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table tr th',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table tr th *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table tr td *',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td strong > span',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td strong > span bdi',
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap tr td strong > span span.woocommerce-Price-currencySymbol',
			];
			$font_side_default          = [ 'default' => '14px', 'unit' => 'px' ];

			$this->add_typography( $tab_id, $field_key . '_typography', implode( ',', $order_total_text_color_opt ), '', '', [], $font_side_default );
			$this->add_color( $tab_id, $field_key . '_order_total_text_color', $order_total_text_color_opt, '', '' );

			$order_total_bg_color_opt = [
				'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_order_total .wfacp_order_total_wrap'
			];

			$this->add_background_color( $tab_id, $field_key . '_order_total_bg_color', $order_total_bg_color_opt, "#f8f8f8", 'Background Color' );
			$this->add_border( $tab_id, $field_key . '_order_total_border_sec', implode( ',', $order_total_bg_color_opt ) );

		}


		protected function order_coupon( $field_key ) {


			$tab_id = $this->add_tab( __( 'Coupon', 'woocommerce' ), 2 );

			$this->add_heading( $tab_id, __( 'Link ', 'woofunnel-aero-checkout' ), '' );


			$coupon_typography_opt = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .woocommerce-info > a',
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .woocommerce-info > a:not(.wfacp_close_icon):not(.button-social-login):not(.wfob_btn_add):not(.ywcmas_shipping_address_button_new):not(.wfob_qv-button):not(.wfob_read_more_link):not(.wfacp_step_text_have ):not(.wfacp_cart_link)',
			];

			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];
			$this->add_typography( $tab_id, $field_key . '_coupon_typography', implode( ',', $coupon_typography_opt ), '', '', [], $font_side_default );
			$this->add_color( $tab_id, $field_key . '_coupon_text_color', $coupon_typography_opt, '', '' );


			$this->add_heading( $tab_id, __( 'Label', 'woofunnel-aero-checkout' ) );


			$form_fields_label_typo = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper label.wfacp-form-control-label',

			];
			$fields_options         = [
				'font_weight' => [
					'default' => '400',
				],
			];

			$font_side_default = [ 'default' => '12px', 'unit' => 'px' ];
			$this->add_typography( $tab_id, $field_key . '_label_typo', implode( ',', $form_fields_label_typo ), $fields_options, [], __( 'Label Typography', 'woofunnels-aero-checkout' ), $font_side_default );


			$form_fields_label_color_opt = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper label.wfacp-form-control-label',
			];
			$this->add_color( $tab_id, $field_key . '_label_color', $form_fields_label_color_opt, '', __( 'Label Color', 'woofunnels-aero-checkout' ) );


			$fields_options = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper .wfacp-form-control',
			];

			$this->add_heading( $tab_id, __( 'Field', 'woofunnel-aero-checkout' ), '' );

			$optionString = implode( ',', $fields_options );
			$this->add_typography( $tab_id, $field_key . '_input_typo', $optionString, [], [], __( 'Coupon Typography' ) );


			$inputColorOption = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper .wfacp-form-control',
			];
			$this->add_color( $tab_id, $field_key . '_input_color', $inputColorOption, '', __( 'Coupon Color', 'woofunnels-aero-checkout' ) );

			$focus_color = [ '%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper .wfacp-form-control:focus' ];


			$this->add_border_color( $tab_id, $field_key . '_focus_color', $focus_color, '', __( 'Focus Color', 'woofunnel-aero-checkout' ), true );


			$fields_options = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper .wfacp-form-control',
			];
			$default        = [
				'border_type'          => 'solid',
				'border_width_top'     => '1',
				'border_width_bottom'  => '1',
				'border_width_left'    => '1',
				'border_width_right'   => '1',
				'border_radius_top'    => '4',
				'border_radius_bottom' => '4',
				'border_radius_left'   => '4',
				'border_radius_right'  => '4',
				'border_color'         => '#bfbfbf',
			];


			$this->add_border( $tab_id, $field_key . '_coupon_border', implode( ',', $fields_options ), [], $default );


			$this->add_heading( $tab_id, __( 'Button', 'woofunnel-aero-checkout' ) );


			/* Button color setting */


			$btnkey = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .wfacp-coupon-field-btn:not([disabled])',
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .wfacp-coupon-btn:not([disabled])',
			];


			$btnkey_hover = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .wfacp-coupon-field-btn:not([disabled]):hover',
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .wfacp-coupon-btn:not([disabled]):hover',
			];

			$control_tab_id = $this->add_controls_tabs( $tab_id, '' );
			$field_keys     = [];
			$field_keys[]   = $this->add_background_color( $tab_id, $field_key . '_btn_bg_color', $btnkey, '', __( 'Background', 'woofunnels-aero-checkout' ) );
			$field_keys[]   = $this->add_color( $tab_id, $field_key . '_btn_text_color', $btnkey, '', __( 'Label', 'woofunnels-aero-checkout' ) );

			$this->add_controls_tab( $control_tab_id, 'Normal', $field_keys );

			$field_keys = [];

			$field_keys[] = $this->add_background_color( $tab_id, $field_key . '_btn_bg_hover_color', $btnkey_hover, '', __( 'Background', 'woofunnels-aero-checkout' ) );
			$field_keys[] = $this->add_color( $tab_id, $field_key . '_btn_bg_hover_text_color', $btnkey_hover, '', __( 'Label', 'woofunnels-aero-checkout' ) );

			$btnkey_typo = [
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .wfacp-coupon-field-btn',
				'%%order_class%% #wfacp-e-form .wfacp-coupon-section .wfacp-coupon-page .wfacp-coupon-btn',
			];
			$this->add_typography( $tab_id, $field_key . '_btn_typo', implode( ',', $btnkey_typo ), __( 'Button Typography' ) );
			$this->add_controls_tab( $control_tab_id, 'Hover', $field_keys );
			/* Button color setting End*/
		}


		public function localize_array() {
			global $post;

			if ( ! is_null( $post ) && $post->post_type !== WFACP_Common::get_post_type_slug() ) {
				return;
			}
			$fields              = array_merge( $this->modules_fields, $this->tab_array );
			$border_data         = [];
			$box_data            = [];
			$border_start        = false;
			$margin_padding_data = [];
			$normal_data         = [];
			$typography_data     = [];
			$box_start           = false;


			foreach ( $fields as $key => $field ) {
				if ( isset( $field['c_type'] ) && 'wfacp_start_border' == $field['c_type'] ) {
					$border_start                       = true;
					$border_data[ $field['field_key'] ] = $field['selector'];
					continue;
				}
				if ( isset( $field['c_type'] ) && 'wfacp_end_border' == $field['c_type'] ) {
					$border_start = false;
					continue;
				}
				if ( isset( $field['c_type'] ) && 'wfacp_start_box_shadow' == $field['c_type'] ) {
					$box_start                       = true;
					$box_data[ $field['field_key'] ] = $field['selector'];
					continue;
				}
				if ( isset( $field['c_type'] ) && 'wfacp_end_box_shadow' == $field['c_type'] ) {
					$box_start = false;
					continue;
				}


				if ( true == $border_start || true == $box_start ) {
					continue;
				}

				if ( ! isset( $field['selector'] ) ) {
					continue;
				}
				$type     = isset( $fields[ $key ]['c_type'] ) ? $fields[ $key ]['c_type'] : ( isset( $fields[ $key ]['type'] ) ? $fields[ $key ]['type'] : '' );
				$property = $this->create_css_property( $key, $type );

				if ( empty( $property ) ) {
					continue;
				}


				if ( false !== strpos( $key, '_margin' ) || false !== strpos( $key, '_padding' ) ) {
					$margin_padding_data[ $key ] = $field['selector'];
					continue;
				} else {
					$normal_data[ $key ] = [ 'selector' => $field['selector'], 'property' => $property['property'] ];
				}
				if ( isset( $this->typography[ $key ] ) ) {
					$typography_data[ $key ] = $field['selector'];
				}
			}
			?>
            <script>
                function <?php echo $this->get_slug()?>_fields(utils, props) {
                    let data = {};
                    data.typography =<?php echo count( $this->typography ) > 0 ? json_encode( $this->typography ) : '{}'?>;
                    data.margin_padding =<?php echo count( $margin_padding_data ) > 0 ? json_encode( $margin_padding_data ) : '{}'?>;
                    data.normal_data =<?php echo count( $normal_data ) > 0 ? json_encode( $normal_data ) : '{}'?>;
                    data.typography_data =<?php echo count( $typography_data ) > 0 ? json_encode( $typography_data ) : '{}'?>;
                    data.border_data =<?php echo count( $border_data ) > 0 ? json_encode( $border_data ) : '{}'?>;
                    data.box_shadow =<?php echo count( $box_data ) > 0 ? json_encode( $box_data ) : '{}'?>;
                    return wfacp_prepare_divi_css(data, utils, props);
                }
            </script>
			<?php
		}


		public function prepare_css( $attrs, $content, $render_slug ) {


			$fields = array_merge( $this->modules_fields, $this->tab_array );


			if ( empty( $fields ) ) {
				return;
			}

			$border_data  = [];
			$box_data     = [];
			$border_start = false;


			foreach ( $fields as $key => $field ) {

				/*-----------------------For Border Styling ---------------------------------*/
				if ( isset( $field['c_type'] ) && 'wfacp_start_border' == $field['c_type'] ) {
					$border_start                       = true;
					$border_data[ $field['field_key'] ] = $field['selector'];
					continue;
				}
				if ( isset( $field['c_type'] ) && 'wfacp_end_border' == $field['c_type'] ) {
					$border_start = false;
					continue;
				}

				/*-----------------------For Box Shadow Styling ---------------------------------*/

				if ( isset( $field['c_type'] ) && 'wfacp_start_box_shadow' == $field['c_type'] ) {
					$border_start                    = true;
					$box_data[ $field['field_key'] ] = $field['selector'];

				}
				if ( isset( $field['c_type'] ) && 'wfacp_end_box_shadow' == $field['c_type'] ) {
					$border_start = false;
					continue;
				}


				if ( true == $border_start ) {
					continue;
				}

				if ( ! isset( $field['selector'] ) ) {
					continue;
				}


				$type     = isset( $fields[ $key ]['c_type'] ) ? $fields[ $key ]['c_type'] : ( isset( $fields[ $key ]['type'] ) ? $fields[ $key ]['type'] : '' );
				$property = $this->create_css_property( $key, $type );

				if ( empty( $property ) ) {
					continue;
				}


				$css_prop = $property['property'];
				if ( false !== strpos( $key, '_margin' ) || false !== strpos( $key, '_padding' ) ) {


					ET_Builder_Element::set_style( $render_slug, array(
						'selector'    => $field['selector'],
						'declaration' => et_builder_get_element_style_css( $this->props[ $key ], $type, true ),
					) );

					$slug_value_tablet            = $this->props[ $key . '_tablet' ];
					$slug_value_phone             = $this->props[ $key . '_phone' ];
					$slug_value_last_edited       = $this->props[ $key . '_last_edited' ];
					$slug_value_responsive_active = et_pb_get_responsive_status( $slug_value_last_edited );

					if ( isset( $slug_value_tablet ) && ! empty( $slug_value_tablet ) && $slug_value_responsive_active ) {
						ET_Builder_Element::set_style( $render_slug, array(
							'selector'    => $field['selector'],
							'declaration' => et_builder_get_element_style_css( $slug_value_tablet, $type, true ),
							'media_query' => ET_Builder_Element::get_media_query( 'max_width_980' ),
						) );
					}

					if ( isset( $slug_value_phone ) && ! empty( $slug_value_phone ) && $slug_value_responsive_active ) {
						ET_Builder_Element::set_style( $render_slug, array(
							'selector'    => $field['selector'],
							'declaration' => et_builder_get_element_style_css( $slug_value_phone, $type, true ),
							'media_query' => ET_Builder_Element::get_media_query( 'max_width_767' ),

						) );
					}


				} elseif ( isset( $this->props[ $key ] ) && '' !== $this->props[ $key ] ) {

					$important = ' !important';

					if ( $key != 'wfacp_font_family_typography_font_size' && $key != 'wfacp_mini_cart_font_family_font_size' ) {


						if ( $key == 'wfacp_form_fields_label_typo_line_height' ) {
							$important = '';
						}
						ET_Builder_Element::set_style( $render_slug, array(
							'selector'    => $field['selector'],
							'declaration' => sprintf( '' . $css_prop . ': %1$s;', $this->props[ $key ] . $important ),
						) );


					}


					if ( et_pb_responsive_options()->is_responsive_enabled( $this->props, $key ) ) {
						$responsive_value = et_pb_responsive_options()->get_property_values( $this->props, $key );

						if ( isset( $responsive_value['tablet'] ) ) {
							ET_Builder_Element::set_style( $render_slug, array(
								'selector'    => $field['selector'],
								'declaration' => sprintf( '' . $css_prop . ': %1$s;', $responsive_value['tablet'] . " !important" ),
								'media_query' => ET_Builder_Element::get_media_query( 'max_width_980' ),
							) );

						}
						if ( isset( $responsive_value['phone'] ) ) {

							ET_Builder_Element::set_style( $render_slug, array(
								'selector'    => $field['selector'],
								'declaration' => sprintf( '' . $css_prop . ': %1$s;', $responsive_value['phone'] . " !important" ),
								'media_query' => ET_Builder_Element::get_media_query( 'max_width_767' ),
							) );
						}

					}

					if ( $key == 'wfacp_form_fields_focus_color' || $key == 'order_coupon_focus_color' || $key == 'wfacp_form_mini_cart_coupon_focus_color' ) {

						ET_Builder_Element::set_style( $render_slug, array(
							'selector'    => $field['selector'],
							'declaration' => sprintf( 'box-shadow: %1$s;', "0 0 0 1px " . $this->props[ $key ] . " !important" ),
						) );

					}


				}


				if ( is_array( $this->typography ) && count( $this->typography ) > 0 && isset( $this->typography[ $key ] ) ) {
					$typography = $this->typography[ $key ];
					if ( isset( $this->props[ $typography ] ) ) {
						ET_Builder_Element::set_style( $render_slug, array(
							'selector'    => $field['selector'],
							'declaration' => et_builder_set_element_font( $this->props[ $typography ] ),
						) );
					}

				}


			}

			if ( count( $box_data ) > 0 ) {
				foreach ( $box_data as $s_key => $selector_value ) {

					$type              = isset( $this->props[ $s_key . '_shadow_enable' ] ) ? $this->props[ $s_key . '_shadow_enable' ] : $fields[ $s_key . '_shadow_enable' ]['default'];
					$shadow_type       = isset( $this->props[ $s_key . '_shadow_type' ] ) ? $this->props[ $s_key . '_shadow_type' ] : $fields[ $s_key . '_shadow_type' ]['default'];
					$shadow_color      = isset( $this->props[ $s_key . '_shadow_color' ] ) ? $this->props[ $s_key . '_shadow_color' ] : $fields[ $s_key . '_shadow_color' ]['default'];
					$shadow_horizontal = isset( $this->props[ $s_key . '_shadow_horizontal' ] ) ? $this->props[ $s_key . '_shadow_horizontal' ] : $fields[ $s_key . '_shadow_horizontal' ]['default'];
					$shadow_vertical   = isset( $this->props[ $s_key . '_shadow_vertical' ] ) ? $this->props[ $s_key . '_shadow_vertical' ] : $fields[ $s_key . '_shadow_vertical' ]['default'];
					$shadow_blur       = isset( $this->props[ $s_key . '_shadow_blur' ] ) ? $this->props[ $s_key . '_shadow_blur' ] : $fields[ $s_key . '_shadow_blur' ]['default'];
					$shadow_spread     = isset( $this->props[ $s_key . '_shadow_spread' ] ) ? $this->props[ $s_key . '_shadow_spread' ] : $fields[ $s_key . '_shadow_spread' ]['default'];

					if ( 'on' === $type ) {
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector_value,
							'declaration' => sprintf( 'box-shadow:%s;', $shadow_horizontal . "px " . $shadow_vertical . "px " . $shadow_blur . "px " . $shadow_spread . "px " . $shadow_color . " " . $shadow_type )
						] );
					}
				}
			}


			if ( count( $border_data ) > 0 ) {

				foreach ( $border_data as $key => $selector ) {
					$type                = isset( $this->props[ $key . '_border_type' ] ) ? $this->props[ $key . '_border_type' ] : $fields[ $key . '_border_type' ]['default'];
					$width_top           = isset( $this->props[ $key . '_border_width_top' ] ) ? $this->props[ $key . '_border_width_top' ] : $fields[ $key . '_border_width_top' ]['default'];
					$width_bottom        = isset( $this->props[ $key . '_border_width_bottom' ] ) ? $this->props[ $key . '_border_width_bottom' ] : $fields[ $key . '_border_width_bottom' ]['default'];
					$width_left          = isset( $this->props[ $key . '_border_width_left' ] ) ? $this->props[ $key . '_border_width_left' ] : $fields[ $key . '_border_width_left' ]['default'];
					$width_right         = isset( $this->props[ $key . '_border_width_right' ] ) ? $this->props[ $key . '_border_width_right' ] : $fields[ $key . '_border_width_right' ]['default'];
					$border_color        = isset( $this->props[ $key . '_border_color' ] ) ? $this->props[ $key . '_border_color' ] : $fields[ $key . '_border_color' ]['default'];
					$radius_top_left     = isset( $this->props[ $key . '_border_radius_top' ] ) ? $this->props[ $key . '_border_radius_top' ] : $fields[ $key . '_border_radius_top' ]['default'];
					$radius_top_right    = isset( $this->props[ $key . '_border_radius_bottom' ] ) ? $this->props[ $key . '_border_radius_bottom' ] : $fields[ $key . '_border_radius_bottom' ]['default'];
					$radius_bottom_right = isset( $this->props[ $key . '_border_radius_left' ] ) ? $this->props[ $key . '_border_radius_left' ] : $fields[ $key . '_border_radius_left' ]['default'];
					$radius_bottom_left  = isset( $this->props[ $key . '_border_radius_right' ] ) ? $this->props[ $key . '_border_radius_right' ] : $fields[ $key . '_border_radius_right' ]['default'];

					if ( 'none' == $type ) {
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => 'border-style:none !important;'
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => 'border-radius:none !important;'
						] );
					} else {
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-color:%s;', $border_color )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-style:%s;', $type )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-top-width:%spx;', $width_top )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-bottom-width:%spx;', $width_bottom )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-left-width:%spx;', $width_left )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-right-width:%spx;', $width_right )
						] );

						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-top-left-radius:%spx;', $radius_top_left )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-top-right-radius:%spx;', $radius_top_right )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-bottom-right-radius:%spx;', $radius_bottom_right )
						] );
						ET_Builder_Element::set_style( $render_slug, [
							'selector'    => $selector,
							'declaration' => sprintf( 'border-bottom-left-radius:%spx;', $radius_bottom_left )
						] );
					}

				}


			}

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

		public function product_switching_saving_text( $tab_id, $field_keys, $field_key ) {

			$field_keys[]      = $this->add_heading( $tab_id, __( 'Saving Text', 'woofunnels-aero-checkout' ) );
			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];


			if ( false !== strpos( $field_key, 'non_selected' ) ) {

				$saveTextColorOption = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span bdi',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_switcher_col_2 .wfacp_product_subs_details > span:not(.subscription-details):not(.woocommerce-Price-amount):not(.woocommerce-Price-currencySymbol)',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_switcher_col_2 .wfacp_product_subs_details lebel',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_switcher_col_2 .wfacp_product_subs_details span:not(.subscription-details):not(.woocommerce-Price-amount):not(.woocommerce-Price-currencySymbol)'
				];


				$typography = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text span bdi',
				];
			} else {
				$saveTextColorOption = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span bdi',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_switcher_col_2 .wfacp_product_subs_details > span:not(.subscription-details):not(.woocommerce-Price-amount):not(.woocommerce-Price-currencySymbol)',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_switcher_col_2 .wfacp_product_subs_details lebel',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_switcher_col_2 .wfacp_product_subs_details span:not(.subscription-details):not(.woocommerce-Price-amount):not(.woocommerce-Price-currencySymbol)'
				];


				$typography = [
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span',
					'%%order_class%% #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text span bdi',
				];
			}

			$typography_selectors = implode( ',', $typography );


			$field_keys[] = $this->add_color( $tab_id, $field_key . '_you_save_color', $saveTextColorOption, '', '#b22323' );
			$temp         = $this->add_typography( $tab_id, $field_key . '_you_save_typo', implode( ',', $typography ), '', '', [], $font_side_default );

			return array_merge( $field_keys, $temp );

		}


	}
}