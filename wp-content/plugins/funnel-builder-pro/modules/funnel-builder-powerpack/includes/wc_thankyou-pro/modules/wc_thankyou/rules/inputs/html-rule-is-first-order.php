<?php
if ( ! class_exists( 'wfty_Input_Html_Rule_Is_First_Order' ) ) {
	class wfty_Input_Html_Rule_Is_First_Order {
		public function __construct() {
			// vars
			$this->type = 'Html_Rule_Is_First_Order';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) {

			_e( 'This Thank You Page will show up on very first order for the customer.', 'funnel-builder-powerpack' );
		}

	}
}