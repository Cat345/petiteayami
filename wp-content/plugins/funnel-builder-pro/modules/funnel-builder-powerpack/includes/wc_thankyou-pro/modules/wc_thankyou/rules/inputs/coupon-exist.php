<?php
if ( ! class_exists( 'wfty_Input_Coupon_Exist' ) ) {
	class wfty_Input_Coupon_Exist {

		public function __construct() {
			// vars
			$this->type = 'Coupon_Exist';

			$this->defaults = array(
				'multiple'      => 0,
				'allow_null'    => 0,
				'choices'       => array( 'parent_order' => __( 'In parent order', 'funnel-builder-powerpack' ) ),
				'default_value' => 'no',
				'class'         => 'chosen_coupon_exist'
			);
		}

		public function render( $field, $value = null ) {

			$field = wp_parse_args( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			} ?>

            <table style="width:100%;">
                <tr>
                    <td><?php _e( 'Coupon Exist', 'funnel-builder-powerpack' ); ?></td>
                </tr>
                <tr>
                    <td>
                        <select id="<?php echo $field['id']; ?>" name="<?php echo $field['name']; ?>[]" class="chosen_coupon_exist" data-placeholder="<?php _e( 'Select option&hellip;', 'funnel-builder-powerpack' ); ?>">
							<?php
							foreach ( $field['choices'] as $value => $choice ) {
								echo "<option value='" . esc_attr( $value ) . "'>" . $choice . "</option>";
							} ?>
                        </select>
                    </td>
                </tr>
            </table>
			<?php
		}
	}
}