<?php
if ( ! class_exists( 'WFOCU_Guten_Variation_Selector' ) ) {
	class WFOCU_Guten_Variation_Selector extends WFOCU_Guten_Field {
		public $slug = 'wfocu_variation_selector';
		protected $id = 'wfocu_variation_selector';

		public function __construct() {
			$this->name = __( "WF Variation Selector" );
			$this->ajax = true;
			parent::__construct();
		}

		public function html( $settings ) {
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;

			$sel_product_key = isset( $settings['product'] ) ? $settings['product'] : '';
			$product_key     = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$product_key     = ( $product_key !== false ) ? $product_key : $sel_product_key;

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return '';
			}

			$is_variable = false;

			if ( ! empty( $product_key ) ) {

				if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
					$is_variable = true;
				}
			}

			if ( false === $is_variable ) {
				return '';
			}

			if ( ! empty( $product_key ) ) {
				if ( true === $is_variable ) {
					echo do_shortcode( '[wfocu_variation_selector_form key="' . $product_key . '"]' );
				}
			}
		}


	}

	return new WFOCU_Guten_Variation_Selector;
}