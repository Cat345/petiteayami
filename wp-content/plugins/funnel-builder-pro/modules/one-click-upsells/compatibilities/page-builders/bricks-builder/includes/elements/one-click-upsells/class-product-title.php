<?php

namespace WfocuFunnelKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WC_Product;

if ( ! class_exists( '\WfocuFunnelKit\Product_Title' ) ) {
	class Product_Title extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-offer-product-title';
		public $icon = 'wfocu-icon-offer_title';

		/**
		 * Retrieves the label for the Product Title element.
		 *
		 * @return string The label for the Product Title element.
		 */
		public function get_label() {
			return esc_html__( 'Product Title' );
		}

		/**
		 * Sets the control groups for the Product Title element.
		 *
		 * This method initializes the control groups array for the Product Title element.
		 * It sets the control groups for the element's content and style tabs.
		 * It also calls the set_common_control_groups() method to set the common control groups.
		 * Finally, it removes the '_typography' control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Product Title' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Product Title' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Sets the controls for the product title element.
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

			$this->controls['htmlTag'] = array(
				'group'   => 'elementContent',
				'label'   => esc_html__( 'HTML Tag' ),
				'type'    => 'select',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'div' => 'div',
					'p'   => 'p',
				),
				'default' => 'div',
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['typography'] = array(
				'group'   => 'elementStyle',
				'tab'     => 'style',
				'label'   => esc_html__( 'Typography' ),
				'type'    => 'typography',
				'css'     => array(
					array(
						'property' => 'font',
					),
				),
				'default' => array(
					'color' => array(
						'hex' => '#414349',
					),
				),
			);
		}

		/**
		 * Renders the product title element.
		 */
		public function render() {
			$settings = $this->settings;

			$title = __( 'Product Title' );
			if ( isset( $settings['selectedProduct'] ) ) {
				$product_data = WFOCU_Core()->template_loader->product_data->products;
				$product_key  = $settings['selectedProduct'];
				$product_key  = WFOCU_Core()->template_loader->default_product_key( $product_key );

				if ( isset( $product_data->{$product_key} ) ) {
					$product = $product_data->{$product_key}->data;
					if ( $product instanceof WC_Product ) {
						$title = $product->get_title();
					}
				}
			}

			if ( empty( $title ) ) {
				return;
			}

			$this->set_attribute( '_root', 'class', 'bricks-product-title-wrapper' );
			$this->set_attribute( 'html_tag', 'class', 'bricks-wfocu-product-title' );

			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php printf( '<%1$s %2$s>%3$s</%1$s>', $settings['htmlTag'], $this->render_attributes( 'html_tag' ), $title ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
			<?php
		}
	}
}