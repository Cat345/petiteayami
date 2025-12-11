<?php
// @codingStandardsIgnoreFile

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wfacpkirki_get_option' ) ) {
	/**
	 * Get the value of a field.
	 * This is a deprecated function that we used when there was no API.
	 * Please use the WFACPKirki::get_option() method instead.
	 * Documentation is available for the new method on https://github.com/aristath/wfacpkirki/wiki/Getting-the-values
	 *
	 * @return mixed
	 */
	function wfacpkirki_get_option( $option = '' ) {
		_deprecated_function( __FUNCTION__, '1.0.0', sprintf( esc_attr__( '%1$s or %2$s', 'wfacpkirki' ), 'get_theme_mod', 'get_option' ) );
		return WFACPKirki::get_option( '', $option );
	}
}

if ( ! function_exists( 'wfacpkirki_sanitize_hex' ) ) {
	function wfacpkirki_sanitize_hex( $color ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->toCSS( \'hex\' )' );
		return WFACPKirki_Color::sanitize_hex( $color );
	}
}

if ( ! function_exists( 'wfacpkirki_get_rgb' ) ) {
	function wfacpkirki_get_rgb( $hex, $implode = false ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->toCSS( \'rgb\' )' );
		return WFACPKirki_Color::get_rgb( $hex, $implode );
	}
}

if ( ! function_exists( 'wfacpkirki_get_rgba' ) ) {
	function wfacpkirki_get_rgba( $hex = '#fff', $opacity = 100 ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->toCSS( \'rgba\' )' );
		return WFACPKirki_Color::get_rgba( $hex, $opacity );
	}
}

if ( ! function_exists( 'wfacpkirki_get_brightness' ) ) {
	function wfacpkirki_get_brightness( $hex ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'ariColor::newColor( $color )->lightness' );
		return WFACPKirki_Color::get_brightness( $hex );
	}
}

if ( ! function_exists( 'WFACPKirki' ) ) {
	function WFACPKirki() {
		return wfacpclass-kirki;
	}
}
