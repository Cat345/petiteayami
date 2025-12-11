<?php
if ( ! class_exists( 'WFOCU_Guten_Accept_Link' ) ) {
	class WFOCU_Guten_Accept_Link extends WFOCU_Guten_Field {
		public $slug = 'wfocu_accept_link';
		protected $id = 'wfocu_accept_link';

		public function __construct() {
			$this->name = __( "WF Accept Link" );
			parent::__construct();
		}


		public function html( $settings, $content = '' ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$sel_product_key     = isset( $settings['product'] ) ? $settings['product'] : '';
			$product_key         = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$settings['product'] = ( $product_key !== false ) ? $product_key : $sel_product_key;

			return BWFBlocksUpsell_Render_Block::do_button_block( $settings, $content );
		}

	}

	return new WFOCU_Guten_Accept_Link;
}