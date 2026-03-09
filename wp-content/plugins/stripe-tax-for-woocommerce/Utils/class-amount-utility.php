<?php
/**
 * Currency converter to and from currency subunits utility.
 *
 * @package Stripe\StripeTaxForWooCommerce\Utils
 */

namespace Stripe\StripeTaxForWooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Currency converter to and from currency subunits utility.
 */
abstract class Amount_Utility {
	// See https://docs.stripe.com/currencies#presentment-currencies.
	public const CURRENCY_SUBDIVISION_MULTIPLIER = array(
		''    => 2,
		'BIF' => 0,
		'CLP' => 0,
		'DJF' => 0,
		'GNF' => 0,
		'JPY' => 0,
		'KMF' => 0,
		'KRW' => 0,
		'MGA' => 0,
		'PYG' => 0,
		'RWF' => 0,
		'UGX' => 0,
		'VND' => 0,
		'VUV' => 0,
		'XAF' => 0,
		'XOF' => 0,
		'XPF' => 0,
	);

	/**
	 * Currency number of decimals
	 *
	 * @var int $num_decimals Currency number of decimals
	 */
	protected static $num_decimals;

	/**
	 * Currency number of decimals as multiplier
	 *
	 * @var int $decimals_multiplier Currency number of decimals as multiplier
	 */
	protected static $decimals_multiplier;

	/**
	 * Initialise internal number of decimals based on currency (Stripe minor units)
	 *
	 * @param string $currency The currency.
	 */
	protected static function init_internals( $currency = '' ) {
		$currency = strtoupper( (string) $currency );

		if ( isset( static::CURRENCY_SUBDIVISION_MULTIPLIER[ $currency ] ) ) {
			static::$num_decimals = static::CURRENCY_SUBDIVISION_MULTIPLIER[ $currency ];
		} else {
			// Fallback mos currencies use 2 decimals.
			static::$num_decimals = static::CURRENCY_SUBDIVISION_MULTIPLIER[''];
		}

		static::$decimals_multiplier = pow( 10, static::$num_decimals );
	}

	/**
	 * Rounds an amount
	 *
	 * @param float $amount The amount.
	 */
	public static function round( $amount ) {
		// Ensure we default (2 decimals) even if nothing called init yet.
		if ( ! isset( static::$num_decimals ) || ! isset( static::$decimals_multiplier ) ) {
			static::init_internals( '' );
		}

		$amount_as_string = number_format( $amount, static::$num_decimals, '.', '' );

		return (float) $amount_as_string;
	}

	/**
	 * Converts an amount from cents
	 *
	 * @param float|int $amount   The amount in minor units.
	 * @param string    $currency The currency.
	 *
	 * @return float Amount in major units.
	 */
	public static function from_cents( $amount, $currency ) {
		// Initialise decimals based on currency, not WC settings.
		static::init_internals( $currency );

		// For zero-decimal currencies (JPY, etc.), minor == major.
		if ( 0 === static::$num_decimals ) {
			return (float) $amount;
		}

		$amount_as_string    = number_format( $amount, 0, '', '' );
		$amount_num_decimals = strlen( $amount_as_string );

		if ( $amount_num_decimals <= static::$num_decimals ) {
			$integer_part = '0';

			for ( $i = $amount_num_decimals; $i < static::$num_decimals; $i++ ) {
				$amount_as_string = '0' . $amount_as_string;
			}

			$decimal_part = $amount_as_string;
		} else {
			$integer_part = substr( $amount_as_string, 0, -static::$num_decimals );
			$decimal_part = substr( $amount_as_string, -static::$num_decimals );
		}

		$units = (float) ( $integer_part . '.' . $decimal_part );

		return $units;
	}

	/**
	 * Converts an amount to cents
	 *
	 * @param float  $amount The amount.
	 * @param string $currency The currency.
	 *
	 * @return int Amount in minor units.
	 */
	public static function to_cents( $amount, $currency ) {
		// Always initialise with the correct currency, independent of WC decimals.
		static::init_internals( $currency );

		return (int) round( $amount * static::$decimals_multiplier );
	}
}
