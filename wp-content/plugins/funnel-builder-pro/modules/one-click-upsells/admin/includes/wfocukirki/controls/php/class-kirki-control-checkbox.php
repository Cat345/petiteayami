<?php
/**
 * Customizer Control: checkbox.
 *
 * Creates a new custom control.
 * Custom controls contains all background-related options.
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.26
 */
if ( ! class_exists( 'WFOCUKirki_Control_Checkbox' ) ) {
	/**
	 * Adds a checkbox control.
	 *
	 * @since 3.0.26
	 */
	class WFOCUKirki_Control_Checkbox extends WFOCUKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfocukirki-checkbox';

		/**
		 * Render the control's content.
		 * Verbatim copy from WP_Customize_Control->render_content.
		 *
		 * @since 3.0.26
		 */
		protected function render_content() {
			$input_id         = '_customize-input-' . $this->id;
			$description_id   = '_customize-description-' . $this->id;
			$describedby_attr = ( ! empty( $this->description ) ) ? ' aria-describedby="' . esc_attr( $description_id ) . '" ' : '';
			?>
            <span class="customize-inside-control-row">
			<input
                id="<?php echo esc_attr( $input_id ); ?>"
				<?php echo $describedby_attr; ?>
				type="checkbox"
                value="<?php echo esc_attr( $this->value() ); ?>"
				<?php $this->link(); ?>
				<?php checked( $this->value() ); ?>
			/>
			<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $this->label ); ?></label>
			<?php if ( ! empty( $this->description ) ) : ?>
                <span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo $this->description; ?></span>
			<?php endif; ?>
		</span>
			<?php
		}
	}
}