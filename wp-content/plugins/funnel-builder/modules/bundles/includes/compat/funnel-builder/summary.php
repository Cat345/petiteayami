<?php
/** Summary — a bundle's member lines in Aero Checkout's order summaries. */
namespace WFFN\Bundles\Includes\Compat\Funnel_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Helpers\Builtin_Translations;
use WFFN\Bundles\Includes\Helpers\WooCommerce;

/**
 * Lets a shopper fold a bundle's member lines under the bundle's own line in the
 * order summary, the sidebar mini cart and the collapsible mobile summary.
 *
 * The toggle and every row's state are printed here, so Aero's fragment refresh
 * redraws them as the shopper left them. The script only flips the state and
 * keeps the folded groups in a cookie that this class reads back.
 */
class Summary implements Integration {

	/** Cookie holding the folded bundle groups, dot-separated. */
	const COOKIE = 'wffn_bundles_aero_collapsed';

	/** Most groups the cookie remembers; a cart holds far fewer bundles. */
	const MAX_GROUPS = 20;

	/**
	 * Always on: Funnel Builder loads an Aero Checkout before this module.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return true;
	}

	/**
	 * Register this integration's hooks. Only called when is_enable() is true.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_cart_item_class', array( $this, 'row_class' ), 20, 2 );
		// Every Aero summary template prints this after the line's title.
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'print_toggle' ), 20, 2 );
		// After Cart::enqueue_cart_css(), whose handle this sheet follows.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		// An embedded form is found inside the page content, after the head is printed.
		add_action( 'wfacp_after_checkout_page_found', array( $this, 'enqueue_assets' ), 20 );
	}

	/**
	 * Load the summary sheet and toggle script where the grouped lines can show.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! self::in_aero() || ! wp_style_is( 'wffn-bundles-cart-grouping', 'enqueued' ) || ( ! self::cart_has_group() && ! Adapter::checkout_sells_a_bundle() ) ) {
			return;
		}
		foreach ( array( 'css', 'js' ) as $ext ) {
			$rel = 'assets/compat/funnel-builder-summary.' . $ext;
			$ver = file_exists( WFFN_BUNDLES_DIR . '/' . $rel ) ? (string) filemtime( WFFN_BUNDLES_DIR . '/' . $rel ) : WFFN_BUNDLES_VERSION;
			if ( 'css' === $ext ) {
				wp_enqueue_style( 'wffn-bundles-compat-funnel-builder-summary', WFFN_BUNDLES_URL . $rel, array( 'wffn-bundles-cart-grouping' ), $ver );
				continue;
			}
			wp_enqueue_script( 'wffn-bundles-compat-funnel-builder-summary', WFFN_BUNDLES_URL . $rel, array(), $ver, true );
		}
	}

	/**
	 * Mark a member line whose bundle the shopper folded.
	 *
	 * @param string $classes Row classes so far.
	 * @param array  $item    Cart item.
	 * @return string
	 */
	public function row_class( $classes, $item = array() ) {
		$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
		if ( ! self::is_member( $b ) || ! self::in_aero() ) {
			return $classes;
		}

		return in_array( (string) ( $b['group'] ?? '' ), self::collapsed_groups(), true ) ? $classes . ' wffn-bundles-cart-collapsed' : $classes;
	}

