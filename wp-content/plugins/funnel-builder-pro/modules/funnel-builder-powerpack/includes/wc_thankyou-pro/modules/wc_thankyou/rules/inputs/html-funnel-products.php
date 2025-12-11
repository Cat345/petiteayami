<?php
if ( ! class_exists( 'wfty_Input_Funnel_Products' ) ) {
	class wfty_Input_Funnel_Products {
		public function __construct() {
			// vars
			$this->type = 'Funnel_Products';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) {

			_e( 'Run this funnel only if any of funnel product is not present in primary offer. ', 'funnel-builder-powerpack' );
		}

	}
}