<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionPromises' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionPromises {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;

		private $template_common;

		/**
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

		public function promises_settings( $i = 0 ) {
			$selected_template_slug = $this->template_common->get_template_slug();

			/** PANEL: Promises  Setting */
			$listing_panel = array();

			$listing_panel[ 'wfacp_promises_' . $i ] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 50,
					'title'       => 'Promises',
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => 'Promises',
							'priority' => 50,

						),
						'fields' => [

							/* Promises Content Setting */
							'ct_promises-icon' => array(
								'type'          => 'custom',
								'default'       => '<div class="options-title-divider">' . esc_html__( 'Promises', 'woofunnels-aero-checkout' ) . '</div>',
								'priority'      => 20,
								'wfacp_partial' => [
									'elem' => '.wfacp_promises_' . $i,
								],
							),

							$selected_template_slug . '_select_badge_structure' => [
								'type'            => 'select',
								'label'           => __( 'Badge Structure', 'woofunnels-aero-checkout' ),
								'default'         => 'wfacp-three-cols',
								'choices'         => [
									'wfacp-one-cols'   => 'One Column',
									'wfacp-two-cols'   => 'Two Column',
									'wfacp-three-cols' => 'Three Column',
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-one-cols', 'wfacp-two-cols', 'wfacp-three-cols' ],
										'elem'   => '.wfacp_promises_' . $i . ' .wfacp-permission-icon ul li',
									],
								],
							],

							'promise_icon_text'                            => array(
								'type'      => 'repeater',
								'label'     => esc_attr__( 'Promises', 'woofunnels-aero-checkout' ),
								'row_label' => array(
									'type'  => 'text',
									'value' => esc_attr__( 'Promises', 'woofunnels-aero-checkout' ),
								),
								'priority'  => 20,
								'default'   => array(
									array(
										'promises_icon' => $this->template_common->img_path . 'default-promises/privacy.svg',
										'promises_text' => esc_attr__( 'We Protect Your Privacy', 'woofunnels-aero-checkout' ),
									),
									array(
										'promises_icon' => $this->template_common->img_path . 'default-promises/ribbon.svg',
										'promises_text' => esc_attr__( '100% Satisfaction Guaranteed', 'woofunnels-aero-checkout' ),
									),
									array(
										'promises_icon' => $this->template_common->img_path . 'default-promises/secure.svg',
										'promises_text' => esc_attr__( 'Your Information Is Secure', 'woofunnels-aero-checkout' ),
									),
								),
								'fields'    => array(
									'promises_icon' => array(
										'type'  => 'image',
										'label' => '',
									),
									'promises_text' => array(
										'type'  => 'textarea',
										'label' => __( 'Text', 'woofunnels-aero-checkout' ),
									),

								),
							),
							$selected_template_slug . '_text_alignment'    => [
								'type'            => 'radio-buttonset',
								'label'           => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default'         => 'wfacp-text-center',
								'choices'         => [
									'wfacp-text-left'   => 'Left',
									'wfacp-text-center' => 'Center',
									'wfacp-text-right'  => 'Right',
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ],
										'elem'   => '.wfacp_promises_' . $i . ' p',
									],
								],
							],
							$selected_template_slug . '_hide_text'         => [
								'type'          => 'checkbox',
								'label'         => __( 'Hide Text', 'woofunnels-aero-checkout' ),
								'description'   => '',
								'default'       => false,
								'wfacp_partial' => [
									'elem' => '.wfacp-review-section .wfacp-section-headings .wfacp-heading',
								],
								'priority'      => 20,
							],

							/* Promises Advance Setting */
							'advanced_setting'                             => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							),
							$selected_template_slug . '_rbox_border_type'  => array(
								'type'    => 'select',
								'label'   => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default' => 'solid',
								'choices' => array(
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',
								),

								'priority'        => 200,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => ".wfacp_promises_$i",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_promises_' . $i,
									],
								],

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
										'setting'  => 'wfacp_promises_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => ".wfacp_promises_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_border_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#efefef',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 220,
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_promises_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => ".wfacp_promises_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_padding'      => array(
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => 0,
								'priority'        => 220,
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_promises_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => [ 'padding' ],
										'elem'     => ".wfacp_promises_$i",
									],
								],

							),

							/* Promises Color Setting */
							'ct_colors'                                    => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],

							$selected_template_slug . '_section_bg_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
								'default'         => '#414349',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => ".wfacp_promises_$i",
									],
								],

							],

							$selected_template_slug . '_content_text_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Content Color', 'woofunnels-aero-checkout' ),
								'default'         => '#414349',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_promises_$i" . ' p',
									],
								],
							],

						],
					),
				),
			);

			$temp = $listing_panel[ 'wfacp_promises_' . $i ];

			$listing_panel[ 'wfacp_promises_' . $i ] = apply_filters( 'wfacp_layout_default_setting', $temp, 'wfacp_promises_' . $i );

			return $listing_panel;

		}

	}
}