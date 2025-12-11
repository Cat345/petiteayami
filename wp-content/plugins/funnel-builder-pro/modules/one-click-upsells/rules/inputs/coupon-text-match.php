<?php
if ( ! class_exists( 'wfocu_Input_Coupon_Text_Match' ) ) {
	class wfocu_Input_Coupon_Text_Match {

		public function __construct() {
			// vars
			$this->type = 'Coupon_Text_Match';

			$this->defaults = array(
				'id'            => 'coupon_text_match',
				'multiple'      => 0,
				'allow_null'    => 0,
				'default_value' => '',
				'class'         => 'coupon_text_match',
				'placeholder'   => __( 'Enter the search key...', 'woofunnels-upstroke-one-click-upsell' )
			);
		}

		public function render( $field, $value = null ) {
			$field = wp_parse_args( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}

			echo '<input name="' . $field['name'] . '" type="text" id="' . esc_attr( $field['id'] ) . '" class="' . esc_attr( $field['class'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . $value . '" />';
		}
	}
}