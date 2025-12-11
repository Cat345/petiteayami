<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFACP_SectionCustomerCare' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionCustomerCare {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;

		private $template_common;

		/**
		 * WFACP_SectionCustomerCare constructor.
		 *
		 * @param WFACP_Pre_Built $template_common
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

		public function customer_care_settings( $i = 0 ) {

			$selected_template_slug = $this->template_common->get_template_slug();
			/** PANEL: Customer Care Setting */
			$customer_care_panel = array();

			$DefaultmailID                                 = __( "support@example.com", 'woofunnels-aero-checkout' );
			$defaultHtml                                   = $DefaultmailID . " \n999-9999-999";
			$customer_care_panel[ 'wfacp_customer_' . $i ] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 45,
					'title'       => __( 'Customer Support', 'woofunnels-aero-checkout' ),
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => __( 'Customer Support', 'woofunnels-aero-checkout' ),
							'priority' => 45,
						),
						'fields' => [

							/* Customer Care  Section Heading */
							'ct_heading' => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Section Heading', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],

							$selected_template_slug . '_enable_heading'          => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Section Heading', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => true,
								'priority'    => 20,
							],
							'heading'                                            => [
								'type'            => 'text',
								'label'           => __( 'Heading', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Customer Support', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_partial'   => [
									'elem' => '.wfacp_customer_' . $i . ' .wfacp_section_title',
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_heading_fs'              => [
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
								'default'         => [
									'desktop' => 24,
									'tablet'  => 24,
									'mobile'  => 24,
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
										'elem'       => '.wfacp_customer_' . $i . ' .wfacp_section_title',
									],
								],
								'active_callback' => [
									[

										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_heading_talign'          => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-text-center',
								'choices' => [
									'wfacp-text-left'   => 'Left',
									'wfacp-text-center' => 'Center',
									'wfacp-text-right'  => 'Right',
								],

								'active_callback' => [
									[
										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'class',
										'elem'   => '.wfacp_customer_' . $i . ' .wfacp_section_title ',
										'remove' => [ 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ],
									],
								],
							],
							$selected_template_slug . '_heading_font_weight'     => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-bold',
								'choices' => [
									'wfacp-bold'   => 'Bold',
									'wfacp-normal' => 'Normal',
								],

								'active_callback' => [
									[
										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-bold', 'wfacp-normal' ],
										'elem'   => '.wfacp_customer_' . $i . ' .wfacp_section_title ',
									],
								],
							],

							/* Customer Care   Sub Heading Section*/
							'ct_sub_heading'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Sub Heading', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_enable_sub_heading'      => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Sub Heading', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => true,
								'priority'    => 20,
							],
							'sub_heading'                                        => [
								'type'            => 'textarea',
								'label'           => __( 'Sub Heading', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'OUR AWARD-WINNING CUSTOMER SUPPORT IS HERE FOR YOU', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type' => 'html',
										'elem' => '.wfacp_customer_' . $i . ' .wfacp-subtitle',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_customer_' . $i . ' .wfacp-subtitle',
									],
								],
								'active_callback' => [
									[

										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_sub_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_sub_heading_fs'          => [
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
								'default'         => [
									'desktop' => 16,
									'tablet'  => 16,
									'mobile'  => 16,
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
										'elem'       => '.wfacp_customer_' . $i . ' .wfacp-subtitle',
									],
								],

								'active_callback' => [
									[

										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_sub_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_sub_heading_talign'      => [
								'type'            => 'radio-buttonset',
								'label'           => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default'         => 'wfacp-text-center',
								'choices'         => [
									'wfacp-text-left'   => 'Left',
									'wfacp-text-center' => 'Center',
									'wfacp-text-right'  => 'Right',
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'class',
										'elem'   => '.wfacp_customer_' . $i . ' .wfacp-subtitle',
										'remove' => [ 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ],
									],
								],
								'active_callback' => [
									[

										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_sub_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_sub_heading_font_weight' => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-normal',
								'choices' => [
									'wfacp-bold'   => 'Bold',
									'wfacp-normal' => 'Normal',
								],

								'active_callback' => [
									[

										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_enable_sub_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-bold', 'wfacp-normal' ],
										'elem'   => '.wfacp_customer_' . $i . ' .wfacp-subtitle ',
									],
								],
							],

							/* Customer Care  Supporter Section */
							'ct_supporter'                                       => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Supporter', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							'supporter_name'                                     => [
								'type'            => 'text',
								'label'           => __( 'Name', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Tavleen Kaur', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type' => 'html',
										'elem' => '.wfacp-support-desc .wfacp-title-name',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp-support-desc .wfacp-title-name',
									],
								],
								'priority'        => 20,
							],
							'supporter_image'                                    => [
								'type'        => 'image',
								'label'       => esc_attr__( 'Image', 'woofunnels-aero-checkout' ),
								'default'     => $this->template_common->img_path . 'customer-support/no_image.jpg',
								'priority'    => 20,
								'description' => '',
							],
							'supporter_designation'                              => [
								'type'            => 'textarea',
								'label'           => __( 'Designation', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Customer Happiness Manager', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type' => 'html',
										'elem' => '.wfacp-support-desc .wfacp-customber-sub-tit',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp-support-desc .wfacp-customber-sub-tit',
									],
								],
								'priority'        => 20,
							],
							'supporter_signature_image'                          => [
								'type'        => 'image',
								'label'       => esc_attr__( 'Signature', 'woofunnels-aero-checkout' ),
								'default'     => $this->template_common->img_path . 'customer-support/default_signature.png',
								'priority'    => 20,
								'description' => '',
							],

							/* Customer Care  Contact Section */

							'ct_contact'          => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Contact', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							'contact_heading'     => [
								'type'            => 'text',
								'label'           => __( 'Email or call us for support', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Email or call us for support', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									[
										'type' => 'html',
										'elem' => '.wfacp-email .wfacp-contact-head',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp-email .wfacp-contact-head',
									],
								),
								'priority'        => 20,
							],
							'contact_description' => [
								'type'            => 'textarea',
								'label'           => __( 'Contact Description', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( $defaultHtml, 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									[
										'type' => 'html',
										'elem' => '.wfacp-email .wfacp_email_description_wrap',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp-email .wfacp_email_description_wrap',
									],
								),
								'priority'        => 20,
							],
							'contact_chat'        => [
								'type'            => 'text',
								'label'           => __( 'Chat Heading', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Chat With Us', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									[
										'type' => 'html',
										'elem' => '.wfacp-chat .wfacp-contact-head',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp-chat .wfacp-contact-head',
									],
								),
								'priority'        => 20,
							],
							'contact_timing'      => [
								'type'            => 'textarea',
								'label'           => __( 'Chat Description', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Monday - Friday, 7:00am - 7:00pm (Pacific Time)', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'type' => 'html',
										'elem' => '.wfacp_chat_description_wrap ',
									),
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp-chat .wfacp_chat_description_wrap',
									],
								),
								'priority'        => 20,
							],

							/* Customer Care Advance Setting */

							'ct_advanced_setting'                           => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							],
							$selected_template_slug . '_rbox_border_type'   => [
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
										'elem'     => ".wfacp_customer_$i",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_customer_' . $i,
									],
								],
							],
							$selected_template_slug . '_rbox_border_width'  => [
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
										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_customer_$i",
									],
								],
							],
							$selected_template_slug . '_rbox_border_color'  => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 220,
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_customer_$i",
									],
								],
							],
							$selected_template_slug . '_rbox_padding'       => [
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => '20',
								'priority'        => 220,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_customer_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_customer_$i",
									],
								],
							],

							/* Customer Care Color Setting */
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
										'elem'     => ".wfacp_customer_$i",
									],
								],

							],
							$selected_template_slug . '_sec_heading_color'  => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Section Heading Color', 'woofunnels-aero-checkout' ),
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
										'elem'     => ".wfacp_customer_$i" . ' .wfacp_section_title',
									],
								],

							],
							$selected_template_slug . '_icon_text_color'    => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Icon Color', 'woofunnels-aero-checkout' ),
								'default'         => '#8a9a5f',
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
										'elem'     => ".wfacp_customer_$i" . ' .wfacp-support-details-wrap li:before',
									],
								],
							],
							$selected_template_slug . '_heading_text_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Sub Heading Color', 'woofunnels-aero-checkout' ),
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
										'elem'     => ".wfacp_customer_$i" . ' .wfacp-subtitle',
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
										'elem'     => ".wfacp_customer_$i" . ' p',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_customer_$i" . ' span',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_customer_$i" . ' h5',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_customer_$i" . ' h6',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_customer_$i" . ' .wfacp_chat_description_wrap  p',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_customer_$i" . ' .wfacp_chat_description_wrap',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_customer_$i" . ' .wfacp_email_description_wrap',
									]

								],
							],

						],
					),
				),
			);

			$section_data_keys['colors'] = [
				$selected_template_slug . '_section_bg_color'   => [
					[
						'type'   => 'background-color',
						'class'  => ".wfacp_customer_$i",
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_sec_heading_color'  => [
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' .wfacp_section_title',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_icon_text_color'    => [
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' .wfacp-support-details-wrap li:before',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_heading_text_color' => [
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' .wfacp-subtitle',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_content_text_color' => [
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' p',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' span',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' h5',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' h6',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' .wfacp_chat_description_wrap  p',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => ".wfacp_customer_$i" . ' .wfacp_chat_description_wrap',
						'device' => 'desktop',
					],
				],

			];

			$this->template_common->set_section_keys_data( 'wfacp_customer_' . $i, $section_data_keys );

			$temp = $customer_care_panel[ 'wfacp_customer_' . $i ];

			$customer_care_panel[ 'wfacp_customer_' . $i ] = apply_filters( 'wfacp_layout_default_setting', $temp, 'wfacp_customer_' . $i );

			return $customer_care_panel;

		}


	}
}