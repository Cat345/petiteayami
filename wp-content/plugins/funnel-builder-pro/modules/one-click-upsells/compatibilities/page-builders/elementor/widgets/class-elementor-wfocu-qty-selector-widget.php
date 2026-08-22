<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

if ( ! class_exists( 'Elementor_WFOCU_Qty_Selector_Widget' ) ) {
	/**
	 * Class Elementor_WFOCU_Qty_Selector_Widget
	 */
	#[\AllowDynamicProperties]
	class Elementor_WFOCU_Qty_Selector_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name.
		 *
		 * @return string Widget name.
		 */
		public function get_name() {
			return 'wfocu-qty-selector';
		}

		/**
		 * Upsell widgets render against the live offer/session for the current request and must
		 * never have their markup baked into Elementor's element cache.
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
		 * Get widget title.
		 *
		 * @return string Widget title.
		 */
		public function get_title() {
			return __( 'Quantity Selector', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * Get widget icon.
		 *
		 * @return string Widget icon.
		 */
		public function get_icon() {
			return 'wfocu-icon-quantity';
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

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}
			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->name;
			}

			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;

			$this->start_controls_section(
				'section_button',
				array(
					'label' => __( 'Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'tab'   => Controls_Manager::TAB_CONTENT,
				)
			);

			if ( false === $qty_selector_enabled ) {
				$upsell_id = get_post_meta( $offer_id, '_funnel_id', true );
				$funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );

				if ( ! empty( $funnel_id ) && absint( $funnel_id ) > 0 ) {
					$products_url = add_query_arg(
						array(
							'page'      => 'bwf',
							'path'      => '/funnel-offer/' . $offer_id . '/product',
							'funnel_id' => $funnel_id,
						),
						admin_url( 'admin.php' )
					);
				} else {
					$products_url = add_query_arg(
						array(
							'page'    => 'upstroke',
							'section' => 'offers',
							'edit'    => $upsell_id,
						),
						admin_url( 'admin.php' )
					);
				}

				$message = sprintf(
					/* translators: %1$s: Opening anchor tag, %2$s: Closing anchor tag */
					'%1$s' . __( 'The quantity selector is currently unavailable for this offer. Please enable customers to choose their preferred quantity when purchasing this upsell product(s) from the "Products" tab', 'woofunnels-upstroke-one-click-upsell' ) . '%2$s',
					'<a href="' . esc_url( $products_url ) . '" target="_blank">',
					'</a>'
				);

				$this->add_control(
					'wfocu_el_qty_error_notice',
					array(
						'type'            => Controls_Manager::RAW_HTML,
						'raw'             => $message,
						'content_classes' => 'elementor-panel-alert elementor-panel-alert-danger',
					)
				);
			}

			if ( true === $qty_selector_enabled ) {
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
					'text',
					array(
						'label'   => __( 'Text', 'woofunnels-upstroke-one-click-upsell' ),
						'type'    => Controls_Manager::TEXT,
						'dynamic' => array(
							'active' => true,
						),
						'default' => __( 'Quantity', 'woofunnels-upstroke-one-click-upsell' ),
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
							'{{WRAPPER}} .wfocu-prod-qty-wrapper' => 'text-align: {{VALUE}}',
						),
					)
				);

				$this->add_control(
					'slider_enabled',
					array(
						'label'        => __( 'Stacked', 'elementor' ),
						'prefix_class' => 'elementor-qnty_block-',
						'type'         => Controls_Manager::SWITCHER,
						'return_value' => 'yes',
						'default'      => 'yes',
						'selectors'    => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper label' => 'display: block; background: transparent; font-weight: normal;',
						),
					)
				);

				$this->add_responsive_control(
					'qty_dropdown_spacing',
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
							'body:not(.rtl) {{WRAPPER}}:not(.elementor-qnty_block-yes) .wfocu-select-qty-input' => 'margin-left: {{SIZE}}{{UNIT}}',
							'body.rtl {{WRAPPER}}:not(.elementor-qnty_block-yes) .wfocu-select-qty-input'       => 'margin-right: {{SIZE}}{{UNIT}}',
							'{{WRAPPER}}.elementor-qnty_block-yes .wfocu-select-qty-input'                      => 'margin-top: {{SIZE}}{{UNIT}}',
						),
					)
				);

				$this->end_controls_section();

				/**
				 * STYLE RELATED CONTROLS
				 */
				$this->start_controls_section(
					'section_atc_quantity_style',
					array(
						'label' => __( 'Quantity', 'elementor' ),
						'tab'   => Controls_Manager::TAB_STYLE,
					)
				);

				$this->_add_typography(
					Group_Control_Typography::get_type(),
					array(
						'name'     => 'quantity_typography',
						'selector' => '.single-wfocu_offer {{WRAPPER}} .wfocu-prod-qty-wrapper label',
					)
				);

				$this->add_control(
					'quantity_text_color',
					array(
						'label'     => __( 'Text Color', 'elementor' ),
						'type'      => Controls_Manager::COLOR,
						'default'   => '#414349',
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper label' => 'color: {{VALUE}};',
						),
					)
				);

				$this->add_control(
					'quantity_bg_color',
					array(
						'label'     => __( 'Background Color', 'elementor' ),
						'type'      => Controls_Manager::COLOR,
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper label' => 'background-color: {{VALUE}}',
						),
					)
				);

				$this->add_control(
					'qty_block_margin',
					array(
						'label'     => __( 'Margin', 'elementor' ),
						'type'      => Controls_Manager::DIMENSIONS,
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						),
					)
				);

				$this->add_control(
					'qty_dropdown',
					array(
						'label'     => __( 'Quantity Dropdown', 'elementor' ),
						'type'      => Controls_Manager::HEADING,
						'separator' => 'before',
					)
				);

				$this->_add_typography(
					Group_Control_Typography::get_type(),
					array(
						'name'     => 'qty_dropdown_typography',
						'selector' => '.single-wfocu_offer {{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input',
						'exclude'  => array( 'text-transform' ),
					)
				);

				$this->add_group_control(
					Group_Control_Border::get_type(),
					array(
						'name'     => 'qty_dropdown_border',
						'selector' => '{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input',
					)
				);

				$this->add_control(
					'qty_dropdown_border_radius',
					array(
						'label'     => __( 'Border Radius', 'elementor' ),
						'type'      => Controls_Manager::DIMENSIONS,
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						),
					)
				);

				$this->add_control(
					'qty_dropdown_padding',
					array(
						'label'     => __( 'Padding', 'elementor' ),
						'type'      => Controls_Manager::DIMENSIONS,
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						),
					)
				);

				$this->add_control(
					'qty_dropdown_color',
					array(
						'label'     => __( 'Text Color', 'elementor' ),
						'type'      => Controls_Manager::COLOR,
						'default'   => '#8d8e92',
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input' => 'color: {{VALUE}}',
						),
					)
				);

				$this->add_control(
					'qty_dropdown_bg_color',
					array(
						'label'     => __( 'Background Color', 'elementor' ),
						'type'      => Controls_Manager::COLOR,
						'default'   => '#ffffff',
						'selectors' => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input' => 'background-color: {{VALUE}}',
						),
					)
				);

				$this->add_responsive_control(
					'qty_dropdown_width',
					array(
						'label'      => __( 'Width', 'elementor' ),
						'type'       => Controls_Manager::SLIDER,
						'size_units' => array( 'px', 'em', '%' ),
						'range'      => array(
							'em' => array(
								'min'  => 5,
								'max'  => 35,
								'step' => 0.1,
							),
							'px' => array(
								'min' => 50,
								'max' => 600,
							),
							'%'  => array(
								'min' => 1,
								'max' => 100,
							),
						),
						'default'    => array(
							'size' => 250,
							'unit' => 'px',
						),
						'selectors'  => array(
							'{{WRAPPER}} .wfocu-prod-qty-wrapper label'                                                                                                                                                                                                                                                                                                         => 'font-weight: 300; line-height: 1; padding-bottom: 8px; font-family: "Open Sans",sans-serif;',
							'{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input'                                                                                                                                                                                                                                                                                       => 'width: {{SIZE}}{{UNIT}}; text-align: left; display: inline-block;',
							'body[data-elementor-device-mode="mobile"] {{WRAPPER}}.elementor-mobile-align-center .wfocu-prod-qty-wrapper label, body[data-elementor-device-mode="tablet"] {{WRAPPER}}.elementor-tablet-align-center .wfocu-prod-qty-wrapper label, body[data-elementor-device-mode="desktop"] {{WRAPPER}}.elementor-align-center .wfocu-prod-qty-wrapper label' => 'width: {{SIZE}}{{UNIT}}; font-weight: 300; margin: auto; text-align: left;',
							'{{WRAPPER}} .wfocu-prod-qty-wrapper > label'                                                                                                                                                                                                                                                                                                       => 'width: {{SIZE}}{{UNIT}};display: inline-block;text-align: left;',
							'{{WRAPPER}}.elementor-qnty_block-yes .wfocu-prod-qty-wrapper > span'                                                                                                                                                                                                                                                                               => 'display:block',
							'{{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input, {{WRAPPER}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input option'                                                                                                                                                                                                                   => 'font-weight: 300; color: #333; box-shadow: none; -webkit-box-shadow: none; -moz-box-shadow: none; font-family: "Open Sans",sans-serif;',
							'{{WRAPPER}} .wfocu-prod-qty-wrapper'                                                                                                                                                                                                                                                                                                               => 'margin-bottom: 1.2em;',
						),
					)
				);
			}
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

			$offer_id             = WFOCU_Core()->template_loader->get_offer_id();
			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;
			$qty_text             = $this->get_settings( 'text' );

			if ( false === $qty_selector_enabled ) {
				return;
			}

			$this->add_render_attribute( 'wrapper', 'class', 'elementor-button-wrapper' );
			?>
			<div <?php echo $this->get_render_attribute_string( 'wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
				if ( ! empty( $product_key ) ) {
					echo do_shortcode( '[wfocu_qty_selector key="' . $product_key . '" label="' . $qty_text . '"]' );
				}
				?>
			</div>
			<?php
		}
	}
}