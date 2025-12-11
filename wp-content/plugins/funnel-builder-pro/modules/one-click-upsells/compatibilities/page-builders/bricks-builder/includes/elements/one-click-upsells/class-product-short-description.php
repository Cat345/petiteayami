<?php

namespace WfocuFunnelKit;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WC_Product;

if ( ! class_exists( '\WfocuFunnelKit\Product_Short_Description' ) ) {
	class Product_Short_Description extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-short-description';
		public $icon = 'wfocu-icon-product_description';

		/**
		 * Retrieves the label for the "Product Short Description" element.
		 *
		 * @return string The label for the element.
		 */
		public function get_label() {
			return esc_html__( 'Product Short Description' );
		}

		/**
		 * Sets the control groups for the product short description element.
		 *
		 * This method initializes the control groups array and sets the control groups for the element's content and style.
		 * It also calls the `set_common_control_groups()` method to set the common control groups.
		 * Finally, it removes the `_typography` control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Offer Product Description' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Offer Product Description' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Set the controls for the product short description element.
		 *
		 * This method sets the controls for the product short description element in the Funnel Builder Bricks Integration plugin.
		 * It retrieves the offer ID and checks if it is not empty. If not empty, it retrieves the product data and creates an array of product options.
		 * The method then sets the controls for the selectedProduct and textTypography properties.
		 *
		 * @return void
		 */
		public function set_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => esc_html__( '--No Product--' ) );
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

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['textTypography'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Typography' ),
				'type'  => 'typography',
				'css'   => array(
					array(
						'property' => 'typography',
					),
				),
			);
		}

		/**
		 * Renders the product short description element.
		 *
		 * This method retrieves the product data and renders the short description of the selected product.
		 * If the product data is not available or the selected product is not a valid instance of WC_Product,
		 * the method returns early without rendering anything.
		 *
		 * @return void
		 * @since 1.0.0
		 *
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

			$post_object = get_post( $product->get_id() );

			$description = $post_object->post_excerpt;
			if ( 'product_variation' === $post_object->post_type ) {
				$product = wc_get_product( $product->get_id() );
				if ( $product instanceof WC_Product ) {
					$description = $product->get_description();
				}
			}

			$short_description = apply_filters( 'woocommerce_short_description', $description );
			if ( empty( $short_description ) ) {
				return;
			}
			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>>
				<?php echo $short_description; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
			<?php
		}
	}
}