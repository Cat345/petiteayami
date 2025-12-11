<?php
/**
 * Customizer Control: dimension
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A text control with validation for CSS units.
 */
if ( ! class_exists( 'WFACPKirki_Control_Dimension' ) ) {
	class WFACPKirki_Control_Dimension extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfacpkirki-dimension';

		/**
		 * Enqueue control related scripts/styles.
		 *
		 * @access public
		 */
		public function enqueue() {
			parent::enqueue();

			wp_localize_script( 'wfacpkirki-script', 'dimensionwfacpkirkiL10n', array(
				'invalid-value' => esc_attr__( 'Invalid Value', 'wfacpkirki' ),
			) );
		}

		/**
		 * An Underscore (JS) template for this control's content (but not its container).
		 *
		 * Class variables for this control class are available in the `data` JS object;
		 * export custom variables by overriding {@see WP_Customize_Control::to_json()}.
		 *
		 * @see WP_Customize_Control::print_template()
		 *
		 * @access protected
		 */
		protected function content_template() {
			?>
            <label class="customizer-text">
                <# if ( data.label ) { #><span class="customize-control-title">{{{ data.label }}}</span><# } #>
                <# if ( data.description ) { #><span class="description customize-control-description">{{{ data.description }}}</span><# } #>
                <div class="input-wrapper">
                    <# var val = ( data.value && _.isString( data.value ) ) ? data.value.replace( '%%', '%' ) : ''; #>
                    <input {{{ data.inputAttrs }}} type="text" value="{{ val }}"/>
                </div>
            </label>
			<?php
		}
	}
}