<?php

if ( ! function_exists( 'bwfabt_clean' ) ) {
	function bwfabt_clean( $var ) {
		if ( is_array( $var ) ) {
			return array_map( 'bwfabt_clean', $var );
		}

		return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
	}
}

/**
 * Converts a string (e.g. 'yes' or 'no' , 'true') to a bool.
 *
 * @param $string
 *
 * @return bool
 */
if ( ! function_exists( 'bwfabt_string_to_bool' ) ) {
	function bwfabt_string_to_bool( $string ) {
		return is_bool( $string ) ? $string : ( 'yes' === strtolower( $string ) || 1 === $string || 'true' === strtolower( $string ) || '1' === $string );
	}
}

/**
 * Converts a bool to a 'yes' or 'no'.
 *
 * @param bool $bool String to convert.
 *
 * @return string
 * @since 3.0.0
 */
if ( ! function_exists( 'bwfabt_bool_to_string' ) ) {
	function bwfabt_bool_to_string( $bool ) {
		if ( ! is_bool( $bool ) ) {
			$bool = bwfabt_string_to_bool( $bool );
		}

		return true === $bool ? 'yes' : 'no';
	}
}

if ( ! function_exists( 'bwfabt_is_wc_active' ) ) {
	function bwfabt_is_wc_active() {
		return bwfabt_is_plugin_active( 'woocommerce/woocommerce.php' );
	}
}

if ( ! function_exists( 'bwfabt_is_plugin_active' ) ) {
	function bwfabt_is_plugin_active( $plugin_basename ) {
		if ( in_array( $plugin_basename, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			return true;
		}

		return false;
	}
}


