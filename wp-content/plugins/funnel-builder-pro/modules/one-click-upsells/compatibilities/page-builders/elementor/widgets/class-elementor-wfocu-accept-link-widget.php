<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;

if ( ! class_exists( 'Elementor_WFOCU_Accept_Link_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Accept_Link_Widget
	 */
	class Elementor_WFOCU_Accept_Link_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wfocu-accept-offer-link';
		}

		/**
		 * Get widget title.
		 */
		public function get_title() {
			return __( 'Accept Link', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * @return string
		 */
		public function get_icon() {
			return 'wfocu-icon-link_yes';
		}

		/**
		 * Get widget categories.
		 *
		 * Retrieve the list of categories the oEmbed widget belongs to.
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
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}
			$this->start_controls_section( 'accept_section', [
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
				'label'       => __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'input_type'  => 'text',
				'default'     => __( 'Accept this offer', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Accept this offer', 'woofunnels-upstroke-one-click-upsell' ),
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
					'{{WRAPPER}} .elementor-wfocu-accpet' => 'background-color: transparent;',
				],
			] );

			$this->end_controls_tab();
			$this->end_controls_section();

			$this->start_controls_section( 'section_style', [
				'label' => __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->_add_typography( \Elementor\Group_Control_Typography::get_type(), [
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} a.elementor-wfocu-accept, {{WRAPPER}} .elementor-wfocu-accpet',
			] );

			$this->start_controls_tabs( 'tabs_wfocu_accpet_style' );

			$this->start_controls_tab( 'tab_wfocu_accpet_normal', [
				'label' => __( 'Normal', 'elementor' ),
			] );

			$this->add_control( 'wfocu_accpet_text_color', [
				'label'     => __( 'Text Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#777777',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-accpet, {{WRAPPER}} .elementor-wfocu-accpet' => 'color: {{VALUE}};',
				],
			] );

			$this->_add_color( 'background_color', [
				'label'   => __( 'Background Color', 'elementor' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#ffffff',

				'selectors' => [
					'.single-wfocu_offer {{WRAPPER}} a.elementor-wfocu-accpet, .single-wfocu_offer {{WRAPPER}} .elementor-wfocu-accpet' => 'background-color: {{VALUE}};',
				],
			] );

			$this->end_controls_tab();

			$this->start_controls_tab( 'tab_wfocu_accpet_hover', [
				'label' => __( 'Hover', 'elementor' ),
			] );

			$this->add_control( 'hover_color', [
				'label'     => __( 'Text Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#777777',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-accpet:hover, {{WRAPPER}} .elementor-wfocu-accpet:hover' => 'color: {{VALUE}};',
				],
			] );

			$this->add_control( 'wfocu_reject_background_hover_color', [
				'label'     => __( 'Background Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-accpet:hover, {{WRAPPER}} .elementor-wfocu-accpet:hover' => 'background-color: {{VALUE}};',
				],
			] );

			$this->add_control( 'wfocu_reject_hover_border_color', [
				'label'     => __( 'Border Color', 'elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [
					'border_border!' => '',
				],
				'selectors' => [
					'{{WRAPPER}} a.elementor-wfocu-accpet:hover, {{WRAPPER}} .elementor-wfocu-accpet:hover' => 'border-color: {{VALUE}};',
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

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_key = $this->get_settings( 'selected_product' );

			if ( ! empty( $product_key ) ) {
				$this->add_render_attribute( 'upstroke-accpet', 'data-key', $product_key );
			}

			$this->add_render_attribute( 'upstroke-accpet', 'href', 'javascript:void(0);' );
			$this->add_render_attribute( 'upstroke-accpet', 'class', 'elementor-wfocu-accpet elementor-wfocu-accpet-link wfocu_upsell wfocu-upsell-offer-link' ); ?>
            <a <?php echo $this->get_render_attribute_string( 'upstroke-accpet' ); ?> <?php WFOCU_Core()->template_loader->add_attributes_to_buy_button(); ?>>
				<?php $this->render_link_text(); ?>
            </a>
			<?php
		}


		/**
		 * Render link text.
		 *
		 * @access protected
		 */
		protected function render_link_text() {
			$settings = $this->get_settings_for_display();

			$this->add_inline_editing_attributes( 'text', 'none' );
			?>
            <span <?php echo $this->get_render_attribute_string( 'content-wrapper' ); ?>>
			<?php if ( ! empty( $settings['icon'] ) ) : ?>
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