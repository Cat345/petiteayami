<?php
/**
 * WordPress Customize Setting classes
 *
 * @package WFOCUKirki
 * @subpackage Modules
 * @since 3.0.0
 */
if ( ! class_exists( 'WFOCUKirki_Setting_Site_Option' ) ) {
	/**
	 * Handles saving and sanitizing of user-meta.
	 *
	 * @since 3.0.0
	 * @see WP_Customize_Setting
	 */
	class WFOCUKirki_Setting_Site_Option extends WP_Customize_Setting {

		/**
		 * Type of customize settings.
		 *
		 * @access public
		 * @since 3.0.0
		 * @var string
		 */
		public $type = 'site_option';

		/**
		 * Get the root value for a setting, especially for multidimensional ones.
		 *
		 * @access protected
		 *
		 * @param mixed $default Value to return if root does not exist.
		 *
		 * @return mixed
		 * @since 3.0.0
		 */
		protected function get_root_value( $default = null ) {
			return get_site_option( $this->id_data['base'], $default );
		}

		/**
		 * Set the root value for a setting, especially for multidimensional ones.
		 *
		 * @access protected
		 *
		 * @param mixed $value Value to set as root of multidimensional setting.
		 *
		 * @return bool Whether the multidimensional root was updated successfully.
		 * @since 3.0.0
		 */
		protected function set_root_value( $value ) {
			return update_site_option( $this->id_data['base'], $value );
		}

		/**
		 * Save the value of the setting, using the related API.
		 *
		 * @access protected
		 *
		 * @param mixed $value The value to update.
		 *
		 * @return bool The result of saving the value.
		 * @since 3.0.0
		 */
		protected function update( $value ) {
			return $this->set_root_value( $value );
		}

		/**
		 * Fetch the value of the setting.
		 *
		 * @access protected
		 * @return mixed The value.
		 * @since 3.0.0
		 */
		public function value() {
			return $this->get_root_value( $this->default );
		}
	}
}