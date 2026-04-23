<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_Section_Mini_Cart_Summary' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Section_Mini_Cart_Summary {

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

		public function order_summary_settings() {

			$selected_template_slug = $this->template_common->get_template_slug();
			$fields                 = $this->template_common->get_checkout_fields();

			/** PANEL: Form Setting */
			$form_cart_panel = array();
			if ( ! is_array( $fields ) || count( $fields ) == 0 ) {
				return;
			}

			$cartTitle                                  = __( 'Mini Cart', 'woofunnels-aero-checkout' );
			$form_cart_panel['wfacp_mini_cart_summary'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 60,
					'title'       => __( $cartTitle, 'woofunnels-aero-checkout' ),
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => __( $cartTitle, 'woofunnels-aero-checkout' ),
							'priority' => 60,
						),
						'fields' => array(
							'ct_product_cart'             => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Settings', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							'mini_cart_heading'           => array(
								'type'        => 'text',
								'label'       => __( 'Mini Cart Heading', 'woocommerce' ),
								'description' => __( 'Enable if you want to Show the image', 'woofunnels-aero-checkout' ),
								'default'     => __( 'Order summary', 'woocommerce' ),
								'priority'    => 20,
							),
							'enable_product_image'        => array(
								'type'        => 'checkbox',
								'label'       => __( 'Enable Product Image', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the image', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							),
							'enable_quantity_box'         => array(
								'type'        => 'checkbox',
								'label'       => __( 'Enable Quantity Image', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the Quantity box', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							'enable_delete_item'          => array(
								'type'        => 'checkbox',
								'label'       => __( 'Enable Delete Item', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to delete the item', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							'enable_coupon'               => array(
								'type'        => 'checkbox',
								'label'       => __( 'Enable Coupon', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the Coupon Field', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							'enable_coupon_collapsible'   => array(
								'type'        => 'checkbox',
								'label'       => __( 'Enable Collapsible Coupon', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the Collapsible Coupon Link', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							'enable_strike_through_price' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Regular & Discounted Price', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable to show strike through original price', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							'enable_saving_price_message' => array(
								'type'        => 'checkbox',
								'label'       => __( 'Total Saving Message', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable to show total saving message', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							),
							'saving_price_message'        => array(
								'type'            => 'textarea',
								'label'           => __( 'Message', 'woofunnels-aero-checkout' ),
								'default'         => __( 'You saved {{saving_amount}} ({{saving_percentage}}) on this order', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_mini_cart_summary_section_enable_saving_price_message',
										'operator' => '==',
										'value'    => true,
									),
								),
							),
						),
					),
				),
			);

			return $form_cart_panel;
		}
	}
}
