<?php
if ( ! class_exists( 'wfocu_Input_User_Select' ) ) {
	class wfocu_Input_User_Select {

		public function __construct() {
			// vars
			$this->type = 'User_Select';

			$this->defaults = array(
				'multiple'      => 1,
				'allow_null'    => 0,
				'choices'       => array(),
				'default_value' => '',
				'class'         => 'ajax_chosen_select_users'
			);
		}

		public function render( $field, $value = null ) {
			$field = array_merge( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}

			$mutiple = isset( $field['multiple'] ) ? $field['multiple'] : false;
			$current = is_array( $value ) ? $value : array();
			$users   = get_users( array( 'number' => 5, 'fields' => array( 'ID' ) ) );

			$user_ids = array();
			foreach ( $users as $user ) {
				array_push( $user_ids, $user->ID );
			}

			if ( count( $current ) > 0 ) {
				$current_users = get_users( array( 'include' => $current, 'number' => count( $current ), 'fields' => array( 'ID' ) ) );
				foreach ( $current_users as $user ) {
					array_push( $user_ids, $user->ID );
				}
			}

			$user_ids = array_unique( $user_ids ); ?>

            <table style="width:100%;">
                <tr>
                    <td><?php esc_html_e( 'Users', 'woofunnels-upstroke-one-click-upsell' ); ?></td>
                </tr>
                <tr>
                    <td>
                        <select <?php echo $mutiple ? 'multiple="multiple"' : ''; ?> id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>[]" class="ajax_chosen_select_users" data-placeholder="<?php esc_html_e( 'Select users&hellip;', 'woofunnels-upstroke-one-click-upsell' ); ?>">
							<?php
							foreach ( $user_ids as $user_id ) {
								echo "<option value='" . esc_attr( $user_id ) . "' " . selected( true, in_array( $user_id, $current, true ) ) . ">" . esc_html( ( get_user_by( 'id', $user_id )->display_name ) ) . "</option>";

							} ?>
                        </select>
                    </td>
                </tr>
            </table>
			<?php
		}
	}
}
