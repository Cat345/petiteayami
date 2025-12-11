<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if ( ! class_exists( 'Elementor_WFOCU_Accept_Button_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Accept_Button_Widget
	 */
	class Elementor_WFOCU_Accept_Button_Widget extends \Elementor\Widget_Button {

		/**
		 * Get widget name.
		 *
		 *
		 * @return string Widget name.
		 */
		public function get_name() {
			return 'wfocu-accept-offer-button';
		}

		/**
		 * Get widget title.
		 *
		 * @return string Widget title.
		 */
		public function get_title() {
			return __( 'Accept Button', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * Get widget icon.
		 *
		 * @return string Widget icon.
		 */
		public function get_icon() {
			return 'wfocu-icon-button_yes';
		}

		/**
		 * Get widget categories.
		 *
		 * @return array Widget categories.
		 */
		public function get_categories() {
			return [ 'upstroke' ];
		}

		/**
		 * Register widget controls.
		 *
		 * Add different input fields to allow the user to change and customize the widget settings.
		 *
		 * @access protected
		 */
		protected function register_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => __( '--No Product--', 'woofunnels-upstroke-one-click-upsell' ) );

			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			$this->start_controls_section( 'section_button', [
				'label' => __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			] );

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}

			$this->add_control( 'selected_product', [
				'label'   => __( 'Product', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::SELECT,
				'default' => key( $product_options ),
				'options' => $product_options,
			] );

			do_action( 'wfocu_add_elementor_controls', $this, $offer_id, $products );

			$this->add_control( 'text', [
				'label'       => __( 'Title', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => [
					'active' => true,
				],
				'default'     => __( 'Yes, Add This To My Order', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Yes, Add This To My Order', 'woofunnels-upstroke-one-click-upsell' ),
			] );

			$this->add_control( 'subtitle', [
				'label'       => __( 'Subtitle', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => [
					'active' => true,
				],
				'default'     => __( 'We will ship it out in same package.', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'We will ship it out in same package.', 'woofunnels-upstroke-one-click-upsell' ),
			] );

			$this->add_control( 'text_spacing', [
				'label'      => __( 'Spacing between Title and Subtitle', 'woofunnels-upstroke-one-click-upsell' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [
					'em' => [
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					],
					'px' => [
						'min' => 2,
						'max' => 50,
					],
				],
				'selectors'  => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-button .elementor-button-text' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
			] );

			$this->add_responsive_control( 'align', [
				'label'        => __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => [
					'left'    => [
						'title' => __( 'Left', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center'  => [
						'title' => __( 'Center', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'   => [
						'title' => __( 'Right', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => __( 'Justified', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-justify',
					],
				],
				'prefix_class' => 'elementor%s-align-',
				'default'      => 'justify',
				'selectors'    => [
					'{{WRAPPER}} .elementor-button .elementor-button-subtitle'                                                   => 'font-size: 15px; line-height: 1.3; font-weight: 400; display: block; margin-top: 5px; font-family: "Open Sans",sans-serif;',
					'{{WRAPPER}} .elementor-button .elementor-button-text, {{WRAPPER}} .elementor-button .elementor-button-icon' => 'font-family: "Open Sans",sans-serif; font-size: 21px; font-weight: 700; line-height: 1.5;',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-button .elementor-button-text'             => 'font-size: 18px;',
				],
			] );

			$this->add_control( 'icon', [
				'label'       => __( 'Icon', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::ICON,
				'label_block' => true,
				'default'     => '',
			] );

			$this->add_control( 'icon_align', [
				'label'     => __( 'Icon Position', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'left',
				'options'   => [
					'left'  => __( 'Before', 'elementor' ),
					'right' => __( 'After', 'elementor' ),
				],
				'condition' => [
					'icon!' => '',
				],
			] );

			$this->add_control( 'icon_indent', [
				'label'     => __( 'Icon Spacing', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'max' => 50,
					],
				],
				'condition' => [
					'icon!' => '',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button .elementor-align-icon-right~span' => 'margin-right: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-button .elementor-align-icon-left~span'  => 'margin-left: {{SIZE}}{{UNIT}};',
				],
			] );
			$this->add_control( 'view', [
				'label'   => __( 'View', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::HIDDEN,
				'default' => 'traditional',
			] );

			$this->end_controls_tab();
			$this->end_controls_section();

			$this->start_controls_section( 'section_style', [
				'label' => __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->_add_typography( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .elementor-button .elementor-button-text, {{WRAPPER}} .elementor-button .elementor-button-icon, body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-button .elementor-button-text',
			] );

			$this->_add_typography( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'typography_subtitle',
				'label'    => 'Subtitle Typography',
				'selector' => '{{WRAPPER}} .elementor-button .elementor-button-subtitle',
			] );

			$this->start_controls_tabs( 'tabs_button_style' );

			$this->start_controls_tab( 'tab_button_normal', [
				'label' => __( 'Normal', 'elementor' ),
			] );

			$this->add_control( 'button_text_color', [
				'label'     => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} .elementor-button .elementor-button-text' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_subtitle_color', [
				'label'     => __( 'Subtitle Text Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} .elementor-button .elementor-button-subtitle' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_icon_color', [
				'label'     => __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} .elementor-button .elementor-button-icon' => 'color: {{VALUE}};',
				],
			] );

			$this->_add_color( 'background_color', [
				'label'   => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#70dc1d',

				'selectors' => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button' => 'background-color: {{VALUE}};',
				],
			] );

			$this->end_controls_tab();

			$this->start_controls_tab( 'tab_button_hover', [
				'label' => __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ),
			] );

			$this->add_control( 'hover_color', [
				'label'     => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover .elementor-button-text' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'hover_subtitle_color', [
				'label'     => __( 'Subtitle Text Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover .elementor-button-subtitle' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_hover_icon_color', [
				'label'     => __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover .elementor-button-icon' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_background_hover_color', [
				'label'     => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#89e047',
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'background-color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_hover_border_color', [
				'label'     => __( 'Border Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [
					'border_border!' => '',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'border-color: {{VALUE}};',
				],
			] );

			$this->add_control( 'hover_animation', [
				'label' => __( 'Hover Animation', 'woofunnels-upstroke-one-click-upsell' ),
				'type'  => Controls_Manager::HOVER_ANIMATION,
			] );

			$this->end_controls_tab();
			$this->end_controls_tabs();

			$this->add_group_control( Group_Control_Border::get_type(), [
				'name'      => 'border',
				'selector'  => '{{WRAPPER}} .elementor-button',
				'separator' => 'before',
			] );

			$this->add_control( 'border_radius', [
				'label'      => __( 'Border Radius', 'woofunnels-upstroke-one-click-upsell' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			] );

			$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
				'name'           => 'box_shadow',
				'selector'       => '{{WRAPPER}} .elementor-button',
				'fields_options' => [
					'box_shadow_type' => [
						'default' => 'yes',
					],
					'box_shadow'      => [
						'default' => [
							'horizontal' => 0,
							'vertical'   => 5,
							'blur'       => 0,
							'spread'     => 0,
							'color'      => '#00b211',
						],
					],
				],
			] );

			$this->add_responsive_control( 'text_padding', [
				'label'      => __( 'Padding', 'woofunnels-upstroke-one-click-upsell' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top'    => 12,
					'right'  => 5,
					'bottom' => 12,
					'left'   => 5,
					'unit'   => 'px',
				],
				'selectors'  => [
					'{{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator'  => 'before',
			] );

			$this->end_controls_section();
		}

		public function _add_color( $id, $args ) {
			$this->add_control( $id, $args );
		}

		public function _add_typography( $group, $args, $typography_type = 'TYPOGRAPHY_1' ) {

			if ( version_compare( ELEMENTOR_VERSION, '3.15.0', '>=' ) ) {
				$args['global'] = [
					'default' => Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT,
				];
			} else if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '2.8.0', '>=' ) ) {
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

			$product_key = $this->get_settings( 'selected_product' );
			$product_id  = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
				if ( $product instanceof WC_Product ) {
					$product_id = $product->get_id();
				}
			}

			if ( ! empty( $product_key ) ) {
				$this->add_render_attribute( 'button', 'data-key', $product_key );
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

			do_action( 'wfocu_add_custom_html_above_accept_button', $product_id, $product_key );
			?>
            <div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>
                <a <?php echo $this->get_render_attribute_string( 'button' ); ?> <?php WFOCU_Core()->template_loader->add_attributes_to_buy_button(); ?>>
					<?php $this->render_button_text(); ?>
                </a>
            </div>
			<?php

		}

		/**
		 * Render button widget output in the editor.
		 *
		 * Written as a Backbone JavaScript template and used to generate the live preview.
		 *
		 * @access protected
		 */
		protected function content_template() {
			?>
            <#
            view.addRenderAttribute( 'text', 'class', 'elementor-button-text' );
            view.addRenderAttribute( 'subtitle', 'class', 'elementor-button-subtitle' );

            view.addInlineEditingAttributes( 'text', 'none' );
            #>
            <div class="elementor-button-wrapper">
                <a id="{{ settings.button_css_id }}" class="elementor-button elementor-size-{{ settings.size }} elementor-animation-{{ settings.hover_animation }} wfocu_upsell" href="javascript:void(0);" role="button">
				<span class="elementor-button-content-wrapper" style="display: block;">
					<# if ( settings.icon ) { #>
					<span class="elementor-button-icon elementor-align-icon-{{ settings.icon_align }}">
						<i class="{{ settings.icon }}" aria-hidden="true"></i>
					</span>
					<# } #>
					<span style="display: block;" {{{ view.getRenderAttributeString( 'text' ) }}}>{{{settings.text }}}</span>
                    <span {{{ view.getRenderAttributeString( 'subtitle' ) }}}>{{{settings.subtitle }}}</span>
                    </span>
                </a>
            </div>
			<?php
		}

		/**
		 * Render button text.
		 *
		 * Render button widget text.
		 *
		 * @access protected
		 */
		protected function render_button_text() {
			$settings = $this->get_settings_for_display();

			$alignment = 'right';
			if ( isset( $settings['icon_align'] ) ) {
				$alignment = $settings['icon_align'];
			}
			$this->add_render_attribute( [
				'content-wrapper' => [
					'class' => 'elementor-button-content-wrapper',
				],
				'icon-align'      => [
					'class' => [
						'elementor-button-icon',
						'elementor-align-icon-' . $alignment,
					],
				],
				'text'            => [
					'class' => 'elementor-button-text',
				],
				'subtitle'        => [
					'class' => 'elementor-button-subtitle',
				],
			] );

			$is_icon_right = ( isset( $settings['icon_align'] ) && 'right' === $settings['icon_align'] ) ? true : false;

			$this->add_inline_editing_attributes( 'text', 'none' );
			?>
            <span <?php echo $this->get_render_attribute_string( 'content-wrapper' ); ?>>
			<?php if ( false === $is_icon_right && isset( $settings['icon'] ) && ! empty( $settings['icon'] ) ) : ?>
                <span <?php echo $this->get_render_attribute_string( 'icon-align' ); ?>>
				<i class="<?php echo esc_attr( $settings['icon'] ); ?>" aria-hidden="true"></i>
			</span>
			<?php endif; ?>
			<span style="display:inline-block;" <?php echo $this->get_render_attribute_string( 'text' ); ?>><?php echo $settings['text']; ?></span>
			<?php if ( true === $is_icon_right && isset( $settings['icon'] ) && ! empty( $settings['icon'] ) ) : ?>
                <span <?php echo $this->get_render_attribute_string( 'icon-align' ); ?>>
				<i class="<?php echo esc_attr( $settings['icon'] ); ?>" aria-hidden="true"></i>
			</span>
			<?php endif; ?>
			<span <?php echo $this->get_render_attribute_string( 'subtitle' ); ?>><?php echo $settings['subtitle']; ?></span>
		</span>
			<?php
		}
	}
}