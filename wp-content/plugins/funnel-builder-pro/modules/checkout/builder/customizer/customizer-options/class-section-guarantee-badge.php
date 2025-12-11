<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionGbadge' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionGbadge {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;

		private $template_common;

		/**
		 * WFACP_SectionCustomerCare constructor.
		 *
		 * @param null|WFACP_Template_Common $template_common
		 */
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

		public function gbadge_settings() {

			$selected_template_slug = $this->template_common->get_template_slug();

			$default_badges                        = array(
				'icon_1'  => $this->template_common->img_path . 'badges/60-day.png',
				'icon_2'  => $this->template_common->img_path . 'badges/money-back_3.png',
				'icon_3'  => $this->template_common->img_path . 'badges/mb_1.png',
				'icon_4'  => $this->template_common->img_path . 'badges/mb_2.png',
				'icon_5'  => $this->template_common->img_path . 'badges/mb_3.png',
				'icon_6'  => $this->template_common->img_path . 'badges/mb_4.png',
				'icon_7'  => $this->template_common->img_path . 'badges/mb_5.png',
				'icon_8'  => $this->template_common->img_path . 'badges/ssl.png',
				'icon_9'  => $this->template_common->img_path . 'badges/image_9.png',
				'icon_10' => $this->template_common->img_path . 'badges/image_10.png',
			);
			$this->template_common->default_badges = $default_badges;

			/** PANEL: Gbadge Setting */
			$gbadge_panel = array();

			$gbadge_panel['wfacp_gbadge'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 25,
					'title'       => 'Guarantee Badge',
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => 'Guarantee Badge',
							'priority' => 25,
						),
						'fields' => [

							/* List Content Setting */
							'ct_gbadge_icon'                               => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Badge', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							$selected_template_slug . '_enable_icon'       => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Badge', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => true,
								'priority'    => 20,
							],
							'ct_select_gbadge'                             => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Select Badge', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							$selected_template_slug . '_badge_icon'        => [
								'type'        => 'radio-image',
								'label'       => esc_attr__( 'Icons', 'woofunnels-aero_checkout-one-click-upsell' ),
								'description' => '',
								'choices'     => $default_badges,
								'default'     => 'icon_8',
								'priority'    => 20,

								'wfacp_partial' => [
									'elem' => '.wfacp_gbadge_icon ',
								],
							],
							$selected_template_slug . '_custom_list_image' => array(
								'type'        => 'image',
								'label'       => esc_attr__( 'Custom Icon', 'woofunnels-aero_checkout-one-click-upsell' ),
								'default'     => '',
								'priority'    => 20,
								'description' => esc_attr__( 'Custom will override built-in selected icon.', 'woofunnels-aero_checkout-one-click-upsell' ),

							),
							$selected_template_slug . '_badge_max_width'   => array(
								'type'            => 'slider',
								'label'           => __( 'Icon Max Width', 'woofunnels-aero-checkout' ),
								'default'         => 150,
								'choices'         => array(
									'min'  => '32',
									'max'  => '250',
									'step' => '2',
								),
								'transport'       => 'postMessage',
								'priority'        => 20,
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'prop'     => array( 'max-width' ),
										'elem'     => '.wfacp_gbadge .wfacp_max_width',
									),
								),

							),
							$selected_template_slug . '_badge_margin_left' => array(
								'type'            => 'number',
								'label'           => __( 'Margin Left', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'default'         => 0,
								'priority'        => 20,
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => array( 'margin-left' ),
										'elem'     => '.wfacp_gbadge_icon img',
									),
								),

							),
							$selected_template_slug . '_badge_margin_top'  => array(
								'type'            => 'number',
								'label'           => __( 'Margin Top', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'default'         => 0,
								'priority'        => 20,
								'wfacp_transport' => array(
									array(
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => array( 'margin-top' ),
										'elem'     => '.wfacp_gbadge_icon img',
									),
								),

							),

						],
					),
				),
			);

			$gbadge_panel['wfacp_gbadge'] = apply_filters( 'wfacp_layout_default_setting', $gbadge_panel['wfacp_gbadge'], 'wfacp_gbadge' );

			return $gbadge_panel;
		}
	}
}