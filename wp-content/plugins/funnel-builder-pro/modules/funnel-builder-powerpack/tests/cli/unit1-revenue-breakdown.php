<?php
/**
 * Unit 1 — landing-step Attributed Revenue breakdown, run via `wp eval-file`.
 *
 * Targets the exact functions this unit changed in
 * includes/class-wffn-rest-api-endpoint.php: prepare_checkout_conversion(),
 * map_defult_step_value(), add_revenue_to_optins_sales(), and the A/B-variant
 * merge in update_data_with_step_item(). No PHPUnit harness exists in this
 * repo (confirmed before writing this), and there is no frontend change in
 * this unit to exercise via Playwright (the breakdown popover is gated to
 * step.type === 'checkout' until a later unit), so this drives the actual
 * PHP class methods directly against real bwf_conversion_tracking rows --
 * the same approach order-bumps/tests/cli/unit2-settings-resolver.php uses
 * for pure data-layer logic.
 *
 * Uses synthetic, out-of-range numeric step/funnel IDs (999901-999905) so
 * this never collides with real content. Cleans up its own DB rows on exit.
 */

$failures = 0;

function assert_true( $label, $cond, $extra = '' ) {
	global $failures;
	if ( $cond ) {
		echo "PASS  $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, not browser-rendered.
	} else {
		echo "FAIL  $label $extra\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, not browser-rendered.
		++$failures;
	}
}

global $wpdb;

$funnel_id   = 999901;
$landing_id  = 999902; // source_id
$checkout_id = 999903;
$variant_id  = 999904; // A/B variant of the landing step
$optin_id    = 999905;
$table       = $wpdb->prefix . 'bwf_conversion_tracking';

// Clean slate.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE funnel_id = %d", $funnel_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

