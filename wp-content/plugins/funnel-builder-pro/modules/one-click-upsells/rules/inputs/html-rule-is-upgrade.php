<?php
if ( ! class_exists( 'wfocu_Input_Html_Rule_Is_Upgrade' ) ) {
	class wfocu_Input_Html_Rule_Is_Upgrade {
		public function __construct() {
			// vars
			$this->type = 'Html_Rule_Is_Upgrade';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			esc_html_e( 'This Page will show on orders that have upgraded subscriptions.', 'woofunnels-upstroke-one-click-upsell' );
		}

	}
}