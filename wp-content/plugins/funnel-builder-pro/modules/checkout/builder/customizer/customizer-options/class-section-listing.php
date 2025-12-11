<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionListing' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionListing {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;
		public static $icon_listing = [];

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

		public function listing_settings( $i = 0 ) {
			$selected_template_slug = $this->template_common->get_template_slug();

			self::$icon_listing = array(
				'wfacp-like'                          => $this->template_common->img_path . 'list-icon/001-like.svg',
				'wfacp-thumbs-up-hand-symbol'         => $this->template_common->img_path . 'list-icon/002-thumbs-up-hand-symbol.svg',
				'wfacp-plus'                          => $this->template_common->img_path . 'list-icon/003-add-circular-button.svg',
				'wfacp-minus-button'                  => $this->template_common->img_path . 'list-icon/004-minus-button.svg',
				'wfacp-remove'                        => $this->template_common->img_path . 'list-icon/005-remove.svg',
				'wfacp-star'                          => $this->template_common->img_path . 'list-icon/006-star-1.svg',
				'wfacp-star-1'                        => $this->template_common->img_path . 'list-icon/007-star.svg',
				'wfacp-hand-finger-pointing-to-right' => $this->template_common->img_path . 'list-icon/008-hand-finger-pointing-to-right.svg',
				'wfacp-right-arrow'                   => $this->template_common->img_path . 'list-icon/009-next.svg',
				'wfacp-add'                           => $this->template_common->img_path . 'list-icon/011-add-plus-button.svg',
				'wfacp-tick'                          => $this->template_common->img_path . 'list-icon/012-tick.svg',

			);

			/** PANEL: Listing Setting */
			$listing_panel = array();

			$listing_panel[ 'wfacp_benefits_' . $i ] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'             => 30,
					'title'                => 'Benefits',
					'description'          => '',
					'wfacp_benefits_' . $i => 'list',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => 'Benefits',
							'priority' => 30,
						),
						'fields' => [

							/* List Section Heading */
							'ct_heading'                                => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Section Heading', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_enable_heading' => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Section Heading', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => true,
								'priority'    => 20,
							],
							'heading'                                   => [
								'type'            => 'text',
								'label'           => __( 'Heading', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'default'         => esc_attr__( 'Why Buy From Us', 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_partial'   => [
									'elem' => '.wfacp_benefits_' . $i . ' .wfacp_section_title',
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_heading_fs'     => [
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
										'elem'       => '.wfacp_benefits_' . $i . ' .wfacp_section_title',
									],
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],
							$selected_template_slug . '_heading_talign' => [
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
										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
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
										'remove' => [ 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ],
										'elem'   => '.wfacp_benefits_' . $i . ' .wfacp_section_title ',
									],
								],

							],

							$selected_template_slug . '_heading_font_weight'       => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-bold',
								'choices' => [
									'wfacp-bold'   => 'Bold',
									'wfacp-normal' => 'Normal',
								],

								'active_callback' => [
									[
										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
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
										'elem'   => '.wfacp_benefits_' . $i . ' .wfacp_section_title ',
									],
								],
							],

							/* List Content Setting */
							'ct_list-icon'                                         => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							'icon_text'                                            => array(
								'type'      => 'repeater',
								'label'     => esc_attr__( 'Benefits', 'woofunnels-aero-checkout' ),
								'row_label' => array(
									'type'  => 'text',
									'value' => esc_attr__( 'Benefit', 'woofunnels-aero-checkout' ),
								),
								'priority'  => 20,
								'default'   => array(
									array(
										'heading' => esc_attr__( '100% Safe and Secure Shopping', 'woofunnels-aero-checkout' ),
										'message' => __( 'All the information that you submit here is 100% encrypted. This is 128 bit SSL encrypted payment.', 'woofunnels-aero-checkout' ),

									),
									array(
										'heading' => esc_attr__( 'Best Prices', 'woofunnels-aero-checkout' ),
										'message' => __( 'We ensure product quality at highly competitive prices. We have 93% customer satisfaction rate.', 'woofunnels-aero-checkout' ),

									),
									array(
										'heading' => esc_attr__( 'Fast Shipping', 'woofunnels-aero-checkout' ),
										'message' => __( 'We work hard to ensure that you get on time delievery. And adhere to our estimated shipping dates.', 'woofunnels-aero-checkout' ),

									),
									array(
										'heading' => esc_attr__( 'Quick Order Processing and Tracking', 'woofunnels-aero-checkout' ),
										'message' => __( 'As soon as you place the order, you will receive email for order confirmation & we shall begin processing your order right after. You will receive tracking id for your order after shipping.', 'woofunnels-aero-checkout' ),

									),
								),
								'fields'    => array(
									'heading' => array(
										'type'  => 'text',
										'label' => __( 'Heading', 'woofunnels-aero-checkout' ),
									),
									'message' => array(
										'type'  => 'textarea',
										'label' => __( 'Text', 'woofunnels-aero-checkout' ),
									),

								),

							),
							$selected_template_slug . '_list_icon'                 => [
								'type'            => 'radio-image',
								'label'           => esc_attr__( 'Built-in List Icons', 'woofunnels-aero-checkout' ),
								'description'     => '',
								'choices'         => self::$icon_listing,
								'default'         => 'wfacp-tick',
								'priority'        => 20,
								'active_callback' => [
									[
										'setting'  => 'wfacp_benefits_' . $i . '_section_hide_list_icon',
										'operator' => '==',
										'value'    => false,
									],
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [
											'wfacp-like',
											'wfacp-thumbs-up-hand-symbol',
											'wfacp-plus',
											'wfacp-minus-button',
											'wfacp-remove',
											'wfacp-star',
											'wfacp-star-1',
											'wfacp-hand-finger-pointing-to-right',
											'wfacp-right-arrow',
											'wfacp-add',
											'wfacp-tick',

										],
										'elem'   => '.wfacp_benefits_' . $i . ' .wfacp-icon-list',
									],
								],
							],
							$selected_template_slug . '_hide_list_icon'            => [
								'type'        => 'checkbox',
								'label'       => __( 'Hide Benefits Icon', 'woofunnels-aero-checkout' ),
								'description' => __( 'You can hide the list icons', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 20,
							],
							$selected_template_slug . '_display_list_heading'      => [
								'type'        => 'checkbox',
								'label'       => __( 'Show Heading', 'woofunnels-aero-checkout' ),
								'description' => __( 'You can show / hide the heading of each list item', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							],
							$selected_template_slug . '_show_list_description'     => [
								'type'        => 'checkbox',
								'label'       => __( 'Show Description', 'woofunnels-aero-checkout' ),
								'description' => __( 'You can Show the description', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,
							],
							$selected_template_slug . '_display_list_bold_heading' => [
								'type'        => 'checkbox',
								'label'       => __( 'Bold Heading', 'woofunnels-aero-checkout' ),
								'description' => __( 'Set heading bold or normal', 'woofunnels-aero-checkout' ),
								'default'     => true,
								'priority'    => 20,

							],

							/* List Advance Setting */
							'advanced_setting'                                     => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							),
							$selected_template_slug . '_rbox_border_type'          => array(
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
										'elem'     => ".wfacp_benefits_$i",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_benefits_' . $i,
									],
								],

							),
							$selected_template_slug . '_rbox_border_width'         => array(
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
										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_benefits_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_border_color'         => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 220,
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_benefits_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_padding'              => array(
								'type'     => 'number',
								'label'    => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'  => 20,
								'priority' => 220,

								'active_callback' => array(
									array(
										'setting'  => 'wfacp_benefits_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_benefits_$i",
									],
								],

							),

							/* List Color Setting */
							'ct_colors'                                            => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],
							$selected_template_slug . '_section_bg_color'          => [
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
										'elem'     => ".wfacp_benefits_$i",
									],
								],

							],
							$selected_template_slug . '_sec_heading_color'         => [
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
										'elem'     => ".wfacp_benefits_$i" . ' .wfacp_section_title',
									],
								],

							],
							$selected_template_slug . '_heading_text_color'        => [
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
										'elem'     => ".wfacp_benefits_$i" . ' .loop_head_sec',
									],
								],

							],
							$selected_template_slug . '_content_text_color'        => [
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
										'elem'     => ".wfacp_benefits_$i" . ' p',
									],
								],
							],
							$selected_template_slug . '_icon_color'                => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Icon Color', 'woofunnels-aero-checkout' ),
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
										'elem'     => ".wfacp_benefits_$i" . ' .wfacp-icon-list',
									],
								],
							],

						],
					),
				),
			);

			$temp = $listing_panel[ 'wfacp_benefits_' . $i ];

			$listing_panel[ 'wfacp_benefits_' . $i ] = apply_filters( 'wfacp_layout_default_setting', $temp, 'wfacp_benefits_' . $i );

			return $listing_panel;

		}


	}
}