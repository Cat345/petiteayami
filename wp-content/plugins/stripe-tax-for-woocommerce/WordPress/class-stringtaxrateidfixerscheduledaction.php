<?php
/**
 * Stripe ID Fixer - recurring scheduled action (Action Scheduler few minutes).
 *
 * @package Stripe\StripeTaxForWooCommerce\WordPress
 */

namespace Stripe\StripeTaxForWooCommerce\WordPress;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\StringTaxRateIdFixer;


/**
 * Handles scheduling and execution of Stripe ID fixing tasks.
 *
 * This class ensures that orders missing Stripe IDs are processed periodically.
 */
class StringTaxRateIdFixerScheduledAction {

	const HOOK_NAME = 'stripe_string_tax_rate_id_fixer';
	const GROUP     = 'stripe-string-tax-rate-id-fixer';
	const INTERVAL  = 300;

	/**
	 * Bootstrap hooks.
	 */
	public static function register() {
		$string_tax_rate_id_fixer_as_status = get_option( 'stripe_tax_string_tax_rate_id_fixer_as_status' );

		if ( 'done' === $string_tax_rate_id_fixer_as_status ) {
			return;
		}

		// Register the task handler with AS.
		add_action( self::HOOK_NAME, array( static::class, 'run' ) );

		add_action( 'action_scheduler_init', array( static::class, 'action_scheduler_init' ) );
	}

	/**
	 * Hook 'action_scheduler_init' handler
	 */
	public static function action_scheduler_init() {
		$num_processed_orders = get_option( static::HOOK_NAME . '_num_processed_orders' );

		if ( false !== $num_processed_orders && '0' === $num_processed_orders ) {
			static::unschedule_all();
			return;
		}

		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		// Check for next scheduled action.
		$next = as_next_scheduled_action( self::HOOK_NAME, array(), self::GROUP );

		if ( ! $next ) {
			as_schedule_recurring_action( time() + static::INTERVAL, static::INTERVAL, self::HOOK_NAME, array(), self::GROUP );
		}
	}

	/**
	 * The task body. This is executed by Action Scheduler when due.
	 */
	public static function run() {
		$num_processed_orders = StringTaxRateIdFixer::process();

		// Optional: mark a flag that it ran, if you want to assert execution later.
		update_option( static::HOOK_NAME . '_num_processed_orders', $num_processed_orders, false );
	}

	/**
	 * Utility: remove any scheduled/pending copies (cleanup if needed).
	 */
	public static function unschedule_all() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_NAME, array(), self::GROUP );
			update_option( 'stripe_tax_string_tax_rate_id_fixer_as_status', 'done' );
		}
	}
}
