<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'WFOCU_Logger' ) ) {
	/**
	 * Class WFOCU_Logger
	 * @package UpStroke
	 * @author WooFunnels
	 */
	class WFOCU_Logger {

		private static $ins = null;
		public $wc_logger = null;

		public function __construct() {
		}

		public static function get_instance() {
			if ( self::$ins === null ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		public function log( $message, $level = 'info' ) {
			if ( did_action( 'plugins_loaded' ) && true === WFOCU_Core()->data->get_option( 'enable_log' ) ) {

				if ( ! is_a( $this->wc_logger, 'WC_Logger' ) ) {
					$this->wc_logger = WFOCU_WC_Compatibility::new_wc_logger();
				}
				if ( is_a( $this->wc_logger, 'WC_Logger' ) ) {
					$get_user_ip     = WC_Geolocation::get_ip_address();
					$message_with_ip = $get_user_ip . ' ' . $message;

					$this->wc_logger->log( $level, $message_with_ip, array( 'source' => 'woofunnels_upstroke_' . $this->get_postfix() ) );

				}
			}

		}

		public function get_postfix() {

			$get_time = new WC_DateTime();
			$get_hour = absint( $get_time->date( 'H' ) );
			$postfix  = strtotime( gmdate( 'd F Y 00:00:00' ) );
			if ( $get_hour > 12 ) {
				$postfix = strtotime( gmdate( 'd F Y 12:00:00' ) );
			}

			return $postfix;
		}


	}

	if ( class_exists( 'WFOCU_Logger' ) ) {
		WFOCU_Core::register( 'log', 'WFOCU_Logger' );
	}
}
