<?php
if ( ! class_exists( 'wfty_Input_Coupon_Select' ) ) {
	class wfty_Input_Coupon_Select {

		public function __construct() {
			// vars
			$this->type = 'Coupon_Select';

			$this->defaults = array(
				'multiple'      => 1,
				'allow_null'    => 0,
				'choices'       => array(),
				'default_value' => '',
				'class'         => 'ajax_chosen_select_coupons'
			);
		}

		public function render( $field, $value = null ) {

			$field = wp_parse_args( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}

			$mutiple = isset( $field['multiple'] ) ? $field['multiple'] : false;
			$current = is_array( $value ) ? $value : array();

			$coupon_codes = array();
			$args         = array(
				'posts_per_page'   => 5,
				'orderby'          => 'post_date',
				'order'            => 'DESC',
				'post_type'        => 'shop_coupon',
				'post_status'      => 'publish',
				'suppress_filters' => false
			);
			$coupons      = get_posts( $args );
			foreach ( $coupons as $coupon ) {
				array_push( $coupon_codes, $coupon->post_title );
			}

			if ( count( $current ) > 0 ) {
				$coupon_codes = array_merge( $coupon_codes, $current );
			}
			$coupon_codes = array_unique( $coupon_codes ); ?>

            <table style="width:100%;">
                <tr>
                    <td><?php _e( 'Coupons', 'funnel-builder-powerpack' ); ?></td>
                </tr>
                <tr>
                    <td>
                        <select <?php echo $mutiple ? 'multiple="multiple"' : ''; ?> id="<?php echo $field['id']; ?>" name="<?php echo $field['name']; ?>[]" class="ajax_chosen_select_coupons" data-placeholder="<?php _e( 'Select coupons&hellip;', 'funnel-builder-powerpack' ); ?>">
							<?php
							foreach ( $coupon_codes as $code ) {
								echo "<option value='" . esc_attr( $code ) . "' " . selected( true, in_array( $code, $current, true ) ) . ">" . ( $code ) . "</option>";
							} ?>
                        </select>
                    </td>
                </tr>
            </table>
			<?php
		}
	}
}