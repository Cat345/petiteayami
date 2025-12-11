<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;

if ( ! class_exists( 'Elementor_WFOCU_Reject_Link_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Reject_Link_Widget
	 */
	class Elementor_WFOCU_Reject_Link_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wfocu-offer-reject-link';
		}

		/**
		 * Get widget title.
		 */
		public function get_title() {
			return __( 'Reject Link', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'wfocu-icon-link_no-01';
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


			$this->start_controls_section( 'reject_section', [
				'label' => __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			] );

			$this->add_control( 'text', [
				'label'       => __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'input_type'  => 'text',
				'default'     => __( 'No thanks, I don’t want to take advantage of this one-time offer > ', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'No thanks, I don’t want to take advantage of this one-time offer > ', 'woofunnels-upstroke-one-click-upsell' ),

			] );

			$this->add_responsive_control( 'align', [
				'label'        => __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => [
					'left'   => [
						'title' => __( 'Left', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'prefix_class' => 'elementor%s-align-',
				'default'      => 'center',
				'selectors'    => [
					'{{WRAPPER}} a.elementor-wfocu-reject, {{WRAPPER}} .elementor-wfocu-reject' => 'font-size: 16px; display: block; font-wight: 400; margin-botton: 15px; text-decoration: underline;',
				],
			] );

			$this->end_controls_tab();
			$this->end_controls_section();

			$this->start_controls_section( 'section_style', [
				'label' => __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} a.elementor-wfocu-reject',
			] );

			$this->start_controls_tabs( 'tabs_wfocu_reject_style' );

			$this->start_controls_tab( 'tab_wfocu_reject_normal', [
				'label' => __( 'Normal', 'elementor' ),
			] );

			$this->add_control( 'wfocu_reject_text_color', [
				'label'     => __( 'Text Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#777777',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-reject, {{WRAPPER}} .elementor-wfocu-reject' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'wfocu_reject_background_hover_color', [
				'label'     => __( 'Background Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-reject span, {{WRAPPER}} .elementor-wfocu-reject span' => 'background-color: {{VALUE}};',
				],
			] );

			$this->end_controls_tab();

			$this->start_controls_tab( 'tab_wfocu_reject_hover', [
				'label' => __( 'Hover', 'elementor' ),
			] );

			$this->add_control( 'hover_color', [
				'label'     => __( 'Text Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#777777',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-reject:hover, {{WRAPPER}} .elementor-wfocu-reject:hover' => 'color: {{VALUE}};',
				],
			] );

			$this->_add_color( 'background_hover_color', [
				'label'     => __( 'Background Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-reject:hover span, {{WRAPPER}} .elementor-wfocu-accpet:hover span' => 'background: {{VALUE}};',
				],
			] );

			$this->add_control( 'wfocu_reject_hover_border_color', [
				'label'     => __( 'Border Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [
					'border_border!' => '',
				],
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-reject:hover, {{WRAPPER}} .elementor-wfocu-reject:hover' => 'border-color: {{VALUE}};',
				],
			] );

			$this->end_controls_tab();

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
			$this->add_render_attribute( 'upstroke-reject', 'href', 'javascript:void(0);' );
			$this->add_render_attribute( 'upstroke-reject', 'class', 'elementor-wfocu-reject elementor-wfocu-reject-link wfocu_skip_offer wfocu-skip-offer-link' );
			if ( isset( $settings['selected_product'] ) && ! empty( $settings['selected_product'] ) ) {
				$this->add_render_attribute( 'upstroke-reject', 'data-key', $settings['selected_product'] );
			} ?>

            <div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>


                <a <?php echo $this->get_render_attribute_string( 'upstroke-reject' ); ?>>
					<?php $this->render_link_text(); ?>
                </a></div>
			<?php
		}


		/**
		 *
		 * Render link text.
		 *
		 * @access protected
		 */
		protected function render_link_text() {
			$settings = $this->get_settings_for_display();

			$this->add_inline_editing_attributes( 'text', 'none' );
			?>
            <span <?php echo $this->get_render_attribute_string( 'content-wrapper' ); ?>>

			<span <?php echo $this->get_render_attribute_string( 'text' ); ?>><?php echo $settings['text']; ?></span>
		</span>
			<?php
		}

	}
}