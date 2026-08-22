<?php

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'Elementor_WFFN_Pro_Optin_Popup_Widget' ) ) {
	/**
	 * Class Elementor_WFFN_Pro_Optin_Popup_Widget
	 */
	#[AllowDynamicProperties]
	class Elementor_WFFN_Pro_Optin_Popup_Widget extends Elementor_WFFN_Optin_Form_Widget {

		/**
		 * Get widget name.
		 *
		 * @return string Widget name.
		 */
		public function get_name() {
			return 'wffn-optin-popup';
		}

		/**
		 * Get widget title.
		 *
		 * @return string Widget title.
		 */
		public function get_title() {
			return __( 'Optin Popup', 'funnel-builder-powerpack' );
		}

		/**
		 * Get widget icon.
		 *
		 * @return string Widget icon.
		 */
		public function get_icon() {
			return 'eicon-form-horizontal';
		}

		/**
		 * Get widget categories.
		 *
		 * Retrieve the list of categories the widget belongs to.
		 *
		 * @access public
		 *
		 * @return array Widget categories.
		 */
		public function get_categories() {
			return array( 'wffn-flex' );
		}

		/**
		 * The optin popup renders against live request state and must never have its markup baked
		 * into Elementor's element cache.
		 *
		 * Returning true makes Elementor store a `[elementor-element k=... data=...]` placeholder
		 * in `_elementor_element_cache` instead of the rendered HTML; the widget is then
		 * re-rendered on every request via do_shortcode().
		 *
		 * Element_Base already defaults to true, but we declare it explicitly so the behaviour is
		 * intentional and survives any change to that default.
		 *
		 * @return bool
		 */
		protected function is_dynamic_content(): bool {
			return true;
		}


		/**
		 * Register widget controls.
		 *
		 * Adds different input fields to allow the user to change and customize the widget settings.
		 *
		 * @access protected
		 */
		public function register_controls() {

			$this->start_controls_section(
				'popup_progress_bar',
				array(
					'label' => __( 'Progress Bar', 'funnel-builder-powerpack' ),
				)
			);

			$this->add_control(
				'progress_bar_sec',
				array(
					'label'     => __( 'Progress Bar', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'popup_bar_pp',
				array(
					'label'        => __( 'Enable', 'funnel-builder-powerpack' ),
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => __( 'Enable', 'funnel-builder-powerpack' ),
					'label_off'    => __( 'Disable', 'funnel-builder-powerpack' ),
					'return_value' => 'enable',
					'default'      => 'enable',
				)
			);
			$this->add_control(
				'popup_bar_text_position',
				array(
					'label'        => __( 'Show progress text above the bar', 'funnel-builder-powerpack' ),
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => __( 'Enable', 'funnel-builder-powerpack' ),
					'label_off'    => __( 'Disable', 'funnel-builder-powerpack' ),
					'return_value' => 'enable',
					'default'      => '',
					'selectors'    => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap .bwf_pp_bar .pp-bar-text' => 'display:none;',
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .pp-bar-text-wrapper'                      => 'display:block;',
					),
					'condition'    => array(
						'popup_bar_pp' => 'enable',
					),
				)
			);

			$this->add_control(
				'popup_bar_animation',
				array(
					'label'        => __( 'Animation', 'funnel-builder-powerpack' ),
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'funnel-builder-powerpack' ),
					'label_off'    => __( 'No', 'funnel-builder-powerpack' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'condition'    => array(
						'popup_bar_pp' => 'enable',
					),
				)
			);

			$this->add_control(
				'popup_bar_text',
				array(
					'label'       => __( 'Text', 'funnel-builder-powerpack' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => __( '75% Complete', 'funnel-builder-powerpack' ),
					'label_block' => true,
					'condition'   => array(
						'popup_bar_pp' => 'enable',
					),
				)
			);

			$this->end_controls_section();

			$this->start_controls_section(
				'popup_progress_bar_heading',
				array(
					'label' => __( 'Heading', 'funnel-builder-powerpack' ),
				)
			);

			$this->add_control(
				'progress_bar_head',
				array(
					'label' => __( 'Text', 'funnel-builder-powerpack' ),
					'type'  => Controls_Manager::HEADING,
				)
			);

			$this->add_control(
				'popup_heading',
				array(
					'label'       => __( 'Heading', 'funnel-builder-powerpack' ),
					'type'        => Controls_Manager::TEXTAREA,
					'default'     => __( "You're just one step away!", 'funnel-builder-powerpack' ),
					'label_block' => true,
				)
			);

			$this->add_control(
				'popup_sub_heading',
				array(
					'label'       => __( 'Sub Heading', 'funnel-builder-powerpack' ),
					'type'        => Controls_Manager::TEXTAREA,
					'default'     => __( "Enter your details below and we'll get you signed up", 'funnel-builder-powerpack' ),
					'label_block' => true,
				)
			);

			$this->end_controls_section();

			parent::register_sections();

			/* Register Optin click pop up Button*/
			$this->add_tab( __( 'Button', 'funnel-builder-powerpack' ) );
			$this->add_text( 'btn_text', __( 'Title', 'funnel-builder-powerpack' ), __( 'Signup Now', 'funnel-builder-powerpack' ) );
			$this->add_text( 'btn_subheading_text', 'Subtitle', '' );
			$this->add_text_alignments( 'btn_alignment', array( '{{WRAPPER}} #bwf-custom-button-wrap' ), 'Button Alignment', array(), 'center' );
			$this->add_text_alignments( 'btn_text_alignment', array( '{{WRAPPER}} #bwf-custom-button-wrap a' ), __( 'Text Alignment', 'funnel-builder-powerpack' ) );

			$this->add_heading( __( 'Button Icon', 'funnel-builder-powerpack' ) );
			$this->add_icon( 'btn_icon', array( '{{WRAPPER}} #bwf-custom-button-wrap' ) );
			$this->add_icon_position( 'btn_icon_position', array( '{{WRAPPER}} #bwf-custom-button-wrap a i' ), '', 'left' );

			$this->close_controls_tab();
			$this->close_controls_tabs();

			$this->end_tab();
			/* End Optin click pop up Button*/

			$this->start_controls_section(
				'popup_section',
				array(
					'label' => __( 'Popup', 'funnel-builder-powerpack' ),
				)
			);

			$this->add_control(
				'popup_open_animation',
				array(
					'label'   => esc_html__( 'Effect' ),
					'type'    => Controls_Manager::SELECT,
					'options' => array(
						'fade'       => __( 'Fade', 'funnel-builder-powerpack' ),
						'slide-up'   => __( 'Slide Up', 'funnel-builder-powerpack' ),
						'slide-down' => __( 'Slide Down', 'funnel-builder-powerpack' ),
					),
					'default' => 'fade',
				)
			);
			$this->add_heading( __( 'Text After Button', 'funnel-builder-powerpack' ) );
			$this->add_control(
				'popup_footer_text',
				array(
					'label'       => __( 'Text', 'funnel-builder-powerpack' ),
					'type'        => Controls_Manager::TEXTAREA,
					'default'     => __( 'Your Information is 100% Secure', 'funnel-builder-powerpack' ),
					'label_block' => true,
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_popover_style',
				array(
					'label' => __( 'Progress Bar', 'funnel-builder-powerpack' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_control(
				'popup_open_effect',
				array(
					'label'     => __( 'Size', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'popup_bar_width',
				array(
					'label'     => __( 'Width', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::SLIDER,
					'range'     => array(
						'%' => array(
							'min' => 1,
							'max' => 100,
						),
					),
					'default'   => array(
						'size' => 75,
						'unit' => '%',
					),
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap .bwf_pp_bar' => 'width: {{SIZE}}{{UNIT}};',
					),
					'condition' => array(
						'popup_bar_pp' => 'enable',
					),
				)
			);
			$this->add_control(
				'popup_bar_heights',
				array(
					'label'     => __( 'Height', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min' => 1,
							'max' => 100,
						),
					),
					'default'   => array(
						'size' => 40,
						'unit' => 'px',
					),
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap' => 'height: {{SIZE}}{{UNIT}};',
					),
					'condition' => array(
						'popup_bar_pp' => 'enable',
					),
				)
			);

			$this->add_control(
				'popup_bar_inner_gaps',
				array(
					'label'     => __( 'Padding', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min' => 0,
							'max' => 100,
						),
					),
					'default'   => array(
						'size' => 4,
						'unit' => 'px',
					),
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap' => 'padding: {{SIZE}}{{UNIT}};',
					),
					'condition' => array(
						'popup_bar_pp' => 'enable',
					),
				)
			);

			$this->add_control(
				'progress_bar_heading',
				array(
					'label'     => __( 'Styling', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'progress_bar_typography',
					'selector' => '.elementor-widget-wffn-optin-popup .bwf_pp_overlay .pp-bar-text',
					'global'   => array(
						'default' => Global_Typography::TYPOGRAPHY_TEXT,
					),
				)
			);

			$this->add_control(
				'progress_text_color',
				array(
					'label'     => __( 'Text', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#FFFFFF',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .pp-bar-text' => 'color: {{VALUE}};',
					),
				)
			);

			$this->add_control(
				'progress_color',
				array(
					'label'     => __( 'Color', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar' => 'background-color: {{VALUE}};',
					),
				)
			);

			$this->add_control(
				'progress_background_color',
				array(
					'label'     => __( 'Background', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#efefef',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap' => 'background-color: {{VALUE}};',
					),
				)
			);

			$this->end_controls_section();

			$this->start_controls_section(
				'popup_heading_style',
				array(
					'label' => __( 'Heading', 'funnel-builder-powerpack' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);

			// Popup Heading
			$this->add_control(
				'popup_heading_head',
				array(
					'label'     => __( 'Heading', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'           => 'popup_heading_typography',
					'selector'       => '.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_head',
					'global'         => array(
						'default' => Global_Typography::TYPOGRAPHY_TEXT,
					),
					'fields_options' => array(
						// first mimic the click on Typography edit icon
						'typography'  => array( 'default' => 'yes' ),
						// then redifine the Elementor defaults
						'font_family' => array( 'default' => 'Open Sans' ),
						'font_size'   => array( 'default' => array( 'size' => 17 ) ),
						'font_weight' => array( 'default' => 400 ),
						'line_height' => array(
							'default' => array(
								'size' => 1.5,
								'unit' => 'em',
							),
						),
					),
				)
			);
			$this->add_control(
				'popup_heading_color',
				array(
					'label'     => __( 'Text', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_head' => 'color: {{VALUE}};',
					),
					'default'   => '#000000',
				)
			);

			// Popup Sub heading
			$this->add_control(
				'popup_subheading',
				array(
					'label'     => __( 'Sub-Heading', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_sub_head' => 'color: {{VALUE}};',
					),
					'default'   => '#000000',
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'           => 'popup_subheading_typography',
					'selector'       => '.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_sub_head',
					'global'         => array(
						'default' => Global_Typography::TYPOGRAPHY_TEXT,
					),
					'fields_options' => array(
						// first mimic the click on Typography edit icon
						'typography'  => array( 'default' => 'yes' ),
						// then redifine the Elementor defaults
						'font_family' => array( 'default' => 'Open Sans' ),
						'font_size'   => array( 'default' => array( 'size' => 24 ) ),
						'font_weight' => array( 'default' => 700 ),
						'line_height' => array(
							'default' => array(
								'size' => 1.5,
								'unit' => 'em',
							),
						),
					),
				)
			);
			$this->add_control(
				'popup_subheading_color',
				array(
					'label'     => __( 'Text', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_sub_head' => 'color: {{VALUE}};',
					),
				)
			);

			$this->end_controls_section();

			parent::register_styles();

			$this->start_controls_section(
				'section_buttons_style',
				array(
					'label' => __( 'Button', 'funnel-builder-powerpack' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_responsive_control(
				'btn_width',
				array(
					'label'     => __( 'Button width (in %)', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::SLIDER,
					'default'   => array(
						'size' => 30,
					),
					'range'     => array(
						'%' => array(
							'min' => 0,
							'max' => 100,
						),
					),
					'selectors' => array(
						'{{WRAPPER}} #bwf-custom-button-wrap a' => 'min-width: {{SIZE}}%;',
					),
				)
			);
			// $this->add_width( 'btn_width', '{{WRAPPER}} #bwf-custom-button-wrap a', '', [ 'width' => 30 ] );

			$this->add_controls_tabs( 'bwf_btn_tabs' );
			$this->add_controls_tab( 'bwf_btn_normal_tab', 'Normal' );
			$this->add_background_color( 'btn_bg_color', array( '{{WRAPPER}} #bwf-custom-button-wrap a' ), '#000' );
			$this->add_color( 'btn_color', array( '{{WRAPPER}} #bwf-custom-button-wrap a', '{{WRAPPER}} #bwf-custom-button-wrap .bwf_subheading' ), '#ffffff' );
			$this->close_controls_tab();
			$this->add_controls_tab( 'bwf_btn_hover_tab', 'Hover' );
			$this->add_background_color( 'btn_hover_bg_color', array( '{{WRAPPER}} #bwf-custom-button-wrap a:hover' ), '#000' );
			$this->add_color( 'btn_hover_color', array( '{{WRAPPER}} #bwf-custom-button-wrap a:hover', '{{WRAPPER}} #bwf-custom-button-wrap a:hover .bwf_subheading' ), '#ffffff' );
			$this->close_controls_tab();
			$this->close_controls_tabs();

			$this->add_heading( __( 'Typography', 'funnel-builder-powerpack' ) );
			$this->add_typography( 'btn_text_typo', '{{WRAPPER}} #bwf-custom-button-wrap .bwf_heading, {{WRAPPER}} #bwf-custom-button-wrap .bwf_icon', array(), array(), 'Heading' );

			$this->add_typography( 'btn_subheading_text_typo', '{{WRAPPER}} #bwf-custom-button-wrap .bwf_subheading', array(), array(), 'Sub Heading' );

			$this->add_heading( 'Advanced' );
			$defaults = array(
				'top'    => 5,
				'right'  => 5,
				'bottom' => 5,
				'left'   => 5,
				'unit'   => 'px',
			);
			$this->add_padding( 'btn_text_padding', '{{WRAPPER}} #bwf-custom-button-wrap a', $defaults );
			$this->add_margin( 'btn_text_margin', '{{WRAPPER}} #bwf-custom-button-wrap a', $defaults );
			$this->add_border( 'btn_text_alignment_border', '{{WRAPPER}} #bwf-custom-button-wrap a' );
			$this->add_border_shadow( 'btn_text_alignment_box_shadow', '{{WRAPPER}} #bwf-custom-button-wrap a' );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_popup_style',
				array(
					'label' => __( 'Popup', 'funnel-builder-powerpack' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_heading( __( 'Text After Button', 'funnel-builder-powerpack' ) );
			$after_button_fields_options = array(
				'typography'  => array( 'default' => 'yes' ),
				// then redefine the Elementor defaults
				'font_family' => array( 'default' => 'Open Sans' ),
				'font_size'   => array( 'default' => array( 'size' => 16 ) ),
				'font_weight' => array( 'default' => 700 ),
				'line_height' => array(
					'default' => array(
						'size' => 1,
						'unit' => 'em',
					),
				),
			);
			$this->add_typography( 'popup_footer_font_family', '{{WRAPPER}} .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer', $after_button_fields_options );
			$this->add_color( 'popup_footer_text_color', array( '{{WRAPPER}} .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer' ), '#000000' );

			$this->add_responsive_control(
				'popup_wrap_width',
				array(
					'label'     => __( 'Button width (in px)', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::SLIDER,
					'default'   => array(
						'size' => 600,
					),
					'range'     => array(
						'px' => array(
							'min'  => 0,
							'max'  => 2500,
							'step' => 5,
						),
					),
					'selectors' => array(
						'{{WRAPPER}} .bwf_pp_wrap' => 'max-width: {{SIZE}}px;',
					),
				)
			);
			$popup_defaults = array(
				'top'    => 40,
				'right'  => 40,
				'bottom' => 40,
				'left'   => 40,
				'unit'   => 'px',
			);
			$this->add_padding( 'popup_padding', '{{WRAPPER}} .bwf_pp_wrap .bwf_pp_cont', $popup_defaults );

			$this->end_controls_section();

			$this->start_controls_section(
				'popup_close_btn',
				array(
					'label' => __( 'Close Button', 'funnel-builder-powerpack' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_heading( __( 'Position', 'funnel-builder-powerpack' ) );
			$this->add_responsive_control(
				'close_button_vertical',
				array(
					'label'          => __( 'Vertical Position', 'funnel-builder-powerpack' ),
					'type'           => Controls_Manager::SLIDER,
					'size_units'     => array( 'px', '%' ),
					'range'          => array(
						'px' => array(
							'max' => 650,
							'min' => - 90,
						),
						'%'  => array(
							'max'  => 100,
							'min'  => 0,
							'step' => 0.1,
						),
					),
					'default'        => array(
						'unit' => 'px',
						'size' => '-8',
					),
					'tablet_default' => array(
						'unit' => 'px',
					),
					'mobile_default' => array(
						'unit' => 'px',
					),
					'selectors'      => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'top: {{SIZE}}{{UNIT}}',
					),
				)
			);

			$this->add_responsive_control(
				'close_button_horizontal',
				array(
					'label'          => __( 'Horizontal Position', 'funnel-builder-powerpack' ),
					'type'           => Controls_Manager::SLIDER,
					'size_units'     => array( 'px', '%' ),
					'range'          => array(
						'px' => array(
							'max' => 1000,
							'min' => - 350,
						),
						'%'  => array(
							'max'  => 100,
							'min'  => 0,
							'step' => 0.1,
						),
					),
					'default'        => array(
						'unit' => 'px',
						'size' => '-14',
					),
					'tablet_default' => array(
						'unit' => 'px',
					),
					'mobile_default' => array(
						'unit' => 'px',
					),
					'selectors'      => array(
						'body:not(.rtl) .elementor-widget-wffn-optin-popup .bwf_pp_close' => 'right: {{SIZE}}{{UNIT}}',
						'body.rtl .elementor-widget-wffn-optin-popup .bwf_pp_close'       => 'left: {{SIZE}}{{UNIT}}',
					),
					'separator'      => 'after',
				)
			);

			$this->add_heading( __( 'Size', 'funnel-builder-powerpack' ) );
			$this->add_responsive_control(
				'icon_size',
				array(
					'label'          => __( 'Font Size', 'funnel-builder-powerpack' ),
					'type'           => Controls_Manager::SLIDER,
					'size_units'     => array( 'px', 'em' ),
					'range'          => array(
						'px' => array(
							'max' => 50,
							'min' => 5,
						),
						'em' => array(
							'max'  => 20,
							'min'  => 0,
							'step' => 0.1,
						),
					),
					'default'        => array(
						'unit' => 'px',
						'size' => '25',
					),
					'tablet_default' => array(
						'unit' => 'px',
					),
					'mobile_default' => array(
						'unit' => 'px',
					),
					'selectors'      => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height:{{SIZE}}{{UNIT}}',
					),
				)
			);

			$this->add_control(
				'close_btn_inner_gap',
				array(
					'label'          => __( 'Padding', 'funnel-builder-powerpack' ),
					'type'           => Controls_Manager::SLIDER,
					'size_units'     => array( 'px', 'em' ),
					'range'          => array(
						'px' => array(
							'max' => 150,
							'min' => 0,
						),
						'em' => array(
							'max'  => 20,
							'min'  => 0,
							'step' => 0.1,
						),
					),
					'default'        => array(
						'unit' => 'px',
						'size' => '0',
					),
					'tablet_default' => array(
						'unit' => 'px',
					),
					'mobile_default' => array(
						'unit' => 'px',
					),
					'selectors'      => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'padding: {{SIZE}}{{UNIT}};',
					),
				)
			);

			$this->add_control(
				'close_btn_border',
				array(
					'label'          => __( 'Border Radius', 'funnel-builder-powerpack' ),
					'type'           => Controls_Manager::SLIDER,
					'size_units'     => array( 'px', 'em' ),
					'range'          => array(
						'px' => array(
							'max' => 50,
							'min' => 0,
						),
						'em' => array(
							'max'  => 20,
							'min'  => 0,
							'step' => 0.1,
						),
					),
					'default'        => array(
						'unit' => 'px',
						'size' => '15',
					),
					'tablet_default' => array(
						'unit' => 'px',
					),
					'mobile_default' => array(
						'unit' => 'px',
					),
					'selectors'      => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'border-radius: {{SIZE}}{{UNIT}};',
					),
				)
			);

			$this->add_heading( __( 'Color', 'funnel-builder-powerpack' ) );
			$this->start_controls_tabs( 'close_button_style_tabs' );

			$this->start_controls_tab(
				'tab_x_button_normal',
				array(
					'label' => __( 'Normal', 'funnel-builder-powerpack' ),
				)
			);

			$this->add_control(
				'close_button_background_color',
				array(
					'label'     => __( 'Background', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#6E6E6E',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'background-color: {{VALUE}}',
					),
				)
			);

			$this->add_control(
				'close_button_color',
				array(
					'label'     => __( 'Color', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#ffffff',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'color: {{VALUE}}',
					),
				)
			);

			$this->end_controls_tab();

			$this->start_controls_tab(
				'tab_x_button_hover',
				array(
					'label' => __( 'Hover', 'funnel-builder-powerpack' ),
				)
			);

			$this->add_control(
				'close_button_hover_background_color',
				array(
					'label'     => __( 'Background', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#D40F0F',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close:hover' => 'background-color: {{VALUE}}',
					),
				)
			);

			$this->add_control(
				'close_button_hover_color',
				array(
					'label'     => __( 'Color', 'funnel-builder-powerpack' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#444444',
					'selectors' => array(
						'.elementor-widget-wffn-optin-popup .bwf_pp_close:hover' => 'color: {{VALUE}}',
					),
				)
			);

			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();
		}

		/**
		 * Render widget output on the frontend.
		 *
		 * Written in PHP and used to generate the final HTML.
		 *
		 * @access protected
		 */
		protected function render() {
			$settings    = $this->get_settings_for_display();
			$button_args = array(
				'title'         => $settings['btn_text'],
				'subtitle'      => $settings['btn_subheading_text'],
				'icon_class'    => $settings['btn_icon'],
				'type'          => 'anchor',
				'link'          => '#',
				'icon_position' => $settings['btn_icon_position'] . ' bwf_icon',
				'show_icon'     => ( isset( $settings['btn_icon'] ) && isset( $settings['btn_icon']['library'] ) && ! empty( $settings['btn_icon']['library'] ) ),
			);

			$wrapper_class  = 'elementor-form-fields-wrapper';
			$show_labels    = isset( $settings['show_labels'] ) ? $settings['show_labels'] : true;
			$wrapper_class .= $show_labels ? '' : ' wfop_hide_label';

			$optinPageId    = WFOPP_Core()->optin_pages->get_optin_id();
			$optin_fields   = WFOPP_Core()->optin_pages->form_builder->get_optin_layout( $optinPageId );
			$optin_settings = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optinPageId );

			/**
			 * The per-field width controls are only registered when the section builder runs
			 * with an optin page in context - it needs get_optin_id() to resolve before it can
			 * ask the form builder for the field list. The widget itself is registered on every
			 * request so Elementor never drops it from the element tree, which means render()
			 * can run against a control stack that has no width control for a given field.
			 * Fall back to the field's own width, and then to the same default the section
			 * builder uses, instead of reading an undefined key.
			 */
			foreach ( $optin_fields as $step_slug => $optinFields ) {
				foreach ( $optinFields as $key => $optin_field ) {
					$input_name = isset( $optin_field['InputName'] ) ? $optin_field['InputName'] : '';

					if ( '' !== $input_name && isset( $settings[ $input_name ] ) ) {
						$width = $settings[ $input_name ];
					} elseif ( isset( $optin_field['width'] ) ) {
						$width = $optin_field['width'];
					} else {
						$width = 'wffn-sm-100';
					}

					$optin_fields[ $step_slug ][ $key ]['width'] = $width;
				}
			}

			// empty() covers both an unset key and an empty value, so this no longer reads an undefined key.
			$settings['popup_bar_pp']       = empty( $settings['popup_bar_pp'] ) ? 'disable' : $settings['popup_bar_pp'];
			$settings['popup_bar_width']    = ( isset( $settings['popup_bar_width'] ) && isset( $settings['popup_bar_width']['size'] ) ) ? $settings['popup_bar_width']['size'] : '75';
			$settings['button_border_size'] = 0;

			$custom_form = WFOPP_Core()->form_controllers->get_integration_object( 'form' );
			if ( $custom_form instanceof WFFN_Optin_Form_Controller_Custom_Form ) { ?>
				<div class="wfop_popup_wrapper wfop_pb_widget_wrap">
					<?php
					$custom_form->wffn_get_button_html( $button_args );
					$show_class = '';
					?>
					<div class="bwf_pp_overlay <?php echo esc_attr( $show_class ); ?> bwf_pp_effect_<?php echo esc_attr( $settings['popup_open_animation'] ); ?>">
						<div class="bwf_pp_wrap">
							<a class="bwf_pp_close" href="javascript:void(0);">&times;</a>
							<div class="bwf_pp_cont">
								<?php
								$settings = wp_parse_args( $settings, WFOPP_Core()->optin_pages->form_builder->form_customization_settings_default() );
								$custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'popover', $settings );
								?>
							</div>
						</div>
					</div>
				</div>
				<script>

					jQuery(document).trigger('wffn_reload_popups');
					jQuery(document).trigger('wffn_reload_phone_field');
				</script>
				<?php

			}
		}


		/**
		 * Render button text.
		 *
		 * Render button widget text.
		 *
		 * @access protected
		 */
		protected function render_text() {
			$settings = $this->get_settings_for_display();

			$this->add_render_attribute(
				array(
					'content-wrapper' => array(
						'class' => 'elementor-button-content-wrapper',
					),
					'text'            => array(
						'class' => 'elementor-button-text wffn-optin-btn-text',
					),
				)
			);

			$this->add_inline_editing_attributes( 'optin_button_text', 'none' );
			?>
			<span class="wffn-optin-popup-btn" <?php echo $this->get_render_attribute_string( 'content-wrapper' ); ?>>
			<?php if ( isset( $settings['icon'] ) && ! empty( $settings['icon'] ) ) : ?>
				<span <?php echo $this->get_render_attribute_string( 'icon-align' ); ?>>
				<i class="<?php echo esc_attr( $settings['icon'] ); ?>" aria-hidden="true"></i>
			</span>
			<?php endif; ?>
			<span style="display:inline-block;" <?php echo $this->get_render_attribute_string( 'optin_button_text' ); ?>><?php echo $settings['optin_button_text']; ?></span>
		</span>
			<?php
		}
	}
}