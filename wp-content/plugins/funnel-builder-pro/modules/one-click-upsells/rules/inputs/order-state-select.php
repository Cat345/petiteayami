<?php
if ( ! class_exists( 'wfocu_Input_Order_State_Select' ) ) {
	class wfocu_Input_Order_State_Select {
		public function __construct() {
			// vars
			$this->type = 'Order_State_Select';

			$this->defaults = array(
				'multiple'      => 0,
				'allow_null'    => 0,
				'choices'       => array(),
				'default_value' => array(),
				'class'         => ''
			);
		}

		public function render( $field, $value = null ) {

			$field = array_merge( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}
			$chosen_states = $value;

			?>

            <select id="<?php echo $field['id']; ?>" name="<?php echo $field['name']; ?>[states][]" class="chosen_select <?php echo esc_attr( $field['class'] ); ?>" multiple="multiple" data-placeholder="<?php echo( isset( $field['placeholder'] ) ? $field['placeholder'] : __( 'Search...', 'woofunnels-upstroke-one-click-upsell' ) ); ?>">
				<?php
				WC()->countries->country_dropdown_options( '', $chosen_states );
				?>
            </select>

			<?php
		}

	}
}

?>