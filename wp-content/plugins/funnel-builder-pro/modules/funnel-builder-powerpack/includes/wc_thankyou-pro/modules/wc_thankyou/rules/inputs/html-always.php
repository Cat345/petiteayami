<?php
if ( ! class_exists( 'wfty_Input_Html_Always' ) ) {
	class wfty_Input_Html_Always {
		public function __construct() {
			// vars
			$this->type = 'Html_Always';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) {

			echo '';
		}

	}
}