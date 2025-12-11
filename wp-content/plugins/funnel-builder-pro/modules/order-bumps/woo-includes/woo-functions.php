<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WFOB_WC_Dependencies' ) ) {
	require_once 'class-wfob-wc-dependencies.php';
}

/**
 * WC Detection
 */
if ( ! function_exists( 'wfob_is_woocommerce_active' ) ) {
	function wfob_is_woocommerce_active() {
		return WFOB_WC_Dependencies::woocommerce_active_check();
	}
}
