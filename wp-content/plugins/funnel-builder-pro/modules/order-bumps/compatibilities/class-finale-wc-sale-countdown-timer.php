<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Finale_WC_Sale_Countdown_Timer' ) ) {

	class WFOB_Compatibility_With_Finale_WC_Sale_Countdown_Timer {
		public function __construct() {
			add_action( 'wfob_layout_style', array( $this, 'wcct_dynamic_css_print' ), 55 );
		}

		public function wcct_dynamic_css_print() {
			if ( function_exists( 'WCCT_Core' ) ) {
				$wcct_appearance_instance = WCCT_Core()->appearance;
				if ( $wcct_appearance_instance instanceof WCCT_Appearance && method_exists( 'WCCT_Appearance', 'wcct_css_print' ) ) {
					add_action( 'wfob_layout_style', array( $wcct_appearance_instance, 'wcct_css_print' ), 56 );
				}
			}
		}
	}

	new WFOB_Compatibility_With_Finale_WC_Sale_Countdown_Timer();
}