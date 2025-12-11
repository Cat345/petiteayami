<?php
if ( ! class_exists( 'WFACP_Divi_Summary' ) ) {
	class WFACP_Divi_Summary extends WFACP_Divi_HTML_BLOCK {

		public $slug = 'wfacp_checkout_form_summary';
		protected $get_local_slug = 'order_summary';
		protected $id = 'wfacp_order_summary_widget';

		public function __construct() {
			$this->name = __( 'Mini Cart', 'woofunnls-aero-checkout' );
			parent::__construct();
		}

		/**
		 * @param $template WFACP_Template_Common;
		 */
		public function setup_data( $template ) {
			$this->mini_cart();
		}


		protected function mini_cart() {

			$tab_id = $this->add_tab( __( 'Heading', 'woofunnels-aero-checkout' ), 5 );
			$this->add_text( $tab_id, 'mini_cart_heading', __( 'Title', 'woofunnels-aero-checkout' ), __( 'Order summary', 'woocommerce' ) );


			$product_tab_id = $this->add_tab( __( 'Products', 'woofunnels-aero-checkout' ), 5 );

			$this->add_switcher( $product_tab_id, 'enable_product_image', __( 'Image', 'woofunnels-aero-checkout' ), 'on' );
			$this->add_switcher( $product_tab_id, 'enable_quantity_box', __( 'Quantity Switcher', 'woofunnels-aero-checkout' ), 'on' );
			$this->add_switcher( $product_tab_id, 'enable_delete_item', __( 'Allow Deletion', 'woofunnels-aero-checkout' ), 'on' );


			/**
			 * -----------------------------Strike Through Price Setting on the mini cart-------------------------------------
			 */


			$this->price_strike_through_content_settings( $product_tab_id, 'mini_cart' );
			/*-------------------------------------------------------- End -----------------------------------------------------*/


			$coupon_tab_id = $this->add_tab( __( 'Coupon', 'woofunnels-aero-checkout' ), 5 );
			$this->add_text( $coupon_tab_id, 'mini_cart_coupon_button_text', __( 'Coupon Button Text', 'woofunnels-aero-checkout' ), __( 'Apply', 'woocommerce' ), [ 'enable_coupon' => 'on' ] );
			$this->add_switcher( $coupon_tab_id, 'enable_coupon', __( 'Enable', 'woofunnels-aero-checkout' ), 'off' );
			$this->add_responsive_control( 'enable_coupon' );
			$this->add_switcher( $coupon_tab_id, 'enable_coupon_collapsible', __( 'Collapsible', 'woofunnels-aero-checkout' ), 'off', [ 'enable_coupon' => 'on' ] );
//		$this->add_responsive_control( 'enable_coupon_collapsible' );

			/**
			 * Style Tab
			 */

			/* ------------------------------------ Section Start------------------------------------ */
			$heading_tab_id    = $this->add_tab( __( 'Heading', 'woofunnels-aero-checkout' ), 2 );
			$font_side_default = [ 'default' => '18px', 'unit' => 'px' ];
			$letterSpacing     = [ 'default' => '1px', 'unit' => 'px' ];
			$this->add_typography( $heading_tab_id, 'mini_cart_section_typo', '%%order_class%% .wfacp_mini_cart_start_h .wfacp-order-summary-label', '', '', [], $font_side_default, $letterSpacing );
			$this->add_color( $heading_tab_id, 'mini_cart_section_text_color', [ '%%order_class%% .wfacp-order-summary-label' ], '', '#333' );
			$this->add_text_alignments( $heading_tab_id, 'mini_cart_section_typo_alignment', [ '%%order_class%% .wfacp-order-summary-label' ] );

			/* ------------------------------------ Products Start------------------------------------ */

			$cart_id = $this->add_tab( __( 'Products', 'woocommerce' ), 2 );
			$this->add_heading( $cart_id, __( 'Typography', 'woocommerce' ) );

			$mini_cart_product_typo = [
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td:not(.product-total)',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > span bdi',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > ins span bdi',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total > span:not(.wfacp_cart_product_name_h):not(.wfacp_delete_item_wrap)',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total ins span:not(.wfacp_cart_product_name_h):not(.wfacp_delete_item_wrap)',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items .product-total small',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dl',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dt',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dd',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_items dd p',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name',

				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td small',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container span.subscription-details',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td p',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name span:not(.subscription-details)',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item td .product-name bdi',
			];

			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];

			$this->add_typography( $cart_id, 'mini_cart_product_typo', implode( ',', $mini_cart_product_typo ), '', '', [], $font_side_default );

			$this->add_color( $cart_id, 'mini_cart_product_color', $mini_cart_product_typo, '', '#666666' );

			$this->add_border_color( $cart_id, 'mini_cart_product_image_border_color', [ '%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_sum .product-image img' ], '', __( 'Image Border Color', 'woofunnel-aero-checkout' ), false );

			$this->add_border_radius( $cart_id, 'mini_cart_item_border_radius', [ '%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_sum .product-image img' ] );


			/* Strike Through Style Setting Order Summary Field */
			$this->price_strike_through_style_settings( $cart_id, 'mini_cart', '%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container' );


			/* ------------------------------------ Coupon Fields Start ------------------------------------ */

			$coupon_css_tab_id = $this->add_tab( __( 'Coupon', 'woocommerce' ), 2 );

			$enable_coupon             = [
				'enable_coupon' => 'on'
			];
			$enable_coupon_collapsible = [
				'enable_coupon_collapsible' => 'on',
				'enable_coupon'             => 'on'
			];


			$this->add_heading( $coupon_css_tab_id, __( 'Link', 'woofunnel-aero-checkout' ), '', $enable_coupon_collapsible );

			$font_side_default1 = [ 'default' => '15px', 'unit' => 'px' ];
			$this->add_typography( $coupon_css_tab_id, 'mini_cart_coupon_heading_typo', '%%order_class%% .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page .wfacp_main_showcoupon', [], '', $enable_coupon_collapsible, $font_side_default1 );
			$this->add_color( $coupon_css_tab_id, 'mini_cart_coupon_label_text_color', [
				'%%order_class%% .wfacp_mini_cart_start_h .woocommerce-info',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp-coupon-page .woocommerce-info a'
			], '', '#057daf', $enable_coupon_collapsible );

			$this->add_heading( $coupon_css_tab_id, __( 'Label', 'woofunnel-aero-checkout' ), 'none' );


			$form_fields_label_typo = [
				'%%order_class%% .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon p:not(.wfacp-anim-wrap) .wfacp-form-control-label',
			];
			$fields_options         = [
				'font_weight' => [
					'default' => '400',
				],
			];

			$this->add_typography( $coupon_css_tab_id, 'wfacp_form_mini_cart_coupon_label_typo', implode( ',', $form_fields_label_typo ), __( 'Label Typography', 'woofunnels-aero-checkout' ), $fields_options, [], $font_side_default );

			$form_fields_label_color_opt = [
				'%%order_class%% .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control-label',
			];
			$this->add_color( $coupon_css_tab_id, 'wfacp_form_fields_label_color', $form_fields_label_color_opt, __( 'Label Color', 'woofunnels-aero-checkout' ), '', [] );


			$fields_options = [
				'%%order_class%% .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
			];

			$this->add_heading( $coupon_css_tab_id, __( 'Input Field', 'woofunnel-aero-checkout' ), 'none', [] );
			$optionString = implode( ',', $fields_options );

			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];
			$this->add_typography( $coupon_css_tab_id, 'wfacp_form_mini_cart_coupon_input_typo', $optionString, __( 'Coupon Typography' ), [], [], $font_side_default );


			$inputColorOption = [
				'%%order_class%% .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
			];
			$this->add_color( $coupon_css_tab_id, 'wfacp_form_mini_cart_coupon_input_color', $inputColorOption, '', __( 'Coupon Color', 'woofunnels-aero-checkout' ), [] );

			$this->add_border_color( $coupon_css_tab_id, 'wfacp_form_mini_cart_coupon_focus_color', [ '%%order_class%% .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control:focus' ], '', __( 'Focus Color', 'woofunnel-aero-checkout' ), true, [] );
			$fields_options = [
				'%%order_class%% .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
			];

			$default = [
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
			$this->add_border( $coupon_css_tab_id, 'wfacp_form_mini_cart_coupon_border', implode( ',', $fields_options ), [], $default );


			/* Field Label */

			$this->add_heading( $coupon_css_tab_id, __( 'Button', 'woofunnel-aero-checkout' ), '', [] );
			/* Button color setting */
			$control_id = $this->add_controls_tabs( $coupon_css_tab_id, "", [] );
			$fields     = [];
			$fields[]   = $this->add_background_color( $coupon_css_tab_id, 'mini_cart_coupon_btn_color', [ '%%order_class%% .wfacp_mini_cart_start_h button.wfacp-coupon-btn:not([disabled])' ], '#000000', __( 'Background', 'woofunnels-aero-checkout' ) );
			$fields[]   = $this->add_color( $coupon_css_tab_id, 'mini_cart_coupon_btn_lable_color', [ '%%order_class%% .wfacp_mini_cart_start_h button.wfacp-coupon-btn:not([disabled])' ], __( 'Label', 'woofunnels-aero-checkout' ) );
			$this->add_controls_tab( $control_id, "Normal", $fields );

			$fields   = [];
			$fields[] = $this->add_background_color( $coupon_css_tab_id, 'mini_cart_coupon_btn_lable_hover_color', [ '%%order_class%% .wfacp_mini_cart_start_h button.wfacp-coupon-btn:not([disabled]):hover' ], __( 'Background', 'woofunnels-aero-checkout' ) );
			$fields[] = $this->add_color( $coupon_css_tab_id, 'mini_cart_coupon_btn_hover_label_color', [ '%%order_class%% .wfacp_mini_cart_start_h button.wfacp-coupon-btn:not([disabled]):hover' ], __( 'Label', 'woofunnels-aero-checkout' ) );
			$this->add_controls_tab( $control_id, 'Hover', $fields );

			$this->add_typography( $coupon_css_tab_id, 'wfacp_form_mini_cart_coupon_button_typo', '%%order_class%% .wfacp_mini_cart_start_h button.wfacp-coupon-btn', __( 'Button Typography' ), [], [] );


			/* ------------------------------------ Cart Total Start------------------------------------ */
			$cart_id = $this->add_tab( __( 'Cart Total', 'woocommerce' ), 2 );
			$this->add_heading( $cart_id, __( 'Subtotal', 'woocommerce' ) );

			$mini_cart_product_meta_typo = [
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount)',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) th',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) th span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span bdi',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td small',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td span.amount',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) td a',
			];


			$this->add_typography( $cart_id, 'mini_cart_product_meta_typo', implode( ',', $mini_cart_product_meta_typo ), '', '', [], $font_side_default );
			$this->add_color( $cart_id, 'mini_cart_product_meta_color', $mini_cart_product_meta_typo, '', '' );


			/* ------------------------------------ Coupon Start------------------------------------ */
			$this->add_heading( $cart_id, __( 'Coupon code', 'woocommerce' ) );
			$coupon_selector = [
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount th',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount th span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount td',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount td span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container table.shop_table  tr.cart-discount td a',
			];

			$default = [
				'range_settings' => [
					'min'  => '1',
					'max'  => '50',
					'step' => '1',
				],
				'default'        => '14px',
				'unit'           => 'px',
				'allowed_units'  => [ 'px' ],

			];


			$this->add_font_size( $cart_id, 'mini_cart_coupon_display_font_size', $coupon_selector, 'Font Size (in px)', $default );


			$coupon_selector_label_color = [
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount th span:not(.wfacp_coupon_code)',
			];
			$this->add_color( $cart_id, 'mini_cart_coupon_display_label_color', $coupon_selector_label_color, __( 'Text Color', 'woofunnel-aero-checkout' ) );

			$coupon_selector_val_color = [
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table tbody tr.cart-discount td a',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount td span bdi',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .shop_table .cart-discount th .wfacp_coupon_code',
			];
			$this->add_color( $cart_id, 'mini_cart_coupon_display_val_color', $coupon_selector_val_color, __( 'Code Color', 'woofunnel-aero-checkout' ), '#24ae4e' );


			/* ------------------------------------ Total Start------------------------------------ */

			$this->add_heading( $cart_id, __( 'Total', 'woocommerce' ) );

			$mini_cart_total_typo         = [
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total td span.amount',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total td span.amount bdi',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total td',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total td span',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total td small',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total th',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total th span',
			];
			$cart_total_label_typo_option = [
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th span',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th small',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total th a',
			];
			$cart_total_value_typo_option = [
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span.woocommerce-Price-amount.amount',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span.woocommerce-Price-amount.amount bdi',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td p',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td span',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td small',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td a',
				'%%order_class%% .wfacp_mini_cart_start_h  table.shop_table tbody tr.order-total td p',
			];
			$font_side_default            = [ 'default' => '24px', 'unit' => 'px' ];
			$this->add_heading( $cart_id, __( 'Label Typography', 'woocommerce' ) );
			$this->add_typography( $cart_id, 'mini_cart_total_label_typo', implode( ', ', $cart_total_label_typo_option ), __( 'Label Typography', 'woofunnel-aero-checkout' ), '', [], $font_side_default );
			$this->add_heading( $cart_id, __( 'Price Typography', 'woocommerce' ) );
			$this->add_typography( $cart_id, 'mini_cart_total_typo', implode( ', ', $cart_total_value_typo_option ), __( 'Price Typography', 'woofunnel-aero-checkout' ), '', [], $font_side_default );
			$this->add_color( $cart_id, 'mini_cart_total_color', $mini_cart_total_typo, '', '#323232' );


			/* ------------------------------------ End ------------------------------------ */
			$background_tab = $this->add_tab( __( 'Background', 'et_builder' ), 2 );
			$this->add_background_color( $background_tab, 'mini_cart_background_color', [ '%%order_class%% .wfacp_mini_cart_start_h' ], "", 'Background Color' );
			$border_tab = $this->add_tab( __( 'Border', 'et_builder' ), 2 );


			$default_args = [
				'border_type'          => 'none',
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
			$this->add_border( $border_tab, 'mini_cart_border', '%%order_class%% .wfacp_mini_cart_start_h', [], '', [], $default_args );


			$spacing_tab = $this->add_tab( __( 'Spacing', 'et_builder' ), 2 );
			$this->add_margin( $spacing_tab, 'wfacp_mini_cart_margin', '%%order_class%% .wfacp_mini_cart_start_h' );
			$this->add_padding( $spacing_tab, 'wfacp_mini_cart_padding', '%%order_class%% .wfacp_mini_cart_start_h' );

			/* ------------------------------------ Mini Cart Global Settings  ------------------------------------ */


			$settings_tab_id = $this->add_tab( __( 'Settings', 'woofunnels-aero-checkout' ), 2 );

			$this->add_heading( $settings_tab_id, __( 'Default Font', 'woocommerce' ) );

			$wfacp_mini_cart_font_family = [
				'%%order_class%% .wfacp_mini_cart_start_h *',
				'%%order_class%% .wfacp_mini_cart_start_h tr.order-total td span.woocommerce-Price-amount.amount',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items .product-total small',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dl',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dt',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dd',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_items dd p',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total)',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) th',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td small',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr:not(.order-total) td a',
				'%%order_class%% .wfacp_mini_cart_start_h span.wfacp_coupon_code',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .wfacp_mini_cart_reviews tr.order-total td span.woocommerce-Price-amount.amount',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table .order-total td',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table .order-total th',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table .order-total td span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container tr.cart_item .product-name',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td small',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td p',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td .product-name span',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_order_summary_container .cart_item td .product-name',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp_main_showcoupon',
				'%%order_class%% .wfacp_mini_cart_start_h .shop_table tr.order-total td',
				'%%order_class%% .wfacp_mini_cart_start_h .shop_table tr.order-total th',
				'%%order_class%% .wfacp_mini_cart_start_h .shop_table tr.order-total td span',
				'%%order_class%% .wfacp_mini_cart_start_h .shop_table tr.order-total td small',
				'%%order_class%% .wfacp_mini_cart_start_h .checkout_coupon.woocommerce-form-coupon .wfacp-form-control-label',
				'%%order_class%% .wfacp_mini_cart_start_h .checkout_coupon.woocommerce-form-coupon .wfacp-form-control',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp-coupon-btn',
			];


			$font_side_default = [ 'default' => '14px', 'unit' => 'px' ];
			$this->add_typography( $settings_tab_id, 'wfacp_mini_cart_font_family', implode( ',', $wfacp_mini_cart_font_family ), '', '', [], $font_side_default );


			$this->add_heading( $cart_id, __( 'Divider', 'woocommerce' ) );
			$this->add_border_color( $settings_tab_id, 'mini_cart_divider_color', [
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp_mini_cart_divi .cart_item',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.cart-subtotal',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.order-total',
				'%%order_class%% .wfacp_mini_cart_start_h table.shop_table tr.wfacp_ps_error_state td',
				'%%order_class%% .wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
				'%%order_class%% .wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp_mini_cart_elementor .cart_item',
				'%%order_class%% .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
			], '', __( 'Color', 'woofunnel-aero-checkout' ), false );
			/* ------------------------------------ End ------------------------------------ */


		}


		public function html( $attrs, $content = null, $render_slug = '' ) {
			$template = wfacp_template();
			if ( is_null( $template ) ) {
				return '';
			}
			$key     = 'wfacp_mini_cart_widgets_' . $template->get_template_type();
			$widgets = WFACP_Common::get_session( $key );
			if ( ! in_array( $key, $widgets ) ) {
				$widgets[] = $this->get_id();
			}
			WFACP_Common::set_session( $key, $widgets );
			ob_start();
			?>
            <div class='wfacp_form_divi_container'>
                <div class='wfacp_divi_forms'>
					<?php $template->get_mini_cart_widget( $this->get_id() ); ?>
                </div>
            </div>
			<?php
			return ob_get_clean();
		}


	}

	new WFACP_Divi_Summary;
}