<?php
if ( ! class_exists( 'wfty_Input_Cart_Category_Select' ) ) {
	#[\AllowDynamicProperties]
	class wfty_Input_Cart_Category_Select {
		public function __construct() {
			// vars
			$this->type = 'Cart_Category_Select';

			$this->defaults = array(
				'multiple'      => 0,
				'allow_null'    => 0,
				'choices'       => array(),
				'default_value' => array(),
				'class'         => '',
			);
		}

		public function render( $field, $value = null ) {

			$field = array_merge( $this->defaults, $field );
			if ( isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			} else {
				$field['id'] = '';
			}

			$current = isset( $value['categories'] ) ? $value['categories'] : array();
			$choices = $field['choices'];
			?>
			<table style="width:100%;">
				<tr>
					<td style="width:32px;"><?php esc_html_e( 'Quantity', 'funnel-builder-powerpack' ); ?></td>
					<td><?php esc_html_e( 'Categories', 'funnel-builder-powerpack' ); ?></td>
				</tr>
				<tr>
					<td style="width:32px; vertical-align:top;">
						<input type="text" id="<?php echo esc_attr( $field['id'] ); ?>_qty" name="<?php echo esc_attr( $field['name'] ); ?>[qty]" value="<?php echo esc_attr( isset( $value['qty'] ) ? $value['qty'] : 1 ); ?>"/>
					</td>
					<td>
						<select id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>[categories][]" class="chosen_select <?php echo esc_attr( $field['class'] ); ?>" multiple="multiple" data-placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : __( 'Search...', 'funnel-builder-powerpack' ) ); ?>">
							<?php
							foreach ( $choices as $choice => $title ) {
								$selected = in_array( $choice, $current );
								echo '<option value="' . esc_attr( $choice ) . '" ' . selected( $selected, true, false ) . '">' . esc_html( $title ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
			</table>

			<?php
		}
	}
}
?>
