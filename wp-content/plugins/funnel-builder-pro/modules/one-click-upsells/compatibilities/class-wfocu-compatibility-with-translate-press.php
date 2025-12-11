<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Translate_Press' ) ) {
	/**
	 *
	 * Class WFOCU_Compatibility_With_Translate_Press
	 */
	class WFOCU_Compatibility_With_Translate_Press {

		public function __construct() {
			add_filter( 'wfocu_localized_data', array( $this, 'hide_form_field' ), 99, 1 );
		}

		public function is_enable() {
			if ( defined( 'TRANSLATE_PRESS' ) ) {
				return true;
			}

			return false;
		}

		public function hide_form_field( $data ) {

			if ( $this->is_enable() && isset( $data['exclude_fields'] ) ) {
				$data['exclude_fields'][] = 'trp-form-language';
			}

			return $data;
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Translate_Press(), 'translatePress' );
}