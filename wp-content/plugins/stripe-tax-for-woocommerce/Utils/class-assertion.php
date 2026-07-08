<?php
/**
 * Assertion utility for logging and validating conditions.
 *
 * @package Stripe\StripeTaxForWooCommerce\Utils
 */

namespace Stripe\StripeTaxForWooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxLogger;

/**
 * Assertion class for condition validation and error logging.
 */
abstract class Assertion {

	const LOW_SEVERITY  = 0;
	const HIGH_SEVERITY = 1;

	/**
	 * Asserts a condition and logs an error if the condition is false.
	 *
	 * @param mixed  $cond The condition to check.
	 * @param string $file The file name where assertion is called.
	 * @param string $message The error message.
	 * @param mixed  $context The context data for logging.
	 * @return bool True if condition is true, false otherwise.
	 */
	public static function assert( $cond, $file, $message, $context ) {
		if ( $cond ) {
			return true;
		}

		$context_json = wp_json_encode( $context );
		$context_text = false !== $context_json ? $context_json : '[context could not be encoded]';

		StripeTaxLogger::log_error( (string) $message . ' | context: ' . (string) $context_text );

		return false;
	}
}
