<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if ( ! class_exists( 'Elementor_WFOCU_Reject_Button_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Reject_Button_Widget
	 */
	class Elementor_WFOCU_Reject_Button_Widget extends \Elementor\Widget_Button {

		/**
		 * Get widget name.
		 *
		 *
		 * @return string Widget name.
		 */
		public function get_name() {
			return 'wfocu-offer-reject-button';
		}

		/**
		 * Get widget title.
		 *
		 * @return string Widget title.
		 */
		public function get_title() {
			return __( 'Reject Button', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * Get widget icon.
		 *
		 * @return string Widget icon.
		 */
		public function get_icon() {
			return 'wfocu-icon-button_no';
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
		 * Adds different input fields to allow the user to change and customize the widget settings.
		 *
		 * @access protected
		 */
		protected function register_controls() {


			$this->start_controls_section( 'section_button', [
				'label' => __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			] );

			$this->add_control( 'text', [
				'label'       => __( 'Title', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'dynamic'     => [
					'active' => true,
				],
				'default'     => __( 'No thanks, I don’t want to take advantage of this one-time offer >', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ),

			] );

			$this->add_control( 'size', [
				'label'          => __( 'Size', 'woofunnels-upstroke-one-click-upsel' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => 'sm',
				'options'        => self::get_button_sizes(),
				'style_transfer' => true,
			] );

			$this->add_responsive_control( 'align', [
				'label'        => __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => \Elementor\Controls_Manager::CHOOSE,
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
				/*'selectors'    => [
					'{{WRAPPER}} .elementor-button .elementor-button-subtitle'                                                   => 'font-size: 15px; line-height: 1.3; font-weight: 400; display: block; margin-top: 5px; font-family: "Open Sans",sans-serif;',
					'{{WRAPPER}} .elementor-button .elementor-button-text, {{WRAPPER}} .elementor-button .elementor-button-icon' => 'font-family: "Open Sans",sans-serif; font-size: 21px; font-weight: 700; line-height: 1.5;',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-button .elementor-button-text'             => 'font-size: 18px;',
				],*/
			] );

			$this->add_control( 'icon', [
				'label'       => __( 'Icon', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => \Elementor\Controls_Manager::ICON,
				'label_block' => true,
				'default'     => '',
			] );

			$this->add_control( 'icon_align', [
				'label'     => __( 'Icon Position', 'elementor' ),
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
				'label'     => __( 'Icon Spacing', 'elementor' ),
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
					'{{WRAPPER}} .elementor-button .elementor-align-icon-right' => 'margin-left: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-button .elementor-align-icon-left'  => 'margin-right: {{SIZE}}{{UNIT}};',
				],
			] );
			$this->add_control( 'view', [
				'label'   => __( 'View', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => 'traditional',
			] );

			$this->end_controls_tab();
			$this->end_controls_section();

			$this->start_controls_section( 'section_style', [
				'label' => __( 'Reject Offer', 'elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			] );

			$this->_add_typography( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button',
			] );

			$this->start_controls_tabs( 'tabs_button_style' );

			$this->start_controls_tab( 'tab_button_normal', [
				'label' => __( 'Normal', 'elementor' ),
			] );

			$this->add_control( 'button_text_color', [
				'label'     => __( 'Text Color', 'elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button .elementor-button-text' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_icon_color', [
				'label'     => __( 'Icon Color', 'elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button .elementor-button-icon' => 'color: {{VALUE}};',
				],
			] );

			$this->_add_color( 'background_color', [
				'label'   => __( 'Background Color', 'elementor' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#d9534f',

				'selectors' => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button' => 'background-color: {{VALUE}};',
				],
			] );

			$this->end_controls_tab();

			$this->start_controls_tab( 'tab_button_hover', [
				'label' => __( 'Hover', 'elementor' ),
			] );

			$this->add_control( 'hover_color', [
				'label'     => __( 'Text Color', 'elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} a.elementor-button:hover, {{WRAPPER}} .elementor-button:hover .elementor-button-text' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_hover_icon_color', [
				'label'     => __( 'Icon Color', 'elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button:hover .elementor-button-icon' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_background_hover_color', [
				'label'     => __( 'Background Color', 'elementor' ),
				'default'   => '#d9534f',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} a.elementor-button:hover, {{WRAPPER}} .elementor-button:hover' => 'background-color: {{VALUE}};',
				],
			] );

			$this->add_control( 'button_hover_border_color', [
				'label'     => __( 'Border Color', 'elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'condition' => [
					'border_border!' => '',
				],
				'selectors' => [
					'{{WRAPPER}} a.elementor-button:hover, {{WRAPPER}} .elementor-button:hover' => 'border-color: {{VALUE}};',
				],
			] );

			$this->add_control( 'hover_animation', [
				'label' => __( 'Hover Animation', 'elementor' ),
				'type'  => \Elementor\Controls_Manager::HOVER_ANIMATION,
			] );

			$this->end_controls_tabs();

			$this->add_group_control( Group_Control_Border::get_type(), [
				'name'      => 'border',
				'selector'  => '{{WRAPPER}} .elementor-button',
				'separator' => 'before',
			] );

			$this->add_control( 'border_radius', [
				'label'      => __( 'Border Radius', 'elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			] );

			$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
				'name'     => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .elementor-button',
			] );

			$this->add_responsive_control( 'text_padding', [
				'label'      => __( 'Padding', 'elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} a.elementor-button, {{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

			$this->add_render_attribute( 'wrapper', 'class', 'elementor-button-wrapper' );

			$this->add_render_attribute( 'button', 'href', 'javascript:void(0);' );
			$this->add_render_attribute( 'button', 'class', 'elementor-button elementor-button-link wfocu_skip_offer' );

			if ( ! empty( $settings['button_css_id'] ) ) {
				$this->add_render_attribute( 'button', 'id', $settings['button_css_id'] );
			}

			if ( ! empty( $settings['size'] ) ) {
				$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['size'] );
			}

			if ( isset( $settings['hover_animation'] ) && $settings['hover_animation'] ) {
				$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['hover_animation'] );
			}

			?>
            <div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>
                <a <?php echo $this->get_render_attribute_string( 'button' ); ?>>
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

            view.addInlineEditingAttributes( 'text', 'none' );
            #>
            <div class="elementor-button-wrapper">
                <a id="{{ settings.button_css_id }}" class="elementor-button elementor-size-{{ settings.size }} elementor-animation-{{ settings.hover_animation }}" href="javascript:void(0);" role="button">
				<span class="elementor-button-content-wrapper">
					<# if ( settings.icon ) { #>
					<span class="elementor-button-icon elementor-align-icon-{{ settings.icon_align }}">
						<i class="{{ settings.icon }}" aria-hidden="true"></i>
					</span>
					<# } #>
					<span {{{ view.getRenderAttributeString( 'text' ) }}}>{{{ settings.text }}}</span>
                    </span>
                </a>
            </div>
			<?php
		}

		/**
		 * Render button widget text.
		 *
		 * @access protected
		 */
		protected function render_button_text() {
			$settings  = $this->get_settings_for_display();
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
			] );

			$this->add_inline_editing_attributes( 'text', 'none' );
			?>
            <span <?php echo $this->get_render_attribute_string( 'content-wrapper' ); ?>>
			<?php if ( isset( $settings['icon'] ) && ! empty( $settings['icon'] ) ) : ?>
                <span <?php echo $this->get_render_attribute_string( 'icon-align' ); ?>>
				<i class="<?php echo esc_attr( $settings['icon'] ); ?>" aria-hidden="true"></i>
			</span>
			<?php endif; ?>
			<span <?php echo $this->get_render_attribute_string( 'text' ); ?>><?php echo $settings['text']; ?></span>
		</span>
			<?php
		}

	}
}