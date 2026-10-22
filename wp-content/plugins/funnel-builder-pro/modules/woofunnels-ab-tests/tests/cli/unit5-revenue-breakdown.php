<?php
/**
 * Unit 5 -- A/B test variant Attributed Revenue breakdown, run via `wp eval-file`.
 *
 * Targets BWFABT_Controller_Landing::get_analytics_data() in
 * test-modules/landing/class-bwfabt-controller-landing.php: the itemized
 * checkout_revenue/bump_revenue/upsell_revenue breakdown, the revenue field
 * now equal to their sum, and the pre-existing order_count/revenue_per_visit
 * fields this unit's grounding confirmed were already computed. No PHPUnit
 * harness exists in this repo, so this drives the actual method directly
 * against real bwf_conversion_tracking + wfco_report_views fixtures -- same
 * approach as funnel-builder-powerpack's tests/cli/unit1-revenue-breakdown.php.
 *
 * Uses synthetic, out-of-range numeric IDs (999911-999913) so this never
 * collides with real content. Cleans up its own DB rows on exit.
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

$variant_a_id = 999911; // control variant's landing step id (== source_id)
$variant_b_id = 999912; // second variant's landing step id
$conv_table   = $wpdb->prefix . 'bwf_conversion_tracking';
$views_table  = $wpdb->prefix . 'wfco_report_views';
$exp_table    = $wpdb->prefix . 'bwf_ab_experiments';

// Clean slate.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$conv_table} WHERE source_id IN (%d, %d)", $variant_a_id, $variant_b_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$views_table} WHERE object_id IN (%d, %d)", $variant_a_id, $variant_b_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// get_analytics_data() calls BWFABT_Experiment($experiment_id)->get_report_start_date()/
// get_report_end_date(), which only fall back to "now" when the DB row's date columns are
// the real '0000-00-00 00:00:00' sentinel (their DEFAULT) -- a raw PHP null (what a
// nonexistent id's uninitialized object property holds) isn't handled and produces a null
// date string, which explode()s into a malformed WHERE clause. A real row, dates left at
// their schema default, is the only way to hit the intended fallback path.
$wpdb->insert(
	$exp_table,
	array(
		'title'   => 'fk-dev unit5 test',
		'desc'    => '',
		'type'    => 'landing',
		'control' => $variant_a_id,
	)
);
$experiment_id = (int) $wpdb->insert_id;

function insert_conversion_row_u5( $step_id, $source_id, $checkout_total, $bump_total, $offer_total ) {
	global $wpdb;
	$table = $wpdb->prefix . 'bwf_conversion_tracking';
	$wpdb->insert(
		$table,
		array(
			'funnel_id'      => 0,
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

echo "--- fixtures: variant A conversions, checkout=100+bump=20+upsell=15=135; variant B checkout=40 only ---\n";
insert_conversion_row_u5( $variant_a_id, $variant_a_id, 100, 20, 15 );
insert_conversion_row_u5( $variant_a_id, $variant_a_id, 30, 0, 0 );
insert_conversion_row_u5( $variant_b_id, $variant_b_id, 40, 0, 0 );

// report_views: type 13 = viewed, type 14 = converted (per get_analytics_data()'s own query).
$wpdb->insert(
	$views_table,
	array(
		'object_id'      => $variant_a_id,
		'type'           => 13,
		'no_of_sessions' => 50,
		'date'           => current_time( 'mysql' ),
	)
); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
$wpdb->insert(
	$views_table,
	array(
		'object_id'      => $variant_a_id,
		'type'           => 14,
		'no_of_sessions' => 2,
		'date'           => current_time( 'mysql' ),
	)
);
$wpdb->insert(
	$views_table,
	array(
		'object_id'      => $variant_b_id,
		'type'           => 13,
		'no_of_sessions' => 20,
		'date'           => current_time( 'mysql' ),
	)
);

$controller = BWFABT_Controller_Landing::get_instance();
$data       = $controller->get_analytics_data( array( $variant_a_id, $variant_b_id ), $experiment_id );

assert_true( 'variant A present in result', isset( $data[ $variant_a_id ] ) );
assert_true( 'variant B present in result', isset( $data[ $variant_b_id ] ) );

$a = $data[ $variant_a_id ];
assert_true( 'A checkout_revenue == 130', 130.0 === (float) $a['checkout_revenue'] );
assert_true( 'A bump_revenue == 20', 20.0 === (float) $a['bump_revenue'] );
assert_true( 'A upsell_revenue == 15', 15.0 === (float) $a['upsell_revenue'] );
assert_true( 'A revenue == checkout+bump+upsell == 165', 165.0 === (float) $a['revenue'] );
assert_true( 'A order_count == 2 (two conversion rows)', 2 === (int) $a['order_count'] );
assert_true( 'A revenue_per_visit == 165/50 == 3.3', 3.3 === (float) $a['revenue_per_visit'] );
assert_true( 'A conversion_rate computed (views=50, conversions=2 => 4%)', 4.0 === (float) $a['conversion_rate'] );

$b = $data[ $variant_b_id ];
assert_true( 'B checkout_revenue == 40', 40.0 === (float) $b['checkout_revenue'] );
assert_true( 'B bump_revenue == 0 (no bump fixture)', 0.0 === (float) $b['bump_revenue'] );
assert_true( 'B upsell_revenue == 0 (no upsell fixture)', 0.0 === (float) $b['upsell_revenue'] );
assert_true( 'B revenue == 40 (checkout only)', 40.0 === (float) $b['revenue'] );

// Cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$conv_table} WHERE source_id IN (%d, %d)", $variant_a_id, $variant_b_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$views_table} WHERE object_id IN (%d, %d)", $variant_a_id, $variant_b_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$exp_table} WHERE id = %d", $experiment_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "$failures FAILURE(S)\n" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script output, not browser-rendered.
