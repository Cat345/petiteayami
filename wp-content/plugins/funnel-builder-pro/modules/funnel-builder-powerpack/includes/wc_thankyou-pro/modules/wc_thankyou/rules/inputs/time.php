<?php
if ( ! class_exists( 'wfty_Input_Time' ) ) {
	class wfty_Input_Time extends wfty_Input_Text {

		public function __construct() {
			$this->type = 'Time';


			parent::__construct();
		}

		public function render( $field, $value = null ) {
			$field = array_merge( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}

			echo '<input placeholder="For eg: 23:59" name="' . $field['name'] . '" type="text" id="' . esc_attr( $field['id'] ) . '" class="wfty-time-picker-field' . esc_attr( $field['class'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . $value . '" />';
		}

	}
}

