<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionTestimonial' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionTestimonial {

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

		public function testimonial_settings( $i = 0 ) {

			$selected_template_slug = $this->template_common->get_template_slug();
			/** PANEL: TESTIMONIALS */
			$testimonial_panel = array();

			$testimonial_panel[ 'wfacp_testimonials_' . $i ] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 35,
					'title'       => 'Testimonials',
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => 'Testimonials',
							'priority' => 35,
						),
						'fields' => [

							/* Testimonial Layout */
							'layout'                                    => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_layout_type'    => array(
								'type'    => 'radio-image-text',
								'label'   => '',
								'default' => 'parallel',

								'choices'  => array(
									'parallel'    => array(
										'label' => __( 'Parallel', 'woofunnels-aero-checkout' ),
										'path'  => $this->template_common->img_path . 'testimonial-layout/parallel.svg',
									),
									'alternative' => array(
										'label' => __( 'Alternative', 'woofunnels-aero-checkout' ),
										'path'  => $this->template_common->img_path . 'testimonial-layout/alternative.svg',
									),

								),
								'priority' => 20,
							),

							/* Testimonial Section Heading */
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
								'default'         => esc_attr__( "What They're Saying", 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'wfacp_partial'   => [
									'elem' => '.wfacp_testimonials_' . $i . ' .wfacp_section_title',
								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
							],

							$selected_template_slug . '_heading_fs'          => [
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
										'elem'       => '.wfacp_testimonials_' . $i . ' .wfacp_section_title',
									],
								],
								'active_callback' => [
									[
										'setting' => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_enable_heading',

										'operator' => '==',
										'value'    => true,
									],
								],
								'priority'        => 20,
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

								'active_callback' => [
									[

										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
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
										'elem'   => '.wfacp_testimonials_' . $i . ' .wfacp_section_title ',
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

								'active_callback' => [
									[

										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_enable_heading',
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
										'elem'   => '.wfacp_testimonials_' . $i . ' .wfacp_section_title ',
									],
								],
							],

							/* Testimonial Section */

							'ct_testimonials' => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],

							$selected_template_slug . '_testimonial_type' => [
								'type'     => 'radio-buttonset',
								'label'    => __( 'Testimonials Type', 'woofunnels-aero-checkout' ),
								'default'  => 'manual',
								'choices'  => [
									'manual'    => 'Manual',
									'automatic' => 'Automatic',
								],
								'priority' => 50,
							],

							'show_review'  => [
								'type'            => 'slider',
								'label'           => __( 'Show Reviews', 'woofunnels-aero-checkout' ),
								'description'     => __( 'Greater than or equal to', 'woofunnels-aero-checkout' ),
								'default'         => 4,
								'choices'         => [
									'min'  => 1,
									'max'  => 5,
									'step' => 1,
								],
								'priority'        => 60,
								'active_callback' => [
									[
										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_testimonial_type',
										'operator' => '!=',
										'value'    => 'manual',
									],
								],
							],
							'review_limit' => [
								'type'            => 'number',
								'label'           => __( 'No. Of Reviews To Show ', 'woofunnels-aero-checkout' ),
								'default'         => 4,
								'choices'         => [
									'min'  => 1,
									'max'  => 50,
									'step' => 1,
								],
								'priority'        => 70,
								'active_callback' => [
									[
										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_testimonial_type',
										'operator' => '!=',
										'value'    => 'manual',
									],
								],
							],
							'testimonials' => array(
								'type'            => 'repeater',
								'label'           => esc_attr__( 'Testimonials', 'woofunnels-aero-checkout' ),
								'priority'        => 80,
								'row_label'       => array(
									'type'  => 'text',
									'value' => esc_attr__( 'Testimonial', 'woofunnels-aero-checkout' ),
								),
								'default'         => array(
									array(
										'testi_heading' => esc_attr__( 'Amazing Product', 'woofunnels-aero-checkout' ),
										'tname'         => esc_attr__( 'Christine McVeigh', 'woofunnels-aero-checkout' ),
										'tdesignation'  => __( 'Store Owner', 'woofunnels-aero-checkout' ),
										'tmessage'      => __( 'Add a testimonial should highlight why they use the product and how It benefits them.', 'woofunnels-aero-checkout' ),
										'tdate'         => __( '2018-09-08', 'woofunnels-aero-checkout' ),
										'trating'       => '5',
										'timage'        => $this->template_common->img_path . 'no_image.jpg',
									),
									array(
										'testi_heading' => esc_attr__( 'Got Great Results', 'woofunnels-aero-checkout' ),
										'tname'         => esc_attr__( 'Christine McVeigh', 'woofunnels-aero-checkout' ),
										'tdesignation'  => __( 'Store Owner', 'woofunnels-aero-checkout' ),
										'tmessage'      => __( 'Add a testimonial should highlight why they use the product and how It benefits them.', 'woofunnels-aero-checkout' ),
										'tdate'         => __( '2018-10-03', 'woofunnels-aero-checkout' ),
										'trating'       => '5',
										'timage'        => $this->template_common->img_path . 'no_image.jpg',
									),
								),
								'fields'          => array(
									'testi_heading' => array(
										'type'  => 'text',
										'label' => __( 'Heading', 'woofunnels-aero-checkout' ),
									),
									'tname'         => array(
										'type'  => 'text',
										'label' => __( 'Name', 'woofunnels-aero-checkout' ),
									),
									'tdesignation'  => array(
										'type'  => 'text',
										'label' => __( 'Designation', 'woofunnels-aero-checkout' ),
									),
									'tmessage'      => array(
										'type'  => 'textarea',
										'label' => __( 'Message', 'woofunnels-aero-checkout' ),
									),
									'tdate'         => array(
										'type'        => 'date',
										'label'       => esc_attr__( 'Date', 'woofunnels-aero-checkout' ),
										'description' => esc_attr__( 'Date Format', 'woofunnels-aero-checkout' ),
									),
									'trating'       => array(
										'type'    => 'radio',
										'label'   => __( 'Rating', 'woofunnels-aero-checkout' ),
										'default' => '5',
										'choices' => array(
											'1' => esc_attr__( '1', 'woofunnels-aero-checkout' ),
											'2' => esc_attr__( '2', 'woofunnels-aero-checkout' ),
											'3' => esc_attr__( '3', 'woofunnels-aero-checkout' ),
											'4' => esc_attr__( '4', 'woofunnels-aero-checkout' ),
											'5' => esc_attr__( '5', 'woofunnels-aero-checkout' ),
										),
									),
									'timage'        => array(
										'type'  => 'image',
										'label' => esc_attr__( 'Image', 'woofunnels-aero-checkout' ),
									),
								),
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_testimonial_type',
										'operator' => '==',
										'value'    => 'manual',
									),
								),
							),

							$selected_template_slug . '_hide_name'         => [
								'type'            => 'checkbox',
								'label'           => __( 'Hide Testimonial Heading', 'woofunnels-aero-checkout' ),
								'description'     => __( 'Enable if you want to hide the testimonial heading', 'woofunnels-aero-checkout' ),
								'default'         => false,
								'priority'        => 110,
								'active_callback' => [
									[
										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_testimonial_type',
										'operator' => '==',
										'value'    => 'manual',
									],

								],
							],
							$selected_template_slug . '_hide_designation'  => [
								'type'            => 'checkbox',
								'label'           => __( 'Hide Designation', 'woofunnels-aero-checkout' ),
								'description'     => __( 'Enable if you want to hide the designation', 'woofunnels-aero-checkout' ),
								'default'         => false,
								'priority'        => 110,
								'active_callback' => [
									[

										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_testimonial_type',
										'operator' => '==',
										'value'    => 'manual',
									],
								],
							],
							$selected_template_slug . '_hide_image'        => [
								'type'        => 'checkbox',
								'label'       => __( 'Hide Image ', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to hide the image', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 120,
							],
							$selected_template_slug . '_hide_author_meta'  => [
								'type'        => 'checkbox',
								'label'       => __( 'Hide Author Meta ', 'woofunnels-aero-checkout' ),
								'description' => __( 'Enable if you want to remove Star Rating and Author Meta', 'woofunnels-aero-checkout' ),
								'default'     => false,
								'priority'    => 120,
							],
							$selected_template_slug . '_image_type'        => [
								'type'            => 'radio-buttonset',
								'label'           => __( 'Image Type', 'woofunnels-aero-checkout' ),
								'default'         => 'wfacp-round',
								'choices'         => [
									'wfacp-round'  => 'Round',
									'wfacp-square' => 'Square',
								],
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-square', 'wfacp-round' ],
										'elem'   => '.wfacp_testimonials_' . $i . ' .wfacp-testing-img',
									],
								],

								'priority' => 120,
							],

							/* Testimonial Advance Setting */
							'advanced_setting'                             => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 190,
							),
							$selected_template_slug . '_rbox_border_type'  => array(
								'type'            => 'select',
								'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default'         => 'solid',
								'choices'         => array(
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
										'elem'     => ".wfacp_testimonials_$i",
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_testimonials_' . $i,
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
										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_testimonials_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_border_color' => array(
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 220,
								'active_callback' => array(
									array(

										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_testimonials_$i",
									],
								],
							),
							$selected_template_slug . '_rbox_padding'      => array(
								'type'            => 'number',
								'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
								'default'         => '20',
								'priority'        => 220,
								'active_callback' => array(
									array(
										'setting'  => 'wfacp_testimonials_' . $i . '_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => ".wfacp_testimonials_$i",
									],
								],

							),

							/* Testimonial Color Setting */

							'ct_colors' => [
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
										'elem'     => ".wfacp_testimonials_$i",
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
										'elem'     => ".wfacp_testimonials_$i" . ' .wfacp_section_title',
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
										'elem'     => ".wfacp_testimonials_$i" . ' .loop_head_sec',
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
										'elem'     => ".wfacp_testimonials_$i" . ' p',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => ".wfacp_testimonials_$i" . ' .wfacp-testi-content-color',
									],

								],
							],

						],
					),
				),
			);

			$temp = $testimonial_panel[ 'wfacp_testimonials_' . $i ];

			$testimonial_panel[ 'wfacp_testimonials_' . $i ] = apply_filters( 'wfacp_layout_default_setting', $temp, 'wfacp_testimonials_' . $i );

			return $testimonial_panel;

		}

	}
}