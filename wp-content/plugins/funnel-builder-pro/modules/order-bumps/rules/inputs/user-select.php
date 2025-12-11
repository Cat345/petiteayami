<?php
if ( ! class_exists( 'wfob_Input_User_Select' ) ) {
	class wfob_Input_User_Select {

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
                    <td><?php _e( 'Users', 'wordpress' ); ?></td>
                </tr>
                <tr>
                    <td>
                        <select <?php echo $mutiple ? 'multiple="multiple"' : ''; ?> id="<?php echo $field['id']; ?>" name="<?php echo $field['name']; ?>[]" class="ajax_chosen_select_users" data-placeholder="<?php _e( 'Select users&hellip;', 'woofunnels-aero-checkout' ); ?>">
							<?php
							foreach ( $user_ids as $user_id ) {
								echo "<option value='" . esc_attr( $user_id ) . "' " . selected( true, in_array( $user_id, $current ) ) . ">" . ( get_user_by( 'id', $user_id )->display_name ) . "</option>";

							} ?>
                        </select>
                    </td>
                </tr>
            </table>
			<?php
		}
	}
}