<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: hCaptcha for Forms and More
 * Version: 4.x
 * Author URI: https://www.hcaptcha.com/
 */

if ( ! class_exists( 'WFACP_HCaptcha' ) ) {
	#[AllowDynamicProperties]

	class WFACP_HCaptcha {
		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'actions' ), 101 );
		}

		public function actions() {
			try {
				if ( ! function_exists( 'hcaptcha' ) ) {
					return;
				}

				$hcaptcha_checkout = hcaptcha()->get( 'HCaptcha\WC\Checkout' );

				if ( ! $hcaptcha_checkout instanceof \HCaptcha\WC\Checkout ) {
					return;
				}

				remove_action( 'woocommerce_review_order_before_submit', array( $hcaptcha_checkout, 'add_captcha' ) );
				add_action( 'wfacp_woocommerce_review_order_before_submit', array( $hcaptcha_checkout, 'add_captcha' ) );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Silent failure by design
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_HCaptcha(), 'hcaptcha' );
}
