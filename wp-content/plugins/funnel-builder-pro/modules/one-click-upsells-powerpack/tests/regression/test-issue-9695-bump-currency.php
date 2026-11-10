<?php
/**
 * Regression test for issue #9695 — Order bump "fixed on Regular Price" discount must be
 * currency-converted for a product on a Sublium plan.
 *
 * Bug: WFOCU_Sublium_Compatibility::override_cart_price_for_regular_basis_discount() computed the
 * charge from a currency-converted regular price (get_true_regular_price()) but subtracted the
 * fixed discount_amount verbatim — and that amount is entered in the store's base currency. With
 * CURCY on CAD (rate 1.3687) a USD 49.99 product with a USD 20 "fixed on regular" bump discount
 * was charged (49.99*1.3687) - 20 = C$48.42 on every subscription-price pass (incl. the recurring
 * total), instead of the correct (49.99 - 20)*1.3687 = C$41.05.
 *
 * Fix: the method converts the base-currency fixed amount to the active currency (wmc_get_price),
 * behind the `wfocu_sublium_regular_basis_discount_amount` filter. This test asserts that seam:
 *   - CAD:  the override converts the base 20 -> 27.37 (so the net charge is C$41.05).
 *   - USD (base): the amount is unchanged at 20 (so behaviour is unchanged in the base currency).
 * On the pre-fix code the filter never fires, so the captured value stays null and the test fails —
 * i.e. this guards the regression rather than re-implementing the arithmetic.
 *
 * CURCY reads its config once at bootstrap, so the test runs in two phases: an OUTER phase that
 * activates + configures CURCY (USD + CAD @ 1.3687) and then re-invokes itself as an INNER phase in
 * a fresh WP process where that config is live. The OUTER phase restores CURCY's state afterwards;
 * the INNER phase creates and removes its own product + Sublium plan.
 *
 * Requires: funnel-builder-pro + sublium-subscriptions-for-woocommerce active, woo-multi-currency
 * (CURCY) installed. Run:
 *   wp eval-file wp-content/plugins/funnel-builder-pro/modules/one-click-upsells-powerpack/tests/regression/test-issue-9695-bump-currency.php
 *
 * @package FunnelBuilderPro\Powerpack\Tests
 */

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return; // Only ever run from wp-cli eval-file.
}

$is_inner = isset( $args[0] ) && 'inner' === $args[0];

/* ========================================================================
 * OUTER PHASE — configure CURCY at the DB level, then re-run self in a
 * fresh process so the config is live at bootstrap. Restore state after.
 * ===================================================================== */
if ( ! $is_inner ) {
	if ( ! file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
		WP_CLI::error( 'Cannot locate wp-admin/includes/plugin.php' );
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$curcy_file = 'woo-multi-currency/woo-multi-currency.php';
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $curcy_file ) ) {
		WP_CLI::warning( 'SKIPPED: woo-multi-currency (CURCY) is not installed.' );
		return;
	}

	$curcy_was_active    = is_plugin_active( $curcy_file );
	$curcy_params_backup = get_option( 'woo_multi_currency_params', array() );

	if ( ! $curcy_was_active ) {
		activate_plugin( $curcy_file );
	}
	$p                       = get_option( 'woo_multi_currency_params', array() );
	$p['enable']             = 1;
	$p['enable_fixed_price'] = 1;
	$p['currency']           = array( 'USD', 'CAD' );
	$p['currency_rate']      = array( '1', '1.3687' );
	$p['currency_rate_fee']  = array( '0', '0' );
	$p['currency_pos']       = array( 'left', 'left' );
	$p['currency_decimals']  = array( '2', '2' );
	$p['currency_custom']    = array( 'USD', 'C$' );
	$p['currency_hidden']    = array( '0', '0' );
	$p['currency_default']   = 'USD';
	update_option( 'woo_multi_currency_params', $p );

	$inner = WP_CLI::runcommand(
		'eval-file ' . escapeshellarg( __FILE__ ) . ' inner',
		array( 'return' => 'all', 'exit_error' => false, 'launch' => true )
	);

	// Restore CURCY to how it was before the test.
	update_option( 'woo_multi_currency_params', $curcy_params_backup );
	if ( ! $curcy_was_active ) {
		deactivate_plugins( $curcy_file );
	}

	if ( ! empty( $inner->stdout ) ) {
		WP_CLI::log( rtrim( $inner->stdout ) );
	}
	if ( ! empty( $inner->stderr ) ) {
		WP_CLI::log( rtrim( $inner->stderr ) );
	}
	if ( 0 !== (int) $inner->return_code ) {
		WP_CLI::halt( (int) $inner->return_code );
	}
	return;
}

