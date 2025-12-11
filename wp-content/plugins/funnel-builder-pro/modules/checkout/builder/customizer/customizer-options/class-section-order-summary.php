<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_Section_Order_Summary' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Section_Order_Summary {

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

			$cartTitle                              = __( 'Order summary', 'woocommerce' );
			$form_cart_panel['wfacp_order_summary'] = [
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
							'ct_product_cart'                                   => [
								'type'          => 'custom',
								'default'       => '<div class="options-title-divider">' . esc_html__( 'Product', 'woocommerce' ) . '</div>',
								'priority'      => 20,
								'wfacp_partial' => [
									'elem' => '#order_summary_field',
								],
							],
							$selected_template_slug . '_order_summary_hide_img' => [
								'type'        => 'checkbox',
								'label'       => __( 'Product Image', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to Show the image', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							],
						],
					],
				],
			];

			$form_cart_panel = apply_filters( 'wfacp_checkout_form_customizer_field', $form_cart_panel, $this );

			$form_cart_panel['wfacp_order_summary'] = apply_filters( 'wfacp_layout_default_setting', $form_cart_panel['wfacp_order_summary'], 'wfacp_order_summary' );

			return $form_cart_panel;
		}
	}
}