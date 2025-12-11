<?php

namespace WfocuFunnelKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WC_Product;

if ( ! class_exists( '\WfocuFunnelKit\Variation_Selector' ) ) {
	class Variation_Selector extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-variation-selector';
		public $icon = 'wfocu-icon-variation';

		/**
		 * Retrieves the label for the Variation Selector element.
		 *
		 * @return string The label for the Variation Selector element.
		 */
		public function get_label() {
			return esc_html__( 'Variation Selector' );
		}

		/**
		 * Sets the control groups for the Variation Selector element.
		 *
		 * This method initializes the control groups array for the Variation Selector element.
		 * It sets the control groups for the element's content and style tabs.
		 * It also calls the `set_common_control_groups()` method to set the common control groups.
		 * Finally, it removes the `_typography` control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Variation Selector' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Variation Selector' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Set the controls for the variation selector element.
		 *
		 * This method sets the controls for the variation selector element, including the quantity error notice and the selected product dropdown.
		 *
		 * @return void
		 */
		public function set_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$variables       = $products = array();
			$product_options = array( '0' => esc_html__( '--No Product--' ) );

			if ( ! empty( $offer_id ) ) {
				$products = WFOCU_Core()->template_loader->product_data->products;
			}

			if ( ! empty( (array) $products ) ) {
				$product_options = array();
				foreach ( $products as $key => $product ) {
					$product_options[ $key ] = $product->data->get_name();

					if ( in_array( $product->type, array( 'variable', 'variable-subscription' ), true ) ) {
						array_push( $variables, $key );
					}
				}
			}

			$this->controls['qtyErrorNotice'] = array(
				'group'    => 'elementContent',
				'content'  => esc_html__( 'Variation dropdowns will only show for Variable products.' ),
				'type'     => 'info',
				'required' => array( 'selectedProduct', '!=', $variables ),
			);

			$this->controls['selectedProduct'] = array(
				'group'   => 'elementContent',
				'label'   => esc_html__( 'Product' ),
				'type'    => 'select',
				'options' => $product_options,
				'default' => strval( key( $product_options ) ),
			);

			$this->controls['attrValueBlock'] = array(
				'group'    => 'elementContent',
				'label'    => esc_html__( 'Stacked' ),
				'type'     => 'checkbox',
				'default'  => true,
				'css'      => array(
					array(
						'selector' => '.variations td',
						'property' => 'display',
						'value'    => 'block',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
				'rerender' => true,
			);

			$this->controls['selectorSpacing'] = array(
				'group'    => 'elementContent',
				'label'    => esc_html__( 'Spacing' ),
				'type'     => 'slider',
				'units'    => array(
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
				'css'      => array(
					array(
						'property' => 'padding-left',
						'selector' => '.wfocu-variation-selector-wrapper:not(.wfocu-variation-selector-wrapper__block-yes) .variations .value',
					),
					array(
						'property' => 'margin-top',
						'selector' => '.wfocu-variation-selector-wrapper.wfocu-variation-selector-wrapper__block-yes .variations .value',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['selectorPadding'] = array(
				'group'    => 'elementContent',
				'label'    => esc_html__( 'Padding' ),
				'type'     => 'spacing',
				'css'      => array(
					array(
						'property' => 'padding',
						'selector' => '.variations td',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['align'] = array(
				'group'    => 'elementContent',
				'label'    => esc_html__( 'Alignment' ),
				'type'     => 'text-align',
				'exclude'  => array( 'justify' ),
				'default'  => 'left',
				'css'      => array(
					array(
						'property' => 'text-align',
						'selector' => '.variations td.value',
					),
					array(
						'property' => 'text-align',
						'selector' => '.wfocu-variation-selector-wrapper.wfocu-variation-selector-wrapper__block-yes .variations tr',
					),
					array(
						'property' => 'display',
						'selector' => '.variations tr td.label',
						'value'    => 'inline-block',
					),
					array(
						'property' => 'text-align',
						'selector' => '.variations tr td.label',
						'value'    => 'left',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attributeLabels'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Attribute Labels' ),
				'type'     => 'separator',
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['textTypography'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Typography' ),
				'type'     => 'typography',
				'default'  => array(
					'color' => array(
						'hex' => '#414349',
					),
				),
				'css'      => array(
					array(
						'property' => 'typography',
						'selector' => '.wfocu_variation_selector_form .variations label',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attributeBgColor'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Background Color' ),
				'type'     => 'color',
				'default'  => array(
					'hex' => 'transparent',
				),
				'css'      => array(
					array(
						'property' => 'background-color',
						'selector' => '.variations label',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['labelMinWidth'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Min Width' ),
				'type'     => 'slider',
				'css'      => array(
					array(
						'property' => 'min-width',
						'selector' => '.variations .label',
					),
				),
				'units'    => array(
					'px' => array(
						'min'  => 50,
						'max'  => 500,
						'step' => 1,
					),
					'em' => array(
						'min'  => 5,
						'max'  => 20,
						'step' => 0.1,
					),
				),
				'default'  => '64px',
				'required' => array(
					array( 'selectedProduct', '=', $variables ),
					array( 'attrValueBlock', '=', '' ),
				),
			);

			$this->controls['attributeValue'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Attribute Value' ),
				'type'     => 'separator',
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attrValueTypography'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Typography' ),
				'type'     => 'typography',
				'css'      => array(
					array(
						'property' => 'typography',
						'selector' => '.variations .value select',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attrValueBorder'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Border' ),
				'type'     => 'border',
				'css'      => array(
					array(
						'property' => 'border',
						'selector' => '.variations .value select',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attrValuePadding'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Padding' ),
				'type'     => 'spacing',
				'css'      => array(
					array(
						'property' => 'padding',
						'selector' => '.variations .value select',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attrValueBgColor'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Background Color' ),
				'type'     => 'color',
				'default'  => array(
					'hex' => '#ffffff',
				),
				'css'      => array(
					array(
						'property' => 'background-color',
						'selector' => '.variations .value select',
					),
				),
				'required' => array( 'selectedProduct', '=', $variables ),
			);

			$this->controls['attrValueWidth'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Width' ),
				'type'     => 'slider',
				'css'      => array(
					array(
						'selector' => '.variations tr td.label',
						'property' => 'width',
					),
					array(
						'selector' => '.variations .value select, .wfocu-variation-selector-wrapper__block-yes.bricks-align-center .variations .label',
						'property' => 'width',
					),
					array(
						'selector' => '.variations .value select',
						'property' => 'display',
						'value'    => 'inline-block',
					),
					array(
						'selector' => '.variations .value select, .wfocu-variation-selector-wrapper__block-yes.bricks-align-center .variations .label',
						'property' => 'margin',
						'value'    => 'auto',
					),
					array(
						'selector' => '.variations .value select, .wfocu-variation-selector-wrapper__block-yes.bricks-align-center .variations .label',
						'property' => 'display',
						'value'    => 'inline-block',
					),
					array(
						'selector' => '.wfocu-variation-selector-wrapper:not(.wfocu-variation-selector-wrapper__block-yes) .variations td',
						'property' => 'display',
						'value'    => 'inline-block',
					),
				),
				'units'    => array(
					'em' => array(
						'min'  => 5,
						'max'  => 20,
						'step' => 0.1,
					),
					'px' => array(
						'min'  => 100,
						'max'  => 600,
						'step' => 1,
					),
					'%'  => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'default'  => '250px',
				'required' => array( 'selectedProduct', '=', $variables ),
			);
		}

		/**
		 * Renders the variation selector element.
		 */
		public function render() {
			$settings = $this->settings;
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product_key  = $settings ['selectedProduct'];
			$product_key  = WFOCU_Core()->template_loader->default_product_key( $product_key );

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

			$this->set_attribute( '_root', 'class', 'wfocu-variation-selector' );
			$this->set_attribute( 'wrapper', 'class', 'wfocu-variation-selector-wrapper' );

			if ( isset( $settings['attrValueBlock'] ) ) {
				$this->set_attribute( 'wrapper', 'class', 'wfocu-variation-selector-wrapper__block-yes' );
			}

			if ( ! empty( $product_key ) ) {
				?>
                <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <div <?php echo $this->render_attributes( 'wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php
						if ( true === $is_variable ) {
							echo do_shortcode( '[wfocu_variation_selector_form key="' . $product_key . '"]' );
						}
						?>
                    </div>
                </div>
				<?php
			}
		}
	}
}