/* ========================================================================
 * INNER PHASE — CURCY is active + configured (USD + CAD) at bootstrap.
 * Provision product + plan, exercise the override, assert, clean up.
 * ===================================================================== */
$fail   = array();
$pass   = array();
$assert = function ( $cond, $label ) use ( &$fail, &$pass ) {
	if ( $cond ) {
		$pass[] = $label;
		WP_CLI::log( "  PASS  {$label}" );
	} else {
		$fail[] = $label;
		WP_CLI::log( "  FAIL  {$label}" );
	}
};
$approx = function ( $a, $b, $eps = 0.02 ) {
	return abs( (float) $a - (float) $b ) <= $eps;
};

if ( ! class_exists( 'WFOCU_Sublium_Compatibility' ) ) {
	WP_CLI::error( 'WFOCU_Sublium_Compatibility not loaded (need funnel-builder-pro + Sublium active).' );
}
if ( ! class_exists( '\Sublium_WCS\Includes\database\GroupPlans' ) ) {
	WP_CLI::error( 'Sublium classes not loaded. Activate sublium-subscriptions-for-woocommerce.' );
}
if ( ! function_exists( 'wmc_get_price' ) ) {
	WP_CLI::error( 'wmc_get_price() unavailable — CURCY did not load in the inner process.' );
}

$product_id = $plan_id = $group_id = $rel_id = 0;
$teardown   = function () use ( &$product_id, &$plan_id, &$group_id, &$rel_id ) {
	global $wpdb;
	if ( ! empty( $product_id ) ) {
		wp_delete_post( $product_id, true );
	}
	if ( ! empty( $plan_id ) ) {
		$wpdb->delete( $wpdb->prefix . 'sublium_wcs_plan', array( 'id' => $plan_id ) );
	}
	if ( ! empty( $group_id ) ) {
		$wpdb->delete( $wpdb->prefix . 'sublium_wcs_plan_group', array( 'id' => $group_id ) );
	}
	if ( ! empty( $rel_id ) ) {
		$wpdb->delete( $wpdb->prefix . 'sublium_wcs_plan_relations', array( 'id' => $rel_id ) );
	}
};

// Product @ 49.99 + a 0% Subscribe & Save plan assigned to it.
$prod = new WC_Product_Simple();
$prod->set_name( 'FB9695 regression product' );
$prod->set_regular_price( '49.99' );
$prod->set_price( '49.99' );
$prod->set_status( 'publish' );
$product_id = $prod->save();

$group_id = ( new \Sublium_WCS\Includes\database\GroupPlans() )->create(
	array( 'type' => 1, 'title' => 'FB9695 Subscribe & Save', 'product_type' => 1, 'data' => array( 'plan_order' => array() ) )
);
$plan_id = ( new \Sublium_WCS\Includes\database\Plan() )->create(
	array(
		'plan_group_id' => (int) $group_id, 'title' => 'FB9695 Monthly (0% off)', 'type' => 1,
		'billing_frequency' => 1, 'billing_interval' => 3, 'billing_length' => 0, 'free_trial' => 0,
		'status' => 1, 'offer' => array(),
		'data' => array( 'subscription_ends' => 'never', 'additional_description' => '', 'display_summary' => '{{subscription_price}}' ),
	)
);
$rel_id = ( new \Sublium_WCS\Includes\database\PlanRelations() )->create(
	array( 'plan_id' => (int) $plan_id, 'status' => 1, 'oid' => (int) $product_id, 'vid' => 0, 'type' => 1, 'data' => array() )
);
delete_post_meta( $product_id, '_sublium_wcs_plan_data' );
if ( class_exists( '\Sublium_WCS\Includes\Main\Plans' ) ) {
	\Sublium_WCS\Includes\Main\Plans::clear_plan_cache();
}
if ( class_exists( '\Sublium_WCS\Includes\Helpers\Plan' ) ) {
	\Sublium_WCS\Includes\Helpers\Plan::clear_relation_cache();
}

if ( ! $product_id || ! $plan_id || ! $rel_id ) {
	$teardown();
	WP_CLI::error( 'Provisioning failed (product/plan/relation).' );
}

// Capture the fix's own seam — it only fires inside the real override path for fixed_discount_*.
$captured = array();
add_filter(
	'wfocu_sublium_regular_basis_discount_amount',
	function ( $converted, $base, $type, $product ) use ( &$captured ) {
		$captured[] = array( 'converted' => (float) $converted, 'base' => (float) $base, 'type' => $type );
		return $converted;
	},
	10,
	4
);

