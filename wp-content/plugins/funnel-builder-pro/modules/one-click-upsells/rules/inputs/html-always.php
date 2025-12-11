<?php
if ( ! class_exists( 'wfocu_Input_Html_Always' ) ) {

	class wfocu_Input_Html_Always {
		public function __construct() {
			// vars
			$this->type = 'Html_Always';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			echo '';
		}

	}
}