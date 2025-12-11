<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_Section_Mini_Cart_Summary' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Section_Mini_Cart_Summary {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;

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
			$form_cart_panel = [];
			if ( ! is_array( $fields ) || count( $fields ) == 0 ) {
				return;
			}

			$cartTitle                                  = __( 'Mini Cart', 'woofunnels-aero-checkout' );
			$form_cart_panel['wfacp_mini_cart_summary'] = [
				'panel'    => 'no',
				'data'     => [
					'priority'    => 60,
					'title'       => __( $cartTitle, 'woofunnels-aero-checkout' ),
					'description' => '',
				],
				'sections' => [
					'section' => [
						'data'   => [
							'title'    => __( $cartTitle, 'woofunnels-aero-checkout' ),
							'priority' => 60,
						],
						'fields' => [
							'ct_product_cart'           => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Settings', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							'mini_cart_heading'         => [
								'type'        => 'text',
								'label'       => __( 'Mini Cart Heading', 'woocommerce' ),
								'description' => __( 'Enable if you want to Show the image', 'woofunnels-aero-checkout' ),
								'default'     => __( 'Order summary', 'woocommerce' ),
								'priority'    => 20,
							],
							'enable_product_image'      => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Product Image', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the image', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							],
							'enable_quantity_box'       => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Quantity Image', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the Quantity box', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							],
							'enable_delete_item'        => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Delete Item', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to delete the item', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							],
							'enable_coupon'             => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Coupon', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the Coupon Field', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							],
							'enable_coupon_collapsible' => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Collapsible Coupon', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the Collapsible Coupon Link', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							],
						],
					],
				],
			];

			return $form_cart_panel;
		}
	}
}