$build_and_calc = function ( $currency ) use ( $product_id, $plan_id ) {
	$_GET['wmc-currency']            = $currency;
	$_COOKIE['wmc_current_currency'] = $currency;
	if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
		WOOMULTI_CURRENCY_F_Data::get_ins()->set_current_currency( $currency );
	}
	if ( ! WC()->cart ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart(
		$product_id, 1, 0, array(),
		array(
			'sublium_wcs_plan' => (int) $plan_id,
			'_wfob_options'    => array( 'discount_type' => 'fixed_discount_reg', 'discount_amount' => 20, 'quantity' => 1, 'plan_id' => (int) $plan_id ),
		)
	);
	WC()->cart->calculate_totals();
};

// CAD: the fixed amount must be converted 20 -> 27.37.
$captured = array();
$build_and_calc( 'CAD' );
$cad = ! empty( $captured ) ? end( $captured ) : null;
$assert( 'CAD' === get_woocommerce_currency(), 'active currency is CAD' );
$assert( null !== $cad, 'override ran in CAD (fix seam fired)' );
if ( $cad ) {
	$assert( $approx( $cad['base'], 20.0 ), 'CAD: base discount amount is 20.00 (store/base currency)' );
	$assert( $approx( $cad['converted'], 27.374 ), 'CAD: discount converted to C$27.37 (20 * 1.3687) — not left at base 20' );
}

// Display path: the un-added bump's shown price (set_bump_plan_price, "One Time"/no plan selected).
// Asserted by relationship, not absolute value: CURCY's product getters only convert get_true_regular_price()
// on the frontend, so in wp-cli it stays in the base currency — but the fix is still observable here as
// (a) the discount applied to the bump is the CONVERTED amount, not the raw base 20, and (b) regular_org
// is the undiscounted regular (shown struck through), not the discounted price. In the browser, where the
// getter converts, these render as the ~~C$68.42~~ C$41.05 the shopper should see instead of C$48.42.
$compat = null;
if ( isset( $GLOBALS['wp_filter']['wfob_product_switcher_price_data'] ) ) {
	foreach ( $GLOBALS['wp_filter']['wfob_product_switcher_price_data']->callbacks as $cbs ) {
		foreach ( $cbs as $cb ) {
			if ( is_array( $cb['function'] ) && $cb['function'][0] instanceof WFOCU_Sublium_Compatibility ) {
				$compat = $cb['function'][0];
				break 2;
			}
		}
	}
}
$assert( null !== $compat, 'located WFOCU_Sublium_Compatibility instance' );
if ( $compat ) {
	$bump             = $compat->set_bump_plan_price( array(), wc_get_product( $product_id ), 1, array( 'discount_type' => 'fixed_discount_reg', 'discount_amount' => 20 ) );
	$applied_discount = ( isset( $bump['regular_org'], $bump['price'] ) ) ? ( $bump['regular_org'] - $bump['price'] ) : -1;
	$expected_conv    = function_exists( 'wmc_get_price' ) ? floatval( wmc_get_price( 20 ) ) : 20.0;
	$assert( $approx( $applied_discount, $expected_conv ), sprintf( 'display bump: applied discount is the CONVERTED amount (%.2f), not the raw base 20', $expected_conv ) );
	$assert( isset( $bump['regular_org'], $bump['price'] ) && $bump['regular_org'] > $bump['price'], 'display bump: regular_org (strikethrough) is the undiscounted regular, above the offer price' );
}

// USD (base): the amount must stay 20 (no behaviour change).
$captured = array();
$build_and_calc( 'USD' );
$usd = ! empty( $captured ) ? end( $captured ) : null;
$assert( null !== $usd, 'override ran in USD (fix seam fired)' );
if ( $usd ) {
	$assert( $approx( $usd['converted'], 20.0 ), 'USD (base): discount unchanged at 20.00' );
}

WC()->cart->empty_cart();
$teardown();

WP_CLI::log( '' );
if ( empty( $fail ) ) {
	WP_CLI::success( sprintf( 'issue #9695 regression: all %d assertions passed.', count( $pass ) ) );
	WP_CLI::halt( 0 );
}
WP_CLI::log( sprintf( 'issue #9695 regression: %d of %d assertions FAILED: %s', count( $fail ), count( $fail ) + count( $pass ), implode( '; ', $fail ) ) );
WP_CLI::halt( 1 );
