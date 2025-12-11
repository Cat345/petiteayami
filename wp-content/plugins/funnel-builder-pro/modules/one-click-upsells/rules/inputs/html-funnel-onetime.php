<?php
if ( ! class_exists( 'wfocu_Input_Funnel_OneTime' ) ) {

	class wfocu_Input_Funnel_OneTime {
		public function __construct() {
			// vars
			$this->type = 'Funnel_OneTime';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			esc_html_e( 'Run this funnel only if the user hasn\'t visited it yet.', 'woofunnels-upstroke-one-click-upsell' );
		}

	}
}