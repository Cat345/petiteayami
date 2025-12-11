<?php
/**
 * Customizer Control: code.
 *
 * Creates a new custom control.
 * Custom controls accept raw HTML/JS.
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show warning if old WordPress.
 */
if ( ! class_exists( 'WP_Customize_Code_Editor_Control' ) ) {
	/**
	 * Adds a warning message instead of the control.
	 */
	class WFOCUKirki_Control_Code extends WFOCUKirki_Control_Base {

		/**
		 * The message.
		 *
		 * @since 3.0.21
		 */
		protected function content_template() {
			?>
			<div class="wfocu-notice notice notice-error" data-type="error"><div class="notification-message">
				<?php esc_attr_e( 'Please update your WordPress installation to a version newer than 4.9 to access the code control.', 'wfocukirki' ); ?>
			</div></div>
			<?php
		}
	}
} else {

	/**
	 * Adds a "code" control, alias of the WP_Customize_Code_Editor_Control class.
	 */
	class WFOCUKirki_Control_Code extends WP_Customize_Code_Editor_Control {}
}
