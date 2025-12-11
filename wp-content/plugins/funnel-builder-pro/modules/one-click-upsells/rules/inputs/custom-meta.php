<?php
if ( ! class_exists( 'wfocu_Input_Custom_Meta' ) ) {
	class wfocu_Input_Custom_Meta {
		public function __construct() {
			// vars
			$this->type = 'Custom_Meta';

			$this->defaults = array(
				'default_value'     => '',
				'class'             => '',
				'placeholder_key'   => __( 'Enter custom meta key', 'woofunnels-upstroke-one-click-upsell' ),
				'placeholder_value' => __( 'Enter custom meta value', 'woofunnels-upstroke-one-click-upsell' ),
			);
		}

		public function render( $field, $value = null ) {
			$field = array_merge( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}

			?>

            <table style="width:100%;">
                <tr>
                    <td style="vertical-align:top;">
                        <input name="<?php echo esc_attr( $field['name'] ); ?>[meta_key]" type="text" id="<?php echo esc_attr( $field['id'] ); ?>_meta_key" class="<?php echo esc_attr( $field['class'] ); ?>" placeholder="<?php echo esc_attr( $field['placeholder_key'] ); ?>" value="<?php echo isset( $value['meta_key'] ) ? esc_attr( $value['meta_key'] ) : ''; ?>"/>

                    </td>
                    <td>
                        <input name="<?php echo esc_attr( $field['name'] ); ?>[meta_value]" type="text" id="<?php echo esc_attr( $field['id'] ); ?>_meta_value" class="<?php echo esc_attr( $field['class'] ); ?>" placeholder="<?php echo esc_attr( $field['placeholder_value'] ); ?>" value="<?php echo isset( $value['meta_value'] ) ? esc_attr( $value['meta_value'] ) : ''; ?>"/>
                    </td>
                </tr>
            </table>

			<?php

		}

	}
}
?>