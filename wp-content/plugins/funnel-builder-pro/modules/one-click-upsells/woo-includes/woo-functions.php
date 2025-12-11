<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WFOCU_WC_Dependencies' ) ) {
	require_once 'class-wfocu-wc-dependencies.php';
}

/**
 * WC Detection
 */
if ( ! function_exists( 'wfocu_is_woocommerce_active' ) ) {
	function wfocu_is_woocommerce_active() {
		return WFOCU_WC_Dependencies::woocommerce_active_check();
	}

}
