<?php
if ( ! class_exists( 'WFOCU_Guten_Quantity_Selector' ) ) {
	class WFOCU_Guten_Quantity_Selector extends WFOCU_Guten_Field {
		public $slug = 'wfocu_qty_selector';
		protected $id = 'wfocu_qty_selector';

		public function __construct() {
			$this->name = __( "WF Quantity Selector" );
			$this->ajax = true;
			parent::__construct();
		}

		public function html( $settings ) {

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}

			$product_data    = WFOCU_Core()->template_loader->product_data->products;
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

			$offer_id             = WFOCU_Core()->template_loader->get_offer_id();
			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;
			$qty_text             = $settings['text'];
			if ( false === $qty_selector_enabled ) {
				return '';
			}

			$class_name = "wfocu_proqty_inline";

			if ( ! empty( $product_key ) ) {
				echo "<div class=$class_name>";
				echo do_shortcode( '[wfocu_qty_selector key="' . $product_key . '" label="' . $qty_text . '"]' );
				echo "</div>";

			}

		}


	}

	return new WFOCU_Guten_Quantity_Selector;
}