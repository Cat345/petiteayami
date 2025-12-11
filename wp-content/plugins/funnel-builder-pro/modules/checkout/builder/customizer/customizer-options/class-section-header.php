<?php

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionHeader' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionHeader {

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

		public function header_settings() {

			/** PANEL: Header Setting */
			$selected_template_slug = $this->template_common->get_template_slug();

			$page_seo_title = 'Checkout | ' . get_bloginfo( 'name' );

			$header_panel                 = [];
			$header_panel['wfacp_header'] = [
				'panel'    => 'no',
				'data'     => [
					'priority'    => 15,
					'title'       => 'Header',
					'description' => '',
				],
				'sections' => [
					'section' => [
						'data'   => [
							'title'    => 'Header',
							'priority' => 15,
						],
						'fields' => [

							/* Logo Setting */
							'ct_logo'             => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Logo', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							'logo'                => [
								'type'          => 'image',
								'default'       => $this->template_common->img_path . 'woo_checkout_logo.png',
								'label'         => __( 'Logo', 'woofunnels-aero-checkout' ),
								'priority'      => 20,
								'transport'     => 'postMessage',
								'wfacp_partial' => [
									'elem'     => '.wfacp_header .wfacp_logo_wrap',
									'callback' => 'wfacp_header_logo',
								],
							],
							'logo_link'           => [
								'type'        => 'text',
								'label'       => __( 'Logo Link', 'woofunnels-aero-checkout' ),
								'default'     => esc_attr__( '#', 'woofunnels-aero-checkout' ),
								'description' => __( 'http://www.yoursite.com', 'woofunnels-aero-checkout' ),
								'priority'    => 20,
							],
							'logo_link_target'    => [
								'type'        => 'checkbox',
								'label'       => __( 'Open link into new window', 'woofunnels-aero-checkout' ),
								'description' => __( 'To Open in new tab', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							],
							'logo_width'          => [
								'type'            => 'slider',
								'label'           => __( 'Max Width', 'woofunnels-aero-checkout' ),
								'default'         => 242,
								'choices'         => [
									'min'  => '20',
									'max'  => '400',
									'step' => '2',
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'max-width' ],
										'elem'     => '.wfacp_header .wfacp-logo',
									],
								],
							],
							'page_meta_title'     => [
								'type'            => 'text',
								'label'           => __( 'Page SEO Title', 'woofunnels-aero-checkout' ),
								'default'         => $page_seo_title,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type' => 'html',
										'elem' => 'title',
									],
								],
								'priority'        => 20,
							],
							/* Menu Setting */
							'contact_information' => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Contact Information', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							'header_text'         => [
								'type'            => 'text',
								'label'           => __( 'Header Text', 'woofunnels-aero-checkout' ),
								'default'         => esc_attr__( 'Need support?', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'                => 'html',
										'container_inclusive' => false,
										'elem'                => '.wfacp_header .wfacp-hd-list-sup',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_header .wfacp_header_list_sup ',
									],
								],
							],
							'helpdesk_text'       => [
								'type'            => 'text',
								'label'           => __( 'Helpdesk Label', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => __( 'Helpdesk', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'                => 'html',
										'container_inclusive' => false,
										'elem'                => '.wfacp_header .wfacp_header_list_help span',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_header .wfacp_header_list_help',
									],
								],
							],
							'helpdesk_url'        => [
								'type'        => 'text',
								'label'       => __( 'Helpdesk URL', 'woofunnels-aero-checkout' ),
								'description' => __( 'URL of your contact page or any support page if you have', 'woofunnels-aero-checkout' ),
								'default'     => esc_attr__( '#', 'woofunnels-aero-checkout' ),
								'priority'    => 20,
							],

							'helpdesk_link_target'                          => [
								'type'        => 'checkbox',
								'label'       => __( 'Helpdesk URL open in New Tab', 'woofunnels-aero-checkout' ),
								'description' => __( 'To Open in new tab', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							],
							'email'                                         => [
								'type'            => 'text',
								'label'           => __( 'Email', 'woofunnels-aero-checkout' ),
								'default'         => esc_attr__( 'support@example.com', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'                => 'html',
										'container_inclusive' => false,
										'elem'                => '.wfacp_header .wfacp_header_email span',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_header .wfacp_header_email',
									],
								],
							],
							'phone'                                         => [
								'type'            => 'text',
								'label'           => __( 'Phone', 'woofunnels-aero-checkout' ),
								'default'         => esc_attr__( '844-440-2777', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'                => 'html',
										'container_inclusive' => false,
										'elem'                => '.wfacp_header .wfacp_header_ph span',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_header .wfacp_header_ph',
									],
								],
							],
							'tel_number'                                    => [
								'type'     => 'text',
								'label'    => __( 'Number to be dial on click', 'woofunnels-aero-checkout' ),
								'default'  => esc_attr__( '8444402777', 'woofunnels-aero-checkout' ),
								'priority' => 20,
							],

							/* Header  Advance Setting */
							$selected_template_slug . '_advanced_setting'   => [
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
										'elem'     => '.wfacp_header',
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_header',
									],
								],
							],
							$selected_template_slug . '_rbox_border_width'  => [
								'type'            => 'slider',
								'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => [
									'min'  => '1',
									'max'  => '12',
									'step' => '1',
								],
								'priority'        => 210,
								'active_callback' => [
									[
										'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => '.wfacp_header',
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
										'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => '.wfacp_header',
									],
								],
							],
							$selected_template_slug . '_rbox_padding'       => [
								'type'     => 'number',
								'label'    => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'  => 20,
								'priority' => 220,

								'active_callback' => [
									[
										'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_rbox_border_type',
										'operator' => '!=',
										'value'    => 'none',
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'suffix'   => 'px',
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'padding' ],
										'elem'     => '.wfacp_header',
									],
								],
							],
							/* Header Color Setting */
							'ct_colors'                                     => [
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
										'elem'     => '.wfacp_header',
									],
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_header_layout',
										'operator' => '!=',
										'value'    => 'outside_header',
									],
								],
							],
							$selected_template_slug . '_header_icon_color'  => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Icon Color', 'woofunnels-aero-checkout' ),
								'default'         => '#565e66',
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
										'elem'     => '.wfacp_header span.wfacp-hd-list-help:before',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_header span.wfacp-hd-list-email:before',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_header span.wfacp-hd-list-phn:before',
									],
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_header_layout',
										'operator' => '!=',
										'value'    => 'outside_header',
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
										'elem'     => '.wfacp_header .wfacp-header-nav p',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_header .wfacp-header-nav ul li',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_header .wfacp-header-nav ul li a',
									],
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_header_layout',
										'operator' => '!=',
										'value'    => 'outside_header',
									],
								],
							],
						],
					],
				],
			];

			$section_data_keys['colors'] = [
				$selected_template_slug . '_section_bg_color'   => [
					[
						'type'   => 'background-color',
						'class'  => '.wfacp_header',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_header_icon_color'  => [
					[
						'type'   => 'color',
						'class'  => '.wfacp_header span.wfacp-hd-list-help:before',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_header span.wfacp-hd-list-email:before',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_header span.wfacp-hd-list-email:before',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_content_text_color' => [
					[
						'type'   => 'color',
						'class'  => '.wfacp_header .wfacp-header-nav p',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_header .wfacp-header-nav ul li',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_header .wfacp-header-nav ul li a',
						'device' => 'desktop',
					],
				],
			];

			$this->template_common->set_section_keys_data( 'wfacp_header', $section_data_keys );

			$header_panel['wfacp_header'] = apply_filters( 'wfacp_layout_default_setting', $header_panel['wfacp_header'], 'wfacp_header' );

			return $header_panel;
		}

	}
}