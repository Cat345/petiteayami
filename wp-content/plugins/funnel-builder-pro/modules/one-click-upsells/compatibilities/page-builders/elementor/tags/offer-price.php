<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'WFOCU_Elementor_Tag_Price' ) ) {
	class WFOCU_Elementor_Tag_Price extends \Elementor\Core\DynamicTags\Tag {

		/**
		 * Get Name
		 *
		 * Returns the Name of the tag
		 *
		 * @return string
		 * @since 2.0.0
		 * @access public
		 *
		 */
		public function get_name() {
			return 'wfocu-elementor-tag-price';
		}

		/**
		 * Get Title
		 *
		 * Returns the title of the Tag
		 *
		 * @return string
		 * @since 2.0.0
		 * @access public
		 *
		 */
		public function get_title() {
			return __( 'Offer Price', 'elementor-pro' );
		}

		/**
		 * Get Group
		 *
		 * Returns the Group of the tag
		 *
		 * @return string
		 * @since 2.0.0
		 * @access public
		 *
		 */
		public function get_group() {
			return 'upstroke';
		}

		/**
		 * Get Categories
		 *
		 * Returns an array of tag categories
		 *
		 * @return array
		 * @since 2.0.0
		 * @access public
		 *
		 */
		public function get_categories() {
			return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
		}

		/**
		 * Register Controls
		 *
		 * Registers the Dynamic tag controls
		 *
		 * @return void
		 * @since 2.0.0
		 * @access protected
		 *
		 */
		protected function register_controls() {

			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			if ( empty( $offer_id ) ) {
				return;
			}

			$products = WFOCU_Core()->template_loader->product_data->products;

			//      if ( is_object( $products ) && count( (array) $products ) > 1 ) {
			$product_options = array();
			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}

			$this->add_control( 'selected_product', [
				'label'        => __( 'Product', 'woofunnels-upstroke-one-click-upsell' ),
				'type'         => \Elementor\Controls_Manager::SELECT,
				'default'      => key( $product_options ),
				'options'      => $product_options,
				'prefix_class' => 'elementor-button-',
			] );

		}

		/**
		 * Render
		 *
		 * Prints out the value of the Dynamic tag
		 *
		 * @return void
		 * @since 2.0.0
		 * @access public
		 *
		 */
		public function render() {
			$key = $this->get_settings( 'selected_product' );

			echo do_shortcode( '[wfocu_product_offer_price key="' . $key . '"]' );
		}
	}
}