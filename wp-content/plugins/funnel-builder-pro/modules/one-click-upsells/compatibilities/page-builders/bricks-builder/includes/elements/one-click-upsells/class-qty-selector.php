<?php

namespace WfocuFunnelKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use stdClass;
use WC_Product;

if ( ! class_exists( '\WfocuFunnelKit\Qty_Selector' ) ) {
	class Qty_Selector extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-qty-selector';
		public $icon = 'wfocu-icon-quantity';

		/**
		 * Retrieves the label for the Quantity Selector element.
		 *
		 * @return string The label for the Quantity Selector element.
		 */
		public function get_label() {
			return esc_html__( 'Quantity Selector' );
		}

		/**
		 * Sets the control groups for the quantity selector element.
		 *
		 * This method initializes the control groups array and adds two control groups:
		 * - 'elementContent': Contains controls related to the content of the quantity selector element.
		 * - 'elementStyle': Contains controls related to the styling of the quantity selector element.
		 *
		 * Additionally, this method calls the 'set_common_control_groups()' method to add any common control groups.
		 * Finally, it removes the '_typography' control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Quantity Selector' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Quantity Selector' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Sets the controls for the quantity selector element.
		 *
		 * This method retrieves the offer ID and checks if the quantity selector is enabled for the offer.
		 * If the quantity selector is enabled, it sets the selected product control with a dropdown of product options.
		 * If the quantity selector is not enabled, it sets an info control with a message indicating that the quantity selector is not available for the offer.
		 *
		 * @return void
		 */
		public function set_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products = WFOCU_Core()->template_loader->product_data->products;
			}

			if ( ! empty( (array) $products ) ) {
				$product_options = array();
				foreach ( $products as $key => $product ) {
					$product_options[ $key ] = $product->data->get_name();
				}
			}

			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;

			if ( $qty_selector_enabled ) {
				$this->controls['selectedProduct'] = array(
					'group'   => 'elementContent',
					'label'   => esc_html__( 'Product' ),
					'type'    => 'select',
					'options' => $product_options,
					'default' => strval( key( $product_options ) ),
				);

				$this->controls['text'] = array(
					'group'   => 'elementContent',
					'label'   => esc_html__( 'Text' ),
					'type'    => 'text',
					'default' => esc_html__( 'Quantity' ),
				);

				$this->controls['_alignSelf']['default'] = 'stretch';

				$this->controls['align'] = array(
					'group' => 'elementContent',
					'label' => esc_html__( 'Alignment' ),
					'type'  => 'text-align',
					'css'   => array(
						array(
							'property' => 'text-align',
						),
					),
				);

				$this->controls['stacked'] = array(
					'group'   => 'elementContent',
					'label'   => esc_html__( 'Stacked' ),
					'type'    => 'checkbox',
					'default' => true,
				);

				$this->controls['qtyDropdownSpacing'] = array(
					'group' => 'elementContent',
					'label' => esc_html__( 'Spacing' ),
					'type'  => 'slider',
					'units' => array(
						'px' => array(
							'min'  => 0,
							'max'  => 100,
							'step' => 1,
						),
						'em' => array(
							'min'  => 0,
							'max'  => 5,
							'step' => 0.1,
						),
					),
					'css'   => array(
						array(
							'property' => 'margin-left',
							'selector' => '.bricks-qty-wrapper:not(bricks-qty-wrapper__block-yes) .wfocu-select-wrapper',
						),
						array(
							'property' => 'margin-top',
							'selector' => '.bricks-qty-wrapper.bricks-qty-wrapper__block-yes .wfocu-select-wrapper',
						),
					),
				);

				$this->controls['quantityTypography'] = array(
					'group'   => 'elementStyle',
					'label'   => esc_html__( 'Typography' ),
					'type'    => 'typography',
					'exclude' => array(
						'text-align',
					),
					'css'     => array(
						array(
							'property' => 'typography',
							'selector' => '.wfocu-prod-qty-wrapper label',
						),
					),
				);

				$this->controls['quantityBgColor'] = array(
					'group'  => 'elementStyle',
					'label'  => esc_html__( 'Background Color' ),
					'type'   => 'color',
					'inline' => true,
					'css'    => array(
						array(
							'property' => 'background-color',
							'selector' => '.wfocu-prod-qty-wrapper label',
						),
					),
				);

				$this->controls['qtyBlockMargin'] = array(
					'group' => 'elementStyle',
					'label' => esc_html__( 'Margin' ),
					'type'  => 'spacing',
					'css'   => array(
						array(
							'property' => 'margin',
							'selector' => '.wfocu-prod-qty-wrapper label',
						),
					),
				);

				$this->controls['qtyDropdown'] = array(
					'group' => 'elementStyle',
					'label' => esc_html__( 'Quantity Dropdown' ),
					'type'  => 'separator',
				);

				$this->controls['qtyDropdownTypography'] = array(
					'group'   => 'elementStyle',
					'label'   => esc_html__( 'Typography' ),
					'type'    => 'typography',
					'css'     => array(
						array(
							'property' => 'typography',
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
						),
					),
					'exclude' => array(
						'text-transform',
					),
				);

				$this->controls['qtyDropdownBorder'] = array(
					'group' => 'elementStyle',
					'label' => esc_html__( 'Border' ),
					'type'  => 'border',
					'css'   => array(
						array(
							'property' => 'border',
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
						),
					),
				);

				$this->controls['qtyDropdownPadding'] = array(
					'group' => 'elementStyle',
					'label' => esc_html__( 'Padding' ),
					'type'  => 'spacing',
					'css'   => array(
						array(
							'property' => 'padding',
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
						),
					),
				);

				$this->controls['qtyDropdownColor'] = array(
					'group'   => 'elementStyle',
					'label'   => esc_html__( 'Text Color' ),
					'type'    => 'color',
					'css'     => array(
						array(
							'property' => 'color',
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
						),
					),
					'default' => array(
						'hex' => '#8d8e92',
					),
				);

				$this->controls['qtyDropdownBgColor'] = array(
					'group'   => 'elementStyle',
					'label'   => esc_html__( 'Background Color' ),
					'type'    => 'color',
					'css'     => array(
						array(
							'property' => 'background-color',
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
						),
					),
					'default' => array(
						'hex' => '#ffffff',
					),
				);

				$this->controls['qtyDropdownWidth'] = array(
					'group'   => 'elementStyle',
					'label'   => esc_html__( 'Width' ),
					'type'    => 'slider',
					'css'     => array(
						array(
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
							'property' => 'width',
						),
						array(
							'selector' => '.bricks-qty-wrapper.bricks-qty-wrapper__block-yes .wfocu-prod-qty-wrapper label',
							'property' => 'width',
						),
						array(
							'selector' => '.wfocu-prod-qty-wrapper .wfocu-select-qty-input',
							'property' => 'text-align',
							'value'    => 'left',
						),
						array(
							'selector' => '.wfocu-prod-qty-wrapper label',
							'property' => 'display',
							'value'    => 'inline-block',
						),
						array(
							'selector' => '.wfocu-prod-qty-wrapper label',
							'property' => 'text-align',
							'value'    => 'left',
						),
						array(
							'selector' => '.bricks-qty-wrapper:not(bricks-qty-wrapper__block-yes) .wfocu-prod-qty-wrapper .wfocu-select-wrapper',
							'property' => 'display',
							'value'    => 'inline-block',
						),
						array(
							'selector' => '.bricks-qty-wrapper.bricks-qty-wrapper__block-yes .wfocu-prod-qty-wrapper .wfocu-select-wrapper',
							'property' => 'display',
							'value'    => 'block',
						),
					),
					'units'   => array(
						'px' => array(
							'min'  => 50,
							'max'  => 600,
							'step' => 1,
						),
						'em' => array(
							'min'  => 5,
							'max'  => 35,
							'step' => 0.1,
						),
						'%'  => array(
							'min' => 1,
							'max' => 100,
						),
					),
					'default' => '250px',
				);
			} else {
				unset( $this->control_groups['elementStyle'] );

				$this->controls['qtyErrorNotice'] = array(
					'group'   => 'elementContent',
					'content' => esc_html__( 'Quantity selector is not available for this offer. Kindly allow customer to chose the quantity while purchasing this upsell product(s) from "Offers" tab.' ),
					'type'    => 'info',
				);
			}
		}

		/**
		 * Renders the quantity selector element.
		 *
		 * This method checks if the product data is set and retrieves the selected product based on the settings.
		 * If the selected product is not an instance of WC_Product, the method returns.
		 * It then checks if the quantity selector is enabled in the offer settings.
		 * If not enabled, the method returns.
		 * Finally, it renders the quantity selector element using the product key and quantity text.
		 *
		 * @since 1.0.0
		 */
		public function render() {
			$settings = $this->settings;

			if ( ! isset( $settings['selectedProduct'] ) ) {
				return;
			}

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product_key  = $settings['selectedProduct'];
			$product_key  = WFOCU_Core()->template_loader->default_product_key( $product_key );

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
			$qty_text             = $settings['text'];

			if ( false === $qty_selector_enabled ) {
				return;
			}

			$this->set_attribute( '_root', 'class', 'bricks-qty' );
			$this->set_attribute( 'wrapper', 'class', 'bricks-qty-wrapper' );

			if ( isset( $settings['stacked'] ) ) {
				$this->set_attribute( 'wrapper', 'class', 'bricks-qty-wrapper__block-yes' );
			}
			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <div <?php echo $this->render_attributes( 'wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php
					if ( ! empty( $product_key ) ) {
						echo do_shortcode( '[wfocu_qty_selector key="' . $product_key . '" label="' . $qty_text . '"]' );
					}
					?>
                </div>
            </div>
			<?php
		}
	}
}