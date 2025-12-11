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
		 * @access public
		 *
		 * @return array Widget categories.
		 */
		public function get_categories() {
			return [ 'wffn-flex' ];
		}


		/**
		 * Register widget controls.
		 *
		 * Adds different input fields to allow the user to change and customize the widget settings.
		 *
		 * @access protected
		 */
		public function register_controls() {
            
			$this->start_controls_section( 'popup_progress_bar', [
				'label' => __( 'Progress Bar', 'funnel-builder-powerpack' ),
			] );

			$this->add_control( 'progress_bar_sec', [
				'label'     => __( 'Progress Bar', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_control( 'popup_bar_pp', [
				'label'        => __( 'Enable', 'funnel-builder-powerpack' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Enable', 'funnel-builder-powerpack' ),
				'label_off'    => __( 'Disable', 'funnel-builder-powerpack' ),
				'return_value' => 'enable',
				'default'      => 'enable',
			] );
			$this->add_control( 'popup_bar_text_position', [
				'label'        => __( 'Show progress text above the bar', 'funnel-builder-powerpack' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Enable', 'funnel-builder-powerpack' ),
				'label_off'    => __( 'Disable', 'funnel-builder-powerpack' ),
				'return_value' => 'enable',
				'default'      => '',
				'selectors'    => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap .bwf_pp_bar .pp-bar-text' => 'display:none;',
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .pp-bar-text-wrapper'                      => 'display:block;',
				],
				'condition'    => [
					'popup_bar_pp' => 'enable',
				],
			] );

			$this->add_control( 'popup_bar_animation', [
				'label'        => __( 'Animation', 'funnel-builder-powerpack' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'funnel-builder-powerpack' ),
				'label_off'    => __( 'No', 'funnel-builder-powerpack' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [
					'popup_bar_pp' => 'enable',
				],
			] );

			$this->add_control( 'popup_bar_text', [
				'label'       => __( 'Text', 'funnel-builder-powerpack' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( '75% Complete', 'funnel-builder-powerpack' ),
				'label_block' => true,
				'condition'   => [
					'popup_bar_pp' => 'enable',
				],
			] );

			$this->end_controls_section();

			$this->start_controls_section( 'popup_progress_bar_heading', [
				'label' => __( 'Heading', 'funnel-builder-powerpack' ),
			] );

			$this->add_control( 'progress_bar_head', [
				'label' => __( 'Text', 'funnel-builder-powerpack' ),
				'type'  => Controls_Manager::HEADING,
			] );

			$this->add_control( 'popup_heading', [
				'label'       => __( 'Heading', 'funnel-builder-powerpack' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => __( "You're just one step away!", 'funnel-builder-powerpack' ),
				'label_block' => true,
			] );

			$this->add_control( 'popup_sub_heading', [
				'label'       => __( 'Sub Heading', 'funnel-builder-powerpack' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => __( "Enter your details below and we'll get you signed up", 'funnel-builder-powerpack' ),
				'label_block' => true,
			] );

			$this->end_controls_section();

			parent::register_sections();


			/* Register Optin click pop up Button*/
			$this->add_tab( __( 'Button', 'funnel-builder-powerpack' ) );
			$this->add_text( 'btn_text', __( 'Title', 'funnel-builder-powerpack' ), __( 'Signup Now', 'funnel-builder-powerpack' ) );
			$this->add_text( 'btn_subheading_text', 'Subtitle', '' );
			$this->add_text_alignments( 'btn_alignment', [ '{{WRAPPER}} #bwf-custom-button-wrap' ], 'Button Alignment', array(), 'center' );
			$this->add_text_alignments( 'btn_text_alignment', [ '{{WRAPPER}} #bwf-custom-button-wrap a' ], __( 'Text Alignment', 'funnel-builder-powerpack' ) );

			$this->add_heading( __( "Button Icon", 'funnel-builder-powerpack' ) );
			$this->add_icon( 'btn_icon', [ '{{WRAPPER}} #bwf-custom-button-wrap' ] );
			$this->add_icon_position( 'btn_icon_position', [ '{{WRAPPER}} #bwf-custom-button-wrap a i' ], '', 'left' );

			$this->close_controls_tab();
			$this->close_controls_tabs();

			$this->end_tab();
			/* End Optin click pop up Button*/

			$this->start_controls_section( 'popup_section', [
				'label' => __( 'Popup', 'funnel-builder-powerpack' ),
			] );

			$this->add_control( 'popup_open_animation', [
				'label'   => esc_html__( 'Effect' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'fade'       => __( 'Fade', 'funnel-builder-powerpack' ),
					'slide-up'   => __( 'Slide Up', 'funnel-builder-powerpack' ),
					'slide-down' => __( 'Slide Down', 'funnel-builder-powerpack' ),
				],
				'default' => 'fade'
			] );
			$this->add_heading( __( 'Text After Button', 'funnel-builder-powerpack' ) );
			$this->add_control( 'popup_footer_text', [
				'label'       => __( 'Text', 'funnel-builder-powerpack' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => __( 'Your Information is 100% Secure', 'funnel-builder-powerpack' ),
				'label_block' => true,
			] );
			$this->end_controls_section();

			$this->start_controls_section( 'section_popover_style', [
				'label' => __( 'Progress Bar', 'funnel-builder-powerpack' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->add_control( 'popup_open_effect', [
				'label'     => __( 'Size', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_control( 'popup_bar_width', [
				'label'     => __( 'Width', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'%' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'default'   => [
					'size' => 75,
					'unit' => '%',
				],
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap .bwf_pp_bar' => 'width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'popup_bar_pp' => 'enable',
				],
			] );
			$this->add_control( 'popup_bar_heights', [
				'label'     => __( 'Height', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'default'   => [
					'size' => 40,
					'unit' => 'px',
				],
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap' => 'height: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'popup_bar_pp' => 'enable',
				],
			] );

			$this->add_control( 'popup_bar_inner_gaps', [
				'label'     => __( 'Padding', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default'   => [
					'size' => 4,
					'unit' => 'px',
				],
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap' => 'padding: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'popup_bar_pp' => 'enable',
				],
			] );

			$this->add_control( 'progress_bar_heading', [
				'label'     => __( 'Styling', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_group_control( Group_Control_Typography::get_type(), [
				'name'     => 'progress_bar_typography',
				'selector' => '.elementor-widget-wffn-optin-popup .bwf_pp_overlay .pp-bar-text',
				'global'   => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			] );

			$this->add_control( 'progress_text_color', [
				'label'     => __( 'Text', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .pp-bar-text' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'progress_color', [
				'label'     => __( 'Color', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar' => 'background-color: {{VALUE}};',
				],
			] );

			$this->add_control( 'progress_background_color', [
				'label'     => __( 'Background', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#efefef',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_bar_wrap' => 'background-color: {{VALUE}};',
				],
			] );

			$this->end_controls_section();

			$this->start_controls_section( 'popup_heading_style', [
				'label' => __( 'Heading', 'funnel-builder-powerpack' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			//Popup Heading
			$this->add_control( 'popup_heading_head', [
				'label'     => __( 'Heading', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_group_control( Group_Control_Typography::get_type(), [
				'name'           => 'popup_heading_typography',
				'selector'       => '.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_head',
				'global'         => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'fields_options' => [
					// first mimic the click on Typography edit icon
					'typography'  => [ 'default' => 'yes' ],
					// then redifine the Elementor defaults
					'font_family' => [ 'default' => 'Open Sans' ],
					'font_size'   => [ 'default' => [ 'size' => 17 ] ],
					'font_weight' => [ 'default' => 400 ],
					'line_height' => [ 'default' => [ 'size' => 1.5, 'unit' => 'em' ] ],
				],
			] );
			$this->add_control( 'popup_heading_color', [
				'label'     => __( 'Text', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_head' => 'color: {{VALUE}};',
				],
				'default'   => '#000000',
			] );

			//Popup Sub heading
			$this->add_control( 'popup_subheading', [
				'label'     => __( 'Sub-Heading', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_sub_head' => 'color: {{VALUE}};',
				],
				'default'   => '#000000',
			] );
			$this->add_group_control( Group_Control_Typography::get_type(), [
				'name'           => 'popup_subheading_typography',
				'selector'       => '.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_sub_head',
				'global'         => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'fields_options' => [
					// first mimic the click on Typography edit icon
					'typography'  => [ 'default' => 'yes' ],
					// then redifine the Elementor defaults
					'font_family' => [ 'default' => 'Open Sans' ],
					'font_size'   => [ 'default' => [ 'size' => 24 ] ],
					'font_weight' => [ 'default' => 700 ],
					'line_height' => [ 'default' => [ 'size' => 1.5, 'unit' => 'em' ] ],
				],
			] );
			$this->add_control( 'popup_subheading_color', [
				'label'     => __( 'Text', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_overlay .bwf_pp_opt_sub_head' => 'color: {{VALUE}};',
				],
			] );

			$this->end_controls_section();

			parent::register_styles();

			$this->start_controls_section( 'section_buttons_style', [
				'label' => __( 'Button', 'funnel-builder-powerpack' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->add_responsive_control( 'btn_width', [
				'label'     => __( 'Button width (in %)', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 30,
				],
				'range'     => [
					'%' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} #bwf-custom-button-wrap a' => 'min-width: {{SIZE}}%;',
				],
			] );
			// $this->add_width( 'btn_width', '{{WRAPPER}} #bwf-custom-button-wrap a', '', [ 'width' => 30 ] );

			$this->add_controls_tabs( "bwf_btn_tabs" );
			$this->add_controls_tab( "bwf_btn_normal_tab", 'Normal' );
			$this->add_background_color( 'btn_bg_color', [ '{{WRAPPER}} #bwf-custom-button-wrap a' ], '#000' );
			$this->add_color( 'btn_color', [ '{{WRAPPER}} #bwf-custom-button-wrap a', '{{WRAPPER}} #bwf-custom-button-wrap .bwf_subheading' ], '#ffffff' );
			$this->close_controls_tab();
			$this->add_controls_tab( "bwf_btn_hover_tab", 'Hover' );
			$this->add_background_color( 'btn_hover_bg_color', [ '{{WRAPPER}} #bwf-custom-button-wrap a:hover' ], '#000' );
			$this->add_color( 'btn_hover_color', [ '{{WRAPPER}} #bwf-custom-button-wrap a:hover', '{{WRAPPER}} #bwf-custom-button-wrap a:hover .bwf_subheading' ], '#ffffff' );
			$this->close_controls_tab();
			$this->close_controls_tabs();

			$this->add_heading( __( "Typography", 'funnel-builder-powerpack' ) );
			$this->add_typography( 'btn_text_typo', '{{WRAPPER}} #bwf-custom-button-wrap .bwf_heading, {{WRAPPER}} #bwf-custom-button-wrap .bwf_icon', array(), array(), 'Heading' );

			$this->add_typography( 'btn_subheading_text_typo', '{{WRAPPER}} #bwf-custom-button-wrap .bwf_subheading', array(), array(), 'Sub Heading' );

			$this->add_heading( "Advanced" );
			$defaults = [ 'top' => 5, 'right' => 5, 'bottom' => 5, 'left' => 5, 'unit' => 'px' ];
			$this->add_padding( 'btn_text_padding', '{{WRAPPER}} #bwf-custom-button-wrap a', $defaults );
			$this->add_margin( 'btn_text_margin', '{{WRAPPER}} #bwf-custom-button-wrap a', $defaults );
			$this->add_border( 'btn_text_alignment_border', '{{WRAPPER}} #bwf-custom-button-wrap a' );
			$this->add_border_shadow( 'btn_text_alignment_box_shadow', '{{WRAPPER}} #bwf-custom-button-wrap a' );
			$this->end_controls_section();

			$this->start_controls_section( 'section_popup_style', [
				'label' => __( 'Popup', 'funnel-builder-powerpack' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );


			$this->add_heading( __( "Text After Button", 'funnel-builder-powerpack' ) );
			$after_button_fields_options = [
				'typography'  => [ 'default' => 'yes' ],
				// then redefine the Elementor defaults
				'font_family' => [ 'default' => 'Open Sans' ],
				'font_size'   => [ 'default' => [ 'size' => 16 ] ],
				'font_weight' => [ 'default' => 700 ],
				'line_height' => [ 'default' => [ 'size' => 1, 'unit' => 'em' ] ],
			];
			$this->add_typography( 'popup_footer_font_family', '{{WRAPPER}} .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer', $after_button_fields_options );
			$this->add_color( 'popup_footer_text_color', [ '{{WRAPPER}} .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer' ], '#000000' );



			$this->add_responsive_control( 'popup_wrap_width', [
				'label'     => __( 'Button width (in px)', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 600,
				],
				'range'     => [
					'px' => [
						'min'  => 0,
						'max'  => 2500,
						'step' => 5,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .bwf_pp_wrap' => 'max-width: {{SIZE}}px;',
				],
			] );
			$popup_defaults = [ 'top' => 40, 'right' => 40, 'bottom' => 40, 'left' => 40, 'unit' => 'px' ];
			$this->add_padding( 'popup_padding', '{{WRAPPER}} .bwf_pp_wrap .bwf_pp_cont', $popup_defaults );

			$this->end_controls_section();

			$this->start_controls_section( 'popup_close_btn', [
				'label' => __( 'Close Button', 'funnel-builder-powerpack' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->add_heading( __( "Position", 'funnel-builder-powerpack' ) );
			$this->add_responsive_control( 'close_button_vertical', [
				'label'          => __( 'Vertical Position', 'funnel-builder-powerpack' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', '%' ],
				'range'          => [
					'px' => [
						'max' => 650,
						'min' => - 90,
					],
					'%'  => [
						'max'  => 100,
						'min'  => 0,
						'step' => 0.1,
					],
				],
				'default'        => [
					'unit' => 'px',
					'size' => '-8'
				],
				'tablet_default' => [
					'unit' => 'px',
				],
				'mobile_default' => [
					'unit' => 'px',
				],
				'selectors'      => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'top: {{SIZE}}{{UNIT}}',
				],
			] );

			$this->add_responsive_control( 'close_button_horizontal', [
				'label'          => __( 'Horizontal Position', 'funnel-builder-powerpack' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', '%' ],
				'range'          => [
					'px' => [
						'max' => 1000,
						'min' => - 350,
					],
					'%'  => [
						'max'  => 100,
						'min'  => 0,
						'step' => 0.1,
					],
				],
				'default'        => [
					'unit' => 'px',
					'size' => '-14'
				],
				'tablet_default' => [
					'unit' => 'px',
				],
				'mobile_default' => [
					'unit' => 'px',
				],
				'selectors'      => [
					'body:not(.rtl) .elementor-widget-wffn-optin-popup .bwf_pp_close' => 'right: {{SIZE}}{{UNIT}}',
					'body.rtl .elementor-widget-wffn-optin-popup .bwf_pp_close'       => 'left: {{SIZE}}{{UNIT}}',
				],
				'separator'      => 'after',
			] );

			$this->add_heading( __( "Size", 'funnel-builder-powerpack' ) );
			$this->add_responsive_control( 'icon_size', [
				'label'          => __( 'Font Size', 'funnel-builder-powerpack' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'em' ],
				'range'          => [
					'px' => [
						'max' => 50,
						'min' => 5,
					],
					'em' => [
						'max'  => 20,
						'min'  => 0,
						'step' => 0.1,
					],
				],
				'default'        => [
					'unit' => 'px',
					'size' => '25'
				],
				'tablet_default' => [
					'unit' => 'px',
				],
				'mobile_default' => [
					'unit' => 'px',
				],
				'selectors'      => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height:{{SIZE}}{{UNIT}}',
				],
			] );

			$this->add_control( 'close_btn_inner_gap', [
				'label'          => __( 'Padding', 'funnel-builder-powerpack' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'em' ],
				'range'          => [
					'px' => [
						'max' => 150,
						'min' => 0,
					],
					'em' => [
						'max'  => 20,
						'min'  => 0,
						'step' => 0.1,
					],
				],
				'default'        => [
					'unit' => 'px',
					'size' => '0'
				],
				'tablet_default' => [
					'unit' => 'px',
				],
				'mobile_default' => [
					'unit' => 'px',
				],
				'selectors'      => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'padding: {{SIZE}}{{UNIT}};',
				],
			] );

			$this->add_control( 'close_btn_border', [
				'label'          => __( 'Border Radius', 'funnel-builder-powerpack' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'em' ],
				'range'          => [
					'px' => [
						'max' => 50,
						'min' => 0,
					],
					'em' => [
						'max'  => 20,
						'min'  => 0,
						'step' => 0.1,
					],
				],
				'default'        => [
					'unit' => 'px',
					'size' => '15'
				],
				'tablet_default' => [
					'unit' => 'px',
				],
				'mobile_default' => [
					'unit' => 'px',
				],
				'selectors'      => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			] );

			$this->add_heading( __( "Color", 'funnel-builder-powerpack' ) );
			$this->start_controls_tabs( 'close_button_style_tabs' );

			$this->start_controls_tab( 'tab_x_button_normal', [
				'label' => __( 'Normal', 'funnel-builder-powerpack' ),
			] );

			$this->add_control( 'close_button_background_color', [
				'label'     => __( 'Background', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6E6E6E',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'background-color: {{VALUE}}',
				],
			] );

			$this->add_control( 'close_button_color', [
				'label'     => __( 'Color', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close' => 'color: {{VALUE}}',
				],
			] );

			$this->end_controls_tab();

			$this->start_controls_tab( 'tab_x_button_hover', [
				'label' => __( 'Hover', 'funnel-builder-powerpack' ),
			] );

			$this->add_control( 'close_button_hover_background_color', [
				'label'     => __( 'Background', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#D40F0F',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close:hover' => 'background-color: {{VALUE}}',
				],
			] );

			$this->add_control( 'close_button_hover_color', [
				'label'     => __( 'Color', 'funnel-builder-powerpack' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#444444',
				'selectors' => [
					'.elementor-widget-wffn-optin-popup .bwf_pp_close:hover' => 'color: {{VALUE}}',
				],
			] );

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
				'show_icon'     => ( isset( $settings['btn_icon'] ) && isset( $settings['btn_icon']['library'] ) && ! empty( $settings['btn_icon']['library'] ) )
			);

			$wrapper_class = 'elementor-form-fields-wrapper';
			$show_labels   = isset( $settings['show_labels'] ) ? $settings['show_labels'] : true;
			$wrapper_class .= $show_labels ? '' : ' wfop_hide_label';

			$optinPageId    = WFOPP_Core()->optin_pages->get_optin_id();
			$optin_fields   = WFOPP_Core()->optin_pages->form_builder->get_optin_layout( $optinPageId );
			$optin_settings = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optinPageId );

			foreach ( $optin_fields as $step_slug => $optinFields ) {
				foreach ( $optinFields as $key => $optin_field ) {
					$optin_fields[ $step_slug ][ $key ]['width'] = $settings[ $optin_field['InputName'] ];
				}
			}

			$settings['popup_bar_pp']       = ( isset( $settings['popup_bar_pp'] ) && empty( $settings['popup_bar_pp'] ) ) ? 'disable' : $settings['popup_bar_pp'];
			$settings['popup_bar_width']    = ( isset( $settings['popup_bar_width'] ) && isset( $settings['popup_bar_width']['size'] ) ) ? $settings['popup_bar_width']['size'] : '75';
			$settings['button_border_size'] = 0;

			$custom_form = WFOPP_Core()->form_controllers->get_integration_object( 'form' );
			if ( $custom_form instanceof WFFN_Optin_Form_Controller_Custom_Form ) { ?>
                <div class="wfop_popup_wrapper wfop_pb_widget_wrap">
					<?php
					$custom_form->wffn_get_button_html( $button_args );
					$show_class = '';
					?>
                    <div class="bwf_pp_overlay <?php echo esc_attr( $show_class ); ?> bwf_pp_effect_<?php echo esc_attr( $settings['popup_open_animation'] ) ?>">
                        <div class="bwf_pp_wrap">
                            <a class="bwf_pp_close" href="javascript:void(0);">&times;</a>
                            <div class="bwf_pp_cont">
								<?php
								$settings = wp_parse_args( $settings, WFOPP_Core()->optin_pages->form_builder->form_customization_settings_default() );
								$custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'popover', $settings ); ?>
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

			$this->add_render_attribute( [
				'content-wrapper' => [
					'class' => 'elementor-button-content-wrapper',
				],
				'text'            => [
					'class' => 'elementor-button-text wffn-optin-btn-text',
				],
			] );

			$this->add_inline_editing_attributes( 'optin_button_text', 'none' ); ?>
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