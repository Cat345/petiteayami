<?php
if ( ! class_exists( 'wfocu_Input_Html_Rule_Is_Renewal' ) ) {
	class wfocu_Input_Html_Rule_Is_Renewal {
		public function __construct() {
			// vars
			$this->type = 'Html_Rule_Is_Renewal';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			esc_html_e( 'This Funnel will initiate on orders that are renewals.', 'woofunnels-upstroke-one-click-upsell' );
		}

	}
}