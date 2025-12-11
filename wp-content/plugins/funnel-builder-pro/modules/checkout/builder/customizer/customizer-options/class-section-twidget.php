<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionTwidget' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionTwidget {

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

		public function twidget_settings( $i = 0 ) {

			$selected_template_slug = $this->template_common->get_template_slug();
			/** PANEL: Twidget Setting */
			$twidget_panel = array();

			$twidget_panel[ 'wfacp_assurance_' . $i ] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 40,
					'title'       => 'Assurance',
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => 'Assurance',
							'priority' => 40,
						),
						'fields' => [

							/*  Content Setting */
							'ct_list-icon' => array(
								'type'          => 'custom',
								'default'       => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
								'priority'      => 20,
								'wfacp_partial' => [
									'elem' => '.wfacp_assurance_' . $i . ' .wfacp-information-container:first-child .wfacp_section_title',
								],
							),

							/* content fields */

							'mwidget_listw' => array(
								'type'      => 'repeater',
								'label'     => esc_attr__( 'Contents', 'woofunnels-aero-checkout' ),
								'row_label' => array(
									'type'  => 'text',
									'value' => esc_attr__( 'Content', 'woofunnels-aero-checkout' ),
								),
								'priority'  => 20,
								'default'   => array(
									array(

										'mwidget_image'   => $this->template_common->img_path . 'product_default_icon.jpg',
										'mwidget_heading' => __( '30 Days Refund Policy', 'woofunnels-aero-checkout' ),
										'mwidget_content' => esc_attr__( 'You have to take enough risks in life, this shouldn’t be one of them. Try this out for 30 days on me and if you aren’t happy just send me an email and I’ll refund your entire purchase – no questions asked.', 'woofunnels-aero-checkout' ),
									),
									array(
										'mwidget_image'   => $this->template_common->img_path . 'product_default_icon.jpg',
										'mwidget_heading' => __( 'Privacy', 'woofunnels-aero-checkout' ),
										'mwidget_content' => esc_attr__( 'We will not share or trade online information that you provide us (including e-mail addresses).', 'woofunnels-aero-checkout' ),
									),

								),
								'fields'    => array(

									'mwidget_image'   => array(
										'type'  => 'image',
										'label' => __( 'Upload Image', 'woofunnels-aero-checkout' ),

									),
									'mwidget_heading' => array(
										'type'  => 'text',
										'label' => __( 'Widget Heading', 'woofunnels-aero-checkout' ),
									),
									'mwidget_content' => array(
										'type'  => 'textarea',
										'label' => __( 'Widget Content', 'woofunnels-aero-checkout' ),
									),

								),

							),

							$selected_template_slug . '_hide_title'          => [
								'type'          => 'checkbox',
								'label'         => __( 'Hide Widget Title', 'woofunnels-aero-checkout' ),
								'description'   => '',
								'default'       => false,
								'wfacp_partial' => [
									'elem' => '.wfacp-review-section .wfacp-section-headings .wfacp-heading',
								],
								'priority'      => 20,
							],
							$selected_template_slug . '_mwidget_show_image'  => [
								'type'        => 'checkbox',
								'label'       => __( 'Show Images', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => false,
								'priority'    => 20,
							],
							$selected_template_slug . '_enable_divider'      => [
								'type'            => 'checkbox',
								'label'           => __( 'Enable Divider Line', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => false,
								'wfacp_partial'   => [
									'elem' => '.wfacp-review-section .wfacp-section-headings .wfacp-heading',
								],
								'priority'        => 20,
								'active_callback' => [
									[
										'setting'  => 'wfacp_twidget_section_content_type',
										'operator' => '==',
										'value'    => 'wfacp-ctype-multiple',
									],
								],
							],
							$selected_template_slug . '_heading_fs'          => [
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
								'default'         => [
									'desktop' => 24,
									'tablet'  => 24,
									'mobile'  => 22,
								],
								'input_attrs'     => [
									'step' => 1,
									'min'  => 12,
									'max'  => 32,
								],
								'units'           => [
									'px' => 'px',
									'em' => 'em',
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => [ 'font-size' ],
										'elem'       => '.wfacp_assurance_' . $i . ' .wfacp_section_title',
									],
								],

								'priority' => 20,
							],
							$selected_template_slug . '_heading_talign'      => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-text-center',
								'choices' => [
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
										'elem'   => '.wfacp_assurance_' . $i . ' .wfacp_section_title ',
									],
								],

							],
							$selected_template_slug . '_heading_font_weight' => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-bold',
								'choices' => [
									'wfacp-bold'   => 'Bold',
									'wfacp-normal' => 'Normal',
								],

								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-bold', 'wfacp-normal' ],
										'elem'   => '.wfacp_assurance_' . $i . ' .wfacp_section_title ',
									],
								],
							],

							/* Advance Setting */
							'advanced_setting'                               => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							),
							$selected_template_slug . '_rbox_border_type'    => array(
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
										'elem'     => ".wfacp_assurance_$i",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_assurance_' . $i,
									],
								],

							),
							$selected_template_slug . '_rbox_border_width'   => array(
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
										'setting'  => 'wfacp_assurance_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_assurance_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_border_color'   => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 220,
								'active_callback' => array(
									array(
										'setting' => 'wfacp_assurance_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',

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
										'elem'     => ".wfacp_assurance_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_padding'        => array(
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => '20',
								'priority'        => 220,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_assurance_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									),
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'suffix'   => 'px',
										'type'     => 'css',
										'prop'     => [ 'padding' ],
										'elem'     => ".wfacp_assurance_$i",
									],
								],

							),

							/* List Color Setting */
							'ct_colors'                                      => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],

							$selected_template_slug . '_section_bg_color'   => [
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
										'elem'     => ".wfacp_assurance_$i",
									],
								],

							],
							$selected_template_slug . '_heading_text_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Heading Color', 'woofunnels-aero-checkout' ),
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
										'elem'     => ".wfacp_assurance_$i" . ' .loop_head_sec',
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
								'priority'        => 260,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_assurance_$i" . ' p',
									],
								],
							],
							$selected_template_slug . '_divider_color'      => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Divider Color', 'woofunnels-aero-checkout' ),
								'default'         => '#d4d4d4',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 260,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => ".wfacp_assurance_$i" . ' .wfacp-information-container .wfacp_enable_border',
									],
								],
							],
						],
					),
				),
			);

			$temp = $twidget_panel[ 'wfacp_assurance_' . $i ];

			$twidget_panel[ 'wfacp_assurance_' . $i ] = apply_filters( 'wfacp_layout_default_setting', $temp, 'wfacp_assurance_' . $i );

			return $twidget_panel;

		}


	}
}