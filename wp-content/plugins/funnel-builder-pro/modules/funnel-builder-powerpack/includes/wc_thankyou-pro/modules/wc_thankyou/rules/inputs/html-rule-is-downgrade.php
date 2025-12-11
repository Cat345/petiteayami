<?php
if ( ! class_exists( 'wfty_Input_Html_Rule_Is_Downgrade' ) ) {
	class wfty_Input_Html_Rule_Is_Downgrade {
		public function __construct() {
			// vars
			$this->type = 'Html_Rule_Is_Downgrade';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) {

			_e( 'This Thank You Page will show up on orders that have downgraded subscriptions.', 'funnel-builder-powerpack' );
		}

	}
}