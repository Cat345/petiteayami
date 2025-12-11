<?php
if ( ! class_exists( 'wfty_Input_Html_Rule_Is_Guest' ) ) {
	class wfty_Input_Html_Rule_Is_Guest {
		public function __construct() {
			// vars
			$this->type = 'Html_Rule_Is_Guest';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) {

			_e( 'This Thank You Page will show up on guest orders.', 'funnel-builder-powerpack' );
		}

	}
}