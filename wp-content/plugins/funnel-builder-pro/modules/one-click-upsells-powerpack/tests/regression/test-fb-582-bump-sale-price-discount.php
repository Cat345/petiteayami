<?php
/**
 * Regression test for the FB#582 follow-up — an order bump's "on Sale Price" discount must be
 * taken off the product's SALE price, not its regular price, when Sublium is active and no
 * subscription plan is selected on the bump.
 *
 * Bug: WFOCU_Sublium_Compatibility::set_bump_plan_price() re-derived the bump's own discount in
 * its "no plan selected" branch, passing get_true_regular_price() as BOTH wfob_product_rp and
 * wfob_product_p. WFOB_Common::calculate_discount() reads wfob_product_p for the
 * fixed_discount_sale / percent_discount_sale types, so a $50 regular / $25 sale product with a
 * $10 "Fixed Amount on Sale Price" bump discount rendered as $40 (50 - 10) instead of $15
 * (25 - 10). It fired for every bump product on a site with Sublium active — including products
 * with no Sublium plan at all, which this test uses.
 *
 * Fix: that branch returns $price_data untouched. Every caller of the filter runs
 * WFOB_Common::set_product_price() on the product first, which has already applied the bump's own
 * discount against the correct basis, and get_bump_product_price_data() falls back to it.
 *
 * Requires: funnel-builder-pro + sublium-subscriptions-for-woocommerce active. Run:
 *   wp eval-file wp-content/plugins/funnel-builder-pro/modules/one-click-upsells-powerpack/tests/regression/test-fb-582-bump-sale-price-discount.php
 *
 * @package FunnelBuilderPro\Powerpack\Tests
 */

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return; // Only ever run from wp-cli eval-file.
}

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
$approx = function ( $a, $b, $eps = 0.01 ) {
	return abs( (float) $a - (float) $b ) <= $eps;
};

if ( ! class_exists( 'WFOCU_Sublium_Compatibility' ) ) {
	WP_CLI::error( 'WFOCU_Sublium_Compatibility not loaded (need funnel-builder-pro + Sublium active).' );
}
if ( ! class_exists( 'WFOB_Common' ) ) {
	WP_CLI::error( 'WFOB_Common not loaded (need the Order Bumps module active).' );
}
$compat = null;
if ( isset( $GLOBALS['wp_filter']['wfob_product_switcher_price_data'] ) ) {
	foreach ( $GLOBALS['wp_filter']['wfob_product_switcher_price_data']->callbacks as $cbs ) {
		foreach ( $cbs as $cb ) {
			if ( is_array( $cb['function'] ) && $cb['function'][0] instanceof WFOCU_Sublium_Compatibility && 'set_bump_plan_price' === $cb['function'][1] ) {
				$compat = $cb['function'][0];
				break 2;
			}
		}
	}
}
$assert( null !== $compat, 'Sublium set_bump_plan_price is hooked on wfob_product_switcher_price_data' );
if ( null === $compat ) {
	WP_CLI::error( 'Sublium bump price filter is not registered — nothing to regress against.' );
}

// $50 regular / $25 sale, deliberately with NO Sublium plan attached.
$prod = new WC_Product_Simple();
$prod->set_name( 'FB582 regression product' );
$prod->set_regular_price( '50' );
$prod->set_sale_price( '25' );
$prod->set_price( '25' );
$prod->set_status( 'publish' );
$product_id = $prod->save();

if ( ! $product_id ) {
	WP_CLI::error( 'Provisioning failed (product).' );
}

/**
 * Render a bump's price exactly as the skins do: discount the product object, run the filter
 * chain, fall back to the object when the chain returns nothing (get_bump_product_price_data()).
 */
$render_bump_price = function ( $options ) use ( $product_id ) {
	$pro  = WFOB_Common::wc_get_product( $product_id );
	$pro  = WFOB_Common::set_product_price( $pro, $options, '' );
	$data = apply_filters( 'wfob_product_switcher_price_data', array(), $pro, 1, $options );
	if ( empty( $data ) ) {
		$data['regular_org'] = $pro->get_regular_price( 'edit' );
		$data['price']       = $pro->get_price( 'edit' );
	}

	return array( floatval( $data['regular_org'] ), floatval( $data['price'] ) );
};

$base = array( 'quantity' => 1, 'id' => $product_id, 'plan_id' => 0, 'parent_product_id' => 0, 'type' => 'simple' );

$cases = array(
	// discount_type              amount  expected regular  expected price  label
	array( 'fixed_discount_sale', 10, 50.0, 15.0, '$10 fixed on SALE price: $25 - $10 = $15 (not $40)' ),
	array( 'percent_discount_sale', 20, 50.0, 20.0, '20% on SALE price: $25 - 20% = $20 (not $40)' ),
	array( 'fixed_discount_reg', 10, 50.0, 40.0, '$10 fixed on REGULAR price: $50 - $10 = $40' ),
	array( 'percent_discount_reg', 20, 50.0, 40.0, '20% on REGULAR price: $50 - 20% = $40' ),
	array( 'flat_price', 12, 50.0, 12.0, 'flat price $12 is charged as-is' ),
);

foreach ( $cases as $case ) {
	list( $type, $amount, $exp_regular, $exp_price, $label ) = $case;
	list( $regular, $price ) = $render_bump_price( array_merge( $base, array( 'discount_type' => $type, 'discount_amount' => $amount ) ) );
	$assert( $approx( $price, $exp_price ) && $approx( $regular, $exp_regular ), sprintf( '%s — got regular %.2f / price %.2f', $label, $regular, $price ) );
}

// The no-plan branch must not touch $price_data at all, so an earlier currency compat's
// converted figures survive it untouched.
$seeded     = array( 'regular_org' => 68.42, 'price' => 41.05 );
$passthru   = $compat->set_bump_plan_price( $seeded, WFOB_Common::wc_get_product( $product_id ), 1, array_merge( $base, array( 'discount_type' => 'fixed_discount_sale', 'discount_amount' => 10 ) ) );
$assert( $passthru === $seeded, 'no-plan branch leaves an earlier compat\'s price data untouched' );

wp_delete_post( $product_id, true );

WP_CLI::log( '' );
if ( empty( $fail ) ) {
	WP_CLI::success( sprintf( 'FB#582 bump sale-price regression: all %d assertions passed.', count( $pass ) ) );
	WP_CLI::halt( 0 );
}
WP_CLI::log( sprintf( 'FB#582 bump sale-price regression: %d of %d assertions FAILED: %s', count( $fail ), count( $fail ) + count( $pass ), implode( '; ', $fail ) ) );
WP_CLI::halt( 1 );