	/**
	 * Print the show/hide control after a bundle line's title, and the cart's
	 * "Qty: N" under each of its members, which Aero templates hide as "× N".
	 *
	 * @param string $html Quantity markup Aero built.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function print_toggle( $html, $item = array() ) {
		$b     = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
		$group = (string) ( $b['group'] ?? '' );
		if ( self::is_member( $b ) && self::in_aero() ) {
			$qty = Cart::get_instance()->item_quantity( '', '', $item );

			return '' === $qty ? $html : $qty;
		}
		if ( '' === $group || ! in_array( (string) ( $b['role'] ?? '' ), array( 'container', 'heading' ), true ) || ! self::in_aero() ) {
			return $html;
		}
		$count = self::member_count( $group );
		if ( $count < 1 ) {
			return $html;
		}
		$open = ! in_array( $group, self::collapsed_groups(), true );

		return $html . sprintf(
			'<button type="button" class="wffn-bundles-sum-toggle" data-wffn-bundles-group="%1$s" aria-expanded="%2$s"><span class="wffn-bundles-sum-toggle-hide">%3$s</span><span class="wffn-bundles-sum-toggle-show">%4$s</span></button>',
			esc_attr( sanitize_html_class( $group ) ),
			esc_attr( $open ? 'true' : 'false' ),
			/* translators: %d: number of products inside the bundle. */
			esc_html( self::toggle_text( _n( 'Hide %d item', 'Hide %d items', $count, 'funnel-builder' ), 1 === $count ? 'Hide %d item' : 'Hide %d items', 'Hide details', $count, 'Hide %d item' ) ),
			/* translators: %d: number of products inside the bundle. */
			esc_html( self::toggle_text( _n( 'Show %d item', 'Show %d items', $count, 'funnel-builder' ), 1 === $count ? 'Show %d item' : 'Show %d items', 'Show details', $count, 'Show %d item' ) )
		);
	}

	/**
	 * The toggle's text, first match wins: the store's own translation (a language pack,
	 * Loco Translate), the built-in words for the store's language (Builtin_Translations),
	 * WooCommerce's "Hide details" / "Show details" with the count in brackets, English.
	 *
	 * @param string $translated This plugin's gettext result.
	 * @param string $original   Its English source for this count.
	 * @param string $woo_msgid  WooCommerce's English phrase for the same action.
	 * @param int    $count      Number of products inside the bundle.
	 * @param string $source     The English source string, as `_n()` gets it: 'Hide %d item' or 'Show %d item'.
	 * @return string
	 */
	private static function toggle_text( $translated, $original, $woo_msgid, $count, $source ) {
		if ( $translated === $original ) {
			$label = Builtin_Translations::plural( $source, $count );
			if ( '' !== $label ) {
				return $label;
			}
		}
		$text = WooCommerce::translated_or_woo( $translated, $original, $woo_msgid );
		if ( $text === $translated ) {
			return sprintf( $translated, $count );
		}
		$items = (string) get_translations_for_domain( 'woocommerce' )->translate_plural( '%s item', '%s items', $count );
		$items = in_array( $items, array( '%s item', '%s items' ), true ) || false === strpos( $items, '%s' ) ? (string) $count : sprintf( $items, $count );

		return $text . ' (' . $items . ')';
	}

	/**
	 * Whether a line is listed under a bundle line.
	 *
	 * @param array $b The line's bundle data.
	 * @return bool
	 */
	private static function is_member( array $b ) {
		return 'child' === (string) ( $b['role'] ?? '' ) || Cart::is_nested( $b );
	}

	/**
	 * Is Aero rendering this page? The classic cart and checkout share these hooks.
	 *
	 * Not WFACP_Common::get_id(): it falls back to the session, so it stays set
	 * on every page after the shopper's first Aero checkout.
	 *
	 * @return bool
	 */
	private static function in_aero() {
		return did_action( 'wfacp_after_checkout_page_found' ) > 0 || Adapter::aero_ajax();
	}

	/**
	 * How many units a bundle group's member lines hold in the cart.
	 *
	 * Units, not lines: two picks of one variation share a line, and the count
	 * has to agree with the offer's own count, such as "5 Tees".
	 *
	 * @param string $group Bundle group id.
	 * @return int
	 */
	private static function member_count( $group ) {
		$count = 0;
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
			if ( (string) ( $b['group'] ?? '' ) === $group && self::is_member( $b ) ) {
				$count += max( 1, (int) ( $item['quantity'] ?? 1 ) );
			}
		}

		return $count;
	}

	/**
	 * Whether the cart holds a bundle line with members under it.
	 *
	 * @return bool
	 */
	private static function cart_has_group() {
		foreach ( function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart() : array() as $item ) {
			if ( in_array( (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ), array( 'container', 'heading' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The bundle groups the shopper folded, from the toggle's cookie.
	 *
	 * @return string[]
	 */
	private static function collapsed_groups() {
		static $groups = null;
		if ( null !== $groups ) {
			return $groups;
		}
		$raw    = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$groups = array_slice(
			array_values(
				array_filter(
					explode( '.', $raw ),
					function ( $group ) {
						return (bool) preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $group );
					}
				)
			),
			0,
			self::MAX_GROUPS
		);

		return $groups;
	}
}
