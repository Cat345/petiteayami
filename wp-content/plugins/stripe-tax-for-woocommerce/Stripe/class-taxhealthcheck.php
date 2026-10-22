<?php
/**
 * Stripe Tax diagnostics and prerequisite checks.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTax;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;

/**
 * Validates Stripe Tax prerequisites and produces actionable diagnostics.
 */
class TaxHealthCheck {
	const CACHE_TTL_SECONDS      = 300; // 5 minutes.
	const CACHE_TRANSIENT_PREFIX = 'stripe_tax_health_check_';

	/**
	 * Run diagnostics and return a health report.
	 *
	 * @param bool $force Force a fresh check and skip transient cache.
	 * @return array
	 */
	public static function run( bool $force = false ): array {
		$mode    = (int) Options::get_mode_type();
		$api_key = (string) Options::get_current_mode_key();
		$cache   = self::get_cache_key( $mode, $api_key, wc_tax_enabled() );

		if ( ! $force ) {
			$cached = get_transient( $cache );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$report = array(
			'is_healthy'  => true,
			'issue_count' => 0,
			'issues'      => array(),
			'checked_at'  => gmdate( 'c' ),
			'status'      => 'healthy',
		);

		$has_valid_api_key         = true;
		$is_tax_collection_enabled = Options::is_live_mode_enabled();

		if ( ! $is_tax_collection_enabled ) {
			$report['issues'][] = self::create_issue(
				'stripe_tax_collection_disabled',
				__( 'Enable tax collection is turned off. Turn it on to start calculating and collecting tax.', 'stripe-tax-for-woocommerce' ),
				'',
				admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce' )
			);
		}

		if ( ! wc_tax_enabled() ) {
			$report['issues'][] = self::create_issue(
				'woocommerce_taxes_disabled',
				__( 'The Stripe Tax plugin requires tax rates and calculations to be enabled. Go to General and Enable taxes', 'stripe-tax-for-woocommerce' ),
				__( 'General', 'stripe-tax-for-woocommerce' ),
				admin_url( 'admin.php?page=wc-settings&tab=general' )
			);
		}

		if ( '' === $api_key ) {
			$has_valid_api_key  = false;
			$report['status']   = 'disconnected';
			$report['issues'][] = self::create_issue(
				'stripe_api_key_missing',
				sprintf(
					/* translators: %s: URL to Stripe Dashboard API keys page */
					__( 'Stripe Restricted key is missing. You can generate your Restricted key from the <a href="%s" target="_blank" rel="noopener noreferrer" class="js-target-blank">Woo Commerce Tax Stripe App</a>.', 'stripe-tax-for-woocommerce' ),
					esc_url( 'https://marketplace.stripe.com/apps/install/link/com.stripe.woocommerce-tax' )
				)
			);
		} else {
			$key_validation = StripeTax::validate_keys( $mode, $api_key );
			if ( is_array( $key_validation ) && ! empty( $key_validation['error'] ) ) {
				$has_valid_api_key  = false;
				$report['status']   = 'disconnected';
				$report['issues'][] = self::create_issue(
					'stripe_api_key_invalid',
					sprintf(
						/* translators: %s: Validation error returned for the Stripe API key. */
						__( 'Stripe API key validation failed: %s', 'stripe-tax-for-woocommerce' ),
						(string) $key_validation['error_msg']
					),
					__( 'Reconnect Stripe Tax', 'stripe-tax-for-woocommerce' ),
					admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce' )
				);
			}
		}

		if ( $has_valid_api_key ) {
			try {
				$tax_settings = ( new TaxSettings( $api_key ) )->get_settings( $force );
				if ( ! self::has_head_office_address( $tax_settings ) ) {
					$report['issues'][] = self::create_issue(
						'tax_head_office_address_missing',
						sprintf(
							/* translators: 1: URL to Stripe Tax Settings API docs. 2: URL to Stripe Dashboard Tax settings page. */
							__( 'Before managing tax registrations, you must set your head office address. You can achieve this by calling the <a href="%1$s" target="_blank" rel="noopener noreferrer" class="js-target-blank">Tax Settings API</a> or updating your business information in the Stripe Dashboard under <a href="%2$s" target="_blank" rel="noopener noreferrer" class="js-target-blank">Tax Settings</a>.', 'stripe-tax-for-woocommerce' ),
							esc_url( 'https://docs.stripe.com/tax/settings-api' ),
							esc_url( 'https://dashboard.stripe.com/settings/tax' )
						)
					);
				}
			} catch ( \Throwable $e ) {
				$report['issues'][] = self::create_issue(
					'tax_settings_check_failed',
					sprintf(
						/* translators: %s: API error message while checking tax settings. */
						__( 'Could not verify Stripe Tax settings: %s', 'stripe-tax-for-woocommerce' ),
						$e->getMessage()
					),
					__( 'Retry diagnostics', 'stripe-tax-for-woocommerce' ),
					admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce&stripe_tax_run_diagnostics=1' )
				);
			}

			try {
				$registrations     = ( new TaxRegistrations( $api_key ) )->get_registrations( $force, false );
				$active_count      = self::count_registrations( $registrations );
				$has_registrations = $active_count > 0;

				if ( ! $has_registrations ) {
					$report['issues'][] = self::create_issue(
						'tax_registrations_missing',
						__( 'No active Stripe Tax registrations found. Add at least one active registration to collect tax.', 'stripe-tax-for-woocommerce' ),
						__( 'Add tax registration', 'stripe-tax-for-woocommerce' ),
						admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce&add_tax_registration' )
					);
				}
			} catch ( \Throwable $e ) {
				$report['issues'][] = self::create_issue(
					'registrations_check_failed',
					sprintf(
						/* translators: %s: API error message while checking tax registrations. */
						__( 'Could not verify Stripe Tax registrations: %s', 'stripe-tax-for-woocommerce' ),
						$e->getMessage()
					),
					__( 'Retry diagnostics', 'stripe-tax-for-woocommerce' ),
					admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce&stripe_tax_run_diagnostics=1' )
				);
			}
		}

		$report['issue_count'] = count( $report['issues'] );
		$report['is_healthy']  = 0 === $report['issue_count'];
		if ( $report['is_healthy'] ) {
			$report['status'] = 'healthy';
		} elseif ( 'healthy' === $report['status'] ) {
			$report['status'] = 'connected_with_issues';
		}

		set_transient( $cache, $report, self::CACHE_TTL_SECONDS );

		return $report;
	}

	/**
	 * True when the report contains only the missing restricted key issue.
	 *
	 * @param array $report Health report.
	 * @return bool
	 */
	public static function has_only_missing_restricted_key_issue( array $report ): bool {
		if ( ! isset( $report['issues'] ) || ! is_array( $report['issues'] ) || 1 !== count( $report['issues'] ) ) {
			return false;
		}

		return isset( $report['issues'][0]['code'] ) && 'stripe_api_key_missing' === $report['issues'][0]['code'];
	}

	/**
	 * Builds a normalized issue structure.
	 *
	 * @param string $code Issue code.
	 * @param string $message Human-readable message.
	 * @param string $action_label Label for the fix action.
	 * @param string $action_url URL for the fix action.
	 * @return array
	 */
	protected static function create_issue( string $code, string $message, string $action_label = '', string $action_url = '' ): array {
		return array(
			'code'         => $code,
			'message'      => $message,
			'action_label' => $action_label,
			'action_url'   => $action_url,
		);
	}

	/**
	 * Count registrations from Stripe response payload.
	 *
	 * @param mixed $registrations Stripe registrations list object.
	 * @return int
	 */
	protected static function count_registrations( $registrations ): int {
		if ( ! is_object( $registrations ) || ! isset( $registrations->data ) ) {
			return 0;
		}

		if ( is_array( $registrations->data ) ) {
			return count( $registrations->data );
		}

		if ( $registrations->data instanceof \Countable ) {
			return count( $registrations->data );
		}

		$count = 0;
		if ( is_iterable( $registrations->data ) ) {
			foreach ( $registrations->data as $item ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Determine whether Stripe Tax head office address is set.
	 *
	 * @param mixed $tax_settings Stripe Tax settings object.
	 * @return bool
	 */
	protected static function has_head_office_address( $tax_settings ): bool {
		if ( ! is_object( $tax_settings ) || ! isset( $tax_settings->head_office ) || ! is_object( $tax_settings->head_office ) ) {
			return false;
		}

		if ( ! isset( $tax_settings->head_office->address ) || ! is_object( $tax_settings->head_office->address ) ) {
			return false;
		}

		$address = $tax_settings->head_office->address;
		$country = isset( $address->country ) && is_string( $address->country ) ? trim( $address->country ) : '';

		return '' !== $country;
	}

	/**
	 * Build cache key scoped to mode and current API key.
	 *
	 * @param int    $mode Current Stripe Tax mode.
	 * @param string $api_key Current API key.
	 * @param bool   $wc_tax_enabled WooCommerce tax enabled flag.
	 * @return string
	 */
	protected static function get_cache_key( int $mode, string $api_key, bool $wc_tax_enabled ): string {
		return self::CACHE_TRANSIENT_PREFIX . md5( $mode . '|' . $api_key . '|' . (int) $wc_tax_enabled );
	}
}
