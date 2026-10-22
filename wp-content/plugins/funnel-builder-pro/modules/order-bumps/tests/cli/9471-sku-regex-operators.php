<?php
/**
 * Regression check for GitHub xlplugins/funnel-builder#9471.
 *
 * Scope, deliberately narrow: the three regex operators
 * (`matches_regex`, `matches_regex_all`, `matches_regex_none`) on the
 * "SKU - Text/Regex Match" Order Bump rule (`wfob_Rule_Order_Item_Sku_Match`
 * in rules/rules/cart.php). Nothing else — not the sibling Text Match rule,
 * not the substring operators (contains/starts_with/ends_with/doesnt_contain),
 * which are unaffected and already covered by manual QA on this issue.
 *
 * Root cause: commit b238a317 added the three regex operators; commit
 * 4629216 ("Update cart.php") accidentally reverted exactly that block back
 * to the pre-regex version. This script proves the regression (fails against
 * 4629216) and proves the fix (passes once the block is restored).
 *
 * Run via: wp eval-file tests/cli/9471-sku-regex-operators.php
 * (this lane needs memory/socket overrides — see the command used in the
 * fk-change run's report; not repeated here to avoid drift).
 *
 * Uses a real WC()->cart (via wc_load_cart()) and the actual rule class
 * directly — no PHPUnit available on this machine, and no need to spin up a
 * full checkout/funnel fixture just to exercise is_match() in isolation.
 */

defined( 'ABSPATH' ) || exit;

// The rule classes are lazy-loaded (see WFOB_Rules::load_rules_classes(),
// normally triggered from admin_init/REST/frontend render) — a bare
// wp eval-file process hits none of those, so load them the same way
// includes/class-wfob-public.php:261 and the REST bump endpoint do.
if ( ! class_exists( 'WFOB_Rules' ) ) {
	WP_CLI::error( 'WFOB_Rules not loaded (is order-bumps active?)' );
}
WFOB_Rules::get_instance()->load_rules_classes();

if ( ! class_exists( 'wfob_Rule_Order_Item_Sku_Match' ) ) {
	WP_CLI::error( 'wfob_Rule_Order_Item_Sku_Match not loaded after load_rules_classes()' );
}

/**
 * Find-or-create a simple product with the given SKU. Idempotent so reruns
 * (RED, then GREEN after the fix) don't accumulate duplicate products.
 */
function fk9471_find_or_create_product( $sku ) {
	$existing_id = wc_get_product_id_by_sku( $sku );
	if ( $existing_id ) {
		return wc_get_product( $existing_id );
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'FK-9471 QA ' . $sku );
	$product->set_sku( $sku );
	$product->set_regular_price( '10' );
	$product->set_status( 'publish' );
	$product->set_manage_stock( false );
	$product->save();

	return $product;
}

/**
 * Load a real cart (wc_load_cart() forces session/cart init in this CLI
 * process), empty it, and add exactly the given products.
 *
 * This site's add_to_cart() is known to auto-inject an unrelated extra item
 * on some product IDs (a cart-for-woocommerce-pro cross-sell reaction — the
 * same behaviour /fk-dev's own notes document happening from WP-CLI cart
 * sessions, not just the browser). Anything that lands in the cart beyond
 * the SKUs this test asked for is stripped back out so "all"/"none" regex
 * operators are only ever evaluated against the intended items.
 */
function fk9471_set_cart( array $products ) {
	if ( function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();

	$expected_ids = array();
	foreach ( $products as $product ) {
		$added = WC()->cart->add_to_cart( $product->get_id(), 1 );
		if ( ! $added ) {
			WP_CLI::error( 'add_to_cart failed for SKU ' . $product->get_sku() );
		}
		$expected_ids[] = $product->get_id();
	}

	foreach ( WC()->cart->get_cart_contents() as $cart_item_key => $cart_item ) {
		if ( ! in_array( $cart_item['product_id'], $expected_ids, true ) ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		}
	}
}

$pri = fk9471_find_or_create_product( 'QA-MU-PRI' );
$dig = fk9471_find_or_create_product( 'QA-MU-DIG' );
$duo = fk9471_find_or_create_product( 'QA-MU-DUO' );

$rule = new wfob_Rule_Order_Item_Sku_Match();

// A results accumulator passed by reference to every check — deliberately
// NOT relying on `global` inside a wp eval-file: WP-CLI's eval-file runner
// includes this script inside one of its own method scopes, so top-level
// variables here are not true PHP globals and `global $x` inside a helper
// function would silently bind to an empty, unrelated variable instead.
$results = array(
	'pass'  => 0,
	'fail'  => 0,
	'lines' => array(),
);

function fk9471_check( array &$results, $label, $expected, $actual ) {
	$ok                 = ( $expected === $actual );
	$results['lines'][] = sprintf( '[%s] %s (expected %s, got %s)', $ok ? 'PASS' : 'FAIL', $label, $expected ? 'true' : 'false', $actual ? 'true' : 'false' );
	if ( $ok ) {
		++$results['pass'];
	} else {
		++$results['fail'];
	}
}

// 0. Operator declaration itself — the most direct reproduction of "the
// operator is no longer even offered".
$operators = $rule->get_possible_rule_operators();
fk9471_check( $results, 'operator matches_regex is declared', true, array_key_exists( 'matches_regex', $operators ) );
fk9471_check( $results, 'operator matches_regex_all is declared', true, array_key_exists( 'matches_regex_all', $operators ) );
fk9471_check( $results, 'operator matches_regex_none is declared', true, array_key_exists( 'matches_regex_none', $operators ) );

$pattern = '-(PRI|DIG)$';

// 1-3. matches_regex ("any"): PRI and DIG match the pattern, DUO doesn't.
fk9471_set_cart( array( $pri ) );
fk9471_check(
	$results,
	'matches_regex: cart=PRI matches "-(PRI|DIG)$"',
	true,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex',
			'condition' => $pattern,
		)
	)
);

