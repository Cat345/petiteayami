<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionForm' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionForm {

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

		public function form_settings() {

			$section_data_keys = [];

			$selected_template_slug = $this->template_common->get_template_slug();
			$fields                 = $this->template_common->get_checkout_fields();
			$fieldset               = $this->template_common->get_fieldsets();
			$num_of_steps           = $this->template_common->get_step_count();
			$template_type          = $this->template_common->get_template_type();


			$backBtnText            = esc_attr__( '&laquo; Return', 'woofunnels-aero-checkout' );
			$backBtnDescriptionText = __( 'Use {step_name} to dynamically show the name of previous step', 'woofunnels-aero-checkout' );

			$current_version = WFACP_Common::get_checkout_page_version();
			$pageID          = WFACP_Common::get_id();


			if ( version_compare( $current_version, '1.9.0', '>=' ) && 'pre_built' === $template_type && $num_of_steps > 1 ) {
				$backBtnText = '&laquo; Return to {step_name}';
			}


			$merge_tags_description = '<a href="javascript:void(0)"  onclick="wfacp_show_form_popup();" >' . __( 'Click here to know more about available classes to setup fields.', 'woofunnels-aero-checkout' ) . '</a>';

			/** PANEL: Form Setting */
			$form_panel = array();
			if ( ! is_array( $fields ) || count( $fields ) == 0 ) {
				return;
			}
			$order_total = false;


			if ( isset( $fields['advanced']['order_total'] ) ) {
				$order_total = true;
			}

			$page_id          = WFACP_Common::get_id();
			$products_details = WFACP_Common::get_page_product( $page_id );

			$best_values = array();

			if ( ! empty( $products_details ) ) {
				$best_values['selected'] = __( 'Select a product', 'woofunnels-aero-checkout' );
				foreach ( $products_details as $p_key => $p_value ) {
					$best_values[ $p_key ] = $p_value['title'];
				}
			}

			$section_fields = [];
			$step_btns      = [ 'order-place' ];

			$form_panel['wfacp_form'] = array(
				'panel'    => 'yes',
				'data'     => array(
					'priority'    => 20,
					'title'       => __( 'Checkout Form', 'woofunnels-aero-checkout' ),
					'description' => '',
				),
				'sections' => array(
					'section'     => array(

						'data'   => [
							'title'    => __( 'Form Style', 'woofunnels-aero-checkout' ),
							'priority' => 10,
						],
						'fields' => [
							/* ------------------------------------section -------------------------------- */
							'ct_heading' => [
								'type'          => 'custom',
								'default'       => '<div class="options-title-divider">' . esc_html__( 'Section', 'woofunnels-aero-checkout' ) . '</div>',
								'priority'      => 20,
								'transport'     => 'postMessage',
								'wfacp_partial' => [
									'elem' => '.wfacp_main_form .step_0 .wfacp_section_title',
								],

							],

							'form_section_ct_typgraphy'                      => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Typography', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_heading_fs'          => [
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
								'default'         => [
									'desktop' => 35,
									'tablet'  => 30,
									'mobile'  => 22,
								],
								'input_attrs'     => [
									'step' => 1,
									'min'  => 12,
									'max'  => 40,
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
										'elem'       => '.wfacp_main_form .wfacp_section_title ',
									],
									[
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => [ 'font-size' ],
										'elem'       => '.wfacp-order-summary-label ',
									],

								],

								'priority' => 20,
							],
							$selected_template_slug . '_heading_font_weight' => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-normal',
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
										'elem'   => '.wfacp_main_form .wfacp_section_title ',
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-bold', 'wfacp-normal' ],
										'elem'   => '.wfacp-order-summary-label',
									],
								],
							],

							$selected_template_slug . '_heading_talign'    => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default' => is_rtl() ? 'wfacp-text-right' : 'wfacp-text-left',
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
										'elem'   => '.wfacp_main_form .wfacp_section_title ',
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ],
										'elem'   => '.wfacp-order-summary-label ',
									],
								],

							],

							/*  colors */
							'form_section_ct_color'                        => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Color', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_sec_heading_color' => [
								'type'            => 'color',
								'class'           => 'myClass',
								'label'           => esc_attr__( 'Section Heading', 'woofunnels-aero-checkout' ),
								'default'         => '#414349',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp_section_title ',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp-order-summary-label ',
									],
								],

							],
							$selected_template_slug . '_sec_bg_color'      => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Section Background', 'woofunnels-aero-checkout' ),
								'default'         => 'transparent',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => '.wfacp_main_form .wfacp-comm-title',
									],

								],

							],

							/* section advanced */
							'advanced_setting'                             => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_rbox_border_type'  => [
								'type'            => 'select',
								'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
								'default'         => 'none',
								'choices'         => [
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],

										'elem' => '.wfacp_main_form .wfacp-comm-title',
									],
									[
										'type'   => 'add_class',
										'direct' => 'true',
										'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
										'elem'   => '.wfacp_main_form .wfacp-comm-title',
									],
								],
							],
							$selected_template_slug . '_rbox_border_width' => [
								'type'            => 'slider',
								'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => [
									'min'  => '1',
									'max'  => '12',
									'step' => '1',
								],
								'priority'        => 20,
								'active_callback' => [
									[
										'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => '.wfacp_main_form .wfacp-comm-title',
									],
								],
							],
							$selected_template_slug . '_rbox_border_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
								'default'         => '#e2e2e2',
								'choices'         => array(
									'alpha' => true,
								),
								'priority'        => 20,
								'active_callback' => [
									[
										'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_rbox_border_type',
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
										'elem'     => '.wfacp_main_form .wfacp-comm-title',
									],
								],
							],
							$selected_template_slug . '_rbox_padding'      => [
								'type'     => 'number',
								'label'    => __( 'Padding (Left and Right)', 'woofunnels-aero-checkout' ),
								'default'  => 0,
								'priority' => 20,

								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => [ 'padding-left' ],
										'elem'     => '.wfacp_main_form .wfacp-comm-title',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => [ 'padding-right' ],
										'elem'     => '.wfacp_main_form .wfacp-comm-title',
									],
								],
							],
							$selected_template_slug . '_rbox_margin'       => [
								'type'            => 'number',
								'label'           => __( 'Margin (Bottom)', 'woofunnels-aero-checkout' ),
								'default'         => 10,
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'margin-bottom' ],
										'elem'     => '.wfacp_main_form .wfacp-comm-title',
										'suffix'   => 'px'
									],

								],
							],

							/* ------------------------------------Sub heading -------------------------------- */

							'ct_sub_heading'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Section Sub Heading', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,

							],
							'form_subheading_ct_typgraphy'                       => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Typography', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
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
									'max'  => 20,
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
										'elem'       => '.wfacp_main_form .wfacp-comm-title h4 ',
									],

								],

								'priority' => 20,
							],
							$selected_template_slug . '_sub_heading_font_weight' => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
								'default' => 'wfacp-normal',
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
										'elem'   => '.wfacp_main_form .wfacp-comm-title h4 ',
									],
								],
							],
							$selected_template_slug . '_sub_heading_talign'      => [
								'type'    => 'radio-buttonset',
								'label'   => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
								'default' => is_rtl() ? 'wfacp-text-right' : 'wfacp-text-left',
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
										'elem'   => '.wfacp_main_form .wfacp-comm-title h4 ',
									],
								],

							],

							/*  colors */
							'form_subheading_ct_color'                           => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Color', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_sec_sub_heading_color'   => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Section Subheading', 'woofunnels-aero-checkout' ),
								'default'         => '#999999',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp-comm-title h4',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form #vat_number-description',
									],
								],

							],
							'ct_field_style'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Field Style', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_wfacp_label_position'    => [
								'type'     => 'select',
								'label'    => __( 'Label Position', 'woofunnels-aero-checkout' ),
								'default'  => 'wfacp-inside',
								'choices'  => [
									'wfacp-top'    => __( 'Top of Field', 'woofunnel-aero-checkout' ),
									'wfacp-inside' => __( 'Inside Field', 'woofunnel-aero-checkout' ),

								],
								'priority' => 20,


							],
							'form_fieldstyle_ct_typgraphy'                       => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Typography', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_field_style_fs'          => [
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
										'elem'       => '.wfacp_main_form label.wfacp-form-control-label',
									],

								],

								'priority' => 20,
							],
							$selected_template_slug . '_field_border_layout'     => [
								'type'            => 'select',
								'label'           => __( 'Field Border Layout', 'woofunnels-aero-checkout' ),
								'default'         => 'solid',
								'choices'         => [
									'none'   => 'None',
									'solid'  => 'Solid',
									'double' => 'Double',
									'dotted' => 'Dotted',
									'dashed' => 'Dashed',

								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper select.wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control-wrapper input',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control-wrapper select',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form p.woocommerce-invalid-required-field.wfacp_select2_country_state .woocommerce-input-wrapper .select2-container .select2-selection--single',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form .wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form select.wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-style' ],
										'elem'     => 'body .wfacp_main_form .wfacp_allowed_countries strong',
									],
								],

							],
							$selected_template_slug . '_field_border_width'      => [
								'type'            => 'slider',
								'label'           => esc_attr__( 'Field  Border Width', 'woofunnels-aero-checkout' ),
								'default'         => 1,
								'choices'         => [
									'min'  => '1',
									'max'  => '12',
									'step' => '1',
								],
								'priority'        => 20,
								'active_callback' => [
									[
										'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_field_border_layout',
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
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper select.wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control-wrapper input',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control-wrapper select',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form select.wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .wfacp_allowed_countries strong',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .select2-container--default .select2-selection--multiple',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body #et-boc .et-l span.select2-selection.select2-selection--multiple',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .select2-container--default.select2-container--focus .select2-selection--multiple',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-email) input[type=email]:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=password]:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=search]:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=tel]:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=text]:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=url]:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) textarea:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) select:hover',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-width' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .select2-container .select2-selection--single .select2-selection__rendered:hover',
									],

								],
							],
							'form_field_style_ct_color'                          => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Color', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							],
							$selected_template_slug . '_field_style_color'       => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Field Label', 'woofunnels-aero-checkout' ),
								'default'         => '#888888',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form label.wfacp-form-control-label',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp_custom_field_radio_wrap label',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp_custom_field_cls span',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp_custom_field_cls label',
									],

									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp-row .automatewoo-birthday-section > label',
									],
								],

							],
							$selected_template_slug . '_field_border_color'      => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Field Border', 'woofunnels-aero-checkout' ),
								'default'         => '#eaeaea',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'active_callback' => [
									[
										'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_field_border_layout',
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
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper select.wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control-wrapper input',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control-wrapper select',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form select.wfacp-form-control',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .wfacp_allowed_countries strong',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .select2-container--default.select2-container--focus .select2-selection--multiple',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .select2-container--default .select2-selection--multiple',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => '#et-boc .et-l span.select2-selection.select2-selection--multiple',
									],

								],
							],
							$selected_template_slug . '_field_focus_color'       => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Field Focus', 'woofunnels-aero-checkout' ),
								'default'         => '#61bdf7',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-email) input[type=email]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-email) input[type=number]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=password]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=search]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=tel]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=text]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=number]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=url]:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) textarea:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) select:focus',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'border-color' ],
										'elem'     => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .select2-container .select2-selection--single .select2-selection__rendered:focus',
									],


								],
							],
							$selected_template_slug . '_field_input_color'       => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Field Value', 'woofunnels-aero-checkout' ),
								'default'         => '#404040',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=email]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=password]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=search]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=tel]',
									],

									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=text]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=url]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row textarea',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row select',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row .select2-container .select2-selection--single .select2-selection__rendered',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=number]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .select2-container .select2-selection--single .select2-selection__rendered',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-checkout select#join_referral_program',
									],


								],
							],
							$selected_template_slug . '_field_input_bg_color'    => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Field Background Color', 'woofunnels-aero-checkout' ),
								'default'         => '#ffffff',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=email]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=password]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=search]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=tel]',
									],

									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=text]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=url]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row textarea',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row select',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_main_form .form-row .select2-container .select2-selection--single .select2-selection__rendered',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .form-row input[type=number]',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .select2-container .select2-selection--single .select2-selection__rendered',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp_main_form .woocommerce-checkout select#join_referral_program',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => 'body .wfacp-inside .form-row > label.wfacp-form-control-label:not(.checkbox)',
									],


								],
							],

							'cta_payment_methods'                                    => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Payment Method' ,'woocommerce' ) . '</div>',
								'priority' => 20,
							],
							'payment_methods_heading'                                => [
								'type'      => 'text',
								'label'     => __( 'Heading', 'woofunnels-aero-checkout' ),
								'default'   => esc_attr__( 'Payment', 'woocommerce'  ),
								'priority'  => 20,
								'transport' => 'postMessage',

								'wfacp_partial' => [
									'elem' => '.wfacp_payment .wfacp_section_heading',
								],
							],
							'payment_methods_sub_heading'                            => [
								'type'            => 'textarea',
								'label'           => __( 'Sub heading', 'woofunnels-aero-checkout' ),
								'default'         => esc_attr__( 'All transactions are secure and encrypted.', 'woofunnels-aero-checkout' ),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'type'                => 'html',
										'container_inclusive' => false,
										'elem'                => '.wfacp_payment h4',
									],
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_payment h4',
									],
								],
							],
							/* Progress bar color setting */
							'ct_steps_colors'                                        => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Color', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 10,
							],
							$selected_template_slug . '_breadcrumb_color_type'       => [
								'type'      => 'radio-buttonset',
								'label'     => __( 'Step Bar Text', 'woofunnels-aero-checkout' ),
								'default'   => 'normal',
								'choices'   => [
									'normal' => 'Normal',
									'hover'  => 'Hover',
								],
								'priority'  => 10,
								'transport' => 'postMessage',
							],
							$selected_template_slug . '_breadcrumb_text_color'       => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Color', 'woofunnels-aero-checkout' ),
								'default'         => '#4d4c4c',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 10,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_steps_sec ul li a',
									],

								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_breadcrumb_color_type',
										'operator' => '=',
										'value'    => 'normal',
									],
								],

							],
							$selected_template_slug . '_breadcrumb_text_hover_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Color', 'woofunnels-aero-checkout' ),
								'default'         => '#4d4c4c',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 10,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => 'body .wfacp_steps_sec ul li a:hover',
									],

								],
								'active_callback' => [
									[
										'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_breadcrumb_color_type',
										'operator' => '=',
										'value'    => 'hover',
									],
								],

							],
							$selected_template_slug . '_additional_text_color'       => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Color', 'woofunnels-aero-checkout' ),
								'default'         => '#000000',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 21,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp-payment-dec',
									],

								],

							],
							$selected_template_slug . '_additional_bg_color'         => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
								'default'         => 'transparent',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 21,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => '.wfacp_main_form .wfacp-payment-dec',
									],

								],

							],
							'ct_form_elements_colors'                                => [
								'type'     => 'custom',
								'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Form', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 21,
							],
							$selected_template_slug . '_validation_color'            => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Validation Text', 'woofunnels-aero-checkout' ),
								'default'         => '#ff0000',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 21,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form .wfacp_error_message',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_main_form span.wfacp_input_error_msg',
									],

								],

							],


						],
					),
					'form_fields' => [
						'data'   => [
							'title'    => 'Field Width',
							'priority' => 10,
						],
						'fields' => [],
					],
				),
			);

			if ( $selected_template_slug == 'layout_1' && isset( $form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_wfacp_label_position' ] ) ) {
				unset( $form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_wfacp_label_position' ] );

			}

			if ( true === $order_total ) {
				$form_panel['wfacp_form']['sections']['section']['fields']['ct_order_total_colors']                                 = [
					'type'          => 'custom',
					'default'       => '<div class="options-title-divider">' . esc_html__( 'Order Total', 'woocommerce' ) . '</div>',
					'priority'      => 24,
					'wfacp_partial' => [
						'elem' => '#order_total_field',
					]
				];
				$form_panel['wfacp_form']['sections']['section']['fields']['ct_order_total_colors_subheading']                      = [
					'type'     => 'custom',
					'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 24,
				];
				$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_order_total_bg_color' ]     = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
					'default'         => '#f8f8f8',
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 24,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background-color' ],
							'elem'     => 'body .wfacp_main_form .wfacp_order_total_field table.wfacp_order_total_wrap',
						],

					],

				];
				$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_order_total_text_color' ]   = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Text', 'woofunnels-aero-checkout' ),
					'default'         => '#737373',
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 24,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => 'body .wfacp_main_form .wfacp_order_total_field table.wfacp_order_total_wrap td',
						],


					],

				];
				$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_order_total_border_color' ] = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Border', 'woofunnels-aero-checkout' ),
					'default'         => '#dedede',
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 24,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-color' ],
							'elem'     => 'body .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap',
						],

					],

				];
			}


			if ( $num_of_steps > 1 ) {

				$stepsCounter = 1;

				$form_panel['wfacp_form']['sections']['section']['fields']['ct_bredcrumb']         = array(
					'type'     => 'custom',
					'default'  => sprintf( '<div class="options-title-divider">%s</div>', esc_html__( 'Breadcrumb' ) ),
					'priority' => 9,
				);
				$form_panel['wfacp_form']['sections']['section']['fields']['ct_bredcrumb_content'] = array(
					'type'     => 'custom',
					'default'  => sprintf( '<div class="wfacp-options-sub-heading">%s</div>', esc_html__( 'Content' ) ),
					'priority' => 9,
				);


				$steps_arr_Count = $num_of_steps + 1;

				for ( $bi = 0; $bi < $steps_arr_Count; $bi ++ ) {

					$breadcrum_default_val = 'Step ' . $stepsCounter;
					if ( $stepsCounter == $steps_arr_Count ) {
						$breadcrum_default_val = __( 'Order Complete', 'woofunnels-aero-checkout' );
					}

					$form_panel['wfacp_form']['sections']['section']['fields'][ 'breadcrumb_' . $bi . '_step_text' ] = [
						'type'            => 'text',
						'label'           => __( 'Step ' . $stepsCounter . ' Title', 'woofunnels-aero-checkout' ),
						'description'     => '',
						'priority'        => 9,
						'default'         => $breadcrum_default_val,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'type'                => 'html',
								'container_inclusive' => false,
								'elem'                => 'body .wfacp_steps_wrap .wfacp_step_' . $bi . ' a',
							],
							[
								'type' => 'add_remove_class',
								'elem' => 'body .wfacp_steps_wrap .wfacp_step_' . $bi,
							],
						],

					];

					if ( $bi == 0 ) {

						unset( $form_panel['wfacp_form']['sections']['section']['fields'][ 'breadcrumb_' . $bi . '_step_text' ]['wfacp_transport'] );
						$form_panel['wfacp_form']['sections']['section']['fields'][ 'breadcrumb_' . $bi . '_step_text' ]['wfacp_partial'] = [
							'elem'     => 'body .wfacp_steps_wrap .wfacp_step_' . $bi,
							'callback' => 'wfacp_changed_step_text',

						];

					}
					$stepsCounter ++;
				}

				$step_btns[] = 'back';
				$step_btns[] = 'next';

			}


			$custom_arr = array();

			$steps_name = [ 'single_step', 'two_step', 'third_step' ];

			$step_count_val = 1;


			$array_reverse = array_reverse( $step_btns );


			foreach ( $array_reverse as $skey => $svalue ) {
				$sheading_key = 'ct_btn_' . $svalue . '_style';
				$st_id        = '';
				$btn_text_cls = $steps_name[ $skey ];


				$btnTexthere          = ' →';
				$btn_parent_class_key = 'wfacp-next-btn-wrap';
				if ( $svalue == 'order-place' ) {
					$st_id                = '#place_order';
					$classAdd             = '';
					$default_value        = __( 'Place order', 'woocommerce' );
					$btnTexthere          = '';
					$btn_parent_class_key = 'wfacp-' . $svalue . '-btn-wrap';

				} elseif ( $svalue == 'next' || $svalue == 'back' ) {
					$default_value = __( 'NEXT STEP', 'woofunnels-aero-checkout' );
				} else {
					$classAdd      = '.wfacp_' . $svalue . '_page_button';
					$default_value = $svalue;
				}
				if ( $svalue != 'back' || $num_of_steps > 2 ) {
					$title_here = 'Step ' . ( $step_count_val ) . ' Button Label';
					$step_count_val ++;
				}
				if ( $skey == 0 ) {
					$form_panel['wfacp_form']['sections']['section']['fields'][ $sheading_key ]      = array(
						'type'     => 'custom',
						'default'  => sprintf( '<div class="options-title-divider">%s</div>', esc_html__( ucfirst( 'Buttons' ) ) ),
						'priority' => 20,
					);
					$form_panel['wfacp_form']['sections']['section']['fields']['cta_button_content'] = [
						'type'     => 'custom',
						'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
						'priority' => 20,
					];
				}

				$hintText = '';
				if ( $svalue == 'next' || $svalue == 'back' ) {
					$hintText = __( 'Use {step_name} to dynamically show the name of next step', 'woofunnels-aero-checkout' );
				}


				$btn_class_key             = 'button';
				$next_btn_parent_class_key = 'wfacp-next-btn-wrap';
				$next_btn_class            = 'wfacp_next_page_button';
				$keyclass                  = $btn_text_cls;
				if ( $svalue != 'back' || $num_of_steps > 2 ) {
					if ( $svalue == 'order-place' && $num_of_steps == $skey ) {
						$keyclass = 'two_step';
					}


					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_btn_text' ] = [
						'type'            => 'text',
						'label'           => $title_here,
						'description'     => $hintText,
						'priority'        => 20,
						'default'         => $default_value . $btnTexthere,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'type'                => 'html',
								'container_inclusive' => false,
								'elem'                => 'body .wfacp_main_form .woocommerce-checkout .' . $keyclass . ' .' . $btn_parent_class_key . ' .button',
							],
						],

					];


			}
			if ( $skey < $num_of_steps ) {
				$form_panel['wfacp_form']['sections']['section']['fields'][ 'step_' . $skey . '_enable_icon_before_button' ] = [
					'type'     => 'radio-buttonset',
					'label'    => __( 'Enable Icon ', 'woofunnels-aero-checkout' ),
					'default'  => 'no',
					'choices'  => [
						'yes' => __( 'Yes', 'woofunnels-aero-checkout' ),
						'no'  => __( 'No', 'woofunnels-aero-checkout' ),
					],
					'priority' => 20,
				];
				$form_panel['wfacp_form']['sections']['section']['fields'][ 'step_' . $skey . '_icon_before_button' ]        = [
					'type'     => 'select',
					'label'    => esc_attr__( 'Select Icons Style', 'woofunnels-aero-checkout' ),
					'default'  => 'aero-e901',
					'choices'  => [
						'aero-e902' => __( 'Arrow 1', 'woofunnels-aero-checkout' ),
						'aero-e906' => __( 'Arrow 2', 'woofunnels-aero-checkout' ),
						'aero-e907' => __( 'Arrow 3', 'woofunnels-aero-checkout' ),
						'aero-e908' => __( 'Checkmark', 'woofunnels-aero-checkout' ),
						'aero-e905' => __( 'Cart 1', 'woofunnels-aero-checkout' ),
						'aero-e901' => __( 'Lock 1', 'woofunnels-aero-checkout' ),
						'aero-e900' => __( 'Lock 2', 'woofunnels-aero-checkout' ),
					],
					'priority' => 20,
				];
				$form_panel['wfacp_form']['sections']['section']['fields'][ 'step_' . $skey . '_text_after_button' ]         = [
					'type'     => 'text',
					'label'    => 'Sub Text',
					'priority' => 20,
					'default'  => '',
				];
			}


				/* Button Icon And text setting */


				if ( $svalue == 'order-place' ) {
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_enable_price_in_place_order_button' ] = [
						'type'     => 'checkbox',
						'label'    => __( 'Enable Price', 'woofunnels-aero-checkout' ),
						'default'  => false,
						'priority' => 20,
					];
				}

				if ( $svalue == 'order-place' && $num_of_steps > 1 ) {
					$form_panel['wfacp_form']['sections']['section']['fields']['back_btn_text'] = [
						'type'          => 'text',
						'label'         => 'Back',
						'description'   => $backBtnDescriptionText,
						'priority'      => 20,
						'default'       => $backBtnText,
						'transport'     => 'postMessage',
						'wfacp_partial' => [
							'elem' => 'body .wfacp_main_form a.wfacp_back_page_button',
						],

					];
				}

				$color_key = $svalue;
				if ( $svalue != 'back' && $svalue != 'next' ) {
					/* button width */

					$form_panel['wfacp_form']['sections']['section']['fields']['cta_button_typography']                                               = [
						'type'     => 'custom',
						'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Typography', 'woofunnels-aero-checkout' ) . '</div>',
						'priority' => 20,
					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_fs' ]                 = [
						'type'            => 'wfacp-responsive-font',
						'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
						'default'         => [
							'desktop' => 25,
							'tablet'  => 25,
							'mobile'  => 22,
						],
						'input_attrs'     => [
							'step' => 1,
							'min'  => 12,
							'max'  => 40,
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
								'elem'       => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal'   => true,
								'responsive' => true,
								'type'       => 'css',
								'prop'       => [ 'font-size' ],
								'elem'       => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],

						],

						'priority' => 20,
					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_top_bottom_padding' ] = [
						'type'            => 'number',
						'label'           => __( 'Padding Top Bottom', 'woofunnels-aero-checkout' ),
						'default'         => 11,
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-top' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-bottom' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-top' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-bottom' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],

						],

					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_left_right_padding' ] = [
						'type'            => 'number',
						'label'           => __( 'Padding Left Right', 'woofunnels-aero-checkout' ),
						'default'         => 11,
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-right' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-left' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],

							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-right' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'padding-left' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],

						],

					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_border_radius' ]      = [
						'type'            => 'number',
						'label'           => __( 'Border Radius', 'woofunnels-aero-checkout' ),
						'default'         => 11,
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'border-radius' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'border-radius' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],
						],

					];


					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_btn_font_weight' ] = [
						'type'            => 'radio-buttonset',
						'label'           => __( 'Font Weight', 'woofunnels-aero-checkout' ),
						'default'         => 'bold',
						'choices'         => [
							'bold'   => 'Bold',
							'normal' => 'Normal',
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'font-weight' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'font-weight' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key,
							],
						],

					];


					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_width' ] = [
						'type'            => 'radio-buttonset',
						'label'           => __( 'Width', 'woofunnels-aero-checkout' ),
						'default'         => 'initial',
						'choices'         => [
							'100%'    => 'Full Width',
							'initial' => 'Normal',
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'width' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'width' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key,
							],
						],

					];


					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_talign' ] = [
						'type'    => 'radio-buttonset',
						'label'   => __( 'Alignment', 'woofunnels-aero-checkout' ),
						'default' => 'center',
						'choices' => [
							'left'   => 'Left',
							'center' => 'Center',
							'right'  => 'Right',
						],

						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'text-align' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .' . $btn_parent_class_key,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'text-align' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .' . $next_btn_parent_class_key,
							],
							[
								'type'   => 'add_class',
								'direct' => 'true',
								'remove' => [ 'left', 'center', 'right' ],
								'elem'   => 'body .wfacp_main_form .woocommerce-checkout .' . $btn_parent_class_key,
							],
							[
								'type'   => 'add_class',
								'direct' => 'true',
								'remove' => [ 'left', 'center', 'right' ],
								'elem'   => 'body .wfacp_main_form .woocommerce-checkout .' . $next_btn_parent_class_key,
							],

						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_btn_' . $svalue . '_width',
								'operator' => '=',
								'value'    => 'initial',
							],
						],

					];


				$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_make_button_sticky_on_mobile' ] = [
					'type'     => 'radio-buttonset',
					'label'    => __( 'Sticky on Mobile', 'woofunnels-aero-checkout' ),
					'default'  => 'yes_sticky',
					'choices'  => [
						'yes_sticky' => __( 'Yes', 'woofunnels-aero-checkout' ),
						'no_sticky'  => __( 'No', 'woofunnels-aero-checkout' ),
					],
					'priority' => 20,

					];


					$form_panel['wfacp_form']['sections']['section']['fields']['ct_button_colors'] = [
						'type'     => 'custom',
						'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Color', 'woofunnels-aero-checkout' ) . '</div>',
						'priority' => 20,
					];

					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . "_color_type" ] = [
						'type'      => 'radio-buttonset',
						'label'     => __( 'Button', 'woofunnels-aero-checkout' ),
						'default'   => 'normal',
						'choices'   => [
							'normal' => 'Normal',
							'hover'  => 'Hover',
						],
						'priority'  => 20,
						'transport' => 'postMessage',
					];

					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_bg_color' ]         = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
						'default'         => '#414349',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'background-color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'background-color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],
						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_color_type',
								'operator' => '=',
								'value'    => 'normal',
							],
						],

					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_text_color' ]       = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Label', 'woofunnels-aero-checkout' ),
						'default'         => '#414349',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id,
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
							],
						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_color_type',
								'operator' => '=',
								'value'    => 'normal',
							],
						],

					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_bg_hover_color' ]   = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Background ', 'woofunnels-aero-checkout' ),
						'default'         => '#414349',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'background-color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . ':hover',
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'background-color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class . ':hover',
							],
						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_color_type',
								'operator' => '=',
								'value'    => 'hover',
							],
						],


					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_' . $svalue . '_text_hover_color' ] = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Label', 'woofunnels-aero-checkout' ),
						'default'         => '#414349',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . ':hover',
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class . ':hover',
							],
						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_color_type',
								'operator' => '=',
								'value'    => 'hover',
							],
						],

					];

					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_icon_color' ]      = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Icon Color', 'woofunnels-aero-checkout' ),
						'default'         => '',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . ":before",
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class . ":before",
							],
						],


					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_icon_text_color' ] = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Sub Text Color', 'woofunnels-aero-checkout' ),
						'default'         => '',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 20,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . ":after",
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class . ":after",
							],
						],


					];

					$form_panel['wfacp_form']['sections']['section']['fields']['ct_other_text_heading'] = [
						'type'     => 'custom',
						'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Additional Text', 'woofunnels-aero-checkout' ) . '</div>',
						'priority' => 20,
					];


					$form_panel['wfacp_form']['sections']['section']['fields']['text_below_placeorder_btn'] = [
						'type'          => 'textarea',
						'label'         => __( 'Text Below Place Order Button', 'woofunnels-aero-checkout' ),
						'default'       => esc_attr__( 'We Respect Your Privacy & Information', 'woofunnels-aero-checkout' ),
						'priority'      => 20,
						'description'   => '',
						'transport'     => 'postMessage',
						'wfacp_partial' => [
							'container_inclusive' => false,
							'elem'                => '.wfacp_main_form .wfacp-payment-dec',
						],
					];


					$custom_arr[] = [
						$selected_template_slug . '_btn_' . $color_key . '_bg_color'         => [
							[
								'type'   => 'background-color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . $classAdd,
								'device' => 'desktop',
							],
							[
								'type'   => 'background-color',
								'class'  => 'body #wfacp_qr_model_wrap .wfacp_qr_wrap .button',
								'device' => 'desktop',
							],

						],
						$selected_template_slug . '_btn_' . $color_key . '_text_color'       => [
							[
								'type'   => 'color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . $classAdd,
								'device' => 'desktop',
							],
							[
								'type'   => 'color',
								'class'  => 'body  #wfacp_qr_model_wrap .wfacp_qr_wrap .button',
								'device' => 'desktop',
							],

						],
						$selected_template_slug . '_btn_' . $color_key . '_bg_hover_color'   => [
							[
								'type'   => 'background-color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . $classAdd . ':hover',
								'device' => 'desktop',
							],
							[
								'type'   => 'background-color',
								'class'  => 'body  #wfacp_qr_model_wrap .wfacp_qr_wrap .button:hover',
								'device' => 'desktop',
							],

						],
						$selected_template_slug . '_btn_' . $color_key . '_text_hover_color' => [
							[
								'type'   => 'color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . $st_id . $classAdd . ':hover',
								'device' => 'desktop',
							],
							[
								'type'   => 'color',
								'class'  => 'body  #wfacp_qr_model_wrap .wfacp_qr_wrap .button:hover',
								'device' => 'desktop',
							],

						],


						$selected_template_slug . '_btn_icon_color'      => [
							[
								'type'   => 'color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . ':before',
								'device' => 'desktop',
							],
						],
						$selected_template_slug . '_btn_icon_text_color' => [
							[
								'type'   => 'color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $btn_class_key . ':after',
								'device' => 'desktop',
							],
						],

					];

				} elseif ( $svalue == 'back' ) {

					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_backlink_color_type' ]       = [
						'type'      => 'radio-buttonset',
						'label'     => __( 'Back Link', 'woofunnels-aero-checkout' ),
						'default'   => 'normal',
						'choices'   => [
							'normal' => 'Normal',
							'hover'  => 'Hover',
						],
						'priority'  => 22,
						'transport' => 'postMessage',
					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_back_text_color' ]       = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Normal Color', 'woofunnels-aero-checkout' ),
						'default'         => '#9e9e9e',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 22,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .btm_btn_sec .wfacp-back-btn-wrap button.button.button-primary.wfacp_back_page_button',
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .place_order_back_btn a',
							],
						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_backlink_color_type',
								'operator' => '=',
								'value'    => 'normal',
							],
						],

					];
					$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_btn_back_text_hover_color' ] = [
						'type'            => 'color',
						'label'           => esc_attr__( 'Hover Color', 'woofunnels-aero-checkout' ),
						'default'         => '#686868',
						'choices'         => [
							'alpha' => true,
						],
						'priority'        => 22,
						'transport'       => 'postMessage',
						'wfacp_transport' => [
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .btm_btn_sec .wfacp-back-btn-wrap button.button.button-primary.wfacp_back_page_button:hover',
							],
							[
								'internal' => true,
								'type'     => 'css',
								'prop'     => [ 'color' ],
								'elem'     => 'body .wfacp_main_form .woocommerce-checkout .place_order_back_btn a:hover',
							],
						],
						'active_callback' => [
							[
								'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_backlink_color_type',
								'operator' => '=',
								'value'    => 'hover',
							],
						],

					];

				} else {

					if ( $svalue == 'back' ) {
						continue;
					}
					$custom_arr[] = [
						$selected_template_slug . '_btn_' . $color_key . '_bg_color'         => [
							[
								'type'   => 'background-color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.wfacp_next_page_button',
								'device' => 'desktop',
							],
							[
								'type'   => 'background-color',
								'class'  => '#wfacp_qr_model_wrap .wfacp_qr_wrap .button',
								'device' => 'desktop',
							],
							[
								'type'   => 'background-color',
								'class'  => '.wfacp_payment #ppcp-hosted-fields .button',
								'device' => 'desktop',
							],
						],
						$selected_template_slug . '_btn_' . $color_key . '_text_color'       => [
							[
								'type'   => 'color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class,
								'device' => 'desktop',
							],
							[
								'type'   => 'color',
								'class'  => 'body #wfacp_qr_model_wrap .wfacp_qr_wrap .button',
								'device' => 'desktop',
							],
							[
								'type'   => 'color',
								'class'  => '.wfacp_payment #ppcp-hosted-fields .button',
								'device' => 'desktop',
							],
						],
						$selected_template_slug . '_btn_' . $color_key . '_bg_hover_color'   => [
							[
								'type'   => 'background-color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class . ':hover',
								'device' => 'desktop',
							],
							[
								'type'   => 'background-color',
								'class'  => '#wfacp_qr_model_wrap .wfacp_qr_wrap .button:hover',
								'device' => 'desktop',
							],
							[
								'type'   => 'background-color',
								'class'  => '.wfacp_payment #ppcp-hosted-fields .button:hover',
								'device' => 'desktop',
							],
						],
						$selected_template_slug . '_btn_' . $color_key . '_text_hover_color' => [
							[
								'type'   => 'color',
								'class'  => 'body .wfacp_main_form .woocommerce-checkout .button.' . $next_btn_class . ':hover',
								'device' => 'desktop',
							],
							[
								'type'   => 'color',
								'class'  => '#wfacp_qr_model_wrap .wfacp_qr_wrap .button:hover',
								'device' => 'desktop',
							],
							[
								'type'   => 'color',
								'class'  => '.wfacp_payment #ppcp-hosted-fields .button:hover',
								'device' => 'desktop',
							],
						],


					];
				}
			}

			$outer_counter = 0;
			$inner_counter = 0;

			$shipping_arr = [];
			if ( isset( $section_fields['shipping'] ) ) {
				$shipping_arr             = [
					'shipping' => [],
				];
				$shipping_arr['shipping'] = $section_fields['shipping'];
				unset( $section_fields['shipping'] );
			}

			$template_slug = $this->template_common->get_template_slug();
			$template_slug = sanitize_title( $template_slug );

			$otherClass = [];

			if ( is_array( $fieldset ) && count( $fieldset ) > 0 ) {
				foreach ( $fieldset as $key => $page_steps ) {


					if ( empty( $page_steps ) ) {
						continue;
					}


					foreach ( $page_steps as $p_index => $sections ) {

						if ( empty( $sections ) || empty( $sections['fields'] ) ) {
							continue;
						}


						$temp_sec_name = $sections['name'];

						$form_panel['wfacp_form']['sections']['form_fields']['fields'][ $p_index . "_" . $inner_counter ] = array(
							'type'     => 'custom',
							'default'  => sprintf( '<div class="options-title-divider">%s</div>', esc_html__( ucfirst( $temp_sec_name ) ) ),
							'priority' => 25,
						);

						$form_panel['wfacp_form']['sections']['form_fields']['fields']['ct_advanced_class'] = [
							'type'     => 'custom',
							'default'  => '<div class="options-title-divider">' . esc_html__( 'Custom Classes', 'woofunnels-aero-checkout' ) . '</div>',
							'priority' => 50,
						];


						$forms_field = $sections['fields'];


						$cls                        = [
							'wfacp-col-full'       => __( 'Full', 'woofunnel-aero-checkout' ),
							'wfacp-col-left-half'  => __( 'One Half', 'woofunnel-aero-checkout' ),
							'wfacp-col-left-third' => __( 'One Third', 'woofunnel-aero-checkout' ),
							'wfacp-col-two-third'  => __( 'Two Third', 'woofunnel-aero-checkout' ),
						];
						$inner_section_fields_count = 0;
						foreach ( $forms_field as $index => $field_value ) {

							if ( ! isset( $field_value['id'] ) ) {
								continue;
							}


							if ( isset( $field_value['type'] ) ) {
								if ( 'wfacp_html' == $field_value['type'] || 'product' == $field_value['type'] ) {
									continue;
								}
								if ( 'wfacp_end_divider' === $field_value['type'] || 'wfacp_start_divider' === $field_value['type'] ) {
									continue;
								}

								if ( $field_value['type'] != 'text' ) {
									$field_value['type'] = "text";
								}
							}


							if ( $field_value['id'] == 'billing_same_as_shipping' || $field_value['id'] == 'shipping_same_as_billing' ) {
								continue;
							}


							$slug  = '1_' . $template_slug . '_' . strtolower( $field_value['id'] );
							$slug1 = '1_' . $template_slug . '_' . strtolower( $field_value['id'] ) . "_other_classes";


							$defaultDes = '';
							$label      = __( 'Label', 'woofunnels-aero-checkout' );
							if ( isset( $field_value['default'] ) ) {
								$defaultDes = $field_value['default'];
							}
							if ( isset( $field_value['label'] ) && $field_value['label'] != '' ) {
								$label = $field_value['label'];
							} elseif ( isset( $field_value['data_label'] ) && $field_value['data_label'] != '' ) {
								$label = $field_value['data_label'];
							}

							$tempArray = array(
								'type'            => 'select',
								'label'           => $label,
								'priority'        => 25,
								'default'         => esc_attr__( apply_filters( 'wfacp_default_field', $defaultDes, $field_value['id'], $slug ), 'woofunnels-aero-checkout' ),
								'transport'       => 'postMessage',
								'choices'         => apply_filters( 'wfacp_field_default_classes', $cls ),
								'wfacp_transport' => [
									[
										'type'   => 'add_class',
										'remove' => [
											'wfacp-col-full',
											'wfacp-col-left-half',
											'wfacp-col-left-third',
											'wfacp-col-two-third',
										],
										'elem'   => '#' . $field_value['id'],
									],
								],
							);


							$tempArray1 = array(
								'type'            => 'text',
								'label'           => $label,
								'priority'        => 51,
								'default'         => '',
								'transport'       => 'postMessage',
								'input_attrs'     => [
									'placeholder' => __( 'Custom Class', 'woofunnels-aero-checkout' ),
								],
								'wfacp_transport' => [
									[
										'type' => 'add_class',
										'elem' => '#' . $field_value['id'],
									],
								],
							);

							$form_panel['wfacp_form']['sections']['form_fields']['fields'][ $slug ]  = $tempArray;
							$form_panel['wfacp_form']['sections']['form_fields']['fields'][ $slug1 ] = $tempArray1;
							$inner_section_fields_count ++;
							$inner_counter ++;
						}
						if ( 0 == $inner_section_fields_count ) {
							unset( $form_panel['wfacp_form']['sections']['form_fields']['fields'][ $p_index . "_" . $inner_counter ] );
							//unset($form_panel['wfacp_form']['sections']['form_fields']['fields'][ $p_index . "_" . $inner_counter ]);
						}

					}


					$outer_counter ++;
				}
			}

			$section_data_keys['colors'] = [
				$selected_template_slug . '_sec_heading_color'           => [
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp_section_title',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .ia_subscription_items h3',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form #woocommerce_eu_vat_compliance #woocommerce_eu_vat_compliance_vat_number h3',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_sec_sub_heading_color'       => [
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp-comm-title h4',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form #woocommerce_eu_vat_compliance #woocommerce_eu_vat_compliance_vat_number h3 + p',
						'device' => 'desktop',
					],

				],
				$selected_template_slug . '_field_style_color'           => [
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form label.wfacp-form-control-label',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp_custom_field_radio_wrap label',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp_custom_field_cls span',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp_custom_field_cls label',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form #vat_number-description',
						'device' => 'desktop',
					],

					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp-row .automatewoo-birthday-section > label',
						'device' => 'desktop',
					],

				],
				$selected_template_slug . '_validation_color'            => [

					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .woocommerce-error',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .woocommerce-error li',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .woocommerce-error li strong',
						'device' => 'desktop',
					],

				],
				$selected_template_slug . '_sec_bg_color'                => [
					[
						'type'   => 'background-color',
						'class'  => '.wfacp_main_form .wfacp-comm-title',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_additional_bg_color'         => [
					[
						'type'   => 'background-color',
						'class'  => '.wfacp_main_form .wfacp-payment-dec',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_additional_text_color'       => [
					[
						'type'   => 'color',
						'class'  => '.wfacp_main_form .wfacp-payment-dec',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_breadcrumb_text_color'       => [
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_steps_sec ul li a',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_breadcrumb_text_hover_color' => [
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_steps_sec ul li a:hover',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_order_total_bg_color'        => [
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .wfacp_order_total_field table.wfacp_order_total_wrap',
						'device' => 'desktop',
					],


				],
				$selected_template_slug . '_order_total_text_color'      => [
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .wfacp_order_total_field table.wfacp_order_total_wrap td',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_order_total_border_color'    => [
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form.woocommerce .wfacp_order_total_field table.wfacp_order_total_wrap',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_field_border_color'          => [
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .select2-container--default.select2-container--focus .select2-selection--multiple',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .select2-container--default .select2-selection--multiple',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => '#et-boc .et-l span.select2-selection.select2-selection--multiple',
						'device' => 'desktop',
					],
				],
				$selected_template_slug . '_field_focus_color'           => [
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-email) input[type=email]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=password]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=search]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=tel]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=text]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=number]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) input[type=url]:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) textarea:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) select:focus',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-color',
						'class'  => 'body .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single:focus',
						'device' => 'desktop',
					],


				],
				$selected_template_slug . '_field_input_color'           => [
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=email]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=password]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=search]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=tel]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=text]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=url]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row textarea',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row select',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row .woocommerce-input-wrapper .select2-container .select2-selection--single',
						'device' => 'desktop',
					],

					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .form-row input[type=number]',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .select2-container .select2-selection--single .select2-selection__rendered',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .woocommerce-checkout select#join_referral_program',
						'device' => 'desktop',
					],


				],
				$selected_template_slug . '_field_input_bg_color'        => [
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=email]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=password]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=search]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=tel]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=text]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=url]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row textarea',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row select',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row .woocommerce-input-wrapper .select2-container .select2-selection--single',
						'device' => 'desktop',
					],

					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .form-row input[type=number]',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .select2-container .select2-selection--single .select2-selection__rendered',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp_main_form .woocommerce-checkout select#join_referral_program',
						'device' => 'desktop',
					],
					[
						'type'   => 'background-color',
						'class'  => 'body .wfacp-inside .form-row > label.wfacp-form-control-label:not(.checkbox)',
						'device' => 'desktop',
					],


				],
				$selected_template_slug . '_field_border_width'          => [
					[
						'type'   => 'border-width',
						'class'  => 'body .wfacp_main_form .select2-container--default.select2-container--focus .select2-selection--multiple',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-width',
						'class'  => 'body .wfacp_main_form .select2-container--default .select2-selection--multiple',
						'device' => 'desktop',
					],
					[
						'type'   => 'border-width',
						'class'  => '#et-boc .et-l span.select2-selection.select2-selection--multiple',
						'device' => 'desktop',
					],
				],

			];

			if ( $num_of_steps > 1 ) {
				$section_data_keys['colors'][ $selected_template_slug . '_btn_back_text_color' ]       = [
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .btm_btn_sec .wfacp-back-btn-wrap button.button.button-primary.wfacp_back_page_button',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .woocommerce-checkout .place_order_back_btn a',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .wfacp-back-btn-wrap a',
						'device' => 'desktop',
					],
				];
				$section_data_keys['colors'][ $selected_template_slug . '_btn_back_text_hover_color' ] = [
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .btm_btn_sec .wfacp-back-btn-wrap button.button.button-primary.wfacp_back_page_button:hover',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .woocommerce-checkout .place_order_back_btn a:hover',
						'device' => 'desktop',
					],
					[
						'type'   => 'color',
						'class'  => 'body .wfacp_main_form .wfacp-back-btn-wrap a:hover',
						'device' => 'desktop',
					],
				];

			}


			$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_form_content_color' ]            = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Form Content', 'woofunnels-aero-checkout' ),
				'default'         => '#737373',
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 21,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form  .woocommerce-form-login-toggle .woocommerce-info',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form form.woocommerce-form.woocommerce-form-login.login p',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form label.woocommerce-form__label span',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form .wfacp_checkbox_field label',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_checkbox_field span',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table td.product-name',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table .product-name',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table .product-name dl *',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table td.product-total',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tfoot tr th',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tfoot tr td',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr.order-total th',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr.order-total td',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form #shipping_calculator_field.wfacp_shipping_options label',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .wfacp_shipping_table tr.shipping td p',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .wfacp-product-switch-title div',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .woocommerce-privacy-policy-text p',
					],


					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .wfacp_shipping_options ul li p',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .shop_table .wfacp-product-switch-title div',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .woocommerce-info .message-container',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form #wc_checkout_add_ons .description',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form ol li',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form ul li',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form ul li label',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .woocommerce-checkout-review-order h3',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .aw_addon_wrap label',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => '.wfacp_shipping_table ul#shipping_method label',
					],


					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form table tr th',
					],

					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form table tr td',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'table.shop_table.woocommerce-checkout-review-order-table thead tr th',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr span.amount',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr span.amount bdi',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr span.woocommerce-Price-currencySymbol',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form table.shop_table tr.order-total td strong>span',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body table.shop_table tr.order-total td strong>span>span',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form ul li',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form ul li span',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form .wfacp_shipping_table ul li span',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .woocommerce-checkout #payment ul.payment_methods label',
					],


				],

			];
			$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_form_content_link_color_type' ]  = [
				'type'      => 'radio-buttonset',
				'label'     => __( 'Form Links', 'woofunnels-aero-checkout' ),
				'default'   => 'normal',
				'choices'   => [
					'normal' => 'Normal',
					'hover'  => 'Hover',
				],
				'priority'  => 22,
				'transport' => 'postMessage',
			];
			$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_form_content_link_color' ]       = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Normal Color', 'woofunnels-aero-checkout' ),
				'default'         => '#dd7575',
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 22,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form .woocommerce-form-login-toggle .woocommerce-info a.showlogin',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form p.lost_password a',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_switcher_description a.wfacp_qv-button',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form a',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_woocommerce_form_coupon .wfacp-coupon-section .woocommerce-info .wfacp_showcoupon',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form.woocommerce #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_switcher_description a.wfacp_qv-button',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body #et-boc #wfacp-e-form .wfacp-form a',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_html_widget a',
					],
				],
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_form_content_link_color_type',
						'operator' => '=',
						'value'    => 'normal',
					],
				],

			];
			$form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_form_content_link_hover_color' ] = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Hover Color', 'woofunnels-aero-checkout' ),
				'default'         => '#965d5d',
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 22,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form .woocommerce-form-login-toggle .woocommerce-info a.showlogin:hover',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form p.lost_password a:hover',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_switcher_description a.wfacp_qv-button:hover',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp-form a:hover',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_woocommerce_form_coupon .wfacp-coupon-section .woocommerce-info .wfacp_showcoupon:hover',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body .wfacp_main_form.woocommerce #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_switcher_description a.wfacp_qv-button:hover',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body #et-boc #wfacp-e-form .wfacp-form a:hover',
					],

				],
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_form_content_link_color_type',
						'operator' => '=',
						'value'    => 'hover',
					],
				],

			];
			$section_data_keys['colors'][ $selected_template_slug . '_form_content_color' ]                                          = [
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form  .woocommerce-form-login-toggle .woocommerce-info',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form form.woocommerce-form.woocommerce-form-login.login p',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form label.woocommerce-form__label span',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form .wfacp_checkbox_field label',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_checkbox_field span',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table td.product-name',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table .product-name',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table .product-name dl *',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table td.product-total',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tfoot tr th',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tfoot tr td',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr.order-total th',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr.order-total td',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form #shipping_calculator_field.wfacp_shipping_options label',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .wfacp_shipping_table tr.shipping td p',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .wfacp-product-switch-title div',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .woocommerce-privacy-policy-text p',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .wfacp_shipping_options ul li p',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .shop_table .wfacp-product-switch-title div',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .woocommerce-info .message-container',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form #wc_checkout_add_ons .description',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form ol li',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form ul li',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form ul li label',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .woocommerce-checkout-review-order h3',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .aw_addon_wrap label',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => '.wfacp_shipping_table ul#shipping_method label',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form table tr th',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form table tr td dl *',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form table tr td',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'table.shop_table.woocommerce-checkout-review-order-table thead tr th',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'device' => 'desktop',
					'class'  => 'body .wfacp-form table.shop_table tr.order-total td strong>span',
				],
				[
					'type'   => 'color',
					'device' => 'desktop',
					'class'  => 'body table.shop_table tr.order-total td strong>span>span',
				],
				[
					'type'   => 'color',
					'device' => 'desktop',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr span.amount',
				],
				[
					'type'   => 'color',
					'device' => 'desktop',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr span.woocommerce-Price-currencySymbol',
				],
				[
					'type'   => 'color',
					'device' => 'desktop',
					'class'  => 'body .wfacp-form table.shop_table.woocommerce-checkout-review-order-table tr span.amount bdi',
				],

				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form ul li span',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form .wfacp_shipping_table ul li span',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form ul li',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .woocommerce-checkout #payment ul.payment_methods label',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .woocommerce-checkout #payment ul.payment_methods p',
					'device' => 'desktop',
				],

			];
			$section_data_keys['colors'][ $selected_template_slug . '_form_content_link_color' ]                                     = [
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form .woocommerce-form-login-toggle .woocommerce-info a.showlogin',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form p.lost_password a',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_switcher_description a.wfacp_qv-button',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form a',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_woocommerce_form_coupon .wfacp-coupon-section .woocommerce-info .wfacp_showcoupon',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body #et-boc #wfacp-e-form .wfacp-form a',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_html_widget a',
					'device' => 'desktop',
				],


			];
			$section_data_keys['colors'][ $selected_template_slug . '_form_content_link_hover_color' ]                               = [
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form .woocommerce-form-login-toggle .woocommerce-info a.showlogin:hover',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form p.lost_password a:hover',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_main_form #product_switching_field .wfacp_product_switcher_col_2 .wfacp_product_switcher_description a.wfacp_qv-button:hover',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp-form a:hover',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body .wfacp_woocommerce_form_coupon .wfacp-coupon-section .woocommerce-info .wfacp_showcoupon:hover',
					'device' => 'desktop',
				],
				[
					'type'   => 'color',
					'class'  => 'body #et-boc #wfacp-e-form .wfacp-form a:hover',
					'device' => 'desktop',
				],

			];


			if ( is_array( $custom_arr ) && count( $custom_arr ) > 0 ) {
				foreach ( $custom_arr as $key => $value ) {
					if ( is_array( $value ) && count( $value ) > 0 ) {
						foreach ( $value as $key1 => $value1 ) {
							$section_data_keys['colors'][ $key1 ] = $value1;
						}
					}
				}
			}

			if ( $num_of_steps <= 1 ) {
				unset( $form_panel['wfacp_form']['sections']['section']['fields']['ct_steps_colors'] );
				unset( $form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_color_type' ] );
				unset( $form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_text_color' ] );
				unset( $form_panel['wfacp_form']['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_text_hover_color' ] );
			}


			$this->template_common->set_section_keys_data( 'wfacp_form', $section_data_keys );

			$form_panel = apply_filters( 'wfacp_checkout_form_customizer_field', $form_panel, $this );

			$form_panel['wfacp_form'] = apply_filters( 'wfacp_layout_default_setting', $form_panel['wfacp_form'], 'wfacp_form' );

			return $form_panel;
		}
	}
}