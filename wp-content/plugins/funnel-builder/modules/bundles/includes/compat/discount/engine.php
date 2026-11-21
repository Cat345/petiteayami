<?php
/**
 * Engine — the contract every discount-engine adapter implements.
 *
 * A discount engine is any third-party plugin that prices cart lines by its own
 * rules. An adapter tells its engine to stand down on a bundle line, then reports
 * what the engine would have applied so the figure can be folded into the
 * bundle's own arithmetic.
 *
 * Every amount crossing this interface is in shop base currency. No method here
 * may charge a shopper, apply a coupon, or mutate the engine's stored rules.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Discount;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for a discount-engine adapter.
 */
interface Engine {

	/**
	 * Is this engine present and in a state where it actually prices lines?
	 *
	 * Engines commonly define their classes at file load but install the runtime
	 * instance later, so an adapter must probe the instance, not the symbol, and
	 * must fail on a version it was not written against.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Register the engine-specific hooks this adapter needs.
	 *
	 * Called once, after the adapter reported itself active, so constructing a
	 * candidate adapter has no side effects.
	 *
	 * @return void
	 */
	public function register_hooks();

	/**
	 * Register the hooks that must exist whenever this engine is merely present.
	 *
	 * Called on every registered adapter regardless of `is_active()`, for the case
	 * of an engine that is present but version-gated out. Implementations must be
	 * read-only with respect to money and idempotent per hook key.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_defensive_hooks();

	/**
	 * Does the engine write the charged price itself?
	 *
	 * True for a push-style engine, which pulls our figure through a filter of its
	 * own and writes the result. False makes `Cart::apply_pricing()` fold the
	 * engine's discount instead; returning true when the engine does not write is
	 * a silent full-price bug.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function supplies_own_price();

	/**
	 * Render merchant warnings about this engine's configuration.
	 *
	 * Called on `admin_notices` regardless of `is_active()`; a silent version gate
	 * is what the warning exists to prevent. Implementations must be read-only.
	 *
	 * @return void
	 */
	public function render_notices();

	/**
	 * Run `$render` with the engine's own price-display filters suspended.
	 *
	 * Implementations need not be reentrant and need not restore on an exception;
	 * the facade owns the depth counter and the `finally`.
	 *
	 * @param callable $render Renderer to run inside the window.
	 * @return mixed Whatever `$render` returns.
	 */
	public function with_display_suppressed( callable $render );

	/**
	 * Mark one cart item as ours, so the engine leaves its price alone.
	 *
	 * Called from several points in a request, so implementations must be
	 * idempotent: claiming an already-claimed item leaves exactly one marker.
	 *
	 * @param array $cart_item Cart item, mutated in place.
	 * @return void
	 */
	public function claim_line( array &$cart_item );

	/**
	 * What the engine would have applied to this line, had it not been claimed.
	 *
	 * Read-only: implementations must not let this query alter the engine's state
	 * or the line's price.
	 *
	 * @param array $cart_item Cart item to interrogate.
	 * @return array List of `[ 'source' => string, 'amount' => float, 'type' => string ]`,
	 *               amounts in base currency, `type` one of `percentage`, `fixed`,
	 *               `no_discount`. Empty array when the engine would apply nothing;
	 *               never null.
	 */
	public function get_applied_discounts( array $cart_item );

	/**
	 * The line's unit price before any of the engine's discounts.
	 *
	 * @param array $cart_item Cart item to price.
	 * @return float Base currency. 0.0 when unresolvable; never null.
	 */
	public function get_price_before_discounts( array $cart_item );

	/**
	 * Offer the engine our final figure to record as its own applied discount.
	 *
	 * Engines with no API for a foreign discount return false, which is not an
	 * error: the figure is still charged, it is simply absent from that engine's
	 * own reporting.
	 *
	 * @param array  $cart_item Cart item the discount belongs to.
	 * @param float  $amount    Discount amount in base currency.
	 * @param string $type      One of `percentage`, `fixed`, `no_discount`.
	 * @return bool Did the engine accept it.
	 */
	public function register_our_discount( array $cart_item, $amount, $type );

	/**
	 * Sort key deciding which engine computes first; lower runs first. Ours is 0.
	 *
	 * @return int
	 */
	public function get_application_order();
}