fk9471_set_cart( array( $dig ) );
fk9471_check(
	$results,
	'matches_regex: cart=DIG matches "-(PRI|DIG)$"',
	true,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex',
			'condition' => $pattern,
		)
	)
);

fk9471_set_cart( array( $duo ) );
fk9471_check(
	$results,
	'matches_regex: cart=DUO does NOT match "-(PRI|DIG)$"',
	false,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex',
			'condition' => $pattern,
		)
	)
);

// 4-5. matches_regex_all: every cart item must match.
fk9471_set_cart( array( $pri, $dig ) );
fk9471_check(
	$results,
	'matches_regex_all: cart=PRI+DIG both match',
	true,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex_all',
			'condition' => $pattern,
		)
	)
);

fk9471_set_cart( array( $pri, $duo ) );
fk9471_check(
	$results,
	'matches_regex_all: cart=PRI+DUO not all match',
	false,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex_all',
			'condition' => $pattern,
		)
	)
);

// 6-7. matches_regex_none: no cart item may match.
fk9471_set_cart( array( $duo ) );
fk9471_check(
	$results,
	'matches_regex_none: cart=DUO, none match',
	true,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex_none',
			'condition' => $pattern,
		)
	)
);

fk9471_set_cart( array( $pri ) );
fk9471_check(
	$results,
	'matches_regex_none: cart=PRI, one matches -> false',
	false,
	$rule->is_match(
		array(
			'operator'  => 'matches_regex_none',
			'condition' => $pattern,
		)
	)
);

// 8. Broken pattern must not throw/crash and must evaluate as no-match
// (GH issue's own QA row 9 — "no PHP warning/notice on the page or in
// debug.log"). Note: PHP's error_get_last() still records an @-suppressed
// warning internally even though @ correctly stops it from being displayed
// or logged (verified directly on this system's PHP 8.3) — so it is not a
// usable signal here; a clean `false` return with no thrown Throwable is.
fk9471_set_cart( array( $pri ) );
try {
	$broken_result = $rule->is_match(
		array(
			'operator'  => 'matches_regex',
			'condition' => '(',
		)
	);
	$threw         = false;
} catch ( \Throwable $e ) {
	$broken_result = null;
	$threw         = true;
}
fk9471_check( $results, 'broken pattern "(" evaluates as no-match, not a crash', false, $broken_result );
fk9471_check( $results, 'broken pattern "(" does not throw', false, $threw );

// 9. ReDoS guard (2026-09-15 AI code-review finding on issue #9471): the
// regex operators must not rely on an ambient php.ini pcre.backtrack_limit
// for safety -- a host that raises/removes that limit must not let a
// pathological admin-authored pattern turn into a multi-second cart/checkout
// stall. Simulate exactly that host config (proven for real below: without
// an explicit in-code bound, "^(a+)+$" against 30 a's takes 3+ seconds once
// the ini limit is raised), then assert the rule still resolves quickly and
// as no-match -- proving cart.php enforces its own bound rather than
// borrowing whatever the host happens to have configured.
$previous_backtrack_limit = ini_set( 'pcre.backtrack_limit', '10000000000' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
$evil_sku                 = fk9471_find_or_create_product( str_repeat( 'a', 30 ) . 'X' );
fk9471_set_cart( array( $evil_sku ) );
$start = microtime( true );
try {
	$evil_result = $rule->is_match(
		array(
			'operator'  => 'matches_regex',
			'condition' => '^(a+)+$',
		)
	);
} catch ( \Throwable $e ) {
	$evil_result = null;
}
$elapsed = microtime( true ) - $start;
if ( false !== $previous_backtrack_limit ) {
	ini_set( 'pcre.backtrack_limit', $previous_backtrack_limit ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
}
fk9471_check( $results, 'pathological regex "^(a+)+$" resolves as no-match even with an unbounded host pcre.backtrack_limit', false, $evil_result );
fk9471_check( $results, 'pathological regex "^(a+)+$" completes in under 2s even with an unbounded host pcre.backtrack_limit', true, $elapsed < 2 );

foreach ( $results['lines'] as $line ) {
	WP_CLI::log( $line );
}

WP_CLI::log( '' );
WP_CLI::log( sprintf( '%d passed, %d failed', $results['pass'], $results['fail'] ) );

WC()->cart->empty_cart();

if ( $results['fail'] > 0 ) {
	WP_CLI::error( sprintf( '%d of %d checks failed', $results['fail'], $results['pass'] + $results['fail'] ) );
}

WP_CLI::success( 'All regex-operator checks passed.' );
