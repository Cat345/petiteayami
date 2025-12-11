<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WFFN_Pro_Dependencies' ) ) {
	require_once __DIR__.'/class-wffn-pro-dependencies.php';
}

/**
 * WFFN lite Detection
 */
if ( ! function_exists( 'wffn_is_lite_active' ) ) {
	function wffn_is_lite_active() {
		return ( WFFN_Pro_Dependencies::wffn_lite_active_check() && defined( 'WFFN_VERSION' ) );
	}
}
