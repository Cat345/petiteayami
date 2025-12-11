<?php
if ( ! class_exists( 'wfocu_Input_Funnel_Products' ) ) {
	class wfocu_Input_Funnel_Products {
		public function __construct() {
			// vars
			$this->type = 'Funnel_Products';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			esc_html_e( 'Run this funnel only if any of funnel product is not present in primary offer. ', 'woofunnels-upstroke-one-click-upsell' );
		}

	}
}