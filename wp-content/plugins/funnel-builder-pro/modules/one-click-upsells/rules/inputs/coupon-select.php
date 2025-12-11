<?php
if ( ! class_exists( 'wfocu_Input_Coupon_Select' ) ) {
	class wfocu_Input_Coupon_Select {

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
			$coupons      = get_posts( $args ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
			foreach ( $coupons as $coupon ) {
				array_push( $coupon_codes, $coupon->post_title );
			}

			if ( count( $current ) > 0 ) {
				$coupon_codes = array_merge( $coupon_codes, $current );
			}
			$coupon_codes = array_unique( $coupon_codes ); ?>

            <table style="width:100%;">
                <tr>
                    <td><?php esc_html_e( 'Coupons', 'woofunnels-upstroke-one-click-upsell' ); ?></td>
                </tr>
                <tr>
                    <td>
                        <select <?php echo $mutiple ? 'multiple="multiple"' : ''; ?> id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>[]" class="ajax_chosen_select_coupons" data-placeholder="<?php esc_html_e( 'Select coupons&hellip;', 'woofunnels-upstroke-one-click-upsell' ); ?>">
							<?php
							foreach ( $coupon_codes as $code ) {
								echo "<option value='" . esc_attr( $code ) . "' " . selected( true, in_array( $code, $current, true ) ) . ">" . esc_attr( $code ) . "</option>";
							} ?>
                        </select>
                    </td>
                </tr>
            </table>
			<?php
		}
	}
}