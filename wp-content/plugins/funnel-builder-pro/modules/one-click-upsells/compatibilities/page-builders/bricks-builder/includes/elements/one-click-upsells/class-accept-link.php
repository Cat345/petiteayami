<?php

namespace WfocuFunnelKit;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( '\WfocuFunnelKit\Accept_Link' ) ) {
	class Accept_Link extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-accept-offer-link';
		public $icon = 'wfocu-icon-link_yes';

		/**
		 * Retrieves the label for the Accept Link element.
		 *
		 * @return string The label for the Accept Link element.
		 */
		public function get_label() {
			return esc_html__( 'Accept Link' );
		}

		/**
		 * Sets the control groups for the Accept Link element.
		 *
		 * This method initializes the control groups array for the Accept Link element.
		 * It sets the control groups for the element's content and style tabs.
		 * It also calls the set_common_control_groups() method to set the common control groups.
		 * Finally, it removes the '_typography' control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Accept Offer' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Accept Offer' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Set the controls for the Accept Link element.
		 *
		 * This method sets the controls for the Accept Link element, including the product options and the text for accepting the offer.
		 *
		 * @return void
		 */
		public function set_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
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

			$this->controls['text'] = array(
				'group'       => 'elementContent',
				'label'       => __( 'Accept Offer' ),
				'type'        => 'text',
				'default'     => __( 'Accept this offer' ),
				'placeholder' => __( 'Accept this offer' ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['typography'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Typography' ),
				'type'    => 'typography',
				'default' => array(
					'text-align' => 'center',
				),
				'css'     => array(
					array(
						'property' => 'typography',
						'selector' => 'a.bricks-wfocu-accept, .bricks-wfocu-accept',
					),
					array(
						'property' => 'display',
						'selector' => 'a.bricks-wfocu-accept, .bricks-wfocu-accept',
						'value'    => 'block',
					),
				),
			);

			$this->controls['backgroundColor'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Background Color' ),
				'type'    => 'color',
				'css'     => array(
					array(
						'property' => 'background-color',
						'selector' => 'a.bricks-wfocu-accept, .bricks-wfocu-accept',
					),
				),
				'default' => array(
					'hex' => '#ffffff',
				),
			);
		}

		/**
		 * Renders the accept link element.
		 *
		 * This method is responsible for rendering the accept link element with the specified settings.
		 * It sets the data-key attribute if the selectedProduct setting is not empty.
		 * It sets the href and class attributes for the link element.
		 * It then renders the link element with the specified attributes and button text.
		 */
		public function render() {


			$product_key = isset( $this->settings['selectedProduct'] ) ? $this->settings['selectedProduct'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $product_key );
			if ( ! empty( $product_key ) ) {
				$this->set_attribute( 'upstroke-accept', 'data-key', $product_key );
			}

			$this->set_attribute( 'upstroke-accept', 'href', 'javascript:void(0);' );
			$this->set_attribute( 'upstroke-accept', 'class', 'bricks-wfocu-accept bricks-wfocu-accept-link wfocu_upsell wfocu-upsell-offer-link' );

			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>>
                <a <?php echo $this->render_attributes( 'upstroke-accept' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php WFOCU_Core()->template_loader->add_attributes_to_buy_button(); ?>>
					<?php $this->render_link_text(); ?>
                </a>
            </div>
			<?php
		}

		/**
		 * Renders the link text for the Accept Link element.
		 *
		 * @return void
		 */
		public function render_link_text() {
			$settings = $this->settings;

			$text = isset( $settings['text'] ) ? $this->render_dynamic_data( $settings['text'] ) : null;

			if ( $text === null ) {
				return;
			}

			echo $text; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}