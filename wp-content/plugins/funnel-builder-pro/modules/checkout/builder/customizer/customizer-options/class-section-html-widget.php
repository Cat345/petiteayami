<?php

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionHtmlWidgets' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionHtmlWidgets {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;
		private $template_common;

		/**
		 * WFACP_SectionHtmlWidget constructor.
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

		public function html_widget_settings() {

			/** PANEL: Html Widget Setting */
			$selected_template_slug = $this->template_common->get_template_slug();

			$border_style = "solid";
			if ( $selected_template_slug == 'layout_1' || $selected_template_slug == 'layout_9' ) {
				$border_style = "none";
			}

			$panel_keys['wfacp_html_widgets']        = [ 'html_widget_1', 'html_widget_2', 'html_widget_3' ];
			$wfacp_html_widget_panel                 = [];
			$wfacp_html_widget_panel['html_widgets'] = [
				'data'     => [
					'priority' => 65,
					'title'    => 'HTML Widgets',
				],
				'sections' => [
					'wfacp_html_widget_1' => [
						'data'   => [
							'title'    => 'Sidebar Widget 1',
							'priority' => 25,
						],
						'fields' => [
							'html_content'                                  => [
								'type'          => 'editor',
								'label'         => __( 'Text', 'woofunnels-aero-checkout' ),
								'default'       => __( 'There is a demo text HTML 1', 'woofunnels-aero-checkout' ),
								'transport'     => 'postMessage',
								'wfacp_partial' => [
									'elem'                => '.wfacp_html_widget_1',
									'container_inclusive' => true,
								],
								'priority'      => 20,
							],

							/* List Advance Setting */
							'advanced_setting'                              => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							],
							$selected_template_slug . '_rbox_border_type'   => [
								'type'            => 'select',
								'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default'         => $border_style,
								'choices'         => [
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',
								],
								'priority'        => 200,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => ".wfacp_html_widget_1",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_html_widget_1',
									],
								],
							],
							$selected_template_slug . '_rbox_border_width'  => [
								'type'            => 'slider',
								'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => [
									'min'  => '0',
									'max'  => '12',
									'step' => '1',
								],
								'priority'        => 210,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_1_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => ".wfacp_html_widget_1",
									],
								],
							],
							$selected_template_slug . '_rbox_border_color'  => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 220,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_1_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => ".wfacp_html_widget_1",
									],
								],
							],
							$selected_template_slug . '_rbox_padding'       => [
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => 20,
								'priority'        => 220,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_1_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'suffix'   => 'px',
										'type'     => 'css',
										'prop'     => [ 'padding' ],
										'elem'     => ".wfacp_html_widget_1",
									],
								],
							],

							/* List Color Setting */
							'ct_colors'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],
							$selected_template_slug . '_section_bg_color'   => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
								'default'         => 'transparent',
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
										'elem'     => ".wfacp_html_widget_1",
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
										'elem'     => ".wfacp_html_widget_1 p",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_1 ul",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_1 ul li",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_1 ol li",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_1",
									]

								],
							],

						],
					],
					'wfacp_html_widget_2' => [
						'data'   => [
							'title'    => 'Sidebar Widget 2',
							'priority' => 30,
						],
						'fields' => [
							'html_content'                                  => [
								'type'          => 'editor',
								'label'         => __( 'Text', 'woofunnels-aero-checkout' ),
								'default'       => __( 'There is a demo text HTML 2', 'woofunnels-aero-checkout' ),
								'transport'     => 'postMessage',
								'wfacp_partial' => [
									'elem'                => '.wfacp_html_widget_2',
									'container_inclusive' => true,
								],
								'priority'      => 20,
							],

							/* List Advance Setting */
							'advanced_setting'                              => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							],
							$selected_template_slug . '_rbox_border_type'   => [
								'type'            => 'select',
								'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default'         => $border_style,
								'choices'         => [
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',
								],
								'priority'        => 200,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => ".wfacp_html_widget_2",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_html_widget_2',
									],
								],
							],
							$selected_template_slug . '_rbox_border_width'  => [
								'type'            => 'slider',
								'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => [
									'min'  => '0',
									'max'  => '12',
									'step' => '1',
								],
								'priority'        => 210,
								'active_callback' => [
									[

										'setting'  => 'html_widgets_wfacp_html_widget_2_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => ".wfacp_html_widget_2",
									],
								],
							],
							$selected_template_slug . '_rbox_border_color'  => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 220,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_2_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => ".wfacp_html_widget_2",
									],
								],
							],
							$selected_template_slug . '_rbox_padding'       => [
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => 20,
								'priority'        => 220,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_2_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'suffix'   => 'px',
										'type'     => 'css',
										'prop'     => [ 'padding' ],
										'elem'     => ".wfacp_html_widget_2",
									],
								],
							],

							/* List Color Setting */
							'ct_colors'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],
							$selected_template_slug . '_section_bg_color'   => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
								'default'         => 'transparent',
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
										'elem'     => ".wfacp_html_widget_2",
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
										'elem'     => ".wfacp_html_widget_2 p",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_2 ul",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_2 ul li",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_2 ol li",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_2",
									]

								],
							],


						],
					],
					'wfacp_html_widget_3' => [
						'data'   => [
							'title'    => 'Widget Below Form',
							'priority' => 31,
						],
						'fields' => [
							'html_content'                                  => [
								'type'          => 'editor',
								'label'         => __( 'Text', 'woofunnels-aero-checkout' ),
								'default'       => __( 'There is a demo text HTML 3', 'woofunnels-aero-checkout' ),
								'transport'     => 'postMessage',
								'wfacp_partial' => [
									'elem'                => '.wfacp_html_widget_3',
									'container_inclusive' => true,
								],
								'priority'      => 20,
							],

							/* List Advance Setting */
							'advanced_setting'                              => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							],
							$selected_template_slug . '_rbox_border_type'   => [
								'type'            => 'select',
								'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default'         => 'solid',
								'choices'         => [
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',
								],
								'priority'        => 200,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => ".wfacp_html_widget_3",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_html_widget_3',
									],
								],
							],
							$selected_template_slug . '_rbox_border_width'  => [
								'type'            => 'slider',
								'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => [
									'min'  => "0",
									'max'  => '12',
									'step' => '1',
								],
								'priority'        => 210,
								'active_callback' => [
									[
										'setting' => 'html_widgets_wfacp_html_widget_3_' . $selected_template_slug . '_rbox_border_type',

										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => ".wfacp_html_widget_3",
									],
								],
							],
							$selected_template_slug . '_rbox_border_color'  => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 220,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_3_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => ".wfacp_html_widget_3",
									],
								],
							],
							$selected_template_slug . '_rbox_padding'       => [
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => 20,
								'priority'        => 220,
								'active_callback' => [
									[
										'setting'  => 'html_widgets_wfacp_html_widget_3_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => [ 'padding' ],
										'elem'     => ".wfacp_html_widget_3",
									],
								],
							],

							/* List Color Setting */
							'ct_colors'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],
							$selected_template_slug . '_section_bg_color'   => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
								'default'         => 'transparent',
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
										'elem'     => ".wfacp_html_widget_3",
									],
								],

							],
							$selected_template_slug . '_content_text_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Content Color', 'woofunnels-aero-checkout' ),
								'default'         => '#737373',
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
										'elem'     => ".wfacp_html_widget_3 p",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_3 ul",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_3 ul li",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_3 ol li",
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_html_widget_3",
									]

								],
							],


						],
					],
				],
			];


			$wfacp_html_widget_panel['html_widgets'] = apply_filters( 'wfacp_multi_tab_default_setting', $wfacp_html_widget_panel['html_widgets'], $panel_keys );


			return $wfacp_html_widget_panel;
		}

	}
}