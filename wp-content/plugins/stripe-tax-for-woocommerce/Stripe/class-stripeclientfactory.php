<?php
/**
 * Stripe Client Factory service
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\SDK\lib\StripeClient;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;

/**
 * Stripe Client Factory service
 */
abstract class StripeClientFactory {
	/**
	 * StripeClient object
	 *
	 * @var \Stripe\StripeTaxForWooCommerce\SDK\lib\StripeClient
	 */
	protected static $stripe_client;

	/**
	 * Create a Stripe Tax Client instance
	 *
	 * @return object
	 */
	public static function get_stripe_client() {
		if ( ! static::$stripe_client ) {
			$api_key = Options::get_current_mode_key();

			static::$stripe_client = new StripeClient( $api_key );
		}

		return static::$stripe_client;
	}
}
