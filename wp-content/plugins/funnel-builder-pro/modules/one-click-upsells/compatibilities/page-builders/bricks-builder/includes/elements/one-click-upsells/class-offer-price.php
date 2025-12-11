<?php

namespace WfocuFunnelKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WFOCU_Common;
use WC_Product;

if ( ! class_exists( '\WfocuFunnelKit\Offer_Price' ) ) {
	class Offer_Price extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-offer-price';
		public $icon = 'wfocu-icon-product_offer';

		/**
		 * Retrieves the label for the Offer Price element.
		 *
		 * @return string The label for the Offer Price element.
		 */
		public function get_label() {
			return esc_html__( 'Offer Price' );
		}

		/**
		 * Sets the control groups for the Offer Price element.
		 *
		 * This method initializes the control groups array for the Offer Price element.
		 * It sets the control groups for the element's content and style tabs.
		 * It also calls the set_common_control_groups() method to set the common control groups.
		 * Finally, it removes the '_typography' control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Prices' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Prices' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Sets the controls for the Offer Price element.
		 */
		public function set_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$subscriptions   = $products = array();
			$product_options = array( '0' => __( '--No Product--' ) );

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

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}

			$this->controls['selectedProduct'] = array(
				'group'   => 'elementContent',
				'label'   => esc_html__( 'Product' ),
				'type'    => 'select',
				'options' => $product_options,
				'default' => key( $product_options ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['textAlign'] = array(
				'group' => 'elementContent',
				'label' => esc_html__( 'Alignment' ),
				'type'  => 'text-align',
				'css'   => array(
					array(
						'property' => 'text-align',
					),
				),
			);

			$this->controls['salePriceSpacing'] = array(
				'group'       => 'elementContent',
				'label'       => esc_html__( 'Spacing' ),
				'description' => esc_html__( 'Between regular and offer blocks' ),
				'type'        => 'slider',
				'units'       => array(
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
				'default'     => '5px',
				'css'         => array(
					array(
						'property' => 'margin-right',
						'selector' => '.bricks-price-wrapper:not(.bricks-price__block-yes) .reg_wrapper',
					),
					array(
						'property' => 'margin-bottom',
						'selector' => '.bricks-price-wrapper.bricks-price__block-yes .reg_wrapper',
					),
				),
			);

			$this->controls['regPriceSeparator'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Regular Price' ),
				'type'  => 'separator',
			);

			$this->controls['showRegPrice'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Show' ),
				'type'    => 'checkbox',
				'default' => true,
			);

			$this->controls['regLabel'] = array(
				'group'       => 'elementStyle',
				'label'       => esc_html__( 'Label' ),
				'type'        => 'text',
				'default'     => esc_html__( 'Regular Price: ' ),
				'placeholder' => esc_html__( 'Regular Price: ' ),
				'required'    => array( 'showRegPrice', '=', true ),
			);

			$this->controls['regLabelTypography'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Typography' ),
				'type'     => 'typography',
				'css'      => array(
					array(
						'property' => 'typography',
						'selector' => '.reg_wrapper .wfocu-reg-label',
					),
				),
				'required' => array( 'showRegPrice', '=', true ),
			);

			$this->controls['regPriceTypography'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Price Typography' ),
				'type'     => 'typography',
				'css'      => array(
					array(
						'property' => 'typography',
						'selector' => '.reg_wrapper strike',
					),
				),
				'required' => array( 'showRegPrice', '=', true ),
			);

			$this->controls['offerPriceSeparator'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Offer Price' ),
				'type'  => 'separator',
			);

			$this->controls['showOfferPrice'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Show' ),
				'type'    => 'checkbox',
				'default' => true,
			);

			$this->controls['offerPriceStacked'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Stacked' ),
				'type'     => 'checkbox',
				'default'  => true,
				'css'      => array(
					array(
						'property' => 'display',
						'value'    => 'block',
						'selector' => '.bricks-price-wrapper.bricks-price__block-yes .reg_wrapper',
					),
				),
				'rerender' => true,
			);

			$this->controls['offerLabel'] = array(
				'group'       => 'elementStyle',
				'label'       => esc_html__( 'Label' ),
				'type'        => 'text',
				'default'     => esc_html__( 'Offer Price: ' ),
				'placeholder' => esc_html__( 'Offer Price: ' ),
				'required'    => array( 'showOfferPrice', '=', true ),
			);

			$this->controls['offerLabelTypography'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Label Typography' ),
				'type'     => 'typography',
				'css'      => array(
					array(
						'property' => 'typography',
						'selector' => '.offer_wrapper .wfocu-offer-label',
					),
				),
				'required' => array( 'showOfferPrice', '=', true ),
			);

			$this->controls['offerPriceTypography'] = array(
				'group'    => 'elementStyle',
				'label'    => esc_html__( 'Price Typography' ),
				'type'     => 'typography',
				'css'      => array(
					array(
						'property' => 'typography',
						'selector' => '.offer_wrapper .wfocu-sale-price span',
					),
				),
				'required' => array( 'showOfferPrice', '=', true ),
			);
		}

		/**
		 * Renders the offer price element.
		 *
		 * This method is responsible for rendering the offer price element on the frontend.
		 * It retrieves the necessary settings and product data, and then generates the HTML output
		 * based on the settings and product information.
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

			$product_key = WFOCU_Core()->template_loader->default_product_key( $product_key );


			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}

			if ( ! $product instanceof WC_Product ) {
				return;
			}

			$this->set_attribute( 'wrapper', 'class', 'bricks-price-wrapper' );

			if ( isset( $settings['offerPriceStacked'] ) ) {
				$this->set_attribute( 'wrapper', 'class', 'bricks-price__block-yes' );
			}

			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <div <?php echo $this->render_attributes( 'wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <div class="bricks-element bricks-element-wfocu_price" data-element_type="wfocu_price.default">
                        <div class="bricks-element-container">
                            <div class="bricks-element-price-wrapper">
								<?php
								/** Price */
								$regular_price     = isset( $settings['showRegPrice'] ) && $settings['showRegPrice'] ? WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' ) : 0;
								$sale_price        = isset( $settings['showOfferPrice'] ) && $settings['showOfferPrice'] ? WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' ) : 0;
								$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
								$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

								$reg_label   = isset( $settings['regLabel'] ) ? '<span class="wfocu-reg-label">' . $settings['regLabel'] . '</span>' : '';
								$offer_label = isset( $settings['offerLabel'] ) ? '<span class="wfocu-offer-label">' . $settings['offerLabel'] . '</span>' : '';

								$price_output = '';
								if ( round( $sale_price_raw, 2 ) !== round( $regular_price_raw, 2 ) ) {
									if ( isset( $settings['showRegPrice'] ) && $settings['showRegPrice'] ) {
										$price_output .= '<span class="reg_wrapper">' . $reg_label . '<span class="wfocu-regular-price"><strike>' . $regular_price . '</strike></span></span>';
									}

									if ( isset( $settings['showOfferPrice'] ) && $settings['showOfferPrice'] ) {
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

								echo $price_output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

								if ( isset( $settings['showSignupFee'] ) && $settings['showSignupFee'] ) {
									$signup_label = isset( $settings['signupLabel'] ) ? $settings['signupLabel'] : '';
									echo WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . $signup_label . '"}}' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}

								if ( isset( $settings['showRecPrice'] ) && $settings['showRecPrice'] ) {
									$recurring_label = isset( $settings['recurringLabel'] ) ? $settings['recurringLabel'] : '';
									echo WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label="' . $recurring_label . '"}}' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}
	}
}