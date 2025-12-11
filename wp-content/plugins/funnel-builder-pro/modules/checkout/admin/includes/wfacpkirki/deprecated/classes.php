<?php
// @codingStandardsIgnoreFile

if ( ! class_exists( 'WFACPKirki_Active_Callback' ) ) {
	// Removed in https://github.com/aristath/wfacpkirki/pull/1682/files
	class WFACPKirki_Active_Callback {
		public static function evaluate() {
			_deprecated_function( __METHOD__, '3.0.17', null );
			return true;
		}
		private static function evaluate_requirement() {
			_deprecated_function( __METHOD__, '3.0.17', null );
			return true;
		}
		public static function compare( $value1, $value2, $operator ) {
			_deprecated_function( __METHOD__, '3.0.17', 'WFACPKirki_Helper::compare_values' );
			return WFACPKirki_Helper::compare_values( $value1, $value2, $operator );
		}
	}
}
