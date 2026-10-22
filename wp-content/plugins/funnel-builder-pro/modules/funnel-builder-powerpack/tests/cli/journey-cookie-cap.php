<?php
/**
 * Regression for PERF-001 and PERF-002 (fk-review, issue #9504): the
 * wffn_journey cookie had no server-side size/entry cap, unlike every
 * sibling tracking field -- a forged Cookie header could write an
 * unbounded journey longtext value. PERF-001 fixed the multi-entry case in
 * WFFN_Visitor_Tracking::journey_normalize(); PERF-002 (found on re-review)
 * closed the remaining single-oversized-entry bypass in
 * WFFN_Visitor_Tracking::get_common_tracking_data() (the entry-trimming
 * loop stops once one entry remains, so a lone forged entry's own size was
 * never checked -- fixed by rejecting an oversized *raw* cookie outright,
 * before it's ever decoded).
 *
 * Run via: wp eval-file tests/cli/journey-cookie-cap.php
 */

if ( ! class_exists( 'WFFN_Visitor_Tracking' ) ) {
	fwrite( STDERR, "FAIL: WFFN_Visitor_Tracking not loaded\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test script (wp eval-file), STDERR diagnostic output, never web-facing.
	exit( 1 );
}

$failures = 0;
$assert   = function ( $condition, $label ) use ( &$failures ) {
	if ( $condition ) {
		echo "PASS: $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test script (wp eval-file) console output, never rendered as HTML.
	} else {
		echo "FAIL: $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test script (wp eval-file) console output, never rendered as HTML.
		++$failures;
	}
};

// 1. Entry-count cap: 50 forged entries -> capped to 20, most recent kept,
// real timestamp keys preserved (not renumbered from 0).
$j = array();
for ( $i = 0; $i < 50; $i++ ) {
	$j[ (string) ( 1700000000 + $i ) ] = array(
		'u' => '/page-' . $i . '/',
		'i' => 100 + $i,
		's' => 1,
	);
}
$normalized = WFFN_Visitor_Tracking::journey_normalize(
	array(
		'j' => $j,
		's' => array( '1' => 'https://example.com' ),
	)
);
$assert( 20 === count( $normalized['j'] ), 'entry-count cap reduces 50 forged entries to 20' );
$keys = array_keys( $normalized['j'] );
sort( $keys, SORT_NUMERIC );
$assert( $keys === range( 1700000030, 1700000049 ), 'entry-count cap keeps the most recent (highest-timestamp) entries with real keys intact' );

// 2. Byte-size cap: a few entries but one has an inflated forged path ->
// dropped down under the byte limit, real timestamp key on the survivor
// preserved (this is the exact bug caught live: array_shift() would have
// silently renumbered it to key 0).
$huge         = str_repeat( 'A', 5000 );
$normalized2  = WFFN_Visitor_Tracking::journey_normalize(
	array(
		'j' => array(
			'1700000000' => array(
				'u' => '/a/',
				'i' => 1,
				's' => 1,
			),
			'1700000001' => array(
				'u' => '/' . $huge . '/',
				'i' => 2,
				's' => 1,
			),
			'1700000002' => array(
				'u' => '/c/',
				'i' => 3,
				's' => 1,
			),
		),
		's' => array( '1' => 'https://example.com' ),
	)
);
$encoded_size = strlen( wp_json_encode( $normalized2, JSON_UNESCAPED_SLASHES ) );
$assert( $encoded_size <= 2000, "byte-size cap keeps the encoded journey at or under 2000 bytes (got $encoded_size)" );
$assert( array( 1700000002 ) === array_keys( $normalized2['j'] ), 'byte-size cap keeps the newest surviving entry with its real timestamp key intact (not renumbered to 0)' );

// 3. Normal case: a small, legitimate journey passes through completely
// untouched -- the caps must never affect real traffic.
$normalized3 = WFFN_Visitor_Tracking::journey_normalize(
	array(
		'j' => array(
			'1700000000' => array(
				'u' => '/landing/',
				'i' => 10,
				's' => 1,
			),
			'1700000010' => array(
				'u' => '/checkout/',
				'i' => 11,
				's' => 1,
			),
		),
		's' => array( '1' => 'https://example.com' ),
	)
);
$assert( 2 === count( $normalized3['j'] ), 'a normal small journey keeps all entries' );
$assert( array( 1700000000, 1700000010 ) === array_keys( $normalized3['j'] ), 'a normal small journey keeps its real timestamp keys unchanged' );

// 4. PERF-002: a single forged entry whose own field is huge must never
// reach storage -- journey_normalize()'s own caps can't catch this (the
// trimming loop stops once one entry remains), so this is enforced one
// level up, on the raw cookie, in get_common_tracking_data().
$original_cookie         = isset( $_COOKIE['wffn_journey'] ) ? $_COOKIE['wffn_journey'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CLI test script (wp eval-file), reads/restores its own fixture cookie value, never web-facing.
$huge_path               = str_repeat( 'A', 50000 );
$oversized_raw           = wp_json_encode(
	array(
		'j' => array(
			'1700000000' => array(
				'u' => '/' . $huge_path . '/',
				'i' => 1,
				's' => 1,
			),
		),
		's' => array( '1' => 'https://example.com' ),
	),
	JSON_UNESCAPED_SLASHES
);
$_COOKIE['wffn_journey'] = addslashes( $oversized_raw ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- CLI test script (wp eval-file), sets its own fixture cookie value to exercise the raw-cookie guard.
$tracker                 = new WFFN_Visitor_Tracking();
$data                    = $tracker->get_common_tracking_data();
$assert( '' === $data['journey'], 'PERF-002: an oversized single-entry cookie is rejected outright, never reaches storage' );

// A legitimate small journey through the same raw-cookie path must still work.
$legit_raw               = wp_json_encode(
	array(
		'j' => array(
			'1700000000' => array(
				'u' => '/landing/',
				'i' => 10,
				's' => 1,
			),
			'1700000010' => array(
				'u' => '/checkout/',
				'i' => 11,
				's' => 1,
			),
		),
		's' => array( '1' => 'https://example.com' ),
	),
	JSON_UNESCAPED_SLASHES
);
$_COOKIE['wffn_journey'] = addslashes( $legit_raw ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- CLI test script (wp eval-file), sets its own fixture cookie value.
$data2                   = $tracker->get_common_tracking_data();
$decoded                 = json_decode( $data2['journey'], true );
$assert( is_array( $decoded ) && isset( $decoded['j'] ) && 2 === count( $decoded['j'] ), 'a legitimate small journey still passes through the raw-cookie guard intact' );

if ( null === $original_cookie ) {
	unset( $_COOKIE['wffn_journey'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- CLI test script (wp eval-file), restores state it changed.
} else {
	$_COOKIE['wffn_journey'] = $original_cookie; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- CLI test script (wp eval-file), restores state it changed.
}

if ( $failures > 0 ) {
	echo "\n$failures assertion(s) FAILED\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test script (wp eval-file) console output, never rendered as HTML.
	exit( 1 );
}
echo "\nAll assertions passed.\n";
