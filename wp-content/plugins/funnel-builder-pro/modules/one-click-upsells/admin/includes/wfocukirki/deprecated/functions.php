<?php
// @codingStandardsIgnoreFile

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wfocukirki_get_option' ) ) {
	/**
	 * Get the value of a field.
	 * This is a deprecated function that we used when there was no API.
	 * Please use the WFOCUKirki::get_option() method instead.
	 * Documentation is available for the new method on https://github.com/aristath/kirki/wiki/Getting-the-values
	 *
	 * @return mixed
	 */
	function wfocukirki_get_option( $option = '' ) {
		_deprecated_function( __FUNCTION__, '1.0.0', sprintf( esc_attr__( '%1$s or %2$s', 'wfocukirki' ), 'get_theme_mod', 'get_option' ) );
		return WFOCUKirki::get_option( '', $option );
	}
}

if ( ! function_exists( 'wfocukirki_sanitize_hex' ) ) {
	function wfocukirki_sanitize_hex( $color ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->toCSS( \'hex\' )' );
		return WFOCUKirki_Color::sanitize_hex( $color );
	}
}

if ( ! function_exists( 'wfocukirki_get_rgb' ) ) {
	function wfocukirki_get_rgb( $hex, $implode = false ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->toCSS( \'rgb\' )' );
		return WFOCUKirki_Color::get_rgb( $hex, $implode );
	}
}

if ( ! function_exists( 'wfocukirki_get_rgba' ) ) {
	function wfocukirki_get_rgba( $hex = '#fff', $opacity = 100 ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->toCSS( \'rgba\' )' );
		return WFOCUKirki_Color::get_rgba( $hex, $opacity );
	}
}

if ( ! function_exists( 'wfocukirki_get_brightness' ) ) {
	function wfocukirki_get_brightness( $hex ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->lightness' );
		return WFOCUKirki_Color::get_brightness( $hex );
	}
}

if ( ! function_exists( 'WFOCUKirki' ) ) {
	function WFOCUKirki() {
		return wfocukirki();
	}
}
