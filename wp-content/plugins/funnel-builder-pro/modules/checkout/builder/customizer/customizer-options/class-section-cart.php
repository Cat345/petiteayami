<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionCart' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionCart {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance             = null;

		/**
		 * @var $template_common  WFACP_Template_Common
		 */
		public $template_common;

		protected function __construct( $template_common = null ) {
			if ( ! is_null( $template_common ) ) {
				$this->template_common = $template_common;
			}
		}

		public static function get_instance( $template_common ) {
			if ( self::$_instance == null ) {
				self::$_instance = new self( $template_common );
			}

			return self::$_instance;
		}

		public function cart_settings() {

			$section_data_keys = array();

			$selected_template_slug = $this->template_common->get_template_slug();
			$template_type          = $this->template_common->get_template_type();
			$fields                 = $this->template_common->get_checkout_fields();

			/** PANEL: Form Setting */
			$form_cart_panel = array();
			if ( ! is_array( $fields ) || count( $fields ) == 0 ) {
				return;
			}

			$pageID = WFACP_Common::get_id();

			$_wfacp_version                = WFACP_Common::get_post_meta_data( $pageID, '_wfacp_version' );
			$cart_setting_qty_delete_value = true;
			if ( version_compare( $_wfacp_version, '1.9.3.1', '<=' ) ) {
				$cart_setting_qty_delete_value = false;
			}

			/* Change value embed form and mini cart embed form */
			$cartTitle = esc_attr__( 'Your Cart', 'woofunnels-aero-checkout' );
			if ( false !== strpos( $template_type, 'embed_form' ) ) {
				$cartTitle = esc_attr__( 'Mini Cart', 'woofunnels-aero-checkout' );
			}

			$form_cart_panel['wfacp_form_cart'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 40,
					'title'       => __( $cartTitle, 'woofunnels-aero-checkout' ),
					'description' => '',

				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => __( $cartTitle, 'woofunnels-aero-checkout' ),
							'priority' => 20,
						),
						'fields' => array(
							/* Cart Section Setting */
							'ct_section_cart'        => array(
								'type'          => 'custom',
								'default'       => '<div class="options-title-divider">' . esc_html__( 'Section', 'woofunnels-aero-checkout' ) . '</div>',
								'priority'      => 20,
								'wfacp_partial' => array(
									'elem' => '.wfacp_order_sec',
								),

							),

							$selected_template_slug . '_enable_heading' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Enable Section Heading', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => false,
								'priority'    => 20,
							),
							'heading'                => array(
								'type'            => 'text',
								'label'           => __( 'Heading', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => $cartTitle,
								'transport'       => 'postMessage',
								'wfacp_partial'   => array(
									'elem' => '.wfacp_form_cart .wfacp_section_title',
								),
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									),
								),
								'priority'        => 20,
							),
							$selected_template_slug . '_heading_fs' => array(
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
								'default'         => array(
									'desktop' => 20,
									'tablet'  => 20,
									'mobile'  => 20,
								),
								'input_attrs'     => array(
									'step' => 1,
									'min'  => 12,
									'max'  => 32,
								),
								'units'           => array(
									'px' => 'px',
									'em' => 'em',
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => array( 'font-size' ),
										'elem'       => 'body .wfacp_form_cart .wfacp_section_title',
									),
								),
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									),
								),
								'priority'        => 20,
							),
							$selected_template_slug . '_heading_talign' => array(
								'type'            => 'radio-buttonset',
								'label'           => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default'         => 'wfacp-text-left',
								'choices'         => array(
									'wfacp-text-left'   => 'Left',
									'wfacp-text-center' => 'Center',
									'wfacp-text-right'  => 'Right',
								),

								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									),
								),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => array( 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ),
										'elem'   => '.wfacp_form_cart .wfacp_section_title',
									),
								),

							),
							$selected_template_slug . '_heading_font_weight' => array(
								'type'            => 'radio-buttonset',
								'label'           => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default'         => 'wfacp-normal',
								'choices'         => array(
									'wfacp-bold'   => 'Bold',
									'wfacp-normal' => 'Normal',
								),

								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									),
								),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => array( 'wfacp-bold', 'wfacp-normal' ),
										'elem'   => '.wfacp_form_cart .wfacp_section_title',
									),
								),
							),
							/* Product Cart Setting */
							'ct_product_cart'        => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Product', 'woocommerce' ) . '</div>',
								'priority' => 20,
							),
							$selected_template_slug . '_order_hide_img' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Image', 'woofunnels-aero-checkout' ),
								'description' => __( 'Check if you want to show the image', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							),
							$selected_template_slug . '_order_quantity_switcher' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Quantity Switcher', 'woofunnels-aero-checkout' ),
								'description' => __( 'Check if you want Quantity Switcher', 'woofunnels-aero-checkout' ),
								'default'     => $cart_setting_qty_delete_value,
								'priority'    => 20,
							),
							$selected_template_slug . '_order_delete_item' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Allow Deletion', 'woofunnels-aero-checkout' ),
								'description' => __( 'Check if you want delete item', 'woofunnels-aero-checkout' ),
								'default'     => $cart_setting_qty_delete_value,
								'priority'    => 20,
							),
							'ct_product_cart_coupon' => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Coupon', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							$selected_template_slug . '_order_hide_right_side_coupon' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Hide Coupon', 'woofunnels-aero-checkout' ),
								'description' => __( 'Check if you want to hide the coupon from the sidebar order summary', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							$selected_template_slug . '_enable_coupon_right_side_coupon' => array(
								'type'            => 'checkbox',
								'label'           => __( 'Make Collapsible', 'woofunnels-aero-checkout' ),
								'description'     => __( 'Check if you want to keep coupon field collapsible', 'woofunnels-aero-checkout' ),
								'default'         => true,
								'priority'        => 20,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon',
										'operator' => '==',
										'value'    => false,
									),
								),
							),
							/* Strike Through & Saving Price Settings */
							$selected_template_slug . '_enable_strike_through_price' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Regular & Discounted Price', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable to show strike through original price', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							$selected_template_slug . '_enable_saving_price_message' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Total Saving Message', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable to show total saving message', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							$selected_template_slug . '_saving_price_message' => array(
								'type'            => 'textarea',
								'label'           => __( 'Message', 'woofunnels-aero-checkout' ),
								'default'         => __( 'You saved {{saving_amount}} ({{saving_percentage}}) on this order', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_enable_saving_price_message',
										'operator' => '==',
										'value'    => true,
									),
								),
							),
							/* Cart  Advance Setting */
							$selected_template_slug . '_advanced_setting' => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							),
							$selected_template_slug . '_rbox_border_type' => array(
								'type'            => 'select',
								'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default'         => 'none',
								'choices'         => array(
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',
								),
								'priority'        => 200,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-style' ),
										'elem'     => '.wfacp_form_cart',
									),
									array(
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => array( 'none', 'solid', 'double', 'dotted', 'dashed' ),
										'elem'   => '.wfacp_form_cart',
									),
								),
							),
							$selected_template_slug . '_rbox_border_width' => array(
								'type'            => 'slider',
								'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => array(
									'min'  => '1',
									'max'  => '12',
									'step' => '1',
								),
								'priority'        => 210,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-width' ),
										'elem'     => '.wfacp_form_cart',
									),
								),
							),
							$selected_template_slug . '_rbox_border_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 220,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-color' ),
										'elem'     => '.wfacp_form_cart',
									),
								),
							),
							$selected_template_slug . '_rbox_padding' => array(
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => 20,
								'priority'        => 220,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'suffix'   => 'px',
										'type'     => 'css',
										'prop'     => array( 'padding' ),
										'elem'     => '.wfacp_form_cart',
									),
								),
							),

							/* Header Color Setting */
							'ct_colors'              => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							),
							$selected_template_slug . '_sec_bg_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Section Background Color', 'woofunnels-aero-checkout' ),
								'default'         => 'transparent',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'background-color' ),
										'elem'     => 'body .wfacp_form_cart',
									),
								),
							),
							$selected_template_slug . '_sec_heading_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Section Title', 'woofunnels-aero-checkout' ),
								'default'         => '#333333',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => 'body .wfacp_form_cart .wfacp_section_title',
									),
								),
							),
							$selected_template_slug . '_label_price_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Label & Price', 'woofunnels-aero-checkout' ),
								'default'         => '#666666',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) th',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) th span',
									),

									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) td',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) td span',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) td span bdi',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td span:not(.wfacp-pro-count)',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td dl.variation *',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td bdi',
									),
								),
							),
							$selected_template_slug . '_total_value_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Total Value', 'woofunnels-aero-checkout' ),
								'default'         => '#323232',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total th',

									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total td',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total td span',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total td span bdi',
									),
								),
							),
							$selected_template_slug . '_divider_line_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Divider Line', 'woofunnels-aero-checkout' ),
								'default'         => '#dddddd',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tr.cart_item',

									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tr.order-total',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-color' ),
										'elem'     => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tr.cart-subtotal',
									),
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'border-color' ),
										'elem'     => '.wfacp_mb_mini_cart_wrap .wfacp_mb_cart_accordian',
									),
								),
							),
							$selected_template_slug . '_coupon_btn_bg_color_type' => array(
								'type'            => 'radio-buttonset',
								'label'           => __( 'Coupon', 'woofunnels-aero-checkout' ),
								'default'         => 'normal',
								'choices'         => array(
									'normal' => 'Normal',
									'hover'  => 'Hover',
								),
								'priority'        => 251,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon',
										'operator' => '==',
										'value'    => false,
									),
								),
							),
							$selected_template_slug . '_coupon_btn_bg_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
								'default'         => '#999999',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 251,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'background-color' ),
										'elem'     => '.wfacp_form_cart button.wfacp-coupon-btn',
									),

								),
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_coupon_btn_bg_color_type',
										'operator' => '=',
										'value'    => 'normal',
									),
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon',
										'operator' => '==',
										'value'    => false,
									),
								),
							),
							$selected_template_slug . '_coupon_btn_label_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Label', 'woofunnels-aero-checkout' ),
								'default'         => '#ffffff',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 251,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart button.wfacp-coupon-btn',
									),
								),
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_coupon_btn_bg_color_type',
										'operator' => '=',
										'value'    => 'normal',
									),
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon',
										'operator' => '==',
										'value'    => false,
									),
								),
							),
							$selected_template_slug . '_coupon_btn_bg_hover_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
								'default'         => '#878484',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 251,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'background-color' ),
										'elem'     => '.wfacp_form_cart button.wfacp-coupon-btn:hover',
									),
								),
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_coupon_btn_bg_color_type',
										'operator' => '=',
										'value'    => 'hover',
									),
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon',
										'operator' => '==',
										'value'    => false,
									),
								),
							),
							$selected_template_slug . '_coupon_btn_label_hover_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Label', 'woofunnels-aero-checkout' ),
								'default'         => '#ffffff',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 251,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart button.wfacp-coupon-btn:hover',
									),
								),
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_coupon_btn_bg_color_type',
										'operator' => '=',
										'value'    => 'hover',
									),
									array(
										'setting'  => 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon',
										'operator' => '==',
										'value'    => false,
									),
								),
							),
							$selected_template_slug . '_qty_bg_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Quantity Background', 'woofunnels-aero-checkout' ),
								'default'         => '#999999',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'background-color' ),
										'elem'     => '.wfacp_form_cart .wfacp-qty-count',
									),
								),
							),
							$selected_template_slug . '_qty_text_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Quantity Text Color', 'woofunnels-aero-checkout' ),
								'default'         => '#fff',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfacp_form_cart .wfacp-qty-count',
									),
								),
							),

							'ct_typography'          => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Typography', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 251,
							),
							$selected_template_slug . '_mini_cart_typography_ff' => array(
								'type'     => 'select',
								'label'    => __( 'Font Family', 'woofunnels-aero-checkout' ),
								'default'  => 'Open Sans',
								'priority' => 251,
								'choices'  => apply_filters( 'wfacp_customizer_fonts_choices', $this->template_common->web_google_fonts ),

							),

						),
					),
				),
			);

			$section_data_keys['colors'] = array(
				$selected_template_slug . '_label_price_color' => array(
					array(
						'type'   => 'color',
						'class'  => 'body .wfacp_form_cart .wfacp_section_title',
						'device' => 'desktop',
					),
				),
				$selected_template_slug . '_sec_bg_color' => array(
					array(
						'type'   => 'background-color',
						'class'  => 'body .wfacp_form_cart',
						'device' => 'desktop',
					),
				),
				$selected_template_slug . '_label_price_color' => array(
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) th',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) th span',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) td',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) td span',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr:not(:last-child) td span bdi',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td span:not(.wfacp-pro-count)',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td dl.variation *',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td dl dt',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tbody tr.cart_item td bdi',
						'device' => 'desktop',
					),

				),
				$selected_template_slug . '_total_value_color' => array(
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total th',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total td',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total td span',
						'device' => 'desktop',
					),
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tfoot tr.order-total td span bdi',
						'device' => 'desktop',
					),

				),
				$selected_template_slug . '_divider_line_color' => array(
					array(
						'type'   => 'border-color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tr.cart_item',
						'device' => 'desktop',
					),
					array(
						'type'   => 'border-color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tr.order-total',
						'device' => 'desktop',
					),
					array(
						'type'   => 'border-color',
						'class'  => '.wfacp_form_cart table.shop_table.woocommerce-checkout-review-order-table_' . $selected_template_slug . ' tr.cart-subtotal',
						'device' => 'desktop',
					),
					array(
						'type'   => 'border-color',
						'class'  => '.wfacp_form_cart .wfacp-coupon-section .wfacp-coupon-page',
						'device' => 'desktop',
					),
					array(
						'type'   => 'border-color',
						'class'  => 'body .wfacp_mb_mini_cart_wrap .wfacp_mb_cart_accordian',
						'device' => 'desktop',
					),
				),
				$selected_template_slug . '_coupon_btn_bg_color' => array(
					array(
						'type'   => 'background-color',
						'class'  => '.wfacp_form_cart button.wfacp-coupon-btn',
						'device' => 'desktop',
					),

				),
				$selected_template_slug . '_coupon_btn_label_color' => array(
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart button.wfacp-coupon-btn',
						'device' => 'desktop',
					),

				),
				$selected_template_slug . '_coupon_btn_bg_hover_color' => array(
					array(
						'type'   => 'background-color',
						'class'  => '.wfacp_form_cart button.wfacp-coupon-btn:hover',
						'device' => 'desktop',
					),

				),
				$selected_template_slug . '_coupon_btn_label_hover_color' => array(
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart button.wfacp-coupon-btn:hover',
						'device' => 'desktop',
					),

				),
				$selected_template_slug . '_qty_bg_color' => array(
					array(
						'type'   => 'background-color',
						'class'  => '.wfacp_form_cart .wfacp-qty-count',
						'device' => 'desktop',
					),
				),
				$selected_template_slug . '_qty_text_color' => array(
					array(
						'type'   => 'color',
						'class'  => '.wfacp_form_cart .wfacp-qty-count',
						'device' => 'desktop',
					),
				),

			);

			$this->template_common->set_section_keys_data( 'wfacp_form_cart', $section_data_keys );

			$form_cart_panel = apply_filters( 'wfacp_checkout_form_customizer_field', $form_cart_panel, $this );

			$form_cart_panel['wfacp_form_cart'] = apply_filters( 'wfacp_layout_default_setting', $form_cart_panel['wfacp_form_cart'], 'wfacp_form_cart' );

			return $form_cart_panel;
		}
	}
}
