<?php
/**
 * Hekoers to get the values of a field.
 * WARNING: PLEASE DO NOT USE THESE.
 * we only have these for backwards-compatibility purposes.
 * please use get_option() & get_theme_mod() instead.
 *
 * @package     WFOCUKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */
if ( ! class_exists( 'WFOCUKirki_Values' ) ) {
	/**
	 * Wrapper class for static methods.
	 */
	class WFOCUKirki_Values {

		/**
		 * Constructor.
		 *
		 * @access public
		 * @since 3.0.10
		 */
		public function __construct() {

			add_filter( 'wfocukirki_values_get_value', array( $this, 'typography_field_tweaks' ), 10, 2 );
		}

		/**
		 * Tweaks for typography fields.
		 *
		 * @access public
		 *
		 * @param string|array $value The value.
		 * @param string $field_id The field-ID.
		 *
		 * @return array
		 * @since 3.0.10
		 */
		public function typography_field_tweaks( $value, $field_id ) {

			if ( isset( WFOCUKirki::$fields[ $field_id ] ) && isset( WFOCUKirki::$fields[ $field_id ]['type'] ) ) {
				if ( 'wfocukirki-typography' === WFOCUKirki::$fields[ $field_id ]['type'] ) {

					// Sanitize the value.
					// This also adds font-weight if it doesn't already exist.
					$value = WFOCUKirki_Field_Typography::sanitize( $value );

					// Combine font-family and font-backup.
					if ( isset( $value['font-family'] ) && isset( $value['font-backup'] ) ) {
						$value['font-family'] .= ', ' . $value['font-backup'];
						unset( $value['font-backup'] );
					}
				}
			}

			return $value;
		}


		/**
		 * Get the value of a field.
		 *
		 * @static
		 * @access public
		 *
		 * @param string $config_id The configuration ID. @see WFOCUKirki_Config.
		 * @param string $field_id The field ID.
		 *
		 * @return string|array
		 */
		public static function get_value( $config_id = '', $field_id = '' ) {

			// Make sure value is defined.
			$value = '';

			// This allows us to skip the $config_id argument.
			// If we skip adding a $config_id, use the 'global' configuration.
			if ( ( '' === $field_id ) && '' !== $config_id ) {
				$field_id  = $config_id;
				$config_id = 'global';
			}

			// If $config_id is empty, set it to 'global'.
			$config_id = ( '' === $config_id ) ? 'global' : $config_id;

			// Fallback to 'global' if $config_id is not found.
			if ( ! isset( WFOCUKirki::$config[ $config_id ] ) ) {
				$config_id = 'global';
			}

			if ( 'theme_mod' === WFOCUKirki::$config[ $config_id ]['option_type'] ) {

				// We're using theme_mods so just get the value using get_theme_mod.
				$default_value = null;
				if ( isset( WFOCUKirki::$fields[ $field_id ] ) && isset( WFOCUKirki::$fields[ $field_id ]['default'] ) ) {
					$default_value = WFOCUKirki::$fields[ $field_id ]['default'];
				}
				$value = get_theme_mod( $field_id, $default_value );

				return apply_filters( 'wfocukirki_values_get_value', $value, $field_id );
			}

			if ( 'option' === WFOCUKirki::$config[ $config_id ]['option_type'] ) {

				// We're using options.
				if ( '' !== WFOCUKirki::$config[ $config_id ]['option_name'] ) {

					// Options are serialized as a single option in the db.
					// We'll have to get the option and then get the item from the array.
					$options = get_option( WFOCUKirki::$config[ $config_id ]['option_name'] );

					if ( ! isset( WFOCUKirki::$fields[ $field_id ] ) && isset( WFOCUKirki::$fields[ WFOCUKirki::$config[ $config_id ]['option_name'] . '[' . $field_id . ']' ] ) ) {
						$field_id = WFOCUKirki::$config[ $config_id ]['option_name'] . '[' . $field_id . ']';
					}
					$setting_modified = str_replace( ']', '', str_replace( WFOCUKirki::$config[ $config_id ]['option_name'] . '[', '', $field_id ) );

					$default_value = ( isset( WFOCUKirki::$fields[ $field_id ] ) && isset( WFOCUKirki::$fields[ $field_id ]['default'] ) ) ? WFOCUKirki::$fields[ $field_id ]['default'] : '';
					$value         = ( isset( $options[ $setting_modified ] ) ) ? $options[ $setting_modified ] : $default_value;
					$value         = maybe_unserialize( $value );

					return apply_filters( 'wfocukirki_values_get_value', $value, $field_id );
				}

				// Each option separately saved in the db.
				$value = get_option( $field_id, WFOCUKirki::$fields[ $field_id ]['default'] );

				return apply_filters( 'wfocukirki_values_get_value', $value, $field_id );

			} // End if().

			return apply_filters( 'wfocukirki_values_get_value', $value, $field_id );

		}

		/**
		 * Gets the value or fallsback to default.
		 *
		 * @static
		 * @access public
		 *
		 * @param array $field The field aruments.
		 *
		 * @return string|array
		 */
		public static function get_sanitized_field_value( $field ) {
			$value = $field['default'];
			if ( isset( $field['option_type'] ) && 'theme_mod' === $field['option_type'] ) {
				$value = get_theme_mod( $field['settings'], $field['default'] );
			} elseif ( isset( $field['option_type'] ) && 'option' === $field['option_type'] ) {
				if ( isset( $field['option_name'] ) && '' !== $field['option_name'] ) {
					$all_values     = get_option( $field['option_name'], array() );
					$sub_setting_id = str_replace( array( ']', $field['option_name'] . '[' ), '', $field['settings'] );
					if ( isset( $all_values[ $sub_setting_id ] ) ) {
						$value = $all_values[ $sub_setting_id ];
					}
				} else {
					$value = get_option( $field['settings'], $field['default'] );
				}
			}

			return $value;

		}
	}
}