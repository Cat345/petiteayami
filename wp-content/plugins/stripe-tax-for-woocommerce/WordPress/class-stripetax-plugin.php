<?php
/**
 * Hooks management class.
 *
 * @package Stripe\StripeTaxForWooCommerce\WordPress
 */

declare(strict_types=1);

namespace Stripe\StripeTaxForWooCommerce\WordPress;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers\Tax_Rate_Tax_Calculation;
use Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers\Cart_Tax_Calculation;
use Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers\Order_Tax_Calculation;
use Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers\Order_Tax_Transaction;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTaxTaxRateHooks;

/**
 * Class responsible fr registerint and unregistering hook handlers
 */
abstract class StripeTax_Plugin {

	/**
	 * Enables hook handlers
	 */
	public static function load() {
		StripeTaxTaxRateHooks::register_hook_handlers();
		Cart_Tax_Calculation::register_hook_handlers();
		Order_Tax_Calculation::register_hook_handlers();
		Order_Tax_Transaction::register_hook_handlers();
	}

	/**
	 * Disables hook handlers
	 */
	public static function unload() {
		StripeTaxTaxRateHooks::unregister_hook_handlers();
		Cart_Tax_Calculation::unregister_hook_handlers();
		Order_Tax_Calculation::unregister_hook_handlers();
		Order_Tax_Transaction::unregister_hook_handlers();
	}
}
