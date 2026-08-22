<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

use Elementor\Controls_Manager;

if ( ! class_exists( 'WFACP_EL_Fields' ) ) {
	#[AllowDynamicProperties]
	abstract class WFACP_EL_Fields extends \Elementor\Widget_Base {
		private $add_tab_number          = 1;
		private $add_heading_number      = 1;
		private $add_divider_number      = 1;
		protected $ajax_session_settings = array();
		public function __construct( $data = array(), $args = null ) {
			parent::__construct( $data, $args );
		}

		/**
		 * Our widgets render against live checkout state (cart, session, customer, gateways)
		 * and must never have their markup baked into Elementor's element cache.
		 *
		 * Returning true makes Elementor store a `[elementor-element k=... data=...]` placeholder
		 * in `_elementor_element_cache` instead of the rendered HTML; the widget is then
		 * re-rendered on every request via do_shortcode(). This matches how Elementor Pro treats
		 * its WooCommerce widgets.
		 *
		 * Element_Base already defaults to true, but we declare it explicitly so the behaviour is
		 * intentional and survives any change to that default.
		 *
		 * @return bool
		 */
		protected function is_dynamic_content(): bool {
			return true;
		}

		protected function add_tab( $title = '', $tab_type = 1, $condition = array() ) {

			if ( empty( $title ) ) {
				$title = $this->get_title();
			}
			$field_key = 'wfacp_' . $this->add_tab_number . '_tab';
			$tab       = Controls_Manager::TAB_CONTENT;
			if ( 2 == $tab_type ) {
				$tab = Controls_Manager::TAB_STYLE;
			} elseif ( 3 == $tab_type ) {
				$tab = Controls_Manager::TAB_ADVANCED;
			} elseif ( 4 == $tab_type ) {
				$tab = Controls_Manager::TAB_SETTINGS;
			} elseif ( 5 == $tab_type ) {
				$tab = Controls_Manager::TAB_CONTENT;
			}

			$this->start_controls_section(
				$field_key,
				array(
					'label'     => $title,
					'tab'       => $tab,
					'condition' => $condition,
				)
			);

			++$this->add_tab_number;
		}

		protected function end_tab() {
			$this->end_controls_section();
		}

		protected function add_margin_padding_border( $field_key, $selector, $full_selector = false, $default = array() ) {

			if ( false == $full_selector ) {
				$selector = '{{WRAPPER}} ' . $selector;
			}

			$this->add_group_control(
				\Elementor\Group_Control_Background::get_type(),
				array(
					'name'     => $field_key . '_background',
					'label'    => __( 'Background', 'elementor' ),
					'types'    => array( 'classic', 'gradient' ),
					'selector' => $selector,
				)
			);
			$this->add_responsive_control(
				$field_key . '_width',
				array(
					'label'      => __( 'Width', 'elementor' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'range'      => array(
						'px' => array(
							'min'  => 0,
							'max'  => 2500,
							'step' => 5,
						),
						'%'  => array(
							'min' => 0,
							'max' => 100,
						),
					),
					'default'    => array(
						'unit' => '%',
						'size' => isset( $default['width'] ) ? $default['width'] : 65,
					),
					'selectors'  => array(
						$selector => 'width: {{SIZE}}{{UNIT}};',
					),
				)
			);

			$this->add_padding( $field_key, $selector );
			$this->add_margin( $field_key, $selector );
			$this->add_border( $field_key, $selector );
		}

		protected function add_width( $field_key, $selector, $label = '', $default = array(), $condition = array(), $size_unit = array(), $tablet_default = array(), $mobile_default = array(), $override_other_selector = array() ) {
			if ( empty( $label ) ) {
				$label = __( 'Width', 'elementor' );
			}

			if ( empty( $size_unit ) ) {
				$size_unit = array( 'px', '%' );
			}

			$args = array(
				'label'      => $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $size_unit,
				'range'      => array(
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
					'px' => array(
						'min'  => 0,
						'max'  => 2500,
						'step' => 5,
					),
				),
				'default'    => array(
					'unit' => isset( $default['unit'] ) ? $default['unit'] : '%',
					'size' => isset( $default['width'] ) ? $default['width'] : 100,
				),
				'selectors'  => array(
					$selector => 'width: {{SIZE}}{{UNIT}};',
				),
				'condition'  => $condition,
			);
			if ( is_array( $override_other_selector ) && count( $override_other_selector ) > 0 ) {

				$args['selectors'] = $override_other_selector;

			}

			if ( ! empty( $size_unit ) ) {
				$args['tablet_default'] = $tablet_default;
				$args['mobile_default'] = $mobile_default;
			}

			$this->add_responsive_control( $field_key, $args );
		}

		protected function add_top_position( $field_key, $selector, $label = '', $default = array(), $condition = array(), $size_unit = array(), $tablet_default = array(), $mobile_default = array(), $override_other_selector = array() ) {
			if ( empty( $label ) ) {
				$label = __( 'Position', 'elementor' );
			}

			if ( empty( $size_unit ) ) {
				$size_unit = array( 'px', '%' );
			}

			$args = array(
				'label'      => $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $size_unit,
				'range'      => array(
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
					'px' => array(
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					),
				),
				'default'    => array(
					'unit' => isset( $default['unit'] ) ? $default['unit'] : '%',
					'size' => isset( $default['top'] ) ? $default['top'] : 100,
				),
				'selectors'  => array(
					$selector => 'top: {{SIZE}}{{UNIT}};',
				),
				'condition'  => $condition,
			);
			if ( is_array( $override_other_selector ) && count( $override_other_selector ) > 0 ) {

				$args['selectors'] = $override_other_selector;

			}

			if ( ! empty( $size_unit ) ) {
				$args['tablet_default'] = $tablet_default;
				$args['mobile_default'] = $mobile_default;
			}

			$this->add_responsive_control( $field_key, $args );
		}


		protected function add_padding( $field_key, $selector, $default = array(), $mobile_default = array(), $condition = array(), $tablet_default = array() ) {
			if ( empty( $default ) ) {
				$default = array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => false,
				);
			}

			$args = array(
				'label'      => __( 'Padding', 'elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => $default,
				'condition'  => $condition,
				'selectors'  => array(
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			);

			if ( ! empty( $mobile_default ) ) {

				$args['mobile_default'] = $mobile_default;
			}

			if ( ! empty( $tablet_default ) ) {
				$args['tablet_default'] = $tablet_default;
			}

			$this->add_responsive_control( $field_key . '_padding', $args );
		}

		protected function add_margin( $field_key, $selector, $default = array(), $mobile_default = array(), $condition = array(), $tablet_default = array() ) {

			if ( empty( $default ) ) {
				$default = array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				);
			}

			$args = array(
				'label'      => __( 'Margin', 'elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => $default,
				'condition'  => $condition,
				'selectors'  => array(
					$selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			);

			if ( ! empty( $mobile_default ) ) {
				$args['mobile_default'] = $mobile_default;
			}

			if ( ! empty( $tablet_default ) ) {
				$args['tablet_default'] = $tablet_default;
			}

			$this->add_responsive_control( $field_key . '_margin', $args );
		}

		protected function add_border( $field_key, $selector, $condition = array(), $default = array(), $fields_options = array() ) {

			if ( empty( $default ) ) {
				$default = array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				);
			}

			$borderdefault = array(
				'name'      => $field_key . '_border',
				'label'     => __( 'Border', 'funnel-builder' ),
				'selector'  => $selector,
				'condition' => $condition,

			);
			if ( is_array( $fields_options ) && count( $fields_options ) > 0 ) {

				$borderdefault['fields_options'] = $fields_options;
			}
			$this->add_group_control( \Elementor\Group_Control_Border::get_type(), $borderdefault );

			$this->add_responsive_control(
				$field_key . '_border_radius',
				array(
					'label'      => __( 'Border Radius', 'elementor' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'default'    => $default,

					'selectors'  => array(
						$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
					'condition'  => $condition,

				)
			);
		}

		protected function add_border_radius( $field_key, $selector, $condition = array(), $default = array(), $fields_options = array(), $custom_label = '' ) {

			$label = __( 'Border Radius', 'elementor' );

			if ( ! empty( $custom_label ) ) {
				$label = $custom_label;
			}

			if ( empty( $default ) ) {
				$default = array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				);
			}

			$this->add_responsive_control(
				$field_key . '_border_radius',
				array(
					'label'      => $label,
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'default'    => $default,
					'selectors'  => array(
						$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
					'condition'  => $condition,

				)
			);
		}

		protected function add_border_without_radius( $field_key, $selector, $condition = array(), $default = array(), $fields_options = array() ) {

			if ( empty( $default ) ) {
				$default = array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				);
			}

			$borderdefault = array(
				'name'      => $field_key . '_border',
				'label'     => __( 'Border', 'funnel-builder' ),
				'selector'  => $selector,
				'condition' => $condition,

			);
			if ( is_array( $fields_options ) && count( $fields_options ) > 0 ) {
				$borderdefault['fields_options'] = $fields_options;
			}
			$this->add_group_control( \Elementor\Group_Control_Border::get_type(), $borderdefault );
		}

		public function add_heading( $heading, $separator = '', $conditions = array() ) {

			if ( empty( $separator ) ) {
				$separator = 'before';
			}

			$field_key = 'wfacp_' . $this->add_heading_number . '_heading';
			$this->add_control(
				$field_key,
				array(
					'label'     => $heading,
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => $separator,
					'condition' => $conditions,
				)
			);
			++$this->add_heading_number;
		}

		public function add_typography( $field_key, $selector, $fields_options = array(), $conditions = array(), $label = '' ) {

			if ( empty( $label ) ) {
				$label = __( 'Typography', 'funnel-builder' );
			}

			$args = array(
				'name'      => $field_key,
				'label'     => $label,
				'selector'  => $selector,
				'condition' => $conditions,

			);

			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				if ( version_compare( ELEMENTOR_VERSION, '3.15.0', '>=' ) ) {
					// For version 3.15.0 and above
					$args['global'] = array(
						'default' => Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT,
					);
				} elseif ( version_compare( ELEMENTOR_VERSION, '2.8.0', '>=' ) ) {
					// For versions between 2.8.0 and 3.14.x
					$args['scheme'] = \Elementor\Core\Schemes\Typography::TYPOGRAPHY_4;
				} else {
					// For versions below 2.8.0
					$args['scheme'] = \Elementor\Scheme_Typography::TYPOGRAPHY_4;
				}
			}

			if ( is_array( $fields_options ) && count( $fields_options ) > 0 ) {
				$args['fields_options'] = $fields_options;

			}

			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $args );
		}


		public function add_color( $field_key, $selectors = array(), $default = '', $label = '', $conditions = array() ) {

			if ( empty( $label ) ) {
				$label = esc_attr__( 'Color', 'elementor' );
			}

			$color_selectors = array();
			if ( is_array( $selectors ) && count( $selectors ) > 0 ) {
				foreach ( $selectors as $selector ) {

					if ( $field_key === 'wfacp_button_bg_color' || $field_key === 'wfacp_button_bg_hover_color' ) {
						$color_selectors[ $selector ] = 'color:{{VALUE}} !important;';
					} else {
						$color_selectors[ $selector ] = 'color:{{VALUE}};';
					}
				}
			}
			$this->add_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $default,
					'selectors' => $color_selectors,
					'condition' => $conditions,
				)
			);
		}


		public function add_background_color( $field_key, $selectors = array(), $default = '#000000', $label = '', $conditions = array() ) {

			if ( empty( $label ) ) {
				$label = esc_attr__( 'Background', 'elementor' );
			}

			$color_selectors = array();
			if ( is_array( $selectors ) && count( $selectors ) > 0 ) {
				foreach ( $selectors as $selector ) {
					if ( 'wfacp_button_bg_color' == $field_key || 'wfacp_button_bg_hover_color' == $field_key ) {
						$color_selectors[ $selector ] = 'background-color:{{VALUE}};';
					} else {
						$color_selectors[ $selector ] = 'background-color:{{VALUE}}';
					}
				}
			}

			$this->add_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $default,
					'selectors' => $color_selectors,
					'condition' => $conditions,
				)
			);
		}

		public function add_controls_tabs( $key, $conditions = array(), $classes = '' ) {
			$this->start_controls_tabs(
				$key,
				array(
					'condition' => $conditions,
					'classes'   => $classes,
				)
			);
		}

		public function add_controls_tab( $key, $label ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Normal', 'elementor' );
			}

			$this->start_controls_tab(
				$key,
				array(
					'label' => $label,
				)
			);
		}

		public function close_controls_tab() {
			$this->end_controls_tab();
		}

		public function close_controls_tabs() {
			$this->end_controls_tabs();
		}

		public function add_border_color( $field_key, $selectors = array(), $default = '#000000', $label = '', $box_shadow = false, $conditions = array() ) {

			if ( empty( $label ) ) {
				$label = esc_attr__( 'Color', 'elementor' );
			}

			$keys_for_imp = array(
				'wfacp_form_fields_validation_color',
				'wfacp_form_fields_hover_color',
				'wfacp_form_fields_focus_color',
				'order_coupon_focus_color',
			);

			$color_selectors = array();
			if ( is_array( $selectors ) && count( $selectors ) > 0 ) {
				foreach ( $selectors as $selector ) {

					if ( in_array( $field_key, $keys_for_imp ) ) {
						$border_color = 'border-color:{{VALUE}} !important;';
					} else {
						$border_color = 'border-color:{{VALUE}};';
					}

					$color_selectors[ $selector ] = $border_color;

					if ( true == $box_shadow ) {

						$border_color .= 'box-shadow:0 0 0 1px {{VALUE}} !important';
					}
					$color_selectors[ $selector ] = $border_color;

				}
			}

			$this->add_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $default,
					'selectors' => $color_selectors,
					'condition' => $conditions,
				)
			);
		}

		public function add_hover( $field_key, $selectors = array(), $default = '#000000', $label = '', $conditions = array() ) {

			if ( empty( $label ) ) {
				$label = esc_attr__( 'Hover Color', 'funnel-builder' );
			}

			$color_selectors = array();
			if ( is_array( $selectors ) && count( $selectors ) > 0 ) {
				foreach ( $selectors as $selector ) {
					$color_selectors[ $selector ] = 'color:{{VALUE}}';
				}
			}

			$this->add_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $default,
					'selectors' => $color_selectors,
					'condition' => $conditions,

				)
			);
		}

		public function add_background( $field_key, $selector, $default = '#000000', $label = '', $types = array(), $conditions = array(), $bg_type = array() ) {

			if ( empty( $label ) ) {
				$label = __( 'Background', 'elementor' );
			}
			if ( empty( $bg_type ) ) {
				$types = array( 'classic', 'gradient' );
			}

			$this->add_group_control(
				\Elementor\Group_Control_Background::get_type(),
				array(
					'name'      => $field_key,
					'label'     => $label,
					'types'     => $types,
					'default'   => $default,
					'selector'  => $selector,
					'condition' => $conditions,
				)
			);
		}

		public function add_number( $field_key, $label, $default = 1, $conditions = array() ) {
			$this->add_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => $default,
					'condition' => $conditions,
				)
			);
		}

		public function add_text( $field_key, $label, $default = '', $conditions = array(), $classes = '', $description = '', $placeholder = '', $device_args = array() ) {

			$textArg = array(
				'label'     => $label,
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => $default,
				'condition' => $conditions,
			);

			if ( ! empty( $device_args ) ) {
				$textArg['device_args'] = $device_args;

			}
			if ( ! empty( $description ) ) {
				$textArg['description'] = $description;
			}

			if ( ! empty( $placeholder ) ) {
				$textArg['placeholder'] = $placeholder;
			}
			if ( ! empty( $classes ) ) {
				$textArg['classes'] = $classes;
			}
			$this->add_control( $field_key, $textArg );
		}

		public function add_textArea( $field_key, $label, $default = '', $conditions = array() ) {
			$this->add_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::TEXTAREA,
					'default'   => $default,
					'condition' => $conditions,
				)
			);
		}

		public function add_choose( $field_key, $label, $options = array(), $default = '', $conditions = array(), $description = '' ) {

			$args = array(
				'label'     => $label,
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => $options,
				'default'   => $default,
				'condition' => $conditions,
				'toggle'    => true,
			);
			if ( ! empty( $description ) ) {
				$args['description'] = $description;
			}

			$this->add_control( $field_key, $args );
		}

		public function add_switcher( $field_key, $label = '', $label_on = '', $label_off = '', $default = 'no', $return_value = 'yes', $conditions = array(), $tablet_default = '', $mobile_default = '', $classes = '', $device_args = array() ) {
			if ( empty( $label ) ) {
				$label = 'Enable';
			}
			if ( empty( $label_on ) ) {
				$label_on = __( 'Yes', 'funnel-builder' );
			}
			if ( empty( $label_off ) ) {
				$label_off = 'no';
			}

			$args = array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => $label_on,
				'label_off'    => $label_off,
				'return_value' => $return_value,
				'condition'    => $conditions,
				'default'      => $default,
			);

			if ( ! empty( $device_args ) ) {
				$args['device_args'] = $device_args;
			}

			if ( ! empty( $classes ) ) {
				$args['classes'] = $classes;

			}

			if ( ! empty( $tablet_default ) ) {
				$args['tablet_default'] = 'yes';
			}
			if ( ! empty( $tablet_default ) ) {
				$args['mobile_default'] = 'yes';
			}

			$this->add_responsive_control( $field_key, $args );
		}

		public function add_switcher_without_responsive( $field_key, $label = '', $label_on = '', $label_off = '', $default = 'no', $return_value = 'yes', $conditions = array(), $tablet_default = '', $mobile_default = '', $classes = '', $device_args = array() ) {
			if ( empty( $label ) ) {
				$label = 'Enable';
			}
			if ( empty( $label_on ) ) {
				$label_on = __( 'Yes', 'funnel-builder' );
			}
			if ( empty( $label_off ) ) {
				$label_off = 'no';
			}

			$args = array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => $label_on,
				'label_off'    => $label_off,
				'return_value' => $return_value,
				'condition'    => $conditions,
				'default'      => $default,
			);

			if ( ! empty( $device_args ) ) {
				$args['device_args'] = $device_args;
			}

			if ( ! empty( $classes ) ) {

				$args['classes'] = $classes;
			}

			if ( ! empty( $tablet_default ) ) {
				$args['tablet_default'] = 'yes';
			}
			if ( ! empty( $tablet_default ) ) {
				$args['mobile_default'] = 'yes';
			}
			$this->add_control( $field_key, $args );
		}

		public function add_select( $field_key, $label, $options = array(), $default = '', $conditions = array(), $description = '', $classes = '' ) {
			if ( empty( $options ) ) {
				return;
			}

			$args = array(
				'label'     => $label,
				'type'      => Controls_Manager::SELECT,
				'default'   => $default,
				'options'   => $options,
				'condition' => $conditions,
			);

			if ( ! empty( $classes ) ) {
				$args['classes'] = $classes;
			}
			if ( ! empty( $description ) ) {
				$args['description'] = $description;

			}
			$this->add_control( $field_key, $args );
		}

		public function add_text_alignments( $field_key, $selectors, $label = '', $options = array(), $default = '', $conditions = array(), $extra_css = null ) {

			$align_selectors = array();

			if ( is_array( $selectors ) && count( $selectors ) > 0 ) {
				foreach ( $selectors as $selector ) {
					if ( is_array( $extra_css ) && array_key_exists( $selector, $extra_css ) ) {
						$align_selectors[ $selector ] = 'text-align:{{VALUE}}; ' . $extra_css[ $selector ];
					} else {
						$align_selectors[ $selector ] = 'text-align:{{VALUE}};';
					}
				}
			}
			if ( empty( $label ) ) {
				$label = __( 'Alignment', 'elementor' );
			}
			if ( empty( $options ) ) {
				$options = array(
					'left'   => array(
						'title' => __( 'Left', 'funnel-builder' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'funnel-builder' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'funnel-builder' ),
						'icon'  => 'eicon-text-align-right',
					),
				);
			}

			$this->add_responsive_control(
				$field_key,
				array(
					'label'     => $label,
					'type'      => Controls_Manager::CHOOSE,
					'options'   => $options,
					'default'   => is_rtl() ? 'right' : $default,
					'selectors' => $align_selectors,
					'condition' => $conditions,
				)
			);
		}

		public function add_font_family( $field_key, $selectors, $label = '', $default = '' ) {

			if ( empty( $label ) ) {
				$label = __( 'Fonts', 'funnel-builder' );
			}

			$fontfamily_selectors = array();
			if ( is_array( $selectors ) && count( $selectors ) > 0 ) {
				foreach ( $selectors as $selector ) {
					$fontfamily_selectors[ $selector ] = 'font-family:{{VALUE}}';
				}
			}

			$args = array(
				'name'      => $field_key,
				'label'     => $label,
				'type'      => \Elementor\Controls_Manager::FONT,
				'selectors' => $fontfamily_selectors,
				'default'   => $default,
			);

			$this->add_control( $field_key, $args );
		}

		protected function add_divider( $separator = '' ) {

			if ( empty( $separator ) ) {
				$separator = 'none';
			}
			$field_key = 'wfacp_' . $this->add_divider_number . '_divider';

			$this->add_control(
				$field_key,
				array(
					'type'      => \Elementor\Controls_Manager::DIVIDER,
					'separator' => $separator,
				)
			);
			++$this->add_divider_number;
		}

		public function add_border_shadow( $field_key, $selector = '', $label = '', $conditions = array() ) {

			if ( empty( $label ) ) {
				$label = __( 'Box Shadow', 'elementor' );
			}

			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => $field_key,
					'label'    => $label,
					'selector' => $selector,
				)
			);
		}

		protected function add_font_size( $field_key, $selector, $label = '', $default = array(), $condition = array(), $size_unit = array(), $tablet_default = array(), $mobile_default = array(), $range = array() ) {
			if ( empty( $label ) ) {
				$label = __( 'Width', 'elementor' );
			}

			if ( empty( $size_unit ) ) {
				$size_unit = array( 'px', '%' );
			}

			if ( sizeof( $range ) == 0 ) {
				$range = array(
					'%'  => array(
						'min' => 0,
						'max' => 50,
					),
					'px' => array(
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					),
				);
			}

			$args = array(
				'label'      => $label,
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $size_unit,
				'range'      => $range,
				'default'    => array(
					'unit' => isset( $default['unit'] ) ? $default['unit'] : '%',
					'size' => isset( $default['size'] ) ? $default['size'] : 100,
				),
				'selectors'  => array(
					$selector => 'font-size: {{SIZE}}{{UNIT}};',
				),
				'condition'  => $condition,
			);

			if ( ! empty( $size_unit ) ) {
				$args['tablet_default'] = $tablet_default;
				$args['mobile_default'] = $mobile_default;
			}

			$this->add_responsive_control( $field_key, $args );
		}
	}
}
