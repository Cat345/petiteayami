<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! class_exists( 'Elementor_WFOCU_Price_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Price_Widget
	 */
	class Elementor_WFOCU_Price_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name.
		 *
		 * @return string Widget name.
		 */
		public function get_name() {
			return 'wfocu-offer-price';
		}

		/**
		 * Get widget title.
		 *
		 * @return string Widget title.
		 */
		public function get_title() {
			return __( 'Offer Price', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * Get widget icon.
		 *
		 * @return string Widget icon.
		 */
		public function get_icon() {
			return 'wfocu-icon-product_offer';
		}

		/**
		 * Get widget categories.
		 *
		 * Retrieve the list of categories the upstroke widget belongs to.
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

			$subscriptions   = $products = array();
			$product_options = array( '0' => __( '--No Product--', 'woofunnels-upstroke-one-click-upsell' ) );

			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
				if ( in_array( $product->type, array( 'subscription', 'variable-subscription', 'subscription_variation' ), true ) ) {
					array_push( $subscriptions, $key );
				}
			}

			$this->start_controls_section( 'section_price', [
				'label' => __( 'Prices', 'woofunnels-upstroke-one-click-upsell' ),
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

			$this->add_responsive_control( 'text_align', [
				'label'     => __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
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
				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper' => 'text-align: {{VALUE}}',
				],
				'separator' => 'before',
			] );

			$this->add_responsive_control( 'sale_price_spacing', [
				'label'       => __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ),
				'description' => __( 'Between regular and offer blocks', 'elementor-pro' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em' ],
				'default'     => [
					'size' => 5,
					'unit' => 'px',
				],
				'range'       => [
					'em' => [
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					],
				],
				'selectors'   => [
					'body:not(.rtl) {{WRAPPER}}:not(.elementor-price_block-yes) .reg_wrapper' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}}:not(.elementor-price_block-yes) .reg_wrapper'       => 'margin-left: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-price_block-yes .reg_wrapper'                      => 'margin-bottom: {{SIZE}}{{UNIT}}',

					'{{WRAPPER}} .elementor-price-wrapper .reg_wrapper strike'                                                     => 'font-family:  "Open Sans",sans-serif; font-weight: 400',
					'{{WRAPPER}} .elementor-price-wrapper .reg_wrapper .wfocu-reg-label'                                           => 'font-family:  "Open Sans",sans-serif; font-weight: normal',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .reg_wrapper .wfocu-reg-label' => 'font-size: 17px; font-family:  "Open Sans",sans-serif;',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .reg_wrapper strike'           => 'font-size: 21px; font-family:  "Open Sans",sans-serif;',

					'{{WRAPPER}} .elementor-price-wrapper .offer_wrapper span'                                                             => 'font-family:  "Open Sans",sans-serif; font-weight: 400',
					'{{WRAPPER}} .elementor-price-wrapper .offer_wrapper .wfocu-offer-label'                                               => 'font-family:  "Open Sans",sans-serif; font-weight: normal',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .offer_wrapper .wfocu-offer-label'     => 'font-size: 17px; font-family:  "Open Sans",sans-serif;',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .offer_wrapper .wfocu-sale-price span' => 'font-size: 21px; font-family:  "Open Sans",sans-serif;',

					'{{WRAPPER}} .elementor-price-wrapper .signup_details_wrap'                                                => 'font-weight: 400; line-heignt: 1; padding-top: 7px; font-family:  "Open Sans",sans-serif;',
					'{{WRAPPER}} .elementor-price-wrapper .signup_details_wrap span'                                           => 'font-size: 13px; line-heignt: 1.6; font-style: italic; font-weight: 400; font-family:  "Open Sans",sans-serif;',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .signup_details_wrap span' => 'font-size: 14px; font-family:  "Open Sans",sans-serif;',

					'{{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap'                                                => 'line-heignt: 1; padding-top: 7px;',
					'{{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap span'                                           => 'font-size: 13px; line-heignt: 1.6; font-style: italic; font-weight: 400; font-family:  "Open Sans",sans-serif;',
					'body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap span' => 'font-size: 14px; font-family:  "Open Sans",sans-serif;',
				],
			] );

			$this->end_controls_section();
			//Style Tab start
			$this->start_controls_section( 'section_price_style', [
				'label' => __( 'Prices', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			//Style Regular Price start
			$this->add_control( 'regular_heading', [
				'label'     => __( 'Regular Price', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_control( 'show_reg_price', [
				'label'        => __( 'Show', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			] );

			$this->add_control( 'reg_label', [
				'label'       => __( 'Label', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Regular Price: ', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Regular Price: ', 'woofunnels-upstroke-one-click-upsell' ),
				'condition'   => [
					'show_reg_price' => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'reg_label_typography',
				'label'     => __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .wfocu-reg-label, body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .reg_wrapper .wfocu-reg-label',
				'condition' => [
					'show_reg_price' => 'yes',
				],
			] );

			$this->_add_color( 'reg_label_color', [
				'label'     => __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#8d8e92',
				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .wfocu-reg-label' => 'color: {{VALUE}}',
				],
				'condition' => [
					'show_reg_price' => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'reg_price_typography',
				'label'     => __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .reg_wrapper strike, body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .reg_wrapper strike',
				'condition' => [
					'show_reg_price' => 'yes',
				],
			] );

			$this->_add_color( 'price_color', [
				'label'     => __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#8d8e92',
				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper strike, {{WRAPPER}} .elementor-price-wrapper strike span' => 'color: {{VALUE}}',
				],
				'condition' => [
					'show_reg_price' => 'yes',
				],
			] );

			$this->add_responsive_control( 'reg_label_spacing', [
				'label'       => __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ),
				'description' => __( 'Between label and price', 'elementor-pro' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em' ],
				'range'       => [
					'em' => [
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					],
				],
				'selectors'   => [
					'body:not(.rtl) {{WRAPPER}} .wfocu-reg-label' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .wfocu-reg-label'       => 'margin-left: {{SIZE}}{{UNIT}}',
				],
				'condition'   => [
					'show_reg_price' => 'yes',
				],
			] );
			//Style Regualar Price end

			//Style Offer Price start
			$this->add_control( 'offer_heading', [
				'label'     => __( 'Offer Price', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );
			$this->add_control( 'show_offer_price', [
				'label'        => __( 'Show', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			] );
			$this->add_control( 'slider_enabled', [
				'label'        => __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ),
				'prefix_class' => 'elementor-price_block-',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => [
					'{{WRAPPER}} .elementor-price-wrapper .reg_wrapper' => 'display: block;',
				],
			] );

			$this->add_control( 'offer_label', [
				'label'       => __( 'Label', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Offer Price: ', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Offer Price: ', 'woofunnels-upstroke-one-click-upsell' ),
				'condition'   => [
					'show_offer_price' => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'offer_label_typography',
				'label'     => __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .wfocu-offer-label, body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .offer_wrapper .wfocu-offer-label',
				'condition' => [
					'show_offer_price' => 'yes',
				],
			] );

			$this->_add_color( 'offer_label_color', [
				'label'   => __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#414349',

				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .wfocu-offer-label' => 'color: {{VALUE}}',
				],
				'condition' => [
					'show_offer_price' => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'offer_price_typography',
				'label'     => __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .offer_wrapper .wfocu-sale-price span, body[data-elementor-device-mode="mobile"] {{WRAPPER}} .elementor-price-wrapper .offer_wrapper .wfocu-sale-price span',
				'condition' => [
					'show_offer_price' => 'yes',
				],
			] );

			$this->add_control( 'offer_price_color', [
				'label'     => __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#414349',
				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .wfocu-sale-price span' => 'color: {{VALUE}}',
				],
				'condition' => [
					'show_offer_price' => 'yes',
				],
			] );

			$this->add_responsive_control( 'offer_label_spacing', [
				'label'      => __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [
					'em' => [
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					],
				],
				'selectors'  => [
					'body:not(.rtl) {{WRAPPER}} .wfocu-offer-label' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .wfocu-offer-label'       => 'margin-left: {{SIZE}}{{UNIT}}',
				],
				'condition'  => [
					'show_offer_price' => 'yes',
				],
			] );

			//Style Offer Price end

			//Style Signup fee start
			$this->add_control( 'signup_fee_heading', [
				'label'     => __( 'Signup Fee', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'selected_product' => $subscriptions,
				],
			] );

			$this->add_control( 'show_signup_fee', [
				'label'        => __( 'Show', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [
					'selected_product' => $subscriptions,
				],
			] );

			$this->add_control( 'signup_label', [
				'label'       => __( 'Label', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Signup Fee: ', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Signup Fee: ', 'woofunnels-upstroke-one-click-upsell' ),
				'condition'   => [
					'selected_product' => $subscriptions,
					'show_signup_fee'  => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'signup_label_typography',
				'label'     => __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .signup_details_wrap .signup_price_label',
				'condition' => [
					'selected_product' => $subscriptions,
					'show_signup_fee'  => 'yes',
				],
			] );

			$this->_add_color( 'label_color', [
				'label'   => __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#414349',

				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .signup_details_wrap .signup_price_label' => 'color: {{VALUE}}',
				],
				'condition' => [
					'selected_product' => $subscriptions,
					'show_signup_fee'  => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'signup_fee_typography',
				'label'     => __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .signup_details_wrap span.amount, .single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .signup_details_wrap span.amount span',
				'condition' => [
					'selected_product' => $subscriptions,
					'show_signup_fee'  => 'yes',
				],
			] );

			$this->_add_color( 'signup_fee_color', [
				'label'   => __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#414349',

				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .signup_details_wrap' => 'color: {{VALUE}}',
				],
				'condition' => [
					'selected_product' => $subscriptions,
					'show_signup_fee'  => 'yes',
				],
			] );

			$this->add_responsive_control( 'signup_label_spacing', [
				'label'      => __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [
					'em' => [
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					],
				],
				'selectors'  => [
					'body:not(.rtl) {{WRAPPER}} .signup_details_wrap .signup_price_label' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .signup_details_wrap .signup_price_label'       => 'margin-left: {{SIZE}}{{UNIT}}',
				],
				'condition'  => [
					'selected_product' => $subscriptions,
					'show_signup_fee'  => 'yes',
				],
			] );
			//Style Signup fee end

			//Style Recurring Price start
			$this->add_control( 'rec_price_heading', [
				'label'     => __( 'Recurring Price', 'woofunnels-upstroke-one-click-upsell' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'selected_product' => $subscriptions,
				],
			] );
			$this->add_control( 'show_rec_price', [
				'label'        => __( 'Show', 'elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [
					'selected_product' => $subscriptions,
				],
			] );
			$this->add_control( 'recurring_label', [
				'label'       => __( 'Label', 'woofunnels-upstroke-one-click-upsell' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Recurring Total: ', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder' => __( 'Recurring Total: ', 'woofunnels-upstroke-one-click-upsell' ),
				'condition'   => [
					'selected_product' => $subscriptions,
					'show_rec_price'   => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'rec_label_typography',
				'label'     => __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap .recurring_price_label',
				'condition' => [
					'selected_product' => $subscriptions,
					'show_rec_price'   => 'yes',
				],
			] );

			$this->_add_color( 'rec_label_color', [
				'label'   => __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#414349',

				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap .recurring_price_label' => 'color: {{VALUE}}',
				],
				'condition' => [
					'selected_product' => $subscriptions,
					'show_rec_price'   => 'yes',
				],
			] );

			$this->_add_typography( Group_Control_Typography::get_type(), [
				'name'      => 'rec_price_typography',
				'label'     => __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ),
				'selector'  => '.single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap .subscription-details, .single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap .amount, .single-wfocu_offer {{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap .amount span',
				'condition' => [
					'selected_product' => $subscriptions,
					'show_rec_price'   => 'yes',
				],
			] );

			$this->_add_color( 'rec_fee_color', [
				'label'   => __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#414349',

				'selectors' => [
					'{{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap span, {{WRAPPER}} .elementor-price-wrapper .recurring_details_wrap .subscription-details' => 'color: {{VALUE}}',
				],
				'condition' => [
					'selected_product' => $subscriptions,
					'show_rec_price'   => 'yes',
				],
			] );

			$this->add_responsive_control( 'rec_label_spacing', [
				'label'      => __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [
					'em' => [
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					],
				],
				'selectors'  => [
					'body:not(.rtl) {{WRAPPER}} .recurring_details_wrap .recurring_price_label' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .recurring_details_wrap .recurring_price_label'       => 'margin-left: {{SIZE}}{{UNIT}}',
				],
				'condition'  => [
					'selected_product' => $subscriptions,
					'show_rec_price'   => 'yes',
				],
			] );
			//Style Recurring Price start

			$this->end_controls_section();


		}

		public function _add_color( $id, $args ) {
			$this->add_control( $id, $args );
		}

		public function _add_typography( $group, $args, $typography_type = 'TYPOGRAPHY_1' ) {

			if ( version_compare( ELEMENTOR_VERSION, '3.15.0', '>=' ) ) {
				$args['global'] = [
					'default' => Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
				];
			} else if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '2.8.0', '>=' ) ) {
				$args['scheme'] = \Elementor\Core\Schemes\Typography::TYPOGRAPHY_1;
			} else {
				$args['scheme'] = \Elementor\Typography::TYPOGRAPHY_1;
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

			if ( ! isset( $settings['selected_product'] ) || empty( $settings['selected_product'] ) ) {
				return;
			}

			$this->add_render_attribute( 'wrapper', 'class', 'elementor-price-wrapper' );

			$this->add_render_attribute( 'button', 'href', 'javascript:void(0);' );
			$this->add_render_attribute( 'button', 'class', 'elementor-button elementor-button-link wfocu_upsell' );

			if ( isset( $settings['selected_product'] ) ) {
				$this->add_render_attribute( 'button', 'data-key', $settings['selected_product'] );
			}

			if ( ! empty( $settings['button_css_id'] ) ) {
				$this->add_render_attribute( 'button', 'id', $settings['button_css_id'] );
			}

			if ( ! empty( $settings['size'] ) ) {
				$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['size'] );
			}

			if ( isset( $settings['hover_animation'] ) && $settings['hover_animation'] ) {
				$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['hover_animation'] );
			}

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

			?>
            <div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>
                <div class="elementor-element elementor-element elementor-widget elementor-widget-wfocu_price" data-element_type="wfocu_price.default">
                    <div class="elementor-widget-container">
                        <div class="elementor-price-wrapper">
							<?php

							/** Price */
							$regular_price     = ( isset( $settings['show_reg_price'] ) && 'yes' === $settings['show_reg_price'] ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' ) : 0;
							$sale_price        = ( isset( $settings['show_offer_price'] ) && 'yes' === $settings['show_offer_price'] ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' ) : 0;
							$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
							$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

							$reg_label   = isset( $settings['reg_label'] ) ? '<span class="wfocu-reg-label">' . $settings['reg_label'] . '</span>' : '';
							$offer_label = isset( $settings['offer_label'] ) ? '<span class="wfocu-offer-label">' . $settings['offer_label'] . '</span>' : '';

							$price_output = '';
							if ( round( $sale_price_raw, 2 ) !== round( $regular_price_raw, 2 ) ) {
								if ( isset( $settings['show_reg_price'] ) && 'yes' === $settings['show_reg_price'] ) {
									$price_output .= '<span class="reg_wrapper">' . $reg_label . '<span class="wfocu-regular-price"><strike>' . $regular_price . '</strike></span></span>';
								}
								if ( isset( $settings['show_offer_price'] ) && 'yes' === $settings['show_offer_price'] ) {
									$price_output .= '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>';
								}
							} else {
								if ( 'variable' === $product->get_type() ) {
									$price_output .= sprintf( '<span class="wfocu-regular-price"><strike><span class="wfocu_variable_price_regular" style="display: none;" data-key="%s"></span></strike></span>', $product_key );
									$price_output .= $sale_price ? '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
								} else {
									$price_output .= $sale_price ? '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
								}
							}

							echo $price_output;

							if ( isset( $settings['show_signup_fee'] ) && 'yes' === $settings['show_signup_fee'] ) {
								$signup_label = isset( $settings['signup_label'] ) ? $settings['signup_label'] : '';
								echo WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . $signup_label . '"}}' );
							}

							if ( isset( $settings['show_rec_price'] ) && 'yes' === $settings['show_rec_price'] ) {
								$recurring_label = isset( $settings['recurring_label'] ) ? $settings['recurring_label'] : '';
								echo WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label="' . $recurring_label . '"}}' );
							} ?>

                        </div>
                    </div>
                </div>
            </div>
			<?php
		}
	}
}