function insert_conversion_row( $funnel_id, $step_id, $source_id, $checkout_total, $bump_total, $offer_total ) {
	global $wpdb;
	$table = $wpdb->prefix . 'bwf_conversion_tracking';
	$wpdb->insert(
		$table,
		array(
			'funnel_id'      => $funnel_id,
			'step_id'        => $step_id,
			'source_id'      => $source_id,
			'type'           => 2, // wc_order
			'value'          => $checkout_total + $bump_total + $offer_total,
			'checkout_total' => $checkout_total,
			'bump_total'     => $bump_total,
			'offer_total'    => $offer_total,
			'first_click'    => current_time( 'mysql' ),
			'timestamp'      => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s' )
	);
}

echo "--- fixtures: 3 conversion rows for the landing step's source_id ---\n";
// checkout-only, +bump, +offer -- expect totals: checkout=180, bump=20, offer=15
insert_conversion_row( $funnel_id, $checkout_id, $landing_id, 100, 0, 0 );
insert_conversion_row( $funnel_id, $checkout_id, $landing_id, 50, 20, 0 );
insert_conversion_row( $funnel_id, $checkout_id, $landing_id, 30, 0, 15 );
// an optin fixture too, to prove the optin branch is untouched
insert_conversion_row( $funnel_id, $checkout_id, $optin_id, 40, 10, 0 );

$endpoint = WFFN_REST_API_EndPoint::get_instance();

echo "\n--- prepare_checkout_conversion(): SQL-level aggregation ---\n";
$raw_steps     = array(
	array(
		'id'   => $checkout_id,
		'type' => 'checkout',
	),
);
$result        = $endpoint->prepare_checkout_conversion( $raw_steps, $funnel_id, '', '' );
$checkout_step = null;
foreach ( $result as $s ) {
	if ( absint( $s['id'] ) === $checkout_id ) {
		$checkout_step = $s;
	}
}
assert_true( 'checkout step found in result', null !== $checkout_step );
assert_true( 'source_revenue has an entry for the landing source_id', isset( $checkout_step['source_revenue'][ $landing_id ] ) );
$sr = $checkout_step['source_revenue'][ $landing_id ] ?? array();
assert_true( 'amount is checkout-only (180), unchanged semantics', 180.0 === (float) ( $sr['amount'] ?? -1 ) );
assert_true( 'bump_revenue summed correctly (20)', 20.0 === (float) ( $sr['bump_revenue'] ?? -1 ) );
assert_true( 'upsell_revenue summed correctly (15)', 15.0 === (float) ( $sr['upsell_revenue'] ?? -1 ) );
assert_true( 'title/type/source_id/order keys still present (AC #4 shape)', isset( $sr['title'], $sr['type'], $sr['source_id'], $sr['order'] ) );

echo "\n--- map_defult_step_value(): revenue_breakdown seeding ---\n";
$mapped       = $endpoint->map_defult_step_value(
	array(
		array(
			'id'   => $landing_id,
			'type' => 'landing',
		),
		array(
			'id'   => $optin_id,
			'type' => 'optin',
		),
		array(
			'id'   => $checkout_id,
			'type' => 'checkout',
		),
	)
);
$mapped_steps = $mapped['step_data'];
assert_true( 'landing step gets a revenue_breakdown array', isset( $mapped_steps[ $landing_id ]['revenue_breakdown'] ) && 3 === count( $mapped_steps[ $landing_id ]['revenue_breakdown'] ) );
assert_true( 'revenue_breakdown starts at zero for all 3 keys', array_sum( array_column( $mapped_steps[ $landing_id ]['revenue_breakdown'], 'amount' ) ) === 0 );
assert_true( 'optin step does NOT get revenue_breakdown (AC #5)', ! isset( $mapped_steps[ $optin_id ]['revenue_breakdown'] ) );
assert_true( 'checkout step keeps source_revenue (no regression)', isset( $mapped_steps[ $checkout_id ]['source_revenue'] ) && ! isset( $mapped_steps[ $checkout_id ]['revenue_breakdown'] ) );

echo "\n--- add_revenue_to_optins_sales(): full pipeline shape ---\n";
$pipeline_steps = array(
	$landing_id  => array(
		'type'              => 'landing',
		'object_id'         => $landing_id,
		'revenue'           => 0,
		'revenue_breakdown' => $mapped_steps[ $landing_id ]['revenue_breakdown'],
	),
	$optin_id    => array(
		'type'      => 'optin',
		'object_id' => $optin_id,
		'revenue'   => 0,
	),
	$checkout_id => array(
		'type'           => 'checkout',
		'object_id'      => $checkout_id,
		'revenue'        => 0,
		'source_revenue' => $checkout_step['source_revenue'],
	),
);
$final          = $endpoint->add_revenue_to_optins_sales( $pipeline_steps );

assert_true( 'landing revenue = checkout + bump + offer (215)', 215.0 === (float) $final[ $landing_id ]['revenue'] );
$breakdown_by_key = array();
foreach ( $final[ $landing_id ]['revenue_breakdown'] as $item ) {
	$breakdown_by_key[ $item['key'] ] = $item['amount'];
}
assert_true( "breakdown['checkout'] == 180", 180.0 === (float) $breakdown_by_key['checkout'] );
assert_true( "breakdown['bump'] == 20", 20.0 === (float) $breakdown_by_key['bump'] );
assert_true( "breakdown['upsell'] == 15", 15.0 === (float) $breakdown_by_key['upsell'] );
assert_true( 'sum of breakdown == revenue (internal consistency, AC #2)', array_sum( $breakdown_by_key ) === $final[ $landing_id ]['revenue'] );

assert_true( 'optin revenue is checkout-only (40), NOT +bump (AC #5, untouched branch)', 40.0 === (float) $final[ $optin_id ]['revenue'] );
assert_true( 'optin step never gets a revenue_breakdown key', ! isset( $final[ $optin_id ]['revenue_breakdown'] ) );

echo "\n--- update_data_with_step_item(): A/B variant -> control merge ---\n";
$with_variant = array(
	$landing_id => array(
		'object_id'         => $landing_id,
		'views'             => 10,
		'conversions'       => 2,
		'revenue'           => 200.0,
		'revenue_breakdown' => array(
			array(
				'key'    => 'checkout',
				'title'  => 'Checkout',
				'amount' => 150.0,
			),
			array(
				'key'    => 'bump',
				'title'  => 'Order Bump',
				'amount' => 30.0,
			),
			array(
				'key'    => 'upsell',
				'title'  => 'One Click Upsell',
				'amount' => 20.0,
			),
		),
	),
	$variant_id => array(
		'object_id'         => $variant_id,
		'control_id'        => $landing_id,
		'views'             => 5,
		'conversions'       => 1,
		'revenue'           => 65.0,
		'revenue_breakdown' => array(
			array(
				'key'    => 'checkout',
				'title'  => 'Checkout',
				'amount' => 50.0,
			),
			array(
				'key'    => 'bump',
				'title'  => 'Order Bump',
				'amount' => 15.0,
			),
			array(
				'key'    => 'upsell',
				'title'  => 'One Click Upsell',
				'amount' => 0.0,
			),
		),
	),
);
$merged       = $endpoint->update_data_with_step_item( $with_variant, array() );
assert_true( 'variant entry removed after merge', ! isset( $merged[ $variant_id ] ) );
assert_true( 'control revenue = 200 + 65 = 265', 265.0 === (float) $merged[ $landing_id ]['revenue'] );
$merged_breakdown = array();
foreach ( $merged[ $landing_id ]['revenue_breakdown'] as $item ) {
	$merged_breakdown[ $item['key'] ] = $item['amount'];
}
assert_true( "merged breakdown['checkout'] = 150+50 = 200", 200.0 === (float) $merged_breakdown['checkout'] );
assert_true( "merged breakdown['bump'] = 30+15 = 45", 45.0 === (float) $merged_breakdown['bump'] );
assert_true( "merged breakdown['upsell'] = 20+0 = 20", 20.0 === (float) $merged_breakdown['upsell'] );
assert_true( 'merged breakdown still sums to merged revenue (265)', array_sum( $merged_breakdown ) === $merged[ $landing_id ]['revenue'] );

// Cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE funnel_id = %d", $funnel_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "$failures FAILURE(S)\n" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, not browser-rendered.
