<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

if ( ! class_exists( 'Elementor_WFOCU_Variation_Selector_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Variation_Selector_Widget
	 */
	#[\AllowDynamicProperties]
	class Elementor_WFOCU_Variation_Selector_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name.
		 *
		 * @return string Widget name.
		 */
		public function get_name() {
			return 'wfocu-variation-selector';
		}

		/**
		 * Get widget title.
		 *
		 * @return string Widget title.
		 */
		public function get_title() {
			return __( 'Variation Selector', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * Get widget icon.
		 *
		 * @return string Widget icon.
		 */
		public function get_icon() {
			return 'wfocu-icon-variation';
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
			return array( 'upstroke' );
		}


		/**
		 * Register widget controls.
		 *
		 * Adds different input fields to allow the user to change and customize the widget settings.
		 *
		 * @access protected
		 */
		protected function register_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$variables       = $products = array();
			$product_options = array( '0' => '--No Product--' );

			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();

				if ( in_array( $product->type, array( 'variable', 'variable-subscription' ), true ) ) {
					array_push( $variables, $key );
				}
			}

			$this->start_controls_section(
				'section_button',
				array(
					'label' => __( 'Variation Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'tab'   => Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'wfocu_el_qty_error_notice',
				array(
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => __( 'Variation dropdowns will only show for Variable products.', 'woofunnels-upstroke-one-click-upsell' ),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
					'condition'       => array(
						'selected_product!' => $variables,
					),
				)
			);

			$this->add_control(
				'selected_product',
				array(
					'label'   => __( 'Product', 'woofunnels-upstroke-one-click-upsell' ),
					'type'    => Controls_Manager::SELECT,
					'default' => key( $product_options ),
					'options' => $product_options,
				)
			);

			do_action( 'wfocu_add_elementor_controls', $this, $offer_id, $products );

			$this->add_control(
				'attr_value_block',
				array(
					'label'        => __( 'Stacked', 'elementor' ),
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
					'prefix_class' => 'elementor-attr_value-block-',
					'selectors'    => array(
						'{{WRAPPER}} .variations td' => 'display: block;',
						'{{WRAPPER}} .variations td label, {{WRAPPER}} .variations td select option' => 'font-weight: normal;',
					),
					'condition'    => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_responsive_control(
				'selector_spacing',
				array(
					'label'      => __( 'Spacing', 'elementor' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'em' => array(
							'min'  => 0,
							'max'  => 5,
							'step' => 0.1,
						),
					),
					'selectors'  => array(
						'body:not(.rtl) {{WRAPPER}}:not(.elementor-attr_value-block-yes) .variations .value' => 'padding-left: {{SIZE}}{{UNIT}}',
						'body.rtl {{WRAPPER}}:not(.elementor-attr_value-block-yes) .variations .value'       => 'padding-right: {{SIZE}}{{UNIT}}',
						'{{WRAPPER}}.elementor-attr_value-block-yes .variations .value'                      => 'margin-top: {{SIZE}}{{UNIT}}',
					),
					'condition'  => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_responsive_control(
				'selector_padding',
				array(
					'label'          => __( 'Padding', 'elementor' ),
					'type'           => Controls_Manager::DIMENSIONS,
					'size_units'     => array( 'px', 'em', '%' ),
					'default'        => array(
						'top'    => 0,
						'right'  => 0,
						'bottom' => 0,
						'left'   => 0,
						'unit'   => 'px',
					),
					'mobile_default' => array(
						'top'    => 0,
						'right'  => 0,
						'bottom' => 0,
						'left'   => 0,
						'unit'   => 'px',
					),
					'selectors'      => array(
						'{{WRAPPER}} .variations td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
					'separator'      => 'before',
					'condition'      => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_responsive_control(
				'align',
				array(
					'label'          => __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' ),
					'type'           => Controls_Manager::CHOOSE,
					'options'        => array(
						'left'   => array(
							'title' => __( 'Left', 'woofunnels-upstroke-one-click-upsell' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'woofunnels-upstroke-one-click-upsell' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'  => array(
							'title' => __( 'Right', 'woofunnels-upstroke-one-click-upsell' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'prefix_class'   => 'elementor%s-align-',
					'default'        => 'left',
					'tablet_default' => 'left',
					'mobile_default' => 'center',
					'selectors'      => array(
						'{{WRAPPER}} .variations td.value, {{WRAPPER}}.elementor-attr_value-block-yes .variations tr' => 'text-align: {{VALUE}};',
						'{{WRAPPER}} .variations tr td.label' => 'display:inline-block;text-align:left;',
					),
					'condition'      => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->end_controls_section();

			$this->start_controls_section(
				'section_product_description_style',
				array(
					'label'     => __( 'Variation Selector', 'elementor' ),
					'tab'       => Controls_Manager::TAB_STYLE,
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_control(
				'atrribute_labels',
				array(
					'label'     => __( 'Attribute Labels', 'elementor' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'      => 'text_typography',
					'label'     => __( 'Typography', 'elementor' ),
					'selector'  => '.single-wfocu_offer {{WRAPPER}} .wfocu_variation_selector_form .variations label',
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_control(
				'attribute_color',
				array(
					'label'     => __( 'Color', 'elementor' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#414349',
					'selectors' => array(
						'{{WRAPPER}} .variations label' => 'color: {{VALUE}}',
					),
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_control(
				'attribute_bg_color',
				array(
					'label'     => __( 'Background Color', 'elementor' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => 'transparent',
					'selectors' => array(
						'{{WRAPPER}} .variations label' => 'background-color: {{VALUE}}',
					),
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);
			$this->add_responsive_control(
				'label_min_width',
				array(
					'label'      => __( 'Min Width', 'elementor' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'default'    => array(
						'size' => 64,
						'unit' => 'px',
					),
					'range'      => array(
						'em' => array(
							'min'  => 5,
							'max'  => 20,
							'step' => 0.1,
						),
						'px' => array(
							'min' => 50,
							'max' => 500,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .variations .label' => 'min-width: {{SIZE}}{{UNIT}};',
					),
					'condition'  => array(
						'selected_product' => $variables,
						'attr_value_block' => '',
					),
				)
			);

			$this->add_control(
				'atrribute_value',
				array(
					'label'     => __( 'Attribute Value', 'elementor' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->_add_typography(
				Group_Control_Typography::get_type(),
				array(
					'name'      => 'attr_value_typography',
					'selector'  => '{{WRAPPER}} .variations .value select',
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'      => 'attr_value_border',
					'selector'  => '{{WRAPPER}} .variations .value select',
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_control(
				'attr_value_border_radius',
				array(
					'label'     => __( 'Border Radius', 'elementor' ),
					'type'      => Controls_Manager::DIMENSIONS,
					'selectors' => array(
						'{{WRAPPER}} .variations .value select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_control(
				'attr_value_padding',
				array(
					'label'     => __( 'Padding', 'elementor' ),
					'type'      => Controls_Manager::DIMENSIONS,
					'selectors' => array(
						'{{WRAPPER}} .variations .value select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_control(
				'attr_value_bg_color',
				array(
					'label'     => __( 'Background Color', 'elementor' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#ffffff',
					'selectors' => array(
						'{{WRAPPER}} .variations .value select' => 'background-color: {{VALUE}}',
					),
					'condition' => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->add_responsive_control(
				'attr_value_width',
				array(
					'label'      => __( 'Width', 'elementor' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em', '%' ),
					'default'    => array(
						'size' => 250,
						'unit' => 'px',
					),
					'range'      => array(
						'em' => array(
							'min'  => 5,
							'max'  => 20,
							'step' => 0.1,
						),
						'px' => array(
							'min' => 100,
							'max' => 800,
						),
						'%'  => array(
							'min' => 1,
							'max' => 100,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .variations .value select' => 'color: #333; font-weight: 300; display: inline-block; box-shadow: none; -webkit-box-shadow: none; -moz-box-shadow: none;',
						'{{WRAPPER}} .variations .value select, body[data-elementor-device-mode="desktop"] {{WRAPPER}}.elementor-attr_value-block-yes.elementor-align-center .variations .label, body[data-elementor-device-mode="mobile"] {{WRAPPER}}.elementor-attr_value-block-yes.elementor-mobile-align-center .variations .label,  body[data-elementor-device-mode="tablet"] {{WRAPPER}}.elementor-attr_value-block-yes.elementor-tablet-align-center .variations .label' => 'width: {{SIZE}}{{UNIT}}; margin: auto; display: inline-block',
						'{{WRAPPER}}:not(.elementor-attr_value-block-yes) .variations td' => 'display: inline-block;',
						'{{WRAPPER}}'                  => 'margin: 0 auto;',
						'{{WRAPPER}} .variations .value select, {{WRAPPER}} .variations .value select option' => 'color: #333; box-shadow: none; -webkit-box-shadow: none; -moz-box-shadow: none;',
						'{{WRAPPER}} .variations .value select' => 'margin-top: 0;',
						'{{WRAPPER}} .variations tr:not(:last-child)' => 'margin-bottom: 24px; display:block;',
						'{{WRAPPER}}, {{WRAPPER}} .variations tr:last-child td.value' => 'margin-bottom: 0px !important;',
						'{{WRAPPER}} .variations .label, {{WRAPPER}} .variations .label label, {{WRAPPER}} .variations .value select, {{WRAPPER}} .variations .value select option ' => 'font-family: "Open Sans",sans-serif; font-weight: 300;',
						'{{WRAPPER}} table.variations' => 'margin-bottom: 24px;',
						'{{WRAPPER}}.elementor-attr_value-block-yes .variations .label' => 'line-height: 1; padding-bottom: 8px;',
						'{{WRAPPER}} .variations tr td.label' => 'width:{{SIZE}}{{UNIT}}',
					),
					'condition'  => array(
						'selected_product' => $variables,
					),
				)
			);

			$this->end_controls_section();
		}

		public function _add_color( $id, $args ) {
			$this->add_control( $id, $args );
		}

		public function _add_typography( $group, $args, $typography_type = 'TYPOGRAPHY_1' ) {

			if ( version_compare( ELEMENTOR_VERSION, '3.15.0', '>=' ) ) {
				$args['global'] = array(
					'default' => Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT,
				);
			} elseif ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '2.8.0', '>=' ) ) {
				$args['scheme'] = \Elementor\Core\Schemes\Typography::TYPOGRAPHY_4;
			} else {
				$args['scheme'] = \Elementor\Typography::TYPOGRAPHY_4;
			}

			$this->add_group_control( $group, $args );
		}

		/**
		 * Render widget output on the frontend.
		 *
		 * Written in PHP and used to generate the final HTML.
		 *
		 * @access protected
		 */
		protected function render() {

			$settings = $this->get_settings_for_display();
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product_key  = $this->get_settings( 'selected_product' );

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			$is_variable = false;

			if ( ! empty( $product_key ) ) {

				if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
					$is_variable = true;
				}
			}

			if ( false === $is_variable ) {
				return;
			}

			$this->add_render_attribute( 'wrapper', 'class', 'elementor-button-wrapper' );

			$this->add_render_attribute( 'button', 'href', 'javascript:void(0);' );
			$this->add_render_attribute( 'button', 'class', 'elementor-button elementor-button-link wfocu_upsell' );

			if ( ! empty( $settings['button_css_id'] ) ) {
				$this->add_render_attribute( 'button', 'id', $settings['button_css_id'] );
			}

			if ( ! empty( $settings['size'] ) ) {
				$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['size'] );
			}

			if ( isset( $settings['hover_animation'] ) && $settings['hover_animation'] ) {
				$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['hover_animation'] );
			}

			if ( ! empty( $product_key ) ) {
				$this->add_render_attribute( 'button', 'data-key', $product_key ); ?>
				<div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>
					<?php
					if ( true === $is_variable ) {
						echo do_shortcode( '[wfocu_variation_selector_form key="' . $product_key . '"]' );
					}
					?>
				</div>
				<?php
			}
		}
	}
}