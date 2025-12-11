<?php

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionStyles' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionStyles {

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

		public function style_settings() {
			$selected_template_slug = $this->template_common->get_template_slug();
			$template_type          = $this->template_common->get_template_type();

			$default_font_size = '16';
			$list_panel_sec    = [
				[
					'internal'   => true,
					'responsive' => true,
					'type'       => 'css',
					'prop'       => array( 'font-size' ),
					'elem'       => 'body p',
				],
				[
					'internal'   => true,
					'responsive' => true,
					'type'       => 'css',
					'prop'       => array( 'font-size' ),
					'elem'       => '.wfacp-comm-inner-inf p',
				],
			];
			if ( $template_type == 'pre_built' && ( $selected_template_slug == 'layout_4' || $selected_template_slug == 'layout_2' || $selected_template_slug == 'layout_9' ) ) {

				$default_font_size = '14';
				$list_panel_sec[]  = [
					'internal'   => true,
					'responsive' => true,
					'type'       => 'css',
					'prop'       => array( 'font-size' ),
					'elem'       => '.wfacp-list-panel p',
				];
				$list_panel_sec[]  = [
					'internal'   => true,
					'responsive' => true,
					'type'       => 'css',
					'prop'       => array( 'font-size' ),
					'elem'       => '.wfacp-testing-text p',
				];
			}

			/** PANEL: Styles Setting */
			$style_panel = array();

			$panel_keys['wfacp_style'] = [ 'colors', 'typography' ];

			$style_panel['wfacp_style'] = array(
				'data'     => array(
					'priority'    => 80,
					'title'       => 'Style',
					'description' => '',
				),
				'sections' => array(
					'colors'     => array(
						'data'   => array(
							'title'    => 'Colors',
							'priority' => 80,
						),
						'fields' => array(
							$selected_template_slug . '_body_background_color'    => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Body Background Color ', 'woofunnels-aero-checkout' ),
								'default'         => '#ffffff',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 10,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background' ],
										'elem'     => 'body',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background' ],
										'elem'     => '.wfacp-main-container',
									],

								],
							),
							$selected_template_slug . '_sidebar_background_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Sidebar Background Color ', 'woofunnels-aero-checkout' ),
								'default'         => '#000000',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 10,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background' ],
										'elem'     => 'body',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background' ],
										'elem'     => '.wfacp-right-panel',
									],
								],
							),
						),
					),
					'typography' => array(
						'data'   => array(
							'title'    => 'Typography',
							'priority' => 20,
						),
						'fields' => array(
							'ct_font_size'                          => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Font Size', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 10,
							),
							$selected_template_slug . '_content_fs' => array(
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Content', 'woofunnels-aero-checkout' ),
								'default'         => array(
									'desktop' => $default_font_size,
									'tablet'  => 14,
									'mobile'  => 14,
								),
								'input_attrs'     => array(
									'step' => 1,
									'min'  => 12,
									'max'  => 20,
								),
								'units'           => array(
									'px' => 'px',
									'em' => 'em',
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => $list_panel_sec,
								'priority'        => 40,
							),

							$selected_template_slug . '_content_ff' => array(
								'type'     => 'select',
								'label'    => __( 'Font Family', 'woofunnels-aero-checkout' ),
								'default'  => 'Open Sans',
								'priority' => 40,
								'choices'  => apply_filters( 'wfacp_customizer_fonts_choices', $this->template_common->web_google_fonts ),

							),

						),
					),
				),
			);

			$style_panel['wfacp_style'] = apply_filters( 'wfacp_style_default_setting', $style_panel['wfacp_style'], $panel_keys );

			return $style_panel;
		}

	}
}