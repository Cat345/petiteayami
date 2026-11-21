<?php
/**
 * Cart — carry a bundle's selection into the WooCommerce cart and price it.
 *
 * Every bundle line carries `wffn_bundles_bundle` cart-item data describing the offer it
 * belongs to. The discounted price is applied in `woocommerce_before_calculate_totals`
 * from a *stored* unit price — never re-derived from the live price — so repeated
 * recalculations never compound (the WooCommerce-canonical dynamic-pricing pattern).
 *
 * Two entry paths feed the same pricing engine:
 *  - Single-product offers (Fixed Bundle's "just this product", and an add-on's
 *    tiers) ride the product's native add-to-cart form; hidden fields
 *    (`wffn_bundles_bundle_id`, and whatever the add-on prints) say what was chosen.
 *  - Multi-product offers (Fixed Bundle members, variable
 *    products with per-unit variations) post the selection to `wffn_bundles_add_bundle`,
 *    which validates it against the bundle, resolves variations, prices each
 *    member, and adds them as one grouped bundle.
 */
namespace WFFN\Bundles\Includes\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Skins\Fixed_Bundle;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Install;
use WFFN\Bundles\Includes\Storage\Order_Attribution;
use WFFN\Bundles\Includes\Storage\Order_Data;
use WFFN\Bundles\Includes\Ajax\Controller;
use WFFN\Bundles\Includes\Helpers\Request_Context;
use WFFN\Bundles\Includes\Helpers\WooCommerce;
use WFFN\Bundles\Includes\Skins\Pricing;
use WFFN\Bundles\Includes\Skins\Frequently_Bought_Together;
use WFFN\Bundles\Includes\Products\Repository;
use WFFN\Bundles\Includes\Storage\Bundle_Rules;
use WFFN\Bundles\Includes\Skins\Theme_Compat;
use WFFN\Bundles\Includes\Compat\Currency\Layer;
use WFFN\Bundles\Includes\Compat\Discount\Layer as Discount_Layer;

/**
 * Carries a bundle's selection into the WooCommerce cart and prices it.
 */
class Cart {

	/**
	 * The price hooks a cart line is charged through.
	 *
	 * Variations use their own hook prefix (`WC_Product_Variation::get_hook_prefix`),
	 * so a variable bundle member never fires the simple-product one.
	 */
	const PRICE_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_variation_get_price',
	);

	/**
	 * The sale-price hooks, kept separate.
	 *
	 * A discount ratio is meaningful here: scaling whatever the switcher resolved
	 * keeps the struck-through price consistent with the charged one. An absolute
	 * override is not — it is the line's charged price, and returning it as the
	 * sale price would make `is_on_sale()` compare two unrelated numbers.
	 */
	const SALE_PRICE_HOOKS = array(
		'woocommerce_product_get_sale_price',
		'woocommerce_product_variation_get_sale_price',
	);

	/** Key a removed line carries naming the line whose removal took it out, so an undo brings it back. */
	const REMOVED_WITH = 'wffn_bundles_removed_with';

	private static $instance = null;

	/** Guard so removing a bundle member does not recurse while it removes its siblings. */
	private $removing_group = null;

	/** Guard so scaling a container's members doesn't recurse on quantity update. */
	private $scaling_group = null;

	/** Reentrancy guard for the before_calculate_totals pricing pass (audit H2 / §1.1). */
	private $applying_pricing = false;

	/** Reentrancy guard for the before_calculate_totals quantity clamp. */
	private $locking_quantities = false;

	/**
	 * Set while the Store API validates the cart. Its woocommerce_check_cart_items
	 * pass keeps only error notices (as cart errors) and throws the rest away.
	 */
	private $store_api_validating = false;

	/**
	 * Price overrides for cart lines the switcher prices itself, keyed by that
	 * line's product object.
	 *
	 * Each entry is `[ 'ratio'|'absolute', value ]`. A member scales the price the
	 * switcher resolved, which is currency-safe because a ratio has no unit; a
	 * Fixed Bundle container's placeholder product has no catalog price to scale,
	 * so it carries an absolute display-currency figure.
	 *
	 * SplObjectStorage rather than an spl_object_id() map: these filters outlive
	 * the pass that built the map, and a freed object's id can be reused by an
	 * unrelated product. Holding the reference is what makes the key safe.
	 *
	 * @var \SplObjectStorage|null
	 */
	private $custom_price_ratios = null;

	/** Have the priority-10000 ratio filters been registered this request? */
	private $ratio_filters_added = false;

	/**
	 * The same absolute prices, keyed by cart item key rather than by object.
	 *
	 * A discount engine may replace a cart line's product with a clone of its own
	 * mid-request, and the object-keyed map above then misses for the rest of the
	 * request. Only consulted when an engine is active, so an uninstrumented store
	 * resolves exactly as it did before.
	 *
	 * @var array<string,float>
	 */
	private $engine_absolutes = array();

	/**
	 * Ids of the products restore_item() gave a composed thumbnail this request.
	 *
	 * @var array<int,true>
	 */
	private static $thumb_products = array();

	/**
	 * The shared instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register the add, pricing, grouping and display hooks.
	 */
	private function __construct() {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'form_fields' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		// Ahead of the default priority, where the stored cart is written per line.
		add_action( 'woocommerce_add_to_cart', array( $this, 'defer_saved_cart' ), 9, 6 );
		// The backstop against a bare add of a bundle that has to be configured;
		// every arg is defaulted because core applies this filter with three to six.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'guard_bare_add' ), 10, 6 );
		// Priority 5, ahead of the page builders that wrap this button at 10: our
		// permalink is then the string they wrap, and their alignment markup survives.
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'loop_add_to_cart_link' ), 5, 3 );
		// The Blocks product button and the Store API read these instead of the loop link filter.
		add_filter( 'woocommerce_product_supports', array( $this, 'no_ajax_add' ), 10, 3 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'configure_first_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'configure_first_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_description', array( $this, 'configure_first_description' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_item' ), 10, 2 );
		// Ahead of apply_pricing at 20: a line priced from its quantity has to be
		// clamped first.
		// Ahead of the clamp and the pricing pass: a set's prices follow what is still in it.
		// Ahead of every callback that reads a line's bundle, add-ons' included.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'prime_bundles' ), 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'reprice_fbt' ), 18 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'lock_quantities' ), 19 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_pricing' ), 20 );
		// Cart/checkout page loads, not every totals recalc: apply_pricing() keeps
		// charging the pricedUnit a line locked in at add time, so a paused,
		// ended or deleted bundle never gets caught there on its own.
		add_action( 'woocommerce_check_cart_items', array( $this, 'drop_stale_bundles' ), 0 );
		// The FunnelKit checkout prints its notices on this hook and only then checks the cart.
		add_action( 'woocommerce_before_checkout_form_cart_notices', array( $this, 'drop_stale_before_checkout_notices' ), 0 );
		// On a page load it first empties the cart and re-adds its own rows, which a stale group never survives.
		add_action( 'wfacp_add_to_cart_init', array( $this, 'drop_stale_before_checkout_rebuild' ), 0 );
		// A drop a background Store API read answered is told on the next page.
		add_action( 'template_redirect', array( $this, 'tell_held_drops' ), 20 );
		// Fires just ahead of that pass in the Store API (block cart / checkout).
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'note_store_api_validation' ), PHP_INT_MAX );
		// Last, so every path that sells the placeholder has stamped its bundle data first.
		add_action( 'woocommerce_add_to_cart', array( $this, 'drop_bare_container' ), PHP_INT_MAX );
		add_action( 'woocommerce_check_cart_items', array( $this, 'drop_bare_container' ), 0 );
		// A pack's children never outlive their container (it can leave while Bundles is not running).
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'drop_orphan_children' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'drop_orphan_children' ), 0 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_meta' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_class', array( $this, 'item_class' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'item_name' ), 10, 2 );
		// The placeholder's own name ("Bundle") must never reach a shopper: notices and labels read get_name().
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'name_container_lines' ), 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'name_container_lines' ), 1 );
		add_filter( 'woocommerce_cart_item_removed_title', array( $this, 'removed_title' ), 10, 2 );
		// The container line owns the qty + remove + total; members are read-only.
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'item_quantity' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'item_remove_link' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'item_price' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'item_price' ), 10, 2 );
		// Again, last: a pricing plugin can rebuild this cell from the line's own
		// figures at a very late priority, which puts a member's 0.00 back.
		add_filter( 'woocommerce_cart_item_price', array( $this, 'item_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'item_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'item_thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_product_get_image', array( $this, 'heading_product_image' ), 10, 3 );
		// Store API equivalent of item_thumbnail, WooCommerce 9.6+; on older
		// versions restore_item()'s image-id override is the fallback.
		add_filter( 'woocommerce_store_api_cart_item_images', array( $this, 'store_api_item_images' ), 10, 2 );
		// Keep a bundle together: removing the container removes its members, and
		// changing the container qty scales them.
		add_action( 'woocommerce_remove_cart_item', array( $this, 'remove_group' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'restore_group' ), 10, 2 );
		// Ahead of the two above, which would warn about each line a whole set leaves behind.
		add_action( 'woocommerce_remove_cart_item', array( $this, 'remove_whole_set' ), 9, 2 );
		add_action( 'woocommerce_remove_cart_item', array( $this, 'fbt_removal_notice' ), 10, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'scale_group' ), 10, 4 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_scale' ), 10, 4 );
		// Grouping CSS for the classic cart / checkout; the block cart loads the
		// same file through the Store API blocks integration.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_css' ) );
		add_filter( 'body_class', array( $this, 'cart_body_class' ) );
		add_filter( 'woocommerce_cart_contents_count', array( $this, 'exclude_container_from_count' ) );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'coupon_valid_for_line' ), 10, 4 );
		add_filter( 'woocommerce_coupon_is_valid_for_sale_items', array( $this, 'coupon_valid_for_sale_items' ), 10, 3 );
	}

	/**
	 * Hold a product coupon to what a bundle line really sells.
	 *
	 * A pack is charged on the shared container product, so it matches the coupon's
	 * products and categories through its members: any member it is valid for
	 * applies it, and any member it excludes keeps it off the pack. A line the
	 * bundle discounted counts as on sale.
	 *
	 * @param bool        $valid   WooCommerce's answer.
	 * @param \WC_Product $product The line's product.
	 * @param \WC_Coupon  $coupon  The coupon.
	 * @param array       $values  The cart line.
	 * @return bool
	 */
	public function coupon_valid_for_line( $valid, $product, $coupon, $values = array() ) {
		$b = is_array( $values ) && is_array( $values['wffn_bundles_bundle'] ?? null ) ? $values['wffn_bundles_bundle'] : null;
		if ( ! $b || ! $coupon instanceof \WC_Coupon || ! $coupon->is_type( wc_get_product_coupon_types() ) ) {
			return $valid;
		}
		// A member at 0 has nothing to discount; its pack carries the money.
		if ( 'child' === (string) ( $b['role'] ?? '' ) && self::has_container( (string) ( $b['group'] ?? '' ) ) ) {
			return false;
		}
		if ( 'container' === (string) ( $b['role'] ?? '' ) ) {
			$members = self::pack_members( (string) ( $b['group'] ?? '' ) );
			if ( ! $members ) {
				return $valid;
			}
			$valid = ! $coupon->get_product_ids() && ! $coupon->get_product_categories();
			foreach ( $members as $member ) {
				$ids  = array( $member->get_id(), $member->get_parent_id() );
				$cats = wc_get_product_cat_ids( $member->get_parent_id() ? $member->get_parent_id() : $member->get_id() );
				if ( array_intersect( $ids, $coupon->get_excluded_product_ids() ) || array_intersect( $cats, $coupon->get_excluded_product_categories() ) ) {
					return false;
				}
				if ( array_intersect( $ids, $coupon->get_product_ids() ) || array_intersect( $cats, $coupon->get_product_categories() ) ) {
					$valid = true;
				}
			}
		}
		return $valid && $coupon->get_exclude_sale_items() && self::line_discounted( $values ) ? false : $valid;
	}

	/**
	 * A cart coupon that excludes sale items treats a bundle-discounted line as one.
	 *
	 * @param bool          $valid     WooCommerce's answer.
	 * @param \WC_Coupon    $coupon    The coupon.
	 * @param \WC_Discounts $discounts The discounts being validated.
	 * @return bool
	 */
	public function coupon_valid_for_sale_items( $valid, $coupon = null, $discounts = null ) {
		if ( ! $valid || ! $discounts instanceof \WC_Discounts ) {
			return $valid;
		}
		foreach ( $discounts->get_items_to_validate() as $item ) {
			if ( is_array( $item->object ?? null ) && self::line_discounted( $item->object ) ) {
				return false;
			}
		}
		return $valid;
	}

	/**
	 * Whether a Fixed Bundle pack's container line is in the cart.
	 *
	 * @param string $group The pack's group.
	 * @return bool
	 */
	private static function has_container( $group ) {
		if ( '' === $group || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $ci ) {
			if ( 'container' === (string) ( $ci['wffn_bundles_bundle']['role'] ?? '' ) && (string) ( $ci['wffn_bundles_bundle']['group'] ?? '' ) === $group ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The member products of a Fixed Bundle pack in the cart.
	 *
	 * @param string $group The pack's group.
	 * @return \WC_Product[]
	 */
	private static function pack_members( $group ) {
		$out = array();
		if ( '' === $group || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $ci ) {
			if ( 'child' === (string) ( $ci['wffn_bundles_bundle']['role'] ?? '' ) && (string) ( $ci['wffn_bundles_bundle']['group'] ?? '' ) === $group && ( $ci['data'] ?? null ) instanceof \WC_Product ) {
				$out[] = $ci['data'];
			}
		}
		return $out;
	}

	/**
	 * Whether a bundle line sells below its products' own prices, or holds a product on sale.
	 *
	 * @param array $item Cart line.
	 * @return bool
	 */
	private static function line_discounted( array $item ) {
		$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
		if ( ! $b || 'child' === (string) ( $b['role'] ?? '' ) ) {
			return false;
		}
		if ( 'container' !== (string) ( $b['role'] ?? '' ) ) {
			return self::stored_unit( $item ) < (float) ( $b['unit'] ?? 0 ) - 0.000001 || ( ( $item['data'] ?? null ) instanceof \WC_Product && $item['data']->is_on_sale() );
		}
		$full = 0.0;
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $ci ) {
			$c = is_array( $ci['wffn_bundles_bundle'] ?? null ) ? $ci['wffn_bundles_bundle'] : array();
			if ( 'child' !== (string) ( $c['role'] ?? '' ) || (string) ( $c['group'] ?? '' ) !== (string) ( $b['group'] ?? '' ) ) {
				continue;
			}
			if ( ( $ci['data'] ?? null ) instanceof \WC_Product && $ci['data']->is_on_sale() ) {
				return true;
			}
			$full += (float) ( $c['unit'] ?? 0 ) * max( 1, (int) ( $c['baseQty'] ?? 1 ) );
		}
		return (float) ( $b['pricedUnit'] ?? 0 ) < $full - 0.000001;
	}

	/**
	 * Keep a Fixed Bundle's container line out of the cart contents count.
	 *
	 * The container carries the pack's money but is not a product the shopper
	 * picked, so counting it inflates every cart-count display by one per bundle.
	 *
	 * @param int $count Cart contents count.
	 * @return int
	 */
	public function exclude_container_from_count( $count ) {
		if ( ! WC()->cart ) {
			return $count;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( in_array( $cart_item['wffn_bundles_bundle']['role'] ?? '', array( 'container', 'heading' ), true ) ) {
				$count -= (int) $cart_item['quantity'];
			}
		}
		return max( 0, $count );
	}

	/**
	 * Load the bundle-grouping stylesheet on classic cart / checkout pages.
	 *
	 * @return void
	 */
	public function enqueue_cart_css() {
		if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) ) {
			return;
		}
		// A store with no active bundle cannot gain a bundle line here without a reload.
		if ( is_cart() && ! is_checkout() && ! Bundle_Store::has_active() && ! self::has_bundle_line() ) {
			return;
		}
		$rel  = 'assets/skins/cart-blocks.css';
		$path = WFFN_BUNDLES_DIR . '/' . $rel;
		wp_enqueue_style( 'wffn-bundles-cart-grouping', WFFN_BUNDLES_URL . $rel, array(), (string) ( file_exists( $path ) ? filemtime( $path ) : '1' ) );
		self::enqueue_theme_compat_css();
	}

	/**
	 * The active theme's compatibility stylesheet, for the cart-side pages.
	 *
	 * Enqueued after `wffn-bundles-cart-grouping` so it can correct the member indent,
	 * which depends on the theme's own table-cell padding and thumbnail size. Same
	 * file, slug and `body.wffn-bundles-theme-{slug}` scope as the product page — see
	 * Theme_Compat. Almost always a no-op.
	 *
	 * @return void
	 */
	public static function enqueue_theme_compat_css() {
		foreach ( Theme_Compat::styles() as $rel ) {
			$rel = ltrim( (string) $rel, '/' );
			// Confine to the plugin's own asset tree, as
			// Product_Display::enqueue_paths() does.
			if ( '' === $rel || false !== strpos( $rel, '..' ) || preg_match( '#^([a-z][a-z0-9+.-]*:)?//#i', $rel ) ) {
				continue;
			}
			$path = WFFN_BUNDLES_DIR . '/' . $rel;
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$handle = 'wffn-bundles-' . sanitize_title( str_replace( array( 'assets/skins/', '.css' ), '', $rel ) );
			wp_enqueue_style( $handle, WFFN_BUNDLES_URL . $rel, array( 'wffn-bundles-cart-grouping' ), (string) filemtime( $path ) );
		}
	}

	/**
	 * Stamp the active theme's slug on the cart-side pages so a compatibility file
	 * can scope its rules as it does on the product page. Product pages get this
	 * from Product_Display::body_class(), which is gated on is_product().
	 *
	 * @param string[] $classes Body classes so far.
	 * @return string[]
	 */
	public function cart_body_class( $classes ) {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) || is_wc_endpoint_url( 'view-order' ) ) ) {
			$slug = Theme_Compat::slug();
			if ( '' !== $slug ) {
				$classes[] = 'wffn-bundles-theme-' . sanitize_html_class( $slug );
			}
		}
		return $classes;
	}

	/**
	 * A bundle is removed as a unit: removing its primary line takes every
	 * dependent line in the same group with it, add-ons included.
	 *
	 * @param string   $key  Cart item key being removed.
	 * @param \WC_Cart $cart The cart.
	 * @return void
	 */
	public function remove_group( $key, $cart ) {
		if ( null !== $this->removing_group ) {
			return;
		}
		$item = $cart->cart_contents[ $key ] ?? null;
		if ( empty( $item['wffn_bundles_bundle']['group'] ) || ! self::is_primary_role( (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) ) ) {
			return;
		}
		$group = (string) $item['wffn_bundles_bundle']['group'];
		// A nested line with a quantity of its own leaves alone while the bundle keeps another product line.
		if ( self::is_nested( $item['wffn_bundles_bundle'] ) && ! self::nested_quantity_locked( $item ) && self::group_keeps_unit( $cart, $group, $key ) ) {
			return;
		}
		$this->removing_group = $group;
		foreach ( $cart->cart_contents as $k => $ci ) {
			if ( $k === $key ) {
				continue;
			}
			if ( (string) ( $ci['wffn_bundles_bundle']['group'] ?? '' ) === $group && self::is_dependent_role( (string) ( $ci['wffn_bundles_bundle']['role'] ?? '' ) ) ) {
				$cart->remove_cart_item( $k );
				if ( isset( $cart->removed_cart_contents[ $k ] ) ) {
					$cart->removed_cart_contents[ $k ][ self::REMOVED_WITH ] = (string) $key;
				}
			}
		}
		$this->removing_group = null;
	}

	/**
	 * Whether a bundle group holds a product line other than `$key`.
	 *
	 * @param \WC_Cart $cart  The cart.
	 * @param string   $group Bundle group id.
	 * @param string   $key   Cart item key to leave out.
	 * @return bool
	 */
	private static function group_keeps_unit( $cart, $group, $key ) {
		foreach ( $cart->cart_contents as $k => $ci ) {
			$b = is_array( $ci['wffn_bundles_bundle'] ?? null ) ? $ci['wffn_bundles_bundle'] : array();
			if ( (string) $k !== (string) $key && (string) ( $b['group'] ?? '' ) === $group && in_array( (string) ( $b['role'] ?? '' ), array( 'member', 'anchor' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Undoing a bundle's removal brings back every line that left with it.
	 *
	 * WooCommerce restores only the line the shopper undid; alone, a pack's
	 * container would come back charging for members that stay removed.
	 *
	 * @param string   $key  Cart item key just restored.
	 * @param \WC_Cart $cart The cart.
	 * @return void
	 */
	public function restore_group( $key, $cart ) {
		if ( ! is_object( $cart ) || empty( $cart->removed_cart_contents ) ) {
			return;
		}
		foreach ( (array) $cart->removed_cart_contents as $k => $line ) {
			if ( (string) ( $line[ self::REMOVED_WITH ] ?? '' ) !== (string) $key ) {
				continue;
			}
			unset( $cart->removed_cart_contents[ $k ][ self::REMOVED_WITH ] );
			$cart->restore_cart_item( $k );
		}
	}

	/**
	 * Changing the container quantity rescales every member's quantity with it.
	 *
	 * @param string   $key          Cart item key that changed.
	 * @param int      $quantity     New quantity.
	 * @param int      $old_quantity Previous quantity.
	 * @param \WC_Cart $cart         The cart.
	 * @return void
	 */
	public function scale_group( $key, $quantity, $old_quantity, $cart ) {
		if ( null !== $this->scaling_group ) {
			return;
		}
		$item = $cart->cart_contents[ $key ] ?? null;
		if ( '' === self::dependent_role( $cart, $key ) ) {
			return;
		}
		$group = (string) $item['wffn_bundles_bundle']['group'];
		$n     = max( 1, (int) $quantity );
		// Every quantity path (classic cart form, Store API, cart drawers, checkout
		// switchers) ends in set_quantity(), so the members' stock is checked here:
		// a raise the stock cannot supply keeps the old quantity.
		$refusal = $n > (int) $old_quantity ? self::scale_refusal( $cart, $key, $n, (int) $old_quantity ) : null;
		if ( null !== $refusal ) {
			$cart->cart_contents[ $key ]['quantity'] = $refusal['keep'];
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $refusal['message'], 'error' );
			}
			return;
		}
		// A bought-together set's companions follow their host in lock_quantities().
		if ( 'container' !== (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) ) {
			return;
		}
		$this->scaling_group = $group;
		foreach ( $cart->cart_contents as $k => $ci ) {
			if ( $k === $key ) {
				continue;
			}
			if ( (string) ( $ci['wffn_bundles_bundle']['group'] ?? '' ) === $group && 'child' === (string) ( $ci['wffn_bundles_bundle']['role'] ?? '' ) ) {
				$base = max( 1, (int) ( $ci['wffn_bundles_bundle']['baseQty'] ?? 1 ) );
				$cart->set_quantity( $k, $base * $n, false );
			}
		}
		$this->scaling_group = null;
	}

	/**
	 * Why a pack's lead line cannot rise to `$n`, and the quantity it keeps.
	 *
	 * @param \WC_Cart $cart The cart.
	 * @param string   $key  The lead line's cart item key.
	 * @param int      $n    Quantity asked for.
	 * @param int      $old  Quantity it holds now.
	 * @return array{keep:int,message:string}|null Null when the raise fits.
	 */
	private static function scale_refusal( $cart, $key, $n, $old ) {
		// A member WooCommerce sells one at a time (or caps per order) caps the packs, stock or not.
		$cap = self::group_purchase_cap( $cart, $key );
		if ( null !== $cap && $n > $cap['packs'] ) {
			return array(
				'keep'    => max( 1, min( $old, $cap['packs'] ) ),
				/* translators: 1: packs allowed, 2: product name. */
				'message' => sprintf( __( 'You can only have %1$d of this bundle in your cart: “%2$s” is limited per order.', 'funnel-builder' ), $cap['packs'], $cap['product']->get_name() ),
			);
		}
		$short = self::group_stock_shortfall( $cart, $key, $n );
		if ( null === $short ) {
			return null;
		}
		return array(
			'keep'    => max( 1, $old ),
			'message' => self::stock_message( 'insufficient_stock', $short['product'], $short['in_cart'], $short['needed'] ),
		);
	}

	/**
	 * Refuse a cart-form raise its pack cannot take before WooCommerce applies it.
	 *
	 * Refused here, the line counts as unchanged, so the form does not also say the cart was updated.
	 *
	 * @param bool   $passed   Whether the update passed so far.
	 * @param string $key      Cart item key.
	 * @param array  $values   Cart item.
	 * @param int    $quantity Quantity posted.
	 * @return bool
	 */
	public function validate_scale( $passed, $key, $values, $quantity ) {
		$cart = function_exists( 'WC' ) ? WC()->cart : null;
		if ( ! $passed || ! $cart || (int) $quantity <= (int) ( $values['quantity'] ?? 0 ) || '' === self::dependent_role( $cart, $key ) ) {
			return $passed;
		}
		$refusal = self::scale_refusal( $cart, $key, (int) $quantity, (int) ( $values['quantity'] ?? 1 ) );
		if ( null === $refusal ) {
			return $passed;
		}
		wc_add_notice( $refusal['message'], 'error' );
		return false;
	}

	/**
	 * The role of the lines a pack's lead line carries with it: a Fixed Bundle
	 * container's children, or a bought-together host's companions (they follow
	 * its quantity). Empty for any other line.
	 *
	 * @param \WC_Cart $cart The cart.
	 * @param string   $key  Cart item key.
	 * @return string
	 */
	private static function dependent_role( $cart, $key ) {
		$b = is_array( $cart->cart_contents[ $key ]['wffn_bundles_bundle'] ?? null ) ? $cart->cart_contents[ $key ]['wffn_bundles_bundle'] : array();
		if ( empty( $b['group'] ) ) {
			return '';
		}
		$role = (string) ( $b['role'] ?? '' );
		if ( 'container' === $role ) {
			return 'child';
		}
		return 'fbt_host' === $role && Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) ( $b['type'] ?? 0 ) ? 'fbt_companion' : '';
	}

	/**
	 * Per-pack units of each stock-managed member of a container's group, and the
	 * units of the same product held elsewhere in the cart.
	 *
	 * @param \WC_Cart $cart The cart.
	 * @param string   $key  The container's cart item key.
	 * @return array<int,array{product:\WC_Product,per_pack:int,elsewhere:int}>
	 */
	private static function group_stock_members( $cart, $key ) {
		$group = (string) ( $cart->cart_contents[ $key ]['wffn_bundles_bundle']['group'] ?? '' );
		if ( '' === $group ) {
			return array();
		}
		$per_pack = array();
		$in_group = array();
		$first    = array();
		$dep      = self::dependent_role( $cart, $key );
		foreach ( $cart->cart_contents as $k => $ci ) {
			$b = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : array();
			if ( $k === $key || (string) ( $b['group'] ?? '' ) !== $group || '' === $dep || (string) ( $b['role'] ?? '' ) !== $dep ) {
				continue;
			}
			$pid = ! empty( $ci['variation_id'] ) ? (int) $ci['variation_id'] : (int) ( $ci['product_id'] ?? 0 );
			if ( ! $pid ) {
				continue;
			}
			// Keyed as get_cart_item_quantities() keys: a variation whose stock the
			// parent manages counts under the parent's id.
			$stock_id = isset( $ci['data'] ) && $ci['data'] instanceof \WC_Product ? (int) $ci['data']->get_stock_managed_by_id() : $pid;
			if ( ! isset( $per_pack[ $stock_id ] ) ) {
				$first[ $stock_id ] = $pid;
			}
			$per_pack[ $stock_id ] = ( $per_pack[ $stock_id ] ?? 0 ) + max( 1, (int) ( $b['baseQty'] ?? 1 ) );
			$in_group[ $stock_id ] = ( $in_group[ $stock_id ] ?? 0 ) + (int) ( $ci['quantity'] ?? 0 );
		}
		$in_cart = $cart->get_cart_item_quantities();
		$out     = array();
		foreach ( $per_pack as $pid => $per ) {
			$product = wc_get_product( $first[ $pid ] );
			// Matches WC_Product::has_enough_stock(): only a managed count without backorders limits.
			if ( ! $product || ! $product->managing_stock() || $product->backorders_allowed() ) {
				continue;
			}
			$out[ $pid ] = array(
				'product'   => $product,
				'per_pack'  => $per,
				'elsewhere' => max( 0, (int) ( $in_cart[ $pid ] ?? 0 ) - (int) ( $in_group[ $pid ] ?? 0 ) ),
			);
		}
		return $out;
	}

	/**
	 * The first member a container's group cannot supply at `$packs` packs.
	 *
	 * Units of the same product outside this group count against its stock, as
	 * validate_stock() counts what is already in the cart.
	 *
	 * @param \WC_Cart $cart  The cart.
	 * @param string   $key   The container's cart item key.
	 * @param int      $packs Pack quantity asked for.
	 * @return array{product:\WC_Product,in_cart:int,needed:int}|null Null when every member fits.
	 */
	private static function group_stock_shortfall( $cart, $key, $packs ) {
		foreach ( self::group_stock_members( $cart, $key ) as $m ) {
			$needed = $m['per_pack'] * max( 1, (int) $packs );
			if ( ! $m['product']->has_enough_stock( $m['elsewhere'] + $needed ) ) {
				return array(
					'product' => $m['product'],
					'in_cart' => $m['elsewhere'],
					'needed'  => $needed,
				);
			}
		}
		return null;
	}

	/**
	 * Most packs a container's group can hold from its members' stock.
	 *
	 * @param \WC_Cart $cart The cart.
	 * @param string   $key  The container's cart item key.
	 * @return int|null Null when no member limits it; never below 1.
	 */
	public static function group_pack_limit( $cart, $key ) {
		$limit = null;
		foreach ( self::group_stock_members( $cart, $key ) as $m ) {
			$packs = intdiv( max( 0, (int) $m['product']->get_stock_quantity() - $m['elsewhere'] ), $m['per_pack'] );
			$limit = null === $limit ? $packs : min( $limit, $packs );
		}
		$cap = self::group_purchase_cap( $cart, $key );
		if ( null !== $cap ) {
			$limit = null === $limit ? $cap['packs'] : min( $limit, $cap['packs'] );
		}
		return null === $limit ? null : max( 1, $limit );
	}

	/**
	 * Most packs a container's group may hold from its members' per-order limits
	 * (sold individually, or a maximum purchase quantity), stock aside.
	 *
	 * @param \WC_Cart $cart The cart.
	 * @param string   $key  The container's cart item key.
	 * @return array{packs:int,product:\WC_Product}|null Null when no member limits it.
	 */
	private static function group_purchase_cap( $cart, $key ) {
		$group = (string) ( $cart->cart_contents[ $key ]['wffn_bundles_bundle']['group'] ?? '' );
		if ( '' === $group ) {
			return null;
		}
		$best = null;
		$dep  = self::dependent_role( $cart, $key );
		foreach ( $cart->cart_contents as $k => $ci ) {
			$b = is_array( $ci['wffn_bundles_bundle'] ?? null ) ? $ci['wffn_bundles_bundle'] : array();
			if ( $k === $key || (string) ( $b['group'] ?? '' ) !== $group || '' === $dep || (string) ( $b['role'] ?? '' ) !== $dep ) {
				continue;
			}
			$product = ( $ci['data'] ?? null ) instanceof \WC_Product ? $ci['data'] : null;
			if ( ! $product ) {
				continue;
			}
			$max = $product->is_sold_individually() ? 1 : (int) $product->get_max_purchase_quantity();
			// Stock is group_stock_members()' part; -1 (or a stock-derived maximum) is no per-order limit here.
			if ( $max <= 0 || ( ! $product->is_sold_individually() && $product->managing_stock() ) ) {
				continue;
			}
			$packs = intdiv( $max, max( 1, (int) ( $b['baseQty'] ?? 1 ) ) );
			if ( null === $best || $packs < $best['packs'] ) {
				$best = array(
					'packs'   => max( 1, $packs ),
					'product' => $product,
				);
			}
		}
		return $best;
	}

	/** Roles that nest inside the grouped-bundle line (not standalone add-ons). */
	private static function is_grouped_role( $role ) {
		return in_array( $role, array( 'container', 'parent', 'child', 'member', 'heading' ), true );
	}

	/**
	 * A bundle's primary line: removing it tears the whole group out, add-ons
	 * included. Covers every grouped member plus the Fixed Bundle "Buy 1" line.
	 * Removing an add-on alone never cascades, so those are not primary. `single`
	 * is deliberately not a bundle line for free shipping.
	 *
	 * @param string $role The line's bundle role.
	 * @return bool
	 */
	private static function is_primary_role( $role ) {
		// `anchor` is the quantity-priced line a native form places (Cart::add_cart_item_data).
		return in_array( $role, array( 'anchor', 'container', 'parent', 'child', 'member', 'heading', 'single' ), true );
	}

	/**
	 * Lines removed alongside a bundle's primary line: its grouped members and its
	 * add-ons, which cannot exist in the cart without the bundle they came with.
	 *
	 * @param string $role The line's bundle role.
	 * @return bool
	 */
	private static function is_dependent_role( $role ) {
		return in_array( $role, array_merge( array( 'container', 'parent', 'child', 'member', 'heading' ), self::addon_roles() ), true );
	}

	/**
	 * Roles of the add-on lines that ride a bundle's group without being part of it.
	 *
	 * @return string[]
	 */
	public static function addon_roles() {
		/**
		 * Filters the cart roles an add-on line may carry.
		 *
		 * Such a line is held to its granted quantity and removed with its bundle.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $roles Add-on roles. Empty by default.
		 */
		return array_map( 'strval', (array) apply_filters( 'wffn_bundles_addon_roles', array() ) );
	}

	/** Whether a built member can actually be added (a variable product needs a resolved variation). */
	private static function member_addable( $m ) {
		$pid = ! empty( $m['variation_id'] ) ? (int) $m['variation_id'] : (int) ( $m['product_id'] ?? 0 );
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $p || ! $p->is_purchasable() ) {
			return false;
		}
		return ! ( $p->is_type( 'variable' ) && empty( $m['variation_id'] ) );
	}

	/**
	 * The shared, hidden placeholder product used as a bundle's container line.
	 *
	 * A pure read of the autoloaded option, with no post lookup: it runs on
	 * every cart request. Install::maybe_ensure_container_product() keeps it
	 * pointing at a product; a stale id makes the add path fail soft (`add_failed`).
	 *
	 * @return int
	 */
	private static function container_id() {
		return max( 0, (int) get_option( Install::OPTION_CONTAINER_ID, 0 ) );
	}

	/**
	 * Let the private container be bought, while this plugin is there to price it.
	 *
	 * @param bool        $purchasable Whether WooCommerce would sell it.
	 * @param \WC_Product $product     The product asked about.
	 * @return bool
	 */
	public static function container_purchasable( $purchasable, $product ) {
		if ( $purchasable || ! $product instanceof \WC_Product ) {
			return $purchasable;
		}
		$id = self::container_id();
		return $id > 0 && $id === (int) $product->get_id() ? true : $purchasable;
	}

	/**
	 * Whether the current cart holds a line this plugin priced.
	 *
	 * @return bool
	 */
	public static function has_bundle_line() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['wffn_bundles_bundle'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove the children of a pack whose container is no longer in the cart.
	 *
	 * A child is priced 0 because its container carries the pack's price; with
	 * the container gone (removed while Bundles was not running, say) the
	 * children would be a half pack at no charge. They leave the cart, and the
	 * shopper is told which products, as for a bundle no longer available.
	 *
	 * @return void
	 */
	public function drop_orphan_children() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$with_container = array();
		$with_child     = array();
		$shares         = array();
		$present        = array();
		$headings       = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$role  = (string) ( $item['wffn_bundles_bundle']['role'] ?? '' );
			$group = (string) ( $item['wffn_bundles_bundle']['group'] ?? '' );
			if ( 'container' === $role ) {
				$with_container[ $group ] = true;
			} elseif ( 'child' === $role ) {
				$with_child[ $group ] = true;
				$shares[ $group ]     = ( $shares[ $group ] ?? 0.0 ) + (float) ( $item['wffn_bundles_bundle']['pricedShare'] ?? 0 ) * max( 1, (int) ( $item['wffn_bundles_bundle']['baseQty'] ?? 1 ) );
			} elseif ( 'member' === $role ) {
				$present[ $group ][ (int) ( ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? 0 ) ) ] = true;
			} elseif ( 'heading' === $role ) {
				$headings[ $group ] = self::bundle_title( $item['wffn_bundles_bundle'] );
			}
		}
		$dropped    = array();
		$incomplete = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
			if ( ! $b || ! isset( WC()->cart->cart_contents[ $key ] ) ) {
				continue;
			}
			$role  = (string) ( $b['role'] ?? '' );
			$group = (string) ( $b['group'] ?? '' );
			// A container with none of its children left would charge the pack price for nothing.
			if ( 'container' === $role && ! isset( $with_child[ $group ] ) ) {
				$dropped[] = self::bundle_title( $b );
				WC()->cart->remove_cart_item( $key );
				continue;
			}
			// A child that left (no longer sellable) takes its share out of a pack still charging it in full.
			if ( 'container' === $role && (float) ( $b['pricedUnit'] ?? 0 ) - ( $shares[ $group ] ?? 0.0 ) > 0.005 ) {
				$incomplete[] = self::bundle_title( $b );
				WC()->cart->remove_cart_item( $key );
				continue;
			}
			if ( 'member' === $role && ! empty( $b['groupIds'] ) && array_diff( array_map( 'intval', (array) $b['groupIds'] ), array_keys( $present[ $group ] ?? array() ) ) ) {
				/**
				 * Filters whether a bundle group that lost one of its products leaves the cart whole.
				 *
				 * A type that re-prices what is left from the remaining quantity answers false.
				 *
				 * @since 1.0.0
				 *
				 * @param bool  $drop Whether to drop the group. True by default.
				 * @param array $b    The line's `wffn_bundles_bundle` data.
				 */
				if ( apply_filters( 'wffn_bundles_cart_drop_incomplete_group', true, $b ) ) {
					$incomplete[] = $headings[ $group ] ?? self::bundle_title( $b );
					WC()->cart->remove_cart_item( $key );
					continue;
				}
			}
			if ( 'child' !== $role || isset( $with_container[ $group ] ) ) {
				continue;
			}
			$product   = $item['data'] ?? null;
			$dropped[] = $product instanceof \WC_Product ? $product->get_name() : (string) ( $item['product_id'] ?? 0 );
			WC()->cart->remove_cart_item( $key );
		}
		$dropped = array_merge( $dropped, self::drop_orphan_addons() );
		if ( $dropped && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: comma-separated list of product names. */
					_n(
						'"%s" is no longer available at that price and has been removed from your cart.',
						'"%s" are no longer available at that price and have been removed from your cart.',
						count( $dropped ),
						'funnel-builder'
					),
					implode( ', ', $dropped )
				),
				'error'
			);
		}
		if ( $incomplete && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: comma-separated list of quoted bundle names. */
					_n(
						'%s has been removed from your cart because a product in it is no longer available.',
						'%s have been removed from your cart because a product in each is no longer available.',
						count( array_unique( $incomplete ) ),
						'funnel-builder'
					),
					'"' . implode( '", "', array_unique( $incomplete ) ) . '"'
				),
				'error'
			);
		}
	}

	/**
	 * Remove add-on lines whose bundle has no line of its own left in the cart.
	 *
	 * An upsell or gift is priced off the bundle it came with; a bundle line that
	 * left without remove_group() (no longer sellable, an undo of the add-on alone)
	 * would leave it discounted or free on its own.
	 *
	 * @return string[] Names of the lines removed.
	 */
	private static function drop_orphan_addons() {
		$addon_roles = self::addon_roles();
		if ( empty( $addon_roles ) ) {
			return array();
		}
		$held = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
			if ( self::is_primary_role( (string) ( $b['role'] ?? '' ) ) ) {
				$held[ 'g:' . (string) ( $b['group'] ?? '' ) ] = true;
				$held[ 'b:' . (string) ( $b['bundle'] ?? '' ) ] = true;
			}
		}
		$dropped = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
			if ( ! in_array( (string) ( $b['role'] ?? '' ), $addon_roles, true ) || '' === (string) ( $b['group'] ?? '' ) ) {
				continue;
			}
			// A repeat add's add-ons may wait on another group of the same bundle to fold them in.
			if ( isset( $held[ 'g:' . (string) $b['group'] ] ) || isset( $held[ 'b:' . (string) ( $b['bundle'] ?? '' ) ] ) ) {
				continue;
			}
			$product   = $item['data'] ?? null;
			$dropped[] = $product instanceof \WC_Product ? $product->get_name() : (string) ( $item['product_id'] ?? 0 );
			WC()->cart->remove_cart_item( $key );
		}
		return $dropped;
	}

	/**
	 * The name a shopper knows a bundle line's bundle by.
	 *
	 * @param array $b The line's `wffn_bundles_bundle` data.
	 * @return string
	 */
	private static function bundle_title( array $b ) {
		if ( '' !== trim( (string) ( $b['bundleTitle'] ?? '' ) ) ) {
			return (string) $b['bundleTitle'];
		}
		$bundle = Bundle_Store::get_config( $b['bundle'] ?? 0 );
		return is_array( $bundle ) && '' !== (string) ( $bundle['name'] ?? '' ) ? (string) $bundle['name'] : __( 'Your bundle', 'funnel-builder' );
	}

	/**
	 * The product or variation ids a bundle's members go into the cart as.
	 *
	 * @param array[] $members Built member rows.
	 * @return int[]
	 */
	private static function member_ids( array $members ) {
		$ids = array();
		foreach ( $members as $m ) {
			$ids[] = (int) ( ! empty( $m['variation_id'] ) ? $m['variation_id'] : $m['product_id'] );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Remove the container placeholder when it was added on its own, outside a bundle.
	 *
	 * The placeholder is a published zero-priced product so a pack can be charged on
	 * it; added bare it would be a free line.
	 *
	 * @param string $cart_key The line just added; empty to check the whole cart.
	 * @return void
	 */
	public function drop_bare_container( $cart_key = '' ) {
		$container = self::container_id();
		if ( ! $container || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$keys    = is_string( $cart_key ) && '' !== $cart_key ? array( $cart_key ) : array_keys( WC()->cart->get_cart() );
		$dropped = false;
		foreach ( $keys as $key ) {
			$item = WC()->cart->get_cart_item( $key );
			if ( is_array( $item ) && absint( $item['product_id'] ?? 0 ) === $container && empty( $item['wffn_bundles_bundle'] ) ) {
				WC()->cart->remove_cart_item( $key );
				$dropped = true;
			}
		}
		if ( $dropped ) {
			wc_add_notice( __( 'That item can only be bought as part of a bundle, so it was not added to your cart.', 'funnel-builder' ), 'error' );
		}
	}

	/**
	 * The container placeholder as a product, for the display-side engine fold.
	 *
	 * A pack of two or more members is charged on this line, so it is the product
	 * whose engine rule moves the pack price.
	 *
	 * @return \WC_Product|null
	 */
	public static function container_product() {
		$id      = self::container_id();
		$product = $id ? wc_get_product( $id ) : null;
		return $product ? $product : null;
	}

	/**
	 * A Fixed Bundle container's cart thumbnail, as [ 'src' => url, 'imgId' => id ].
	 *
	 * Mirrors the product page's lead image (Product_Display::offer_image for the
	 * 'fb-bundle' offer), keyed by the page product the bundle was added from.
	 * Public because the Aero Checkout adapter builds its own container line.
	 *
	 * `src` is the configured URL, which the classic cart renders directly even
	 * when it is not a local attachment; `imgId` is that URL resolved to an
	 * attachment, which is all the block cart can render.
	 *
	 * @param array  $dc              Bundle discount config.
	 * @param int    $page_product_id Page product the bundle was added from.
	 * @param array  $members         Built member rows.
	 * @param string $offer_id        Offer whose image leads the line.
	 * @return array{src:string,imgId:int}
	 */
	/**
	 * The image id of the product a pack was bought from, or 0.
	 *
	 * Read once, while that product is in front of the shopper and already loaded,
	 * and carried on the cart line from then on.
	 *
	 * @param int $anchor_id The target product the pack was bought from.
	 * @return int
	 */
	private static function anchor_image_id( $anchor_id ) {
		$anchor_id = absint( $anchor_id );
		$product   = $anchor_id && function_exists( 'wc_get_product' ) ? wc_get_product( $anchor_id ) : null;

		return $product instanceof \WC_Product ? (int) $product->get_image_id() : 0;
	}

	public static function container_thumb( $dc, $page_product_id, $members, $offer_id = 'fb-bundle' ) {
		$url = '';
		$img = 0;
		if ( Product_Display::offer_images_on( $dc ) ) {
			$url = Product_Display::offer_image( $dc, absint( $page_product_id ), $offer_id, '' );
			$img = self::image_id_from_url( $url );
			// Same fallback offer_image() applies for the product-page card.
			if ( '' === $url ) {
				$ap   = $page_product_id ? wc_get_product( absint( $page_product_id ) ) : null;
				$aimg = $ap ? (int) $ap->get_image_id() : 0;
				if ( $aimg ) {
					$img = $aimg;
					$url = (string) wp_get_attachment_image_url( $aimg, 'woocommerce_thumbnail' );
				}
			}
		}
		// Last resort so the thumbnail is never broken: the first member's image.
		if ( ! $img ) {
			foreach ( (array) $members as $m ) {
				$tid = ! empty( $m['variation_id'] ) ? (int) $m['variation_id'] : (int) ( $m['product_id'] ?? 0 );
				$tp  = $tid ? wc_get_product( $tid ) : null;
				$mid = $tp ? (int) $tp->get_image_id() : 0;
				if ( $mid ) {
					$img = $mid;
					break;
				}
			}
		}
		return array( 'src' => $url, 'imgId' => $img );
	}

	/**
	 * Show a heading line's composed image wherever its product renders an image.
	 *
	 * @param string      $html    Image markup WooCommerce built.
	 * @param \WC_Product $product Product being pictured.
	 * @param mixed       $size    Requested size.
	 * @return string
	 */
	public function heading_product_image( $html, $product = null, $size = '' ) {
		// Only a product restore_item() stamped can answer; asking any other reads its meta from the database.
		if ( ! $product instanceof \WC_Product || ! isset( self::$thumb_products[ $product->get_id() ] ) ) {
			return $html;
		}
		$src = (string) $product->get_meta( '_wffn_bundles_thumb_src' );
		if ( '' === $src ) {
			return $html;
		}
		$dim = is_array( $size ) ? ' width="' . (int) $size[0] . '" height="' . (int) ( $size[1] ?? $size[0] ) . '"' : '';

		return '<img src="' . esc_url( $src ) . '" alt=""' . $dim . ' class="attachment-woocommerce_thumbnail wp-post-image" />';
	}

	/**
	 * A heading line's thumbnail: the offer's own image, else a collage of its picks.
	 *
	 * @param array  $dc              Bundle discount config.
	 * @param int    $page_product_id Page product the box was added from.
	 * @param array  $members         Built member rows.
	 * @param string $offer_id        Offer the box was added under.
	 * @return array{src:string,imgId:int}
	 */
	public static function heading_thumb( $dc, $page_product_id, $members, $offer_id ) {
		if ( Product_Display::offer_images_on( $dc ) && '' !== Product_Display::offer_image( $dc, $page_product_id, $offer_id, '' ) ) {
			return self::container_thumb( $dc, $page_product_id, $members, $offer_id );
		}
		$ids = array();
		foreach ( $members as $m ) {
			$ids[] = $m['variation_id'] ? (int) $m['variation_id'] : (int) $m['product_id'];
		}
		$collage = Group_Thumb::url( Group_Thumb::image_ids( $ids ) );

		return '' !== $collage ? array( 'src' => $collage, 'imgId' => 0 ) : self::container_thumb( $dc, $page_product_id, $members, $offer_id );
	}

	/**
	 * Resolve a media URL to its attachment id, tolerating a resized or `-scaled`
	 * variant: attachment_url_to_postid() matches the original upload URL only, so
	 * that suffix is stripped and retried. Returns 0 for a non-local URL.
	 *
	 * @param string $url Media URL.
	 * @return int
	 */
	private static function image_id_from_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return 0;
		}
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid -- The offer stores an image URL, not an attachment id, and the VIP wrapper does not exist outside WordPress VIP.
		$id = (int) attachment_url_to_postid( $url );
		if ( $id ) {
			return $id;
		}
		$stripped = preg_replace( '/-(?:\d+x\d+|scaled)(\.[A-Za-z0-9]+)$/', '$1', $url );
		if ( is_string( $stripped ) && $stripped !== $url ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid -- Same lookup for the URL with its size suffix removed; there is no non-VIP alternative.
			$id = (int) attachment_url_to_postid( $stripped );
		}
		return $id;
	}

	/** Session key: stale-group drops a background Store API read answered, still to be told. */
	const SESSION_HELD_DROPS = 'wffn_bundles_held_drops';

	/** Form field posted only with JavaScript off (inside <noscript>). */
	const FIELD_NO_SCRIPT = 'wffn_bundles_no_script';

	/**
	 * With JavaScript off the widget's own option pickers post nothing, and the
	 * product form's are the only ones that reach the cart: show the form's,
	 * which the widget otherwise replaces, and hide the widget's.
	 */
	const NO_SCRIPT_PICKERS = '<style>body.wffn-bundles-owns-pickers form.cart .variations{display:revert !important}.wffn-bundles .wffn-bundles-variant{display:none !important}</style>';

	/** @var bool Whether add_in_process() is running, so its own adds are not re-routed. */
	private static $in_process = false;

	/**
	 * Whether a product-form post is to be sold as the product's bundle.
	 *
	 * Only the bundle the product page offers this visitor (a stale or another
	 * product's id never is). A configure-first type is always built here, since
	 * its widget takes over the button whenever it runs; a type whose widget
	 * rides the native form (bought together, quantity breaks) only when the
	 * form was posted without JavaScript.
	 *
	 * @param string $posted       Posted bundle id.
	 * @param int    $product_id   Product being added.
	 * @param int    $variation_id Variation being added, if any.
	 * @return bool
	 */
	private static function sells_native_form( $posted, $product_id, $variation_id ) {
		if ( self::$in_process || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		$anchor = $variation_id ? (int) wp_get_post_parent_id( $variation_id ) : $product_id;
		$anchor = $anchor ? $anchor : $product_id;
		$bundle = Bundle_Store::active_for_product( $anchor );
		$bundle = $bundle ? $bundle : Bundle_Store::active_for_product( $anchor, Bundle_Store::SLOT_FBT );
		if ( ! $bundle || (string) ( $bundle['id'] ?? '' ) !== (string) $posted ) {
			return false;
		}
		if ( in_array( (int) ( $bundle['discountType'] ?? 0 ), self::configure_first_types(), true ) ) {
			return true;
		}
		return '1' === Request_Context::text( self::FIELD_NO_SCRIPT );
	}

	/**
	 * Whether the product page offers this bundle to the current visitor (either slot).
	 *
	 * @param string $posted       Posted bundle id.
	 * @param int    $product_id   Product being added.
	 * @param int    $variation_id Variation being added, if any.
	 * @return bool
	 */
	private static function offers_bundle( $posted, $product_id, $variation_id ) {
		$anchor = $variation_id ? (int) wp_get_post_parent_id( $variation_id ) : $product_id;
		$anchor = $anchor ? $anchor : $product_id;
		$bundle = Bundle_Store::active_for_product( $anchor );
		$bundle = $bundle ? $bundle : Bundle_Store::active_for_product( $anchor, Bundle_Store::SLOT_FBT );
		return $bundle && (string) ( $bundle['id'] ?? '' ) === (string) $posted;
	}

	/**
	 * The offer and selection the widget opens on, built on the server for a
	 * form posted without it. Never trusted: the add re-validates every id,
	 * offer and quantity against the stored bundle, as for the widget's own add.
	 *
	 * @param array $bundle       The bundle the product page offers.
	 * @param int   $anchor       The product the form is on.
	 * @param int   $quantity     The form's quantity.
	 * @param int   $variation_id The form's variation, if any: the one its attributes
	 *                            resolve to when the form posts only those.
	 * @return array{offer:string,selection:array}|null Null when the bundle has no default to sell.
	 */
	public static function native_form_default( array $bundle, $anchor, $quantity = 1, $variation_id = 0 ) {
		$type = (int) ( $bundle['discountType'] ?? 0 );
		$dc   = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$out  = null;
		// The product form's own options are the shopper's pick of the anchor.
		$form_attrs = self::form_variation_attributes( $variation_id );
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			$items = array();
			foreach ( array_values( (array) ( $dc['fixedBundleItems'] ?? array() ) ) as $it ) {
				$pid     = absint( is_array( $it['product'] ?? null ) ? ( $it['product']['id'] ?? 0 ) : 0 );
				$items[] = array( 'attributes' => $form_attrs && (int) $anchor === $pid ? $form_attrs : self::default_attributes( $pid ) );
			}
			$out = array(
				'offer'     => '',
				'selection' => array(
					'items'   => $items,
					'product' => (int) $anchor,
				),
			);
		} elseif ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			$host  = wc_get_product( $anchor );
			$items = array();
			if ( $host instanceof \WC_Product ) {
				foreach ( Frequently_Bought_Together::companion_ids( $dc, $host ) as $id ) {
					if ( Frequently_Bought_Together::starts_checked( $dc, $id ) ) {
						$items[] = array(
							'id'         => $id,
							'attributes' => self::default_attributes( absint( $id ) ),
							'on'         => true,
						);
					}
				}
			}
			$out = array(
				'offer'     => Bundle_Store::FBT_OFFER,
				'selection' => array(
					'product'        => (int) $anchor,
					'variation'      => $variation_id ? (string) $variation_id : '',
					'hostAttributes' => $form_attrs,
					'hostOn'         => true,
					'quantity'       => max( 1, (int) $quantity ),
					'items'          => $items,
				),
			);
		}

		/**
		 * Filters the offer and selection a product form posted without the
		 * widget sells: the widget's opening state. A type this plugin does not
		 * build answers for its own bundles; null sells nothing.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null $default  { offer, selection }, or null.
		 * @param array      $bundle   The bundle the product page offers.
		 * @param array      $context  { anchor, quantity, variation_id }.
		 */
		$out = apply_filters(
			'wffn_bundles_native_form_default',
			$out,
			$bundle,
			array(
				'anchor'       => (int) $anchor,
				'quantity'     => max( 1, (int) $quantity ),
				'variation_id' => (int) $variation_id,
			)
		);

		return is_array( $out ) && isset( $out['selection'] ) && is_array( $out['selection'] ) ? array(
			'offer'     => (string) ( $out['offer'] ?? '' ),
			'selection' => $out['selection'],
		) : null;
	}

	/**
	 * A variation's attributes, keyed by bare attribute name, with an "any" axis
	 * read off the posted form. Empty when the form named no variation.
	 *
	 * @param int $variation_id The form's variation.
	 * @return array<string,string>
	 */
	private static function form_variation_attributes( $variation_id ) {
		$variation = $variation_id ? wc_get_product( $variation_id ) : null;
		if ( ! $variation instanceof \WC_Product || ! $variation->is_type( 'variation' ) ) {
			return array();
		}
		$out = array();
		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			$out[ substr( (string) $key, strlen( 'attribute_' ) ) ] = '' !== (string) $value ? (string) $value : Request_Context::text( (string) $key );
		}
		return $out;
	}

	/**
	 * The variation a product form's posted options name, for a form posted
	 * without JavaScript: WooCommerce applies the add-to-cart validation before it
	 * resolves them, so `variation_id` is still empty there.
	 *
	 * @param int   $product_id The form's product.
	 * @param array $variations Posted `attribute_*` values.
	 * @return int The variation; 0 when the form posts no options (the bundle's defaults
	 *             apply); -1 when an option is left unchosen or the choice matches none.
	 */
	private static function posted_variation( $product_id, $variations ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) || ! is_array( $variations ) ) {
			return 0;
		}
		$picked = array();
		foreach ( array_keys( $product->get_variation_attributes() ) as $name ) {
			$key = 'attribute_' . sanitize_title( (string) $name );
			if ( array_key_exists( $key, $variations ) ) {
				$picked[ $key ] = is_scalar( $variations[ $key ] ) ? wc_clean( (string) $variations[ $key ] ) : '';
			}
		}
		if ( ! $picked ) {
			return 0;
		}
		// Every axis must be chosen: find_matching_product_variation() matches on a subset.
		if ( count( $picked ) < count( $product->get_variation_attributes() ) || count( array_filter( $picked, 'strlen' ) ) < count( $picked ) ) {
			return -1;
		}
		$vid = (int) \WC_Data_Store::load( 'product' )->find_matching_product_variation( $product, $picked );
		return $vid ? $vid : -1;
	}

	/**
	 * A product's default variation attributes, keyed by bare attribute name, as
	 * the widget's pickers open on them. Empty for a simple product.
	 *
	 * @param int $product_id Product id.
	 * @return array<string,string>
	 */
	private static function default_attributes( $product_id ) {
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) $product->get_default_attributes() as $name => $value ) {
			$out[ (string) $name ] = (string) $value;
		}
		return $out;
	}

	/**
	 * Sell a product-form post as the product's bundle, then send the shopper on
	 * the way WooCommerce does after an add. False keeps WooCommerce from adding
	 * the product alone.
	 *
	 * @param string $posted       Posted bundle id.
	 * @param int    $product_id   Product being added.
	 * @param int    $quantity     Quantity being added.
	 * @param int    $variation_id Variation being added, if any.
	 * @return bool
	 */
	private static function add_native_form( $posted, $product_id, $quantity, $variation_id ) {
		$anchor  = $variation_id ? (int) wp_get_post_parent_id( $variation_id ) : $product_id;
		$anchor  = $anchor ? $anchor : $product_id;
		$bundle  = Bundle_Store::get_config( $posted );
		$default = is_array( $bundle ) ? self::native_form_default( $bundle, $anchor, $quantity, $variation_id ) : null;
		if ( null === $default ) {
			wc_add_notice( __( 'Choose your bundle options on the product page before adding this to your cart.', 'funnel-builder' ), 'error' );
			return false;
		}
		$fields = array(
			'bundle'    => (string) $posted,
			'type'      => (string) (int) ( $bundle['discountType'] ?? 0 ),
			'offer'     => $default['offer'],
			'selection' => wp_json_encode( $default['selection'] ),
		);
		// All or nothing, like one press of the widget: a pack count the store cannot
		// supply puts back the cart as it was, the anchor included, and only says why.
		$cart_before    = WC()->cart->get_cart_contents();
		$removed_before = WC()->cart->get_removed_cart_contents();
		$refuse         = static function () use ( $cart_before, $removed_before ) {
			WC()->cart->set_cart_contents( $cart_before );
			WC()->cart->set_removed_cart_contents( $removed_before );
			WC()->cart->calculate_totals();
			return false;
		};
		$result = self::add_in_process( $fields );
		if ( ! $result['success'] ) {
			wc_add_notice( self::refusal_text( $result['data'] ), 'error' );
			return $refuse();
		}
		// The form's quantity box is a pack count for a type whose selection carries none.
		$packs     = max( 1, (int) $quantity );
		$container = '';
		foreach ( (array) $result['keys'] as $key ) {
			if ( 'container' === (string) ( WC()->cart->get_cart_item( $key )['wffn_bundles_bundle']['role'] ?? '' ) ) {
				$container = (string) $key;
				break;
			}
		}
		if ( $packs > 1 && '' !== $container ) {
			// scale_group() checks every member's stock and per-order limit and names the short product.
			if ( self::set_pack_count( array( $container ), $packs, (string) ( $bundle['name'] ?? '' ) ) < $packs ) {
				return $refuse();
			}
		} elseif ( $packs > 1 && in_array( (int) ( $bundle['discountType'] ?? 0 ), self::configure_first_types(), true ) ) {
			for ( $i = 1; $i < $packs; $i++ ) {
				$more = self::add_in_process( $fields );
				if ( ! $more['success'] ) {
					wc_add_notice( self::refusal_text( $more['data'] ), 'error' );
					return $refuse();
				}
				foreach ( (array) $more['lines'] as $id => $qty ) {
					$result['lines'][ $id ] = ( $result['lines'][ $id ] ?? 0 ) + (int) $qty;
				}
			}
		}
		if ( $result['lines'] && function_exists( 'wc_add_to_cart_message' ) ) {
			wc_add_to_cart_message( $result['lines'], true );
		}
		// The request ends here: totals first, so WooCommerce stores the cart as it now stands (a raised pack count included).
		WC()->cart->calculate_totals();
		$url = 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ? wc_get_cart_url() : get_permalink( $anchor );
		wp_safe_redirect( (string) apply_filters( 'woocommerce_add_to_cart_redirect', $url, wc_get_product( $anchor ) ) );
		exit;
	}

	/**
	 * The sentence a refused add shows, from its machine code.
	 *
	 * @param mixed $data The refusal: a code, or { code, text }.
	 * @return string
	 */
	private static function refusal_text( $data ) {
		if ( is_array( $data ) && '' !== (string) ( $data['text'] ?? '' ) ) {
			return (string) $data['text'];
		}
		$code   = is_array( $data ) ? (string) ( $data['code'] ?? '' ) : (string) $data;
		$errors = (array) ( Controller::config()['errors'] ?? array() );
		return (string) ( $errors[ $code ] ?? ( $errors['_default'] ?? __( 'Sorry, this bundle couldn’t be added to your cart. Please try again.', 'funnel-builder' ) ) );
	}

	/**
	 * Put one bundle group of a past order back in the cart ("Order again").
	 *
	 * While its bundle is live the pack comes back as one bundle, built and
	 * priced today exactly as the widget's add is; otherwise, or when today's
	 * add refuses it, its products come back as ordinary products at their
	 * normal prices. Never a half pack, never the price the old order paid.
	 *
	 * @param \WC_Order_Item_Product[] $items The group's order lines.
	 * @return void
	 */
	public static function reorder_group( array $items ) {
		if ( empty( $items ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$first     = reset( $items );
		$bundle_id = (string) Order_Data::get( $first, Order_Attribution::META_BUNDLE_ID );
		$type      = (int) Order_Data::get( $first, Order_Attribution::META_TYPE );
		$bundle    = '' !== $bundle_id ? Bundle_Store::get_config( $bundle_id ) : null;
		if ( is_array( $bundle ) && (int) ( $bundle['discountType'] ?? 0 ) === $type && Bundle_Store::is_active_now( $bundle ) ) {
			$plan   = self::reorder_fields( $bundle, $type, $items );
			$result = null !== $plan ? self::add_in_process( $plan['fields'] ) : array( 'success' => false );
			if ( $result['success'] ) {
				$added = (array) $result['lines'];
				$packs = (int) $plan['packs'];
				if ( $packs > 1 && Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
					self::set_pack_count( (array) $result['keys'], $packs, (string) ( $bundle['name'] ?? '' ) );
				} elseif ( $packs > 1 ) {
					// One box per add: each further box is its own group, priced as the first.
					for ( $i = 1; $i < $packs; $i++ ) {
						$more  = self::add_in_process( $plan['box_fields'] );
						$added = $more['success'] ? $added + (array) $more['lines'] : $added;
					}
				}
				// An add-on the bundle no longer offers comes back as its own product, as on the fallback;
				// one it still offers comes back at the quantity the order bought.
				foreach ( $items as $item ) {
					$id = $item->get_variation_id() ? (int) $item->get_variation_id() : (int) $item->get_product_id();
					if ( 'upsell' !== (string) Order_Data::get( $item, Order_Attribution::META_ROLE ) ) {
						continue;
					}
					if ( empty( $added[ $id ] ) ) {
						self::reorder_plain_line( $item );
						continue;
					}
					foreach ( (array) $result['keys'] as $key ) {
						$line = WC()->cart->get_cart_item( $key );
						$lid  = ! empty( $line['variation_id'] ) ? (int) $line['variation_id'] : (int) ( $line['product_id'] ?? 0 );
						if ( $lid === $id && 'upsell' === (string) ( $line['wffn_bundles_bundle']['role'] ?? '' ) && (int) $item->get_quantity() > (int) $line['quantity'] ) {
							WC()->cart->set_quantity( $key, (int) $item->get_quantity(), false );
						}
					}
				}
				return;
			}
		}
		$container = self::container_id();
		foreach ( $items as $item ) {
			$role = (string) Order_Data::get( $item, Order_Attribution::META_ROLE );
			// A pack's placeholder line and a gift are not products the shopper bought at a price.
			if ( (int) $item->get_product_id() === $container || in_array( $role, array( 'container', 'heading', 'gift', 'shipping_gift' ), true ) ) {
				continue;
			}
			self::reorder_plain_line( $item );
		}
	}

	/**
	 * Put a past order's line back in the cart as an ordinary product at its normal price.
	 *
	 * @param \WC_Order_Item_Product $item The order line.
	 * @return void
	 */
	private static function reorder_plain_line( $item ) {
		$product = $item->get_product();
		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}
		$variation = array();
		foreach ( self::order_item_attributes( $item ) as $name => $value ) {
			$variation[ 'attribute_' . sanitize_title( $name ) ] = $value;
		}
		WC()->cart->add_to_cart( (int) $item->get_product_id(), max( 1, (int) $item->get_quantity() ), (int) $item->get_variation_id(), $variation );
	}

	/**
	 * Raise a Fixed Bundle group just added to a number of packs.
	 *
	 * Through set_quantity(), so scale_group() scales the members and checks
	 * their stock; when the stock cannot supply them all, the group keeps what
	 * it has and scale_group() tells the shopper which product is short.
	 *
	 * @param string[] $keys  Cart keys the add placed.
	 * @param int      $packs Packs wanted.
	 * @param string   $name  Bundle name, for the notice.
	 * @return int Packs the group now holds.
	 */
	public static function set_pack_count( array $keys, $packs, $name = '' ) {
		unset( $name ); // Kept for callers; scale_group() names the short product itself.
		$packs = max( 1, (int) $packs );
		foreach ( $keys as $key ) {
			$line = WC()->cart->get_cart_item( $key );
			if ( 'container' !== (string) ( $line['wffn_bundles_bundle']['role'] ?? '' ) ) {
				continue;
			}
			if ( $packs > 1 ) {
				WC()->cart->set_quantity( $key, $packs, false );
			}
			// A short stock already told the shopper (scale_group()), naming the product.
			return (int) ( WC()->cart->get_cart_item( $key )['quantity'] ?? 1 );
		}
		return 1;
	}

	/**
	 * The widget's add fields that rebuild a past order's bundle group: the
	 * stored offer and the products and variations the shopper picked.
	 *
	 * @param array                    $bundle The group's bundle, today.
	 * @param int                      $type   Bundle type.
	 * @param \WC_Order_Item_Product[] $items  The group's order lines.
	 * @return array|null Null when the group cannot be rebuilt as a bundle.
	 */
	private static function reorder_fields( array $bundle, $type, array $items ) {
		$offer     = '';
		$selection = array();
		$picks     = array();
		$upsells   = array();
		$packs     = 1;
		foreach ( $items as $item ) {
			$role = (string) Order_Data::get( $item, Order_Attribution::META_ROLE );
			if ( '' === $offer ) {
				$offer = (string) Order_Data::get( $item, Order_Attribution::META_OFFER );
			}
			// A Fixed pack's container, or a box's heading, holds the number of packs bought.
			if ( in_array( $role, array( 'container', 'heading' ), true ) ) {
				$packs = max( 1, (int) $item->get_quantity() );
			}
			if ( 'upsell' === $role ) {
				$upsells[] = array(
					'id'         => (int) $item->get_product_id(),
					'attributes' => self::order_item_attributes( $item ),
				);
				continue;
			}
			if ( (int) $item->get_product_id() === self::container_id() || in_array( $role, array( 'container', 'heading', 'gift', 'shipping_gift' ), true ) ) {
				continue;
			}
			$picks[] = array(
				'id'         => (int) $item->get_product_id(),
				'qty'        => max( 1, (int) $item->get_quantity() ),
				'attributes' => self::order_item_attributes( $item ),
				'role'       => $role,
			);
		}
		if ( empty( $picks ) ) {
			return null;
		}
		$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			// The members come from the bundle today; the order gives each one's variation.
			$offer = 'fb-bundle' === $offer ? '' : $offer;
			$by_id = array();
			foreach ( $picks as $pick ) {
				$by_id[ $pick['id'] ][] = $pick['attributes'];
			}
			$slots = array();
			foreach ( array_values( (array) ( $dc['fixedBundleItems'] ?? array() ) ) as $it ) {
				$pid     = absint( is_array( $it['product'] ?? null ) ? ( $it['product']['id'] ?? 0 ) : 0 );
				$attrs   = ! empty( $by_id[ $pid ] ) ? array_shift( $by_id[ $pid ] ) : array();
				$slots[] = array( 'attributes' => $attrs );
			}
			$selection = array( 'items' => $slots );
		} elseif ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			$offer = Bundle_Store::FBT_OFFER;
			$host  = null;
			$rest  = array();
			foreach ( $picks as $pick ) {
				if ( null === $host && 'fbt_host' === $pick['role'] ) {
					$host = $pick;
				} else {
					$rest[] = array(
						'id'         => $pick['id'],
						'attributes' => $pick['attributes'],
						'on'         => true,
					);
				}
			}
			$selection = array(
				'product'        => $host ? $host['id'] : 0,
				'hostAttributes' => $host ? $host['attributes'] : array(),
				'hostOn'         => null !== $host,
				'quantity'       => $host ? $host['qty'] : 1,
				'items'          => $rest,
			);
		} else {
			// Several boxes were sent as one set of multiplied picks: one box is each pick ÷ the box count.
			$per_box = $packs > 1;
			foreach ( $picks as $pick ) {
				$per_box = $per_box && 0 === $pick['qty'] % $packs;
			}
			$selection = array(
				'items' => array_map(
					static function ( $pick ) use ( $per_box, $packs ) {
						unset( $pick['role'] );
						$pick['qty'] = $per_box ? intdiv( $pick['qty'], $packs ) : $pick['qty'];
						return $pick;
					},
					$picks
				),
			);
			$packs = $per_box ? $packs : 1;
		}
		if ( $upsells ) {
			$selection['upsells'] = $upsells;
		}

		/**
		 * Filters the widget's add fields that rebuild a past order's bundle group.
		 *
		 * A type this plugin does not build reshapes its own selection; null brings
		 * the group's products back as ordinary products.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null               $fields { offer, selection }.
		 * @param array                    $bundle The group's bundle, today.
		 * @param \WC_Order_Item_Product[] $items  The group's order lines.
		 */
		$fields = apply_filters(
			'wffn_bundles_order_again_fields',
			array(
				'offer'     => $offer,
				'selection' => $selection,
			),
			$bundle,
			$items
		);
		if ( ! is_array( $fields ) || ! is_array( $fields['selection'] ?? null ) ) {
			return null;
		}
		$box = $fields['selection'];
		unset( $box['upsells'] );
		$base = array(
			'bundle' => (string) ( $bundle['id'] ?? '' ),
			'type'   => (string) $type,
			'offer'  => (string) ( $fields['offer'] ?? '' ),
		);
		return array(
			'fields'     => $base + array( 'selection' => wp_json_encode( $fields['selection'] ) ),
			'box_fields' => $base + array( 'selection' => wp_json_encode( $box ) ),
			'packs'      => (int) $packs,
		);
	}

	/**
	 * An order line's variation attributes, keyed by bare attribute name, the
	 * way the widget posts them; empty for a simple product.
	 *
	 * @param \WC_Order_Item_Product $item Order line.
	 * @return array<string,string>
	 */
	private static function order_item_attributes( $item ) {
		$out       = array();
		$variation = $item->get_variation_id() ? wc_get_product( $item->get_variation_id() ) : null;
		if ( ! $variation instanceof \WC_Product ) {
			return $out;
		}
		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			$name = substr( (string) $key, strlen( 'attribute_' ) );
			if ( '' === (string) $value ) {
				// An "Any" axis: the shopper's pick is on the line.
				$value = (string) $item->get_meta( $name, true );
			}
			$out[ $name ] = (string) $value;
		}
		return $out;
	}

	/**
	 * Run the widget's add (ajax_add()) inside this request, for a server-side
	 * add that is not the widget's own: every id, offer and quantity is
	 * re-validated and priced exactly as the widget's add is.
	 *
	 * @param array $fields { bundle, type, offer, selection (JSON) }.
	 * @return array{success:bool,data:mixed,lines:array<int,int>,keys:string[]} The add's answer, the products it added (id => quantity) and the cart keys it placed.
	 */
	public static function add_in_process( array $fields ) {
		$out = array(
			'success' => false,
			'data'    => 'no_cart',
			'lines'   => array(),
			'keys'    => array(),
		);
		if ( self::$in_process || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		$fields['action'] = 'wffn_bundles_add_bundle';
		$fields['nonce']  = wp_create_nonce( Controller::NONCE );
		$lines            = array();
		$keys             = array();
		$track            = static function ( $key, $product_id, $quantity, $variation_id ) use ( &$lines, &$keys ) {
			$keys[] = (string) $key;
			if ( self::container_id() === (int) $product_id ) {
				return;
			}
			$id           = $variation_id ? (int) $variation_id : (int) $product_id;
			$lines[ $id ] = ( $lines[ $id ] ?? 0 ) + max( 1, (int) $quantity );
		};
		// The handler answers with wp_send_json(), which ends an ajax request in
		// wp_die(); here that answer is caught and the request carries on.
		$stop = static function () {
			return static function () {
				throw new \RuntimeException( 'wffn_bundles_in_process' );
			};
		};
		add_action( 'woocommerce_add_to_cart', $track, 10, 4 );
		add_filter( 'wp_doing_ajax', '__return_true', 999 );
		add_filter( 'wp_die_ajax_handler', $stop, 999 );
		self::$in_process = true;
		$notices          = function_exists( 'wc_get_notices' ) ? wc_get_notices() : array();
		ob_start();
		try {
			Request_Context::run( $fields, array( __CLASS__, 'ajax_add' ) );
		} catch ( \RuntimeException $e ) {
			if ( 'wffn_bundles_in_process' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			$echoed           = (string) ob_get_clean();
			self::$in_process = false;
			remove_filter( 'wp_die_ajax_handler', $stop, 999 );
			remove_filter( 'wp_doing_ajax', '__return_true', 999 );
			remove_action( 'woocommerce_add_to_cart', $track, 10 );
			// The answer set a JSON content type and its status; this request is a page.
			if ( ! headers_sent() ) {
				header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
				status_header( 200 );
			}
			// The handler printed and cleared the notices for its own answer.
			if ( function_exists( 'wc_set_notices' ) ) {
				wc_set_notices( $notices );
			}
		}
		$json = json_decode( $echoed, true );
		if ( is_array( $json ) ) {
			$out['success'] = ! empty( $json['success'] );
			$out['data']    = $json['data'] ?? null;
		} else {
			$out['data'] = 'add_failed';
		}
		$out['lines'] = $out['success'] ? $lines : array();
		$out['keys']  = $out['success'] ? $keys : array();
		return $out;
	}

	/**
	 * Reject an add of a product whose bundle has to be configured first.
	 *
	 * A page builder can rebuild the product page without the single-product
	 * add-to-cart template, leaving a bare button that adds the anchor at full
	 * price. A type that rides the native form is deliberately not rejected; only
	 * the configure-first types are.
	 *
	 * Every parameter is defaulted because core applies the filter with three,
	 * five or six args depending on the add path.
	 *
	 * @param bool  $passed         Validation result so far.
	 * @param int   $product_id     Product being added.
	 * @param int   $quantity       Quantity being added.
	 * @param int   $variation_id   Variation being added, if any.
	 * @param array $variations     Chosen variation attributes.
	 * @param array $cart_item_data Cart item data, on the paths that supply it.
	 * @return bool
	 */
	public function guard_bare_add( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		// Never literal true: other callbacks share this priority and overwriting
		// their false would re-enable password-protected products.
		if ( ! $passed ) {
			return $passed;
		}
		// Our own internal adds always carry wffn_bundles_bundle and are never the bare,
		// widget-less add this guards against.
		if ( is_array( $cart_item_data ) && ! empty( $cart_item_data['wffn_bundles_bundle'] ) ) {
			return $passed;
		}
		// A reorder rebuilds lines that were never posted through a form.
		if ( \WFFN\Bundles\Includes\Storage\Order_Attribution::is_order_again() ) {
			return $passed;
		}
		// Add-to-cart-time value, not nonce-protected by WooCommerce; only gates a UX guard, applies no pricing.
		$posted = Request_Context::text( 'wffn_bundles_bundle_id' );
		if ( '' === $posted ) {
			$posted = Request_Context::text( 'bundle' );
		}
		// The product form posted without the widget (JavaScript off, or a page
		// builder's bare form): the server sells the product's own bundle.
		if ( '' !== $posted && self::sells_native_form( $posted, (int) $product_id, (int) $variation_id ) ) {
			// A form posted without JavaScript names the shopper's options, not the variation.
			$variation_id = (int) $variation_id ? (int) $variation_id : self::posted_variation( (int) $product_id, $variations );
			if ( $variation_id < 0 ) {
				wc_add_notice( __( 'Choose your bundle options on the product page before adding this to your cart.', 'funnel-builder' ), 'error' );
				return false;
			}
			return self::add_native_form( $posted, (int) $product_id, (int) $quantity, $variation_id );
		}
		// A posted id that names no bundle (malformed, or deleted) is treated as no id:
		// the configure-first check below still applies.
		if ( '' !== $posted && null !== Bundle_Store::get_config( $posted ) ) {
			// A form naming a bundle the page no longer offers here (paused, out of its window
			// or audience, another product's) sells nothing rather than the product alone.
			if ( ! self::$in_process && Request_Context::text( 'wffn_bundles_bundle_id' ) === $posted && ! self::offers_bundle( $posted, (int) $product_id, (int) $variation_id ) ) {
				wc_add_notice( self::refusal_text( 'not_available' ), 'error' );
				return false;
			}
			return $passed;
		}
		$anchor   = $variation_id ? wp_get_post_parent_id( $variation_id ) : $product_id;
		$bundle = Bundle_Store::active_for_product( (int) $anchor );
		if ( ! $bundle || ! in_array( (int) ( $bundle['discountType'] ?? 0 ), self::configure_first_types(), true ) ) {
			return $passed;
		}
		/**
		 * Filters whether a bare add of a product whose bundle has to be configured
		 * first goes through as the product alone.
		 *
		 * An add path that offers the product itself rather than its bundle (a cart
		 * drawer's upsell) lets it through; the product page still sells the bundle.
		 *
		 * @param bool  $allow      False by default.
		 * @param int   $product_id Product being added.
		 * @param array $bundle   The product's active bundle.
		 */
		if ( apply_filters( 'wffn_bundles_allow_bare_add', false, (int) $anchor, $bundle ) ) {
			return $passed;
		}
		wc_add_notice( __( 'Choose your bundle options on the product page before adding this to your cart.', 'funnel-builder' ), 'error' );
		return false;
	}

	/** Whether this request's saved-cart writes are deferred; see defer_saved_cart(). */
	private static $saved_cart_deferred = false;

	/**
	 * Save the shopper's stored cart once per request that adds a bundle line.
	 *
	 * A signed-in shopper's other request reads the stored cart when its session
	 * has none, so a group stored line by line can be read half-built there.
	 *
	 * @param string $key          Cart key of the line just added.
	 * @param int    $product_id   Product added.
	 * @param int    $quantity     Quantity added.
	 * @param int    $variation_id Variation added.
	 * @param array  $variation    Variation attributes.
	 * @param array  $data         Cart item data.
	 * @return void
	 */
	public function defer_saved_cart( $key, $product_id = 0, $quantity = 1, $variation_id = 0, $variation = array(), $data = array() ) {
		if ( self::$saved_cart_deferred || ! is_array( $data ) || empty( $data['wffn_bundles_bundle'] ) ) {
			return;
		}
		self::$saved_cart_deferred = true;
		add_filter( 'woocommerce_persistent_cart_enabled', array( __CLASS__, 'saved_cart_held' ), PHP_INT_MAX );
		add_action( 'shutdown', array( __CLASS__, 'write_saved_cart' ), 0 );
	}

	/**
	 * Write the stored cart a request deferred, with every line it placed.
	 *
	 * @return void
	 */
	public static function write_saved_cart() {
		remove_filter( 'woocommerce_persistent_cart_enabled', array( __CLASS__, 'saved_cart_held' ), PHP_INT_MAX );
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->persistent_cart_update();
		}
	}

	/**
	 * Answer for the stored-cart switch while its writes are deferred.
	 *
	 * @return bool
	 */
	public static function saved_cart_held() {
		return false;
	}

	/**
	 * add_to_cart() with the validation filter a native form submit gets from
	 * WC_Form_Handler, which this plugin's own direct calls otherwise skip.
	 *
	 * Every caller here passes `wffn_bundles_bundle` in `$cart_item_data`, so
	 * guard_bare_add() (and any other listener) sees a bundle line, never a
	 * bare one. A third-party min/max-qty or membership-restriction plugin now
	 * gets to reject one of this plugin's lines the same way it would a native
	 * add, instead of never being asked.
	 *
	 * @param int   $product_id     Product being added.
	 * @param int   $quantity       Quantity being added.
	 * @param int   $variation_id   Variation being added, if any.
	 * @param array $variation      Chosen variation attributes.
	 * @param array $cart_item_data Cart item data.
	 * @return string|false Cart item key, or false when validation or the add failed.
	 */
	public static function validated_add_to_cart( $product_id, $quantity, $variation_id = 0, array $variation = array(), array $cart_item_data = array() ) {
		if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) ) {
			return false;
		}
		return WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
	}

	/**
	 * Refuse an ajax add after one of its cart writes was refused, without the
	 * notices that refusal queued.
	 *
	 * WooCommerce's add_to_cart() queues its reason as an error notice. This
	 * answer is JSON, so nothing shows that notice, and while it is queued every
	 * Store API checkout answers 409 with it, for a cart the refusal left valid.
	 *
	 * @param string $code    Machine code, as keyed in Ajax\Controller::config.
	 * @param array  $notices The notices queued before the add began.
	 * @return void
	 */
	private static function fail_add( $code, array $notices ) {
		if ( function_exists( 'wc_set_notices' ) ) {
			wc_set_notices( $notices );
		}
		Controller::fail( $code );
	}

	/**
	 * Send a loop add-to-cart button for a configuration-requiring bundle to the
	 * product page instead of adding straight from the archive.
	 *
	 * @param string      $html    Button markup.
	 * @param \WC_Product $product Loop product.
	 * @param array       $args    Button args (class, attributes, …).
	 * @return string
	 */
	public function loop_add_to_cart_link( $html, $product, $args = array() ) {
		// Blocks applies this filter for its own product button, where a bare
		// permalink would strip the wrapper classes and interactivity attributes.
		if ( false !== strpos( (string) ( $args['class'] ?? '' ), 'wp-block-button__link' ) ) {
			return $html;
		}
		if ( ! $product instanceof \WC_Product || ! self::needs_configuration( (int) $product->get_id() ) ) {
			return $html;
		}
		return sprintf(
			'<a href="%s" class="button %s">%s</a>',
			esc_url( $product->get_permalink() ),
			esc_attr( (string) ( $args['class'] ?? '' ) ),
			esc_html( $product->add_to_cart_text() )
		);
	}

	/**
	 * Does this product's bundle have to be configured before it can be added?
	 *
	 * Memoised per request and short-circuited on a store with no active
	 * bundle of a configure-first type, because it runs once per product card.
	 *
	 * @param int $product_id Loop product id.
	 * @return bool
	 */
	private static function needs_configuration( $product_id ) {
		static $memo = array();
		static $any  = null;

		if ( null === $any ) {
			$any = array() !== array_intersect( Bundle_Store::active_types(), self::product_page_first_types() );
		}
		if ( ! $any ) {
			return false;
		}
		if ( ! isset( $memo[ $product_id ] ) ) {
			// Most cards are named by no configure-first bundle; only those resolve their winner, unhydrated.
			$type                = Bundle_Store::may_target_product( $product_id, self::product_page_first_types() )
				? Bundle_Store::active_type_for_product( $product_id )
				: 0;
			$memo[ $product_id ] = Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER !== $type
				&& in_array( $type, self::product_page_first_types(), true );
		}
		return $memo[ $product_id ];
	}

	/**
	 * Bundle types whose shop and category button opens the product page instead of adding.
	 *
	 * @return int[]
	 */
	private static function product_page_first_types() {
		/**
		 * Filters the bundle types whose archive button links to the product page, labelled
		 * "Select options". Unlike wffn_bundles_configure_first_types, a bare add stays allowed.
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $types Bundle type ids, the configure-first types by default.
		 */
		$types = (array) apply_filters( 'wffn_bundles_product_page_first_types', self::configure_first_types() );
		return array_values( array_unique( array_map( 'intval', array_merge( self::configure_first_types(), $types ) ) ) );
	}

	/**
	 * Bundle types a shopper has to configure on the product page before adding.
	 *
	 * @return int[]
	 */
	private static function configure_first_types() {
		/**
		 * Filters the bundle types that must be configured on the product page,
		 * so archives link there and a bare add is refused.
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $types Bundle type ids.
		 */
		return array_map( 'intval', (array) apply_filters( 'wffn_bundles_configure_first_types', array( Bundle_Store::TYPE_FIXED_BUNDLES ) ) );
	}

	/**
	 * Turn off the archive AJAX add for a bundle that has to be configured first.
	 *
	 * @param bool        $supports Whether the product supports the feature.
	 * @param string      $feature  Feature being checked.
	 * @param \WC_Product $product  Product being checked.
	 * @return bool
	 */
	public function no_ajax_add( $supports, $feature, $product ) {
		if ( ! $supports || 'ajax_add_to_cart' !== $feature || ! $product instanceof \WC_Product ) {
			return $supports;
		}
		return ! self::needs_configuration( self::anchor_of( $product ) );
	}

	/**
	 * Point the add-to-cart URL of a bundle that has to be configured first at its product page.
	 *
	 * @param string      $url     Add-to-cart URL.
	 * @param \WC_Product $product Product the URL is for.
	 * @return string
	 */
	public function configure_first_url( $url, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::needs_configuration( self::anchor_of( $product ) ) ) {
			return $url;
		}
		return $product->get_permalink();
	}

	/**
	 * Label the archive button of a bundle chosen on its product page "Select options".
	 *
	 * @param string      $text    Button text.
	 * @param \WC_Product $product Product the button is for.
	 * @return string
	 */
	public function configure_first_text( $text, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::needs_configuration( self::anchor_of( $product ) ) ) {
			return $text;
		}
		return __( 'Select options', 'funnel-builder' );
	}

	/**
	 * Screen-reader description matching the "Select options" label.
	 *
	 * @param string      $text    Button description.
	 * @param \WC_Product $product Product the button is for.
	 * @return string
	 */
	public function configure_first_description( $text, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::needs_configuration( self::anchor_of( $product ) ) ) {
			return $text;
		}
		/* translators: %s: product name */
		return sprintf( __( 'Select options for &ldquo;%s&rdquo;', 'funnel-builder' ), wp_strip_all_tags( $product->get_name() ) );
	}

	/**
	 * The product a bundle targets: a variation's parent, otherwise the product itself.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return int
	 */
	private static function anchor_of( $product ) {
		return (int) ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
	}

	/**
	 * Emit the bundle's hidden field inside the add-to-cart form so the native
	 * submit carries it; an add-on adds its own offer fields beside it.
	 *
	 * @param \WC_Product|null $product The form's product, when the caller has one.
	 * @return void
	 */
	public function form_fields( $product = null ) {
		// No `global $product;`: it would rebind the parameter and discard a
		// caller-supplied product, and writing it would clobber the page's own.
		$prod = $product instanceof \WC_Product
			? $product
			: ( ( $GLOBALS['product'] ?? null ) instanceof \WC_Product ? $GLOBALS['product'] : wc_get_product( get_the_ID() ) );
		if ( ! $prod ) {
			return;
		}
		$bundle = Bundle_Store::active_for_product( $prod->get_id() );
		if ( ! $bundle ) {
			// A bought-together set rides the product's own button only with
			// JavaScript off; with it on, that button adds the product alone.
			$set = Bundle_Store::active_for_product( $prod->get_id(), Bundle_Store::SLOT_FBT );
			if ( $set ) {
				printf( '<noscript><input type="hidden" name="wffn_bundles_bundle_id" value="%s" /><input type="hidden" name="%s" value="1" />%s</noscript>', esc_attr( (string) ( $set['id'] ?? '' ) ), esc_attr( self::FIELD_NO_SCRIPT ), self::NO_SCRIPT_PICKERS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a constant stylesheet.
			}
			return;
		}
		printf( '<input type="hidden" name="wffn_bundles_bundle_id" value="%s" />', esc_attr( (string) ( $bundle['id'] ?? '' ) ) );
		// Posted only with JavaScript off: the widget is not there to build the selection.
		printf( '<noscript><input type="hidden" name="%s" value="1" />%s</noscript>', esc_attr( self::FIELD_NO_SCRIPT ), self::NO_SCRIPT_PICKERS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a constant stylesheet.

		/**
		 * Fires inside the add-to-cart form after the bundle's hidden field.
		 *
		 * An add-on prints its own hidden inputs here so the native submit
		 * carries them alongside `wffn_bundles_bundle_id`.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $bundle Resolved bundle row.
		 * @param \WC_Product $prod     The form's product.
		 */
		do_action( 'wffn_bundles_form_fields', $bundle, $prod );
	}

	/**
	 * Stamp every bundle line with the pricing it was granted at.
	 *
	 * Every add path (AJAX, native form, an add-on's own add_to_cart() call)
	 * funnels through this filter, so it is the one place that can stamp every
	 * bundle line regardless of who built `$data['wffn_bundles_bundle']`. An add-on that
	 * builds a line from the native form does so at an earlier priority.
	 *
	 * @param array $data         Cart item data being built.
	 * @param int   $product_id   Product being added.
	 * @param int   $variation_id Variation being added, if any.
	 * @return array
	 */
	public function add_cart_item_data( $data, $product_id = 0, $variation_id = 0 ) {
		self::stamp_pricing_fingerprint( $data, $variation_id ? $variation_id : $product_id );
		return $data;
	}

	/**
	 * Fingerprint of the parts of a bundle's discount config that set a price,
	 * so a line can tell whether its pricing is still what the bundle offers.
	 *
	 * @param array $bundle Bundle config.
	 * @return string
	 */
	public static function pricing_fingerprint( array $bundle ) {
		if ( ! is_array( $bundle['discountConfig'] ?? null ) ) {
			return '';
		}
		$priced = array();
		foreach ( $bundle['discountConfig'] as $key => $value ) {
			// Wording and design never change a price; a missed key only re-checks more often.
			if ( 'skin' === $key || preg_match( '/(Title|Subtitle|Heading|Subheading|Text|Label|Design|Layout|ShowRadio)$/', (string) $key ) ) {
				continue;
			}
			$priced[ $key ] = self::priced_value( $value );
		}
		$priced = self::priced_value( $priced );
		return md5( wp_json_encode( $priced ) );
	}

	/**
	 * Whether a config value is a hydrated product (an id with its name and shown price).
	 *
	 * @param array $value Config value.
	 * @return bool
	 */
	private static function is_product_shaped( array $value ) {
		return isset( $value['id'] ) && array_key_exists( 'name', $value ) && ( array_key_exists( 'price', $value ) || array_key_exists( 'priceRaw', $value ) );
	}

	/**
	 * A config value reduced to what sets a price, so saving a bundle unchanged
	 * (the editor adds row ids, empty lists and switched-off add-ons, and
	 * hydrates each product with its name, picture and shown price) never reads
	 * as a re-price. A product, alone or in a list (gift products, variations),
	 * keeps only its id; a row's own id, an empty value and a switched-off flag
	 * drop out; keys are sorted.
	 *
	 * @param mixed  $value  Config value.
	 * @param string $parent Key the value sits under.
	 * @return mixed
	 */
	private static function priced_value( $value, $parent = '' ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( 'product' === $parent || self::is_product_shaped( $value ) ) {
			return array( 'id' => (string) ( $value['id'] ?? '' ) );
		}
		// A list of products (a gift's products, a product's variations): each by its id.
		if ( in_array( $parent, array( 'products', 'variations' ), true ) ) {
			$ids = array();
			foreach ( $value as $item ) {
				$ids[] = is_array( $item ) ? (string) ( $item['id'] ?? '' ) : (string) $item;
			}
			return $ids;
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			if ( 'id' === $key && is_string( $key ) ) {
				continue;
			}
			$item = self::priced_value( $item, is_string( $key ) ? $key : '' );
			if ( null === $item || false === $item || '' === $item || array() === $item ) {
				continue;
			}
			$out[ $key ] = $item;
		}
		if ( array_keys( $out ) !== range( 0, count( $out ) - 1 ) ) {
			ksort( $out );
		}
		return $out;
	}

	/**
	 * Stamp the granting bundle's current fingerprint and the product's base
	 * price onto a bundle line, once, at add time. `drop_stale_bundles()` compares both.
	 *
	 * @param array $data       Cart item data being built.
	 * @param int   $product_id Product or variation the line sells.
	 * @return void
	 */
	private static function stamp_pricing_fingerprint( array &$data, $product_id = 0 ) {
		$bundle_id = (string) ( $data['wffn_bundles_bundle']['bundle'] ?? '' );
		if ( '' === $bundle_id ) {
			return;
		}
		if ( ! isset( $data['wffn_bundles_bundle']['pricingFingerprint'] ) ) {
			$bundle = Bundle_Store::get_config( $bundle_id );
			if ( $bundle ) {
				$data['wffn_bundles_bundle']['pricingFingerprint'] = self::pricing_fingerprint( $bundle );
			}
		}
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! isset( $data['wffn_bundles_bundle']['baseAt'] ) && $product instanceof \WC_Product ) {
			$data['wffn_bundles_bundle']['baseAt'] = Layer::raw_base_price( $product );
		}
	}

	/**
	 * Whether a line's product no longer costs what it did when it was added.
	 *
	 * Read from a fresh product: the cart's own object carries the price set_price() gave it.
	 *
	 * @param array $item Cart item carrying `wffn_bundles_bundle`.
	 * @return bool
	 */
	private static function base_price_changed( array $item ) {
		$b = (array) ( $item['wffn_bundles_bundle'] ?? array() );
		if ( ! isset( $b['baseAt'] ) ) {
			return false;
		}
		$id      = absint( $item['variation_id'] ?? 0 ) ? absint( $item['variation_id'] ) : absint( $item['product_id'] ?? 0 );
		$product = $id ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}
		return abs( Layer::raw_base_price( $product ) - (float) $b['baseAt'] ) > 0.00001;
	}

	/**
	 * The member rows for a fixed bundle, priced as the bundle prices it.
	 *
	 * Public for the same reason as tier_unit_members(): an Aero Checkout row has
	 * to build the pack the product page builds, and the pricing lives here.
	 *
	 * @param array $bundle Resolved bundle.
	 * @param array $picks    Per-member { attributes: { Color: 'Black', … } }, in
	 *                        the config's own member order. Empty for simple members.
	 * @return array[] Member rows, empty when the pack does not resolve.
	 */
	public static function fixed_bundle_members( array $bundle, array $picks = array() ) {
		$dc  = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$out = self::members_fixed_bundle( $dc, array( 'items' => $picks ) );

		return is_array( $out ) && '' === (string) ( $out['code'] ?? '' ) ? (array) ( $out['members'] ?? array() ) : array();
	}

	/**
	 * The member rows for one quantity tier bought as individual units.
	 *
	 * Public so an integration that sells a tier outside the product page can
	 * build the rows the product page builds. The add-on that renders the type
	 * prices them; without it there are none.
	 *
	 * @param array  $bundle  Resolved bundle.
	 * @param string $offer     Tier the row sells.
	 * @param int    $anchor_id Product the tier is priced from.
	 * @param array  $units     Rows of { attributes: { Color: 'Black', … } }.
	 * @return array[] Member rows, empty when the selection does not resolve.
	 */
	public static function tier_unit_members( array $bundle, $offer, $anchor_id, array $units ) {
		/**
		 * Filters the priced member rows for a tier bought as individual units.
		 *
		 * @since 1.0.0
		 *
		 * @param array[] $members   Member rows. Empty by default.
		 * @param array   $bundle  Resolved bundle.
		 * @param string  $offer     Tier the row sells.
		 * @param int     $anchor_id Product the tier is priced from.
		 * @param array   $units     Rows of { attributes }.
		 */
		return (array) apply_filters( 'wffn_bundles_tier_unit_members', array(), $bundle, (string) $offer, absint( $anchor_id ), $units );
	}

	/**
	 * The anchor datum for one quantity tier, or null when nothing prices it.
	 *
	 * @param array            $bundle Resolved bundle.
	 * @param string           $tier_id  Tier the buyer chose.
	 * @param \WC_Product|bool $priced   Product carrying the base unit price.
	 * @param int              $quantity Quantity being added.
	 * @return array|null
	 */
	public static function tier_bundle_data( array $bundle_config, $tier_id, $priced, $quantity = 1 ) {
		/**
		 * Filters the `wffn_bundles_bundle` datum a line selling one tier carries.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null       $bundle   Line datum. Null by default.
		 * @param array            $bundle Resolved bundle.
		 * @param string           $tier_id  Tier the buyer chose.
		 * @param \WC_Product|bool $priced   Product carrying the base unit price.
		 * @param int              $quantity Quantity being added.
		 */
		$bundle = apply_filters( 'wffn_bundles_tier_bundle_data', null, $bundle_config, (string) $tier_id, $priced, max( 1, (int) $quantity ) );

		return is_array( $bundle ) ? $bundle : null;
	}

	/**
	 * The product a bundle group hangs off: the posted id when the bundle is
	 * placed on it, else the bundle's first listed target.
	 *
	 * @param array      $bundle Resolved bundle.
	 * @param int|string $posted   Product id the client sent.
	 * @return int Product id, or 0 when the bundle lists none.
	 */
	public static function validated_anchor( array $bundle, $posted ) {
		$posted = absint( $posted );
		if ( $posted && Bundle_Store::targets_product( $bundle, (string) $posted ) && wc_get_product( $posted ) ) {
			return $posted;
		}
		$listed = is_array( $bundle['productSelection']['products'] ?? null ) ? $bundle['productSelection']['products'] : array();
		foreach ( $listed as $row ) {
			$id = absint( is_array( $row ) ? ( $row['id'] ?? 0 ) : 0 );
			if ( $id && Bundle_Store::targets_product( $bundle, (string) $id ) ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Add a validated multi-product selection to the cart as one grouped bundle.
	 *
	 * @return void
	 */
	public static function ajax_add() {
		Controller::verify();
		self::track_adds();
		self::assert_currency();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			Controller::fail( 'no_cart' );
		}
		$bundle_id = Controller::param( 'bundle' );
		$type        = Bundle_Store::type_id( Controller::param( 'type' ) );
		$offer       = Controller::param( 'offer' );
		$raw_json    = Controller::raw( 'selection' );
		$selection   = Controller::decode_json( $raw_json ) ?? array();

		$bundle = Bundle_Store::get_config( $bundle_id );
		if ( ! $bundle || (int) ( $bundle['discountType'] ?? 0 ) !== $type ) {
			Controller::fail( 'bad_bundle' );
		}

		// The client selection is never trusted: every id, offer and quantity is
		// re-checked against the stored bundle config before any price is computed.
		$payload_code = self::validate_payload( is_string( $raw_json ) ? $raw_json : '', $selection );
		if ( '' !== $payload_code ) {
			Controller::fail( $payload_code, 'payload_too_large' === $payload_code ? 413 : 400 );
		}
		if ( ! Bundle_Store::is_active_now( $bundle ) ) {
			Controller::fail( 'not_available' );
		}
		$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( ! self::valid_offer( $dc, $type, $offer ) ) {
			Controller::fail( 'bad_offer' );
		}
		// valid_offer() waves Fixed Bundle through (it has no offer list), so its
		// structural slots are the whitelist rather than a request value.
		// `fb-single` is served by ajax_add_single(); building members here would
		// return the whole pack under the single option's name.
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type && 'fb-single' === (string) $offer ) {
			Controller::fail( 'bad_offer' );
		}
		if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type && Bundle_Store::FBT_OFFER !== (string) $offer ) {
			Controller::fail( 'bad_offer' );
		}
		$offer_meta = ( Bundle_Store::TYPE_FIXED_BUNDLES === $type && 'fb-bundle' !== (string) $offer )
			? ''
			: (string) $offer;
		// The anchor is the product the pack is sold on.
		$anchor_id = self::validated_anchor( $bundle, $selection['product'] ?? 0 );

		// On any rule violation build_validated returns a machine code and the
		// request fails below on empty members.
		$built       = self::build_validated( $bundle, $type, $offer, $selection );
		$bundle_code = (string) $built['code'];
		// A variable member with no resolved variation cannot go in the cart, so it
		// does not count toward the bundle.
		$members = ( '' === $bundle_code )
			? array_values( array_filter( $built['members'], array( __CLASS__, 'member_addable' ) ) )
			: array();

		// A selection carrying only add-ons fails with the bundle's own reason: an
		// add-on cannot be bought alone.
		if ( empty( $members ) ) {
			if ( '' !== $bundle_code && '' !== (string) ( $built['detail'] ?? '' ) ) {
				Controller::fail_detail( $bundle_code, (string) $built['detail'] );
			}
			Controller::fail( '' !== $bundle_code ? $bundle_code : 'empty_selection' );
		}

		// Aggregated across the bundle members; each add-on stock-checks itself in
		// add_to_cart. A bundle failure sinks the whole request.
		$stock_code   = '';
		$stock_detail = '';
		if ( ! self::validate_stock( $members, $stock_code, $stock_detail ) ) {
			Controller::fail_detail( $stock_code, $stock_detail );
		}

		// The notices queued before any add, so a refused add can put them back
		// (fail_add()).
		$notices      = function_exists( 'wc_get_notices' ) ? wc_get_notices() : array();
		$group        = uniqid( 'wffn-bundles', false );
		$bundle_title = ( ! empty( $members ) && '' !== (string) ( $members[0]['label'] ?? '' ) ) ? (string) $members[0]['label'] : __( 'Bundle', 'funnel-builder' );
		$added        = 0;
		$offer_type   = self::offer_discount_type( $dc, $type, $offer );

		if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			// Each product is its own priced line; reprice_fbt() keeps the set's prices
			// right as lines leave. All-or-nothing on the way in, like a Fixed Bundle.
			$rule   = Frequently_Bought_Together::rule( $dc );
			$placed = array();
			$as_set = self::fbt_host_on( $selection );
			foreach ( $members as $m ) {
				// Without the viewed product it is no set: each product goes in on its own, at its own price.
				$key = self::validated_add_to_cart(
					$m['product_id'],
					$m['qty'],
					$m['variation_id'],
					$m['variation'],
					! $as_set ? array() : array(
						'wffn_bundles_bundle' => array(
							'bundle'     => $bundle_id,
							'type'         => $type,
							'group'        => $group,
							'role'         => $m['role'],
							'offer'        => Bundle_Store::FBT_OFFER,
							'label'        => $m['label'],
							'bundleTitle'  => $bundle_title,
							'baseQty'      => 1,
							'unit'         => $m['unit'],
							'pricedUnit'   => $m['pricedUnit'],
							'discountType' => $rule['mode'],
							'fbtRule'      => $rule,
						),
					)
				);
				if ( ! $key ) {
					foreach ( $placed as $placed_key => $placed_qty ) {
						// A plain line may have merged into one already in the cart; give that its quantity back.
						$left = (int) ( WC()->cart->get_cart_item( $placed_key )['quantity'] ?? 0 ) - $placed_qty;
						if ( $left > 0 ) {
							WC()->cart->set_quantity( $placed_key, $left, false );
						} else {
							WC()->cart->remove_cart_item( $placed_key );
						}
					}
					self::fail_add( 'out_of_stock', $notices );
				}
				$placed[ $key ] = ( $placed[ $key ] ?? 0 ) + (int) $m['qty'];
			}
			$added += count( $placed );
		} elseif ( Bundle_Store::TYPE_FIXED_BUNDLES === $type && count( $members ) > 1 ) {
			// Only Fixed Bundles group: a container line carries the whole price and
			// every member nests beneath it as a read-only child priced at 0.
			// Fail soft rather than create products on the public path.
			$container_id = self::container_id();
			if ( ! $container_id ) {
				wc_get_logger()->error( 'Bundle container product missing — re-run plugin activation/upgrade.', array( 'source' => 'wffn-bundles' ) );
				Controller::fail( 'add_failed' );
			}
			// No title set: name the pack after the product it is offered on.
			$anchor_product = '' === trim( (string) ( $dc['fixedBundleTitle'] ?? '' ) ) ? wc_get_product( $anchor_id ) : null;
			if ( $anchor_product instanceof \WC_Product ) {
				/* translators: %s: name of the product the bundle is offered on. */
				$bundle_title = sprintf( __( '%s - Bundle', 'funnel-builder' ), $anchor_product->get_name() );
			}
			$total = 0.0;
			foreach ( $members as $m ) {
				$total += (float) $m['pricedUnit'] * max( 1, (int) $m['qty'] );
			}
			$thumb = 0;
			foreach ( $members as $m ) {
				$thumb = $m['variation_id'] ? (int) $m['variation_id'] : (int) $m['product_id'];
				if ( $thumb ) {
					break;
				}
			}
			$thumb_meta = self::container_thumb( $dc, $anchor_id, $members );
			// A Fixed Bundle is all-or-nothing, so the write is atomic: every key is
			// collected and the whole group rolls back if any member fails to add.
			$group_keys    = array();
			$container_key = self::validated_add_to_cart(
				$container_id,
				1,
				0,
				array(),
				array(
					'wffn_bundles_bundle' => array(
						'bundle'    => $bundle_id,
						'type'        => $type,
						'group'       => $group,
						'role'        => 'container',
						'offer'       => $offer_meta,
						// The container is a shared placeholder, so this is the only line
						// that can name the product whose offers the group belongs to.
						'anchor'      => $anchor_id,
						'label'       => $bundle_title,
						'bundleTitle' => $bundle_title,
						'thumbId'     => $thumb,
						'thumbImgId'  => $thumb_meta['imgId'],
						'thumbSrc'    => $thumb_meta['src'],
						// The anchor's own image, recorded here because the order summary
						// asks for it on every later request, where loading the anchor
						// product to read one id costs three queries a line.
						'anchorImgId' => self::anchor_image_id( $anchor_id ),
						'unit'         => $total,
						'pricedUnit'   => $total,
						'discountType' => $offer_type,
					),
				)
			);
			if ( ! $container_key ) {
				self::fail_add( 'add_failed', $notices );
			}
			$group_keys[] = $container_key;
			foreach ( $members as $m ) {
				$key = self::validated_add_to_cart(
					$m['product_id'],
					$m['qty'],
					$m['variation_id'],
					$m['variation'],
					array(
						'wffn_bundles_bundle' => array(
							'bundle'    => $bundle_id,
							'type'        => $type,
							'group'       => $group,
							'role'        => 'child',
							'offer'       => $offer_meta,
							'label'       => $m['label'],
							'bundleTitle' => $bundle_title,
							'baseQty'     => max( 1, (int) $m['qty'] ),
							'unit'        => $m['unit'],
							'pricedUnit'  => 0.0,
							// The money sits on the container, but the child's discounted
							// base share is what price_container() rebuilds the total from.
							'pricedShare' => (float) $m['pricedUnit'],
						),
					)
				);
				if ( ! $key ) {
					// Undo the container + every child already placed, then fail —
					// the shopper never ends up with a broken half-bundle.
					foreach ( $group_keys as $placed_key ) {
						WC()->cart->remove_cart_item( $placed_key );
					}
					self::fail_add( 'out_of_stock', $notices );
				}
				$group_keys[] = $key;
			}
			$added += count( $group_keys );
		} else {
			/**
			 * Filters whether a type's members nest under a heading line in the cart.
			 *
			 * A heading is a zero-price line that names the group; every member keeps
			 * its own price and sits beneath it, read-only.
			 *
			 * @since 1.0.0
			 *
			 * @param bool    $nested   Whether to nest. False by default.
			 * @param array   $bundle The bundle config.
			 * @param int     $type     Bundle type id.
			 * @param array[] $members  The priced members about to be added.
			 */
			$nested = (bool) apply_filters( 'wffn_bundles_cart_nested_group', false, $bundle, $type, $members );
			$heading_key = '';
			if ( $nested ) {
				$container_id = self::container_id();
				if ( ! $container_id ) {
					wc_get_logger()->error( 'Bundle container product missing — re-run plugin activation/upgrade.', array( 'source' => 'wffn-bundles' ) );
					Controller::fail( 'add_failed' );
				}
				$thumb_meta  = self::heading_thumb( $dc, $anchor_id, $members, $offer_meta );
				$heading_key = self::validated_add_to_cart(
					$container_id,
					1,
					0,
					array(),
					array(
						'wffn_bundles_bundle' => array(
							'bundle'     => $bundle_id,
							'type'         => $type,
							'group'        => $group,
							'role'         => 'heading',
							'offer'        => $offer_meta,
							'anchor'       => $anchor_id,
							'label'        => $bundle_title,
							'bundleTitle'  => sprintf(
								/* translators: 1: bundle type name, 2: box size label. */
								__( '%1$s: %2$s', 'funnel-builder' ),
								Bundle_Store::type_label( $type ),
								$bundle_title
							),
							'thumbId'      => (int) ( $members[0]['variation_id'] ? $members[0]['variation_id'] : $members[0]['product_id'] ),
							'thumbImgId'   => $thumb_meta['imgId'],
							'thumbSrc'     => $thumb_meta['src'],
							'unit'         => 0.0,
							'pricedUnit'   => 0.0,
							'discountType' => $offer_type,
						),
					)
				);
				if ( ! $heading_key ) {
					self::fail_add( 'add_failed', $notices );
				}
			}
			// Ordinary cart lines, each carrying its own discounted price; they share
			// the bundle group so the set still removes together.
			foreach ( $members as $m ) {
				$member_data = array(
					'bundle'     => $bundle_id,
					'type'         => $type,
					'group'        => $group,
					'role'         => 'member',
					'offer'        => $offer_meta,
					'label'        => $m['label'],
					'bundleTitle'  => $bundle_title,
					// Fixed Bundle's single-product pack has nothing to re-price on a
					// quantity edit, unlike a quantity-priced 'member' line; see
					// granted_quantity()'s type check.
					'baseQty'      => max( 1, (int) $m['qty'] ),
					'unit'         => $m['unit'],
					'pricedUnit'   => $m['pricedUnit'],
					'discountType' => $offer_type,
				);
				if ( $nested ) {
					$member_data['nested'] = true;
				}
				if ( count( $members ) > 1 ) {
					$member_data['groupIds'] = self::member_ids( $members );
				}
				$ok = self::validated_add_to_cart(
					$m['product_id'],
					$m['qty'],
					$m['variation_id'],
					$m['variation'],
					array( 'wffn_bundles_bundle' => $member_data )
				);
				if ( $ok ) {
					++$added;
				}
			}
			// A heading with nothing beneath it is dropped rather than left dangling.
			if ( '' !== $heading_key && ! $added ) {
				WC()->cart->remove_cart_item( $heading_key );
			}
		}

		// The bundle itself must be in the cart before an add-on is placed: when
		// every member was refused, the add fails and no add-on goes in alone.
		if ( ! $added ) {
			self::fail_add( 'add_failed', $notices );
		}

		/**
		 * Filters the bundle-dependent add-on lines a selection has earned.
		 *
		 * Each row is placed as a standalone cart line sharing the bundle's group,
		 * so it removes with the bundle (see remove_group). Reached only once a
		 * real bundle is in the cart — an add-on never rides an invalid bundle.
		 *
		 * A row is shaped like a member row: { product_id, variation_id, variation,
		 * qty, unit, pricedUnit, role, offer, label }.
		 *
		 * @param array[] $members Add-on rows to place.
		 * @param array   $context { bundle, type, offer, selection }.
		 */
		$addons = Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ? array() : (array) apply_filters(
			'wffn_bundles_cart_addons',
			array(),
			array(
				'bundle'  => $bundle,
				'type'      => $type,
				'offer'     => $offer,
				'selection' => is_array( $selection ) ? $selection : array(),
			)
		);
		foreach ( $addons as $m ) {
			self::validated_add_to_cart(
				$m['product_id'],
				$m['qty'],
				$m['variation_id'],
				$m['variation'],
				array(
					'wffn_bundles_bundle' => array(
						'bundle'   => $bundle_id,
						'type'       => $type,
						'group'      => $group,
						'role'       => $m['role'],
						'offer'      => (string) ( $m['offer'] ?? '' ),
						'label'      => $m['label'],
						'baseQty'    => max( 1, (int) $m['qty'] ),
						'unit'       => $m['unit'],
						'pricedUnit' => $m['pricedUnit'],
						'discountType' => (string) ( $m['discountType'] ?? $offer_type ),
					),
				)
			);
		}

		self::respond_added();
	}

	/**
	 * Add the Fixed Bundle "Buy 1" option to the cart via AJAX, so it updates the
	 * cart in place rather than through a full-page form submit.
	 *
	 * The anchor at its regular price with no bundle discount, so pricing stays
	 * native and accepting a client product id is price-safe: the endpoint only
	 * resolves a variation and enforces the bundle path's stock gate.
	 *
	 * @return void
	 */
	public static function ajax_add_single() {
		Controller::verify();
		self::track_adds();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			Controller::fail( 'no_cart' );
		}
		$product_id = absint( Controller::param( 'product', 0 ) );
		$quantity   = max( 1, absint( Controller::param( 'quantity', 1 ) ) );
		$attributes = array();
		foreach ( Controller::json( 'attributes' ) ?? array() as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$attributes[ (string) $k ] = (string) $v;
			}
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! $product->is_purchasable() ) {
			Controller::fail( 'invalid_selection' );
		}

		// A chosen-but-incomplete pick must not fall back to a default the shopper
		// never selected; an empty pick keeps the default so it stays addable.
		$variation_id = 0;
		$variation    = array();
		if ( $product->is_type( 'variable' ) ) {
			if ( ! empty( $attributes ) ) {
				list( $variation_id, $variation ) = self::resolve_variation( $product_id, $attributes );
			} else {
				list( $variation_id, $variation ) = self::default_variation( $product );
			}
			if ( ! $variation_id ) {
				Controller::fail( 'invalid_selection' );
			}
		}

		// Reused so the single pick and the bundle enforce stock identically.
		$members      = array( array( 'product_id' => $product_id, 'variation_id' => $variation_id, 'qty' => $quantity ) );
		$stock_code   = '';
		$stock_detail = '';
		if ( ! self::validate_stock( $members, $stock_code, $stock_detail ) ) {
			Controller::fail_detail( $stock_code, $stock_detail );
		}

		// A qualifying purchase, so an add-on rides along through the same seam the
		// bundle path uses, on a selection carrying only the anchor.
		$bundle_id = Controller::param( 'bundle' );
		$bundle    = '' !== $bundle_id ? Bundle_Store::get_config( $bundle_id ) : null;
		$raw_extras  = Controller::raw( 'selection' );
		$posted      = Controller::decode_json( $raw_extras ) ?? array();
		$extras_code = self::validate_payload( $raw_extras, $posted );
		if ( '' !== $extras_code ) {
			Controller::fail( $extras_code );
		}

		$addons = is_array( $bundle )
			? (array) apply_filters(
				'wffn_bundles_cart_addons',
				array(),
				array(
					'bundle'  => $bundle,
					'type'      => Bundle_Store::TYPE_FIXED_BUNDLES,
					'offer'     => 'fb-single',
					'selection' => array_merge( $posted, array( 'product' => $product_id ) ),
				)
			)
			: array();

		$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices() : array();
		// No add-ons: the anchor goes in as a plain native cart line.
		if ( empty( $addons ) ) {
			if ( false === self::validated_add_to_cart( $product_id, $quantity, $variation_id, $variation ) ) {
				self::fail_add( 'add_failed', $notices );
			}
			self::respond_added();
		}

		// Grouping only, no discount: `pricedUnit` is the anchor's own price.
		// `bundle` stays empty, so only the add-ons are attributed.
		$anchor = wc_get_product( $variation_id ? $variation_id : $product_id );
		$unit   = $anchor ? Layer::raw_base_price( $anchor ) : 0.0;
		$group  = uniqid( 'wffn-bundles', false );

		$anchor_key = self::validated_add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$variation,
			array(
				'wffn_bundles_bundle' => array(
					'bundle'   => '',
					'type'       => Bundle_Store::TYPE_FIXED_BUNDLES,
					'group'      => $group,
					'role'       => 'single',
					'unit'       => $unit,
					'pricedUnit' => $unit,
				),
			)
		);
		if ( ! $anchor_key ) {
			self::fail_add( 'add_failed', $notices );
		}
		foreach ( $addons as $m ) {
			self::validated_add_to_cart(
				$m['product_id'],
				$m['qty'],
				$m['variation_id'],
				$m['variation'],
				array(
					'wffn_bundles_bundle' => array(
						'bundle'   => $bundle_id,
						'type'       => Bundle_Store::TYPE_FIXED_BUNDLES,
						'group'      => $group,
						'role'       => $m['role'],
						'offer'      => (string) ( $m['offer'] ?? '' ),
						'label'      => $m['label'],
						'baseQty'    => max( 1, (int) $m['qty'] ),
						'unit'       => $m['unit'],
						'pricedUnit' => $m['pricedUnit'],
					),
				)
			);
		}
		self::respond_added();
	}

	/** What this request added, as product id => quantity, for the cart notice. */
	private static $added_lines = array();

	/** The cart keys this request added to or raised. */
	private static $added_keys = array();

	/**
	 * Start recording what an add puts in the cart.
	 *
	 * `woocommerce_add_to_cart` fires once per line, including the ones our own
	 * group adds behind the shopper's click.
	 *
	 * @return void
	 */
	private static function track_adds() {
		self::$added_lines = array();
		self::$added_keys  = array();
		add_action(
			'woocommerce_add_to_cart',
			function ( $key, $product_id, $quantity, $variation_id ) {
				self::$added_keys[] = (string) $key;
				// The hidden container placeholder is not a product the shopper picked.
				if ( self::container_id() === (int) $product_id ) {
					return;
				}
				$id                       = $variation_id ? (int) $variation_id : (int) $product_id;
				self::$added_lines[ $id ] = ( self::$added_lines[ $id ] ?? 0 ) + max( 1, (int) $quantity );
			},
			10,
			4
		);
	}

	/**
	 * Queue WooCommerce's own "added to cart" notice and render it.
	 *
	 * The add is posted over fetch, which leaves WooCommerce no chance to print
	 * its own confirmation.
	 *
	 * @return string Rendered notice HTML, '' when there is nothing to say.
	 */
	private static function queue_added_notice() {
		if ( empty( self::$added_lines ) || ! function_exists( 'wc_add_to_cart_message' ) ) {
			return '';
		}
		wc_add_to_cart_message( self::$added_lines, true );
		self::$added_lines = array();

		return function_exists( 'wc_print_notices' ) ? (string) wc_print_notices( true ) : '';
	}

	/**
	 * Swap out the bundle a shopper reopened from the cart, now that its replacement is in.
	 *
	 * The cart drawer's "Select options" on a bundle's lead line reopens the bundle and posts
	 * the line's key with the new pick. The old group leaves only after the new one landed,
	 * so a pick that fails never costs the shopper what they had.
	 *
	 * @return void
	 */
	private static function replace_edited_group() {
		$key = Controller::param( 'replace', '' );
		if ( '' === $key || ! WC()->cart ) {
			return;
		}
		$old = WC()->cart->get_cart_item( $key );
		if ( empty( $old ) ) {
			return;
		}
		$group = (string) ( $old['wffn_bundles_bundle']['group'] ?? '' );
		// A plain line (no bundle data) is replaced on its own, unless the add merged into it.
		if ( '' === $group ) {
			if ( ! in_array( (string) $key, self::$added_keys, true ) ) {
				WC()->cart->remove_cart_item( $key );
			}
			return;
		}
		foreach ( array_keys( WC()->cart->get_cart() ) as $k ) {
			$line = WC()->cart->get_cart_item( $k );
			if ( (string) ( $line['wffn_bundles_bundle']['group'] ?? '' ) === $group && ! in_array( (string) $k, self::$added_keys, true ) ) {
				WC()->cart->remove_cart_item( $k );
			}
		}
	}

	/**
	 * Emit WooCommerce's standard add-to-cart success response: redirect to the
	 * cart when the store opts into it, otherwise refreshed fragments so the
	 * mini-cart updates in place. Shared by the bundle and single-add paths.
	 *
	 * @return void
	 */
	private static function respond_added() {
		self::replace_edited_group();
		$notices = self::queue_added_notice();
		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			// The notice stays in the session, so the cart page shows it on arrival.
			wp_send_json_success( array( 'redirect' => wc_get_cart_url() ) );
		}
		$payload = array(
			'fragments'  => self::cart_fragments(),
			'cart_hash'  => WC()->cart->get_cart_hash(),
			'cart_count' => WC()->cart->get_cart_contents_count(),
			'notices'    => $notices,
		);
		/**
		 * Filters the add-to-cart success payload.
		 *
		 * An add-on that was earned but could not be placed answers here, so the
		 * widget can say why rather than letting a promised line vanish.
		 *
		 * @param array $payload The response body.
		 */
		wp_send_json_success( apply_filters( 'wffn_bundles_cart_add_response', $payload ) );
	}

	/**
	 * The cart fragments an add-to-cart response carries, built the way
	 * `WC_AJAX::get_refreshed_fragments()` builds them.
	 *
	 * The mini-cart body is seeded before the filter runs:
	 * `woocommerce_add_to_cart_fragments` collects what other code wants to add,
	 * it does not produce `div.widget_shopping_cart_content` itself, so filtering
	 * an empty array returns no cart contents and a slide-out drawer stays empty.
	 *
	 * Guarded because `woocommerce_mini_cart()` is a template function.
	 *
	 * @return array
	 */
	private static function cart_fragments() {
		if ( ! function_exists( 'woocommerce_mini_cart' ) ) {
			return apply_filters( 'woocommerce_add_to_cart_fragments', array() );
		}
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		return apply_filters(
			'woocommerce_add_to_cart_fragments',
			array(
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
			)
		);
	}

	/**
	 * Price the current selection for live display, reusing the cart's own
	 * products, discounts and variation resolution so the quote always equals what
	 * the customer pays. A variable member with no chosen variation contributes a
	 * range, so the total is a range until every variation is picked. The response
	 * is ready-to-inject `wc_price` markup.
	 *
	 * @return void
	 */
	public static function ajax_quote() {
		Controller::verify();
		self::assert_currency();
		$bundle_id = Controller::param( 'bundle' );
		$type        = Bundle_Store::type_id( Controller::param( 'type' ) );
		$offer       = Controller::param( 'offer' );
		$raw_json    = Controller::raw( 'selection' );
		$selection   = Controller::decode_json( $raw_json ) ?? array();
		if ( '' !== self::validate_payload( $raw_json, $selection ) ) {
			wp_send_json_success( array( 'hasItems' => false ) );
		}

		$bundle = Bundle_Store::get_config( $bundle_id );
		if ( ! $bundle || (int) ( $bundle['discountType'] ?? 0 ) !== $type ) {
			wp_send_json_success( array( 'hasItems' => false ) );
		}
		$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();

		// The same gate as the add path, but soft-failing: an ineligible bundle or
		// a forged offer answers {hasItems:false} rather than an error.
		if ( ! Bundle_Store::is_active_now( $bundle ) || ! self::valid_offer( $dc, $type, $offer ) ) {
			wp_send_json_success( array( 'hasItems' => false ) );
		}

		if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			$fbt = self::quote_fbt( $bundle, $dc, $selection );
			if ( null === $fbt ) {
				wp_send_json_success( array( 'hasItems' => false ) );
			}
			/** This filter is documented in includes/frontend/cart.php */
			$response = apply_filters(
				'wffn_bundles_quote_response',
				array(
					'hasItems' => true,
					'fbt'      => $fbt['quote'],
				),
				array(
					'bundle'  => $bundle,
					'type'      => $type,
					'offer'     => $offer,
					'selection' => $selection,
					'lines'     => $fbt['lines'],
					'totals'    => $fbt['totals'],
				)
			);
			wp_send_json_success( $response );
		}

		// Each group is a set of {min,max,qty} rows priced by one discount spec.
		$groups  = array();
		$members = array();
		$tiers   = array();
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			$fb = self::quote_fixed_bundle( $bundle, $dc, $offer, $selection );
			if ( is_array( $fb ) ) {
				// Separate-products scope prices each member by its own discount, so it
				// quotes as one single-row group per member.
				foreach ( isset( $fb['groups'] ) ? $fb['groups'] : array( $fb ) as $g ) {
					$groups[] = $g;
				}
				// Per-member price HTML, so a member line collapses to the exact price
				// the moment its variation is picked.
				if ( isset( $fb['members'] ) ) {
					$members = $fb['members'];
				}
			}
		} else {
			/**
			 * Filters a bundle type this plugin does not price into the quote.
			 *
			 * `groups` price the box; `members` and `tiers` carry the per-tile and
			 * per-offer prices the widget shows without one.
			 *
			 * @since 1.0.0
			 *
			 * @param array $quote   { groups, members, tiers }. Empty by default.
			 * @param array $context { bundle, type, offer, selection }.
			 */
			$quote = (array) apply_filters(
				'wffn_bundles_quote_bundle',
				array(),
				array(
					'bundle'  => $bundle,
					'type'      => $type,
					'offer'     => $offer,
					'selection' => $selection,
				)
			);
			$groups  = array_values( (array) ( $quote['groups'] ?? array() ) );
			$members = (array) ( $quote['members'] ?? array() );
			$tiers   = (array) ( $quote['tiers'] ?? array() );
		}
		/**
		 * Filters the priced groups the quote sums.
		 *
		 * An add-on priced apart from the bundle appends a group flagged `addon`,
		 * which keeps its money out of the `bundleMin`/`bundleMax` totals below.
		 *
		 * @param array[] $groups  Groups of { rows, discount, ... }.
		 * @param array   $context { bundle, type, offer, selection }.
		 */
		$groups = (array) apply_filters(
			'wffn_bundles_quote_groups',
			$groups,
			array(
				'bundle'  => $bundle,
				'type'      => $type,
				'offer'     => $offer,
				'selection' => $selection,
			)
		);
		$groups = array_filter( $groups );
		$priced = array_filter(
			$groups,
			static function ( $g ) {
				return empty( $g['addon'] ) && ! empty( $g['rows'] );
			}
		);
		// Add-ons alone are not a price: they cannot be bought without the bundle.
		if ( empty( $priced ) ) {
			// No box total, but the tiles and the per-tier cards still carry prices
			// that do not depend on the box being priceable.
			wp_send_json_success( array( 'hasItems' => false, 'members' => $members, 'tiers' => $tiers ) );
		}

		$dp         = self::base_decimals();
		$orig_min   = 0.0;
		$orig_max   = 0.0;
		$final_min  = 0.0;
		$final_max  = 0.0;
		// Tracked apart from the bundle's own money, for a consumer pricing the
		// bundle alone.
		$addon_min  = 0.0;
		$addon_max  = 0.0;
		$shown      = array( 0.0, 0.0, 0.0, 0.0 );
		$own_shown  = array( 0.0, 0.0, 0.0, 0.0 );
		$quote_lines = array();
		foreach ( $groups as $g ) {
			list( $o_min, $o_max, $f_min, $f_max, $per_line ) = self::price_range( $g['rows'], $g['discount'], ! empty( $g['lines'] ) );
			// Only a group that named its anchor folds; the rest have no single
			// product to ask the engine about.
			if ( ! empty( $g['foldProduct'] ) ) {
				$f_min = Pricing::fold_engine( $f_min, $o_min, $g['foldQty'] ?? 1, $g['foldProduct'] );
				$f_max = Pricing::fold_engine( $f_max, $o_max, $g['foldQty'] ?? 1, $g['foldProduct'] );
			}
			$orig_min  += $o_min;
			$orig_max  += $o_max;
			$final_min += $f_min;
			$final_max += $f_max;
			// As the cart converts each line: unit first, then multiplied.
			foreach ( array( $o_min, $o_max, $f_min, $f_max ) as $i => $amount ) {
				$line = ( null === $per_line || ! empty( $g['foldProduct'] ) )
					? Pricing::convert_line( $amount, $g['foldQty'] ?? 1 )
					: $per_line[ $i ];
				$shown[ $i ] += $line;
				if ( empty( $g['addon'] ) ) {
					$own_shown[ $i ] += $line;
				}
			}
			if ( ! empty( $g['addon'] ) ) {
				$addon_min += $f_min;
				$addon_max += $f_max;
				continue;
			}
			foreach ( self::priced_rows( $g ) as $row ) {
				$quote_lines[] = $row;
			}
		}

		$is_range     = round( $final_min, $dp ) !== round( $final_max, $dp );
		$has_discount = round( $final_max, $dp ) < round( $orig_max, $dp ) || round( $final_min, $dp ) < round( $orig_min, $dp );

		$response = array(
			'hasItems'    => true,
			'isRange'     => $is_range,
			'hasDiscount' => $has_discount,
			'html'        => self::quote_html( $shown[0], $shown[1], $shown[2], $shown[3], $is_range, $has_discount ),
			// The bundle's own price, add-ons left out, for a card that prices the bundle alone.
			'bundleHtml'  => self::quote_html(
				$own_shown[0],
				$own_shown[1],
				$own_shown[2],
				$own_shown[3],
				round( $own_shown[2], $dp ) !== round( $own_shown[3], $dp ),
				round( $own_shown[3], $dp ) < round( $own_shown[1], $dp ) || round( $own_shown[2], $dp ) < round( $own_shown[0], $dp )
			),
			'members'     => $members,
			'tiers'       => $tiers,
		);

		/**
		 * Filters the live quote before it is sent.
		 *
		 * An integration that prices the same selection its own way answers here.
		 * `$context` carries the base-currency totals the response only ships as
		 * HTML.
		 *
		 * @param array $response The quote payload.
		 * @param array $context  { bundle, type, offer, selection, totals }.
		 */
		wp_send_json_success(
			apply_filters(
				'wffn_bundles_quote_response',
				$response,
				array(
					'bundle'  => $bundle,
					'type'      => $type,
					'offer'     => $offer,
					'selection' => $selection,
					// Every non-add-on line at its discounted total, for a consumer that
					// composes per cart line rather than over the box.
					'lines'     => $quote_lines,
					'totals'    => array(
						'origMin'    => $orig_min,
						'origMax'    => $orig_max,
						'finalMin'   => $final_min,
						'finalMax'   => $final_max,
						// The bundle without its add-on lines.
						'bundleMin'  => $final_min - $addon_min,
						'bundleMax'  => $final_max - $addon_max,
					),
				)
			)
		);
	}

	/**
	 * Price HTML for one base-currency amount — the quote's conversion boundary,
	 * the markup twin of Skins\Pricing::format().
	 */
	private static function quote_money( $amount ) {
		return self::quote_display_money( Layer::convert( (float) $amount ) );
	}

	/** Price HTML for an amount already in display currency. */
	private static function quote_display_money( $display ) {
		return function_exists( 'wc_price' )
			? Layer::format( (float) $display )
			: '<span class="woocommerce-Price-amount amount">' . number_format( (float) $display, 2 ) . '</span>';
	}

	/** A single member line's price HTML: a min–max range when the ends differ, else one price. Base in. */
	private static function member_price_html( $lo, $hi ) {
		$money = array( __CLASS__, 'quote_money' );
		$dash  = '<span class="wffn-bundles-price-dash"> &ndash; </span>';
		return $hi > $lo ? $money( $lo ) . $dash . $money( $hi ) : $money( $lo );
	}

	/** Compose the price HTML: a strike+final for a single total, a min–max range otherwise. Display currency in. */
	private static function quote_html( $orig_min, $orig_max, $final_min, $final_max, $is_range, $has_discount ) {
		$money = array( __CLASS__, 'quote_display_money' );
		$dash  = '<span class="wffn-bundles-price-dash"> &ndash; </span>';
		if ( $is_range ) {
			$final = $money( $final_min ) . $dash . $money( $final_max );
			return '<span class="wffn-bundles-live-price"><ins>' . $final . '</ins></span>';
		}
		if ( $has_discount ) {
			return '<span class="wffn-bundles-live-price"><del aria-hidden="true">' . $money( $orig_max ) . '</del> <ins>' . $money( $final_max ) . '</ins></span>';
		}
		return '<span class="wffn-bundles-live-price">' . $money( $final_max ) . '</span>';
	}

	/**
	 * A variable member with no chosen variation prices as a [min,max] range; else
	 * [unit,unit]. Base currency both ends.
	 */
	public static function unit_range( $product_id, $attributes ) {
		static $memo = array();
		$attr_key  = is_array( $attributes ) ? $attributes : array();
		ksort( $attr_key );
		$cache_key = $product_id . '|' . wp_json_encode( $attr_key );
		if ( isset( $memo[ $cache_key ] ) ) {
			return $memo[ $cache_key ];
		}

		list( $vid ) = self::resolve_variation( $product_id, $attr_key );
		if ( $vid ) {
			$p = wc_get_product( $vid );
			$x = $p ? Layer::get_base_price( $p ) : 0.0;
			$memo[ $cache_key ] = array( $x, $x );
			return $memo[ $cache_key ];
		}
		$p = wc_get_product( $product_id );
		if ( ! $p ) {
			$memo[ $cache_key ] = array( 0.0, 0.0 );
			return $memo[ $cache_key ];
		}
		if ( $p->is_type( 'variable' ) ) {
			list( $min, $max ) = Layer::get_base_variation_range( $p );
			if ( $max > 0 ) {
				$memo[ $cache_key ] = array( $min, $max );
				return $memo[ $cache_key ];
			}
		}
		$x = Layer::get_base_price( $p );
		$memo[ $cache_key ] = array( $x, $x );
		return $memo[ $cache_key ];
	}

	/**
	 * The chosen variation's own image, or '' when it has none.
	 *
	 * Deliberately never falls back to the parent: a member row ships with the
	 * image the bundle stored, which a merchant may have overridden per product,
	 * and answering with the parent's would overwrite that override on every quote.
	 * '' means "leave the row as rendered". get_image_id() is read in `edit`
	 * context because the `view` default already falls back to the parent.
	 *
	 * @param int    $product_id Parent product.
	 * @param array  $attributes Chosen attributes.
	 * @param string $size       Image size name; anything unregistered loads the thumbnail.
	 * @return string Image URL, or ''.
	 */
	public static function variation_image( $product_id, $attributes, $size = 'thumbnail' ) {
		list( $vid ) = self::resolve_variation( $product_id, is_array( $attributes ) ? $attributes : array() );
		if ( ! $vid ) {
			return '';
		}
		$variation = wc_get_product( $vid );
		if ( ! $variation ) {
			return '';
		}
		$image_id = (int) $variation->get_image_id( 'edit' );

		return $image_id ? (string) wp_get_attachment_image_url( $image_id, WooCommerce::image_size( $size ) ) : '';
	}

	/**
	 * One group's rows at their discounted line totals, dear end.
	 *
	 * price_range() folds the same arithmetic into one figure; a consumer that
	 * composes per cart line needs the rows kept apart.
	 *
	 * @param array $group A priced group: { rows, discount }.
	 * @return array[] Rows of { product, qty, final }.
	 */
	private static function priced_rows( array $group ) {
		$rows  = isset( $group['rows'] ) && is_array( $group['rows'] ) ? $group['rows'] : array();
		$orig  = 0.0;
		foreach ( $rows as $r ) {
			$orig += (float) $r['max'] * (int) $r['qty'];
		}
		$ratio = self::discount_ratio( $orig, (string) ( $group['discount']['type'] ?? 'no_discount' ), (float) ( $group['discount']['value'] ?? 0 ) );

		$out = array();
		foreach ( $rows as $r ) {
			$row_max = (float) $r['max'] * (int) $r['qty'];
			$product = isset( $r['product'] ) ? $r['product'] : null;
			$out[]   = array(
				'product' => $product,
				'qty'     => max( 1, (int) $r['qty'] ),
				'final'   => Pricing::fold_engine( $row_max * $ratio, $row_max, (int) $r['qty'], $product ),
			);
		}
		return $out;
	}

	/**
	 * Apply a discount spec to a set of {min,max,qty} rows.
	 *
	 * @param array $rows     Rows of { min, max, qty, product, line }.
	 * @param array $discount { type, value }.
	 * @param bool  $per_line Rows become separate cart lines: round each line, as the cart does.
	 * @return array [ origMin, origMax, finalMin, finalMax, shown ]; `shown` is the same four in
	 *               display currency, converted per cart line, or null when the rows are not lines.
	 */
	public static function price_range( $rows, $discount, $per_line = false ) {
		$orig_min = 0.0;
		$orig_max = 0.0;
		foreach ( $rows as $r ) {
			$orig_min += (float) $r['min'] * (int) $r['qty'];
			$orig_max += (float) $r['max'] * (int) $r['qty'];
		}
		$type = (string) ( $discount['type'] ?? 'no_discount' );
		$val  = (float) ( $discount['value'] ?? 0 );
		// Off the whole group, so a fixed amount spreads across its rows.
		$ratio_min = self::discount_ratio( $orig_min, $type, $val );
		$ratio_max = self::discount_ratio( $orig_max, $type, $val );

		$final_min = 0.0;
		$final_max = 0.0;
		$lines     = array();
		foreach ( $rows as $i => $r ) {
			$row_min    = (float) $r['min'] * (int) $r['qty'];
			$row_max    = (float) $r['max'] * (int) $r['qty'];
			// A row naming its product is its own cart line, so the engine is asked
			// per row; a row without one leaves the arithmetic untouched.
			$product    = isset( $r['product'] ) ? $r['product'] : null;
			$folded_min = Pricing::fold_engine( $row_min * $ratio_min, $row_min, (int) $r['qty'], $product );
			// No spread: the max fold would repeat identical engine work for the same result.
			$folded_max = ( $row_min === $row_max && $ratio_min === $ratio_max )
				? $folded_min
				: Pricing::fold_engine( $row_max * $ratio_max, $row_max, (int) $r['qty'], $product );
			$final_min += $folded_min;
			$final_max += $folded_max;
			// Rows sharing a `line` key are merged into one cart line.
			$key = (string) ( $r['line'] ?? $i );
			$lines[ $key ]     = $lines[ $key ] ?? array( 0.0, 0.0, 0.0, 0.0, 0 );
			$lines[ $key ][0] += $folded_min;
			$lines[ $key ][1] += $folded_max;
			$lines[ $key ][2] += $row_min;
			$lines[ $key ][3] += $row_max;
			$lines[ $key ][4] += (int) $r['qty'];
		}
		$shown = null;
		if ( $per_line ) {
			$dp        = self::base_decimals();
			$final_min = 0.0;
			$final_max = 0.0;
			$shown     = array( 0.0, 0.0, 0.0, 0.0 );
			// The cart settles each group's rounding cent on its largest line; the quote does the same.
			foreach ( array( 0, 1 ) as $end ) {
				$settle = array();
				foreach ( $lines as $k => $line ) {
					$settle[] = array(
						'key'  => $k,
						'qty'  => $line[4],
						'base' => round( $line[ $end ], $dp ),
						'raw'  => $line[ $end ],
					);
				}
				foreach ( self::settle_base_cents( array( $settle ), $dp ) as $moved ) {
					$lines[ $moved['key'] ][ $end ] = $moved['base'];
				}
			}
			foreach ( $lines as $line ) {
				$final_min += round( $line[0], $dp );
				$final_max += round( $line[1], $dp );
				// Each line converted with its own unit count, as the cart converts it.
				$shown[0] += Pricing::convert_line( $line[2], $line[4] );
				$shown[1] += Pricing::convert_line( $line[3], $line[4] );
				$shown[2] += Pricing::convert_line( $line[0], $line[4] );
				$shown[3] += Pricing::convert_line( $line[1], $line[4] );
			}
		}
		return array( $orig_min, $orig_max, $final_min, $final_max, $shown );
	}

	/**
	 * Fixed Bundle quote group: the single page product, or the configured members
	 * with the bundle discount.
	 *
	 * @param array  $bundle  Resolved bundle.
	 * @param array  $dc        Bundle discount config.
	 * @param string $offer     Offer being quoted.
	 * @param array  $selection Client selection.
	 * @return array|null
	 */
	private static function quote_fixed_bundle( $bundle, $dc, $offer, $selection ) {
		if ( 'single' === (string) $offer ) {
			$anchor = absint( $selection['anchor'] ?? 0 );
			if ( ! $anchor ) {
				return null;
			}
			// No forged anchor can be quoted either; mirrors the add path's own
			// targets_product() gate.
			if ( ! Bundle_Store::targets_product( is_array( $bundle ) ? $bundle : array(), (string) $anchor ) ) {
				return null;
			}
			list( $min, $max ) = self::unit_range( $anchor, is_array( $selection['attributes'] ?? null ) ? $selection['attributes'] : array() );
			$single_product    = wc_get_product( $anchor );
			$single_product    = $single_product ? $single_product : null;
			// The anchor as its own member, so its card re-prices from the payload the
			// bundle's member lines use rather than keeping its stamped parent range.
			$original = self::member_price_html( $min, $max );
			$final    = self::member_price_html(
				Pricing::fold_engine( $min, $min, 1, $single_product ),
				Pricing::fold_engine( $max, $max, 1, $single_product )
			);
			return array(
				'rows'     => array( array( 'min' => $min, 'max' => $max, 'qty' => 1, 'product' => $single_product ) ),
				'discount' => array( 'type' => 'no_discount', 'value' => 0 ),
				'members'  => array(
					array(
						'final'    => $final,
						'original' => $original,
						// The single card can carry its own Offer Images artwork.
						'image'    => Product_Display::has_offer_image( $dc, $anchor, 'fb-single' )
							? ''
							: self::variation_image( $anchor, is_array( $selection['attributes'] ?? null ) ? $selection['attributes'] : array(), $dc['bundleDesign']['image_size'] ?? '' ),
					),
				),
			);
		}
		// A variable member with no chosen variation shows a price range; once the
		// shopper picks one it collapses to that exact price.
		$picks    = is_array( $selection['items'] ?? null ) ? array_values( $selection['items'] ) : array();
		$scope    = (string) ( $dc['fixedBundleDiscountScope'] ?? 'whole_bundle' );
		$separate = ( 'separate_products' === $scope );
		// Two or more members are charged on a container line no engine rule can
		// price, so only a one-member pack folds. Mirrors Skins\Fixed_Bundle.
		$solo     = count( (array) ( $dc['fixedBundleItems'] ?? array() ) ) < 2;
		$rows     = array();
		$members  = array();
		$groups   = array();
		$member_ids = array();
		foreach ( (array) ( $dc['fixedBundleItems'] ?? array() ) as $it ) {
			$member_ids[] = is_array( $it ) && is_array( $it['product'] ?? null ) ? ( $it['product']['id'] ?? 0 ) : 0;
		}
		// A pack of two or more is priced on the shared container, which this quote
		// reads moments later; named here it rides the members' batch.
		if ( ! $solo ) {
			$member_ids[] = self::container_id();
		}
		Repository::prime_posts( $member_ids );
		foreach ( array_values( (array) ( $dc['fixedBundleItems'] ?? array() ) ) as $i => $it ) {
			$p = is_array( $it['product'] ?? null ) ? $it['product'] : null;
			if ( empty( $p['id'] ) ) {
				continue;
			}
			$qty               = max( 1, (int) ( $it['quantity'] ?? 1 ) );
			$attrs             = is_array( $picks[ $i ]['attributes'] ?? null ) ? $picks[ $i ]['attributes'] : array();
			list( $min, $max ) = self::unit_range( absint( $p['id'] ), $attrs );
			// Only a one-member separate-scope pack is its own priced cart line.
			$member_product    = ( $separate && $solo ) ? wc_get_product( absint( $p['id'] ) ) : null;
			$member_product    = $member_product ? $member_product : null;
			$row               = array( 'min' => $min, 'max' => $max, 'qty' => $qty, 'product' => $member_product );
			$rows[]            = $row;

			// Mirrors the stamped view-model: separate scope shows each member's own
			// discount, whole-bundle shows the plain line.
			$orig_min  = $min * $qty;
			$orig_max  = $max * $qty;
			$final_min = $orig_min;
			$final_max = $orig_max;
			if ( $separate ) {
				$dtype      = (string) ( $it['discountType'] ?? 'percentage' );
				$dval       = (float) ( $it['discountValue'] ?? 0 );
				$final_min *= self::discount_ratio( $orig_min, $dtype, $dval );
				$final_max *= self::discount_ratio( $orig_max, $dtype, $dval );
				$final_min  = Pricing::fold_engine( $final_min, $orig_min, $qty, $member_product );
				$final_max  = Pricing::fold_engine( $final_max, $orig_max, $qty, $member_product );
				// price_range() applies one discount spec per group, so separate scope
				// needs a single-row group per member or the total stays at full price.
				$groups[] = array( 'rows' => array( $row ), 'discount' => array( 'type' => $dtype, 'value' => $dval ) );
			}
			$members[] = array(
				'final'    => self::member_price_html( $final_min, $final_max ),
				'original' => self::member_price_html( $orig_min, $orig_max ),
				'image'    => self::variation_image( absint( $p['id'] ), $attrs, $dc['bundleDesign']['image_size'] ?? '' ),
			);
		}
		if ( empty( $rows ) ) {
			return null;
		}
		$discount = $separate
			? array( 'type' => 'no_discount', 'value' => 0 )
			: array( 'type' => (string) ( $dc['fixedBundleDiscountType'] ?? 'percentage' ), 'value' => (float) ( $dc['fixedBundleDiscountValue'] ?? 0 ) );
		$out = array( 'rows' => $rows, 'discount' => $discount, 'members' => $members );
		if ( $separate ) {
			$out['groups'] = $groups;
		}
		if ( ! $solo ) {
			// The pack is one container line, so the engine's rule applies once to its
			// total rather than to any member.
			$out['foldProduct'] = self::container_product();
			$out['foldQty']     = 1;
			foreach ( $out['groups'] ?? array() as $i => $group ) {
				$out['groups'][ $i ]['foldProduct'] = $out['foldProduct'];
				$out['groups'][ $i ]['foldQty']     = 1;
			}
		}
		return $out;
	}

	/**
	 * Bound the decoded selection.
	 *
	 * A selection that was sent but is not JSON (a truncated or hand-made post) is
	 * refused: its callers decode a failure to an empty selection, which would
	 * otherwise build the bundle's default pack from the stored config.
	 *
	 * @param string $raw_json  Raw posted JSON.
	 * @param array  $selection Decoded selection.
	 * @return string '' when ok, else a machine code.
	 */
	public static function validate_payload( $raw_json, $selection ) {
		if ( strlen( (string) $raw_json ) > 65536 ) {
			return 'payload_too_large';
		}
		if ( is_string( $raw_json ) && '' !== trim( $raw_json ) ) {
			json_decode( $raw_json );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return 'invalid_selection';
			}
		}
		$items   = is_array( $selection['items'] ?? null ) ? $selection['items'] : array();
		$units   = is_array( $selection['units'] ?? null ) ? $selection['units'] : array();
		if ( count( $items ) > 100 || count( $units ) > 100 ) {
			return 'payload_too_large';
		}
		// Any other list is an add-on's rows.
		$addons = array();
		foreach ( (array) $selection as $key => $list ) {
			if ( 'items' === $key || 'units' === $key || ! is_array( $list ) || array_values( $list ) !== $list ) {
				continue;
			}
			if ( count( $list ) > 20 ) {
				return 'payload_too_large';
			}
			$addons = array_merge( $addons, $list );
		}
		// A pool tile the shopper emptied arrives as qty 0 and is skipped downstream,
		// so rejecting it here would fail the whole add for a selection the box
		// considers valid. Add-ons carry no such state.
		foreach ( $items as $row ) {
			if ( isset( $row['qty'] ) && ( (int) $row['qty'] < 0 || (int) $row['qty'] > 9999 ) ) {
				return 'invalid_selection';
			}
		}
		foreach ( $addons as $row ) {
			if ( is_array( $row ) && isset( $row['qty'] ) ) {
				$q = (int) $row['qty'];
				if ( $q < 1 || $q > 9999 ) {
					return 'invalid_selection';
				}
			}
		}
		return '';
	}

	/**
	 * The chosen offer id must be one the bundle defines. Fixed Bundle offers are
	 * server-derived, so there is nothing to authenticate.
	 *
	 * @param array  $dc    Bundle discount config.
	 * @param int    $type  Bundle type.
	 * @param string $offer Offer id from the request.
	 * @return bool
	 */
	private static function valid_offer( $dc, $type, $offer ) {
		/**
		 * Filters whether an offer id is one the bundle's type really defines.
		 *
		 * Fixed Bundle offers are server-derived, so nothing is authenticated here
		 * and the default passes. A type that does publish an offer list answers
		 * false for an id that is not on it.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $valid Whether the offer is authentic.
		 * @param array  $dc    Bundle discount config.
		 * @param int    $type  Bundle type.
		 * @param string $offer Offer id from the request.
		 */
		return (bool) apply_filters( 'wffn_bundles_cart_valid_offer', true, $dc, $type, $offer );
	}

	/**
	 * The discount type an offer applies, as the order-meta vocabulary spells it.
	 *
	 * Not to be confused with `$bundle['discountType']`, which is the integer
	 * bundle type compared against `Bundle_Store::TYPE_*`.
	 *
	 * @param array  $dc    Bundle discount config.
	 * @param int    $type  Bundle type constant.
	 * @param string $offer Selected offer id.
	 * @return string One of `percentage`, `fixed`, `no_discount`.
	 */
	public static function offer_discount_type( $dc, $type, $offer ) {
		if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			return Frequently_Bought_Together::rule( (array) $dc )['mode'];
		}
		if ( Bundle_Store::TYPE_FIXED_BUNDLES !== $type ) {
			/**
			 * Filters the discount vocabulary an offer of another type records.
			 *
			 * @since 1.0.0
			 *
			 * @param string $spelling One of `percentage`, `fixed`, `no_discount`.
			 * @param array  $dc       Bundle discount config.
			 * @param int    $type     Bundle type.
			 * @param string $offer    Offer id the line sells.
			 */
			return (string) apply_filters( 'wffn_bundles_cart_offer_discount_type', 'no_discount', $dc, $type, $offer );
		}
		if ( 'separate_products' !== (string) ( $dc['fixedBundleDiscountScope'] ?? 'whole_bundle' ) ) {
			return (string) ( $dc['fixedBundleDiscountType'] ?? 'percentage' );
		}
		// Per-item scope has no single type for the container line; report one only
		// when every item agrees.
		$types = array();
		foreach ( (array) ( $dc['fixedBundleItems'] ?? array() ) as $it ) {
			$types[] = (string) ( $it['discountType'] ?? 'percentage' );
		}
		$types = array_values( array_unique( $types ) );
		return 1 === count( $types ) ? $types[0] : 'no_discount';
	}

	/**
	 * Quantities of each stock-managed id already in the cart (WooCommerce keys
	 * simple products / parent-managed variations by product id).
	 */
	private static function cart_quantities() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		$q = WC()->cart->get_cart_item_quantities();
		return is_array( $q ) ? $q : array();
	}

	/**
	 * Units of each product already in the cart, keyed by the exact product or
	 * variation id, for the once-per-order limit. cart_quantities() cannot serve
	 * there: it counts a variation whose stock the parent manages under the
	 * parent's id, so that variation itself read as absent.
	 *
	 * @return array<int,int>
	 */
	private static function cart_item_counts() {
		$out = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $ci ) {
			$id = ! empty( $ci['variation_id'] ) ? (int) $ci['variation_id'] : (int) ( $ci['product_id'] ?? 0 );
			if ( $id ) {
				$out[ $id ] = ( $out[ $id ] ?? 0 ) + (int) ( $ci['quantity'] ?? 0 );
			}
		}
		return $out;
	}

	/**
	 * Aggregated stock validation across every built member. Duplicate ids sum
	 * into one check, and quantities already in the cart are counted so the bundle
	 * cannot overdraw stock.
	 *
	 * @param array       $members Built member rows.
	 * @param string      $code    Receives a machine code on failure.
	 * @param string|null $detail  Receives the translated reason on failure.
	 * @return bool
	 */
	public static function validate_stock( $members, &$code, &$detail = null ) {
		$need = array();
		foreach ( $members as $m ) {
			$pid = ! empty( $m['variation_id'] ) ? (int) $m['variation_id'] : (int) ( $m['product_id'] ?? 0 );
			if ( ! $pid ) {
				continue;
			}
			$need[ $pid ] = ( $need[ $pid ] ?? 0 ) + max( 1, (int) ( $m['qty'] ?? 1 ) );
		}
		$in_cart = self::cart_quantities();
		$items   = self::cart_item_counts();
		$claimed = array();
		foreach ( $need as $pid => $qty ) {
			$product = wc_get_product( $pid );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				$code   = 'out_of_stock';
				$detail = self::stock_message( 'out_of_stock', $product, 0, $qty );
				return false;
			}
			// A limit of one per order holds for a bundle's members as it does for any add.
			// Counted by the member's own id, as $need is: the stock count keys a
			// parent-managed variation under its parent.
			if ( $product->is_sold_individually() && ( (int) ( $items[ $pid ] ?? 0 ) + $qty ) > 1 ) {
				$code   = 'sold_individually';
				$detail = sprintf(
					/* translators: %s: product name. */
					__( '“%s” can only be bought once per order, so this bundle can’t be added.', 'funnel-builder' ),
					$product->get_name()
				);
				return false;
			}
			// Cart contents count only against a stock-managed product, matching what
			// WooCommerce itself decrements. WooCommerce counts a variation whose stock
			// the parent manages under the parent's id, so two of its variations share
			// one count.
			$stock_id = (int) $product->get_stock_managed_by_id();
			$already  = $product->managing_stock() ? (int) ( $in_cart[ $stock_id ] ?? 0 ) : 0;
			$needed   = (int) ( $claimed[ $stock_id ] ?? 0 ) + $qty;
			if ( ! $product->has_enough_stock( $already + $needed ) ) {
				$code   = 'insufficient_stock';
				$detail = self::stock_message( 'insufficient_stock', $product, $already, $needed );
				return false;
			}
			$claimed[ $stock_id ] = $needed;
		}
		return true;
	}

	/**
	 * Say why a member failed the stock gate, naming it — the generic per-code
	 * copy in Ajax\Controller::config cannot.
	 *
	 * The unit count follows the same rule as the Fixed Bundle row label in
	 * Product_Display::member_availability: hidden when the store hides stock
	 * amounts, and for anything not reporting a managed count.
	 *
	 * @param string             $reason  'out_of_stock' or 'insufficient_stock'.
	 * @param \WC_Product|false  $product The member that failed.
	 * @param int                $in_cart Units of it already in the cart.
	 * @param int                $needed  Units this bundle asked for.
	 * @return string Translated sentence.
	 */
	private static function stock_message( $reason, $product, $in_cart, $needed ) {
		$name = $product ? (string) $product->get_name() : '';
		if ( '' === $name ) {
			return 'out_of_stock' === $reason
				? __( 'An item in this bundle is out of stock, so it can’t be added.', 'funnel-builder' )
				: __( 'There isn’t enough stock to add this bundle. Please reduce the quantity and try again.', 'funnel-builder' );
		}
		if ( 'out_of_stock' === $reason ) {
			/* translators: %s: product name. */
			return sprintf( __( '“%s” is out of stock, so this bundle can’t be added.', 'funnel-builder' ), $name );
		}
		$left = self::stock_left( $product );
		if ( null === $left ) {
			/* translators: %s: product name. */
			return sprintf( __( 'There isn’t enough stock of “%s” for this bundle. Please reduce the quantity and try again.', 'funnel-builder' ), $name );
		}
		if ( $in_cart > 0 ) {
			return sprintf(
				/* translators: 1: units left in stock, 2: product name, 3: units already in the cart, 4: units this bundle needs. */
				_n(
					'Only %1$d of “%2$s” is left and %3$d is already in your cart, so this bundle’s %4$d won’t fit. Please reduce the quantity.',
					'Only %1$d of “%2$s” are left and %3$d are already in your cart, so this bundle’s %4$d won’t fit. Please reduce the quantity.',
					$left,
					'funnel-builder'
				),
				$left,
				$name,
				$in_cart,
				$needed
			);
		}
		return sprintf(
			/* translators: 1: units left in stock, 2: product name, 3: units this bundle needs. */
			_n(
				'Only %1$d of “%2$s” is left, but this bundle needs %3$d. Please reduce the quantity.',
				'Only %1$d of “%2$s” are left, but this bundle needs %3$d. Please reduce the quantity.',
				$left,
				'funnel-builder'
			),
			$left,
			$name,
			$needed
		);
	}

	/**
	 * Units on hand to quote, or null when the store hides amounts.
	 *
	 * @param \WC_Product $product The member.
	 * @return int|null
	 */
	private static function stock_left( $product ) {
		if ( 'no_amount' === get_option( 'woocommerce_stock_format' ) || ! $product->managing_stock() ) {
			return null;
		}
		$left = (int) $product->get_stock_quantity();
		return $left > 0 ? $left : null;
	}

	/**
	 * Validate a client selection against the stored bundle config and build the
	 * priced members. Prices always come from the product; the client never
	 * supplies one.
	 *
	 * @param array  $bundle  Resolved bundle.
	 * @param int    $type      Bundle type.
	 * @param string $offer     Offer id from the request.
	 * @param array  $selection Client selection.
	 * @return array{code:string,members:array} A non-empty code is a hard failure.
	 */
	private static function build_validated( $bundle, $type, $offer, $selection ) {
		$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			return self::members_fixed_bundle( $dc, $selection );
		}
		if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			return self::members_fbt( $bundle, $dc, $selection );
		}

		/**
		 * Filters the priced members a bundle type this plugin does not build
		 * produces from a validated selection.
		 *
		 * The answer is trusted as validated: whoever owns the type re-checks every
		 * id, offer and quantity against the stored config. A non-empty `code`
		 * fails the request.
		 *
		 * @since 1.0.0
		 *
		 * @param array $built   { code, members }. Rejected by default.
		 * @param array $context { bundle, type, offer, selection }.
		 */
		return (array) apply_filters(
			'wffn_bundles_cart_bundle_members',
			array( 'code' => 'invalid_selection', 'members' => array() ),
			array(
				'bundle'  => $bundle,
				'type'      => $type,
				'offer'     => $offer,
				'selection' => $selection,
			)
		);
	}

	/**
	 * Fixed Bundle members, derived entirely from the stored config; the client
	 * selection only contributes per-member variation picks. Completeness is
	 * enforced: one missing, unpurchasable or unresolvable member aborts the whole
	 * add rather than shipping a partial bundle.
	 *
	 * @param array $dc        Bundle discount config.
	 * @param array $selection Client selection.
	 * @return array{code:string,members:array}
	 */
	private static function members_fixed_bundle( $dc, $selection = array() ) {
		$fail = function ( $code ) {
			return array( 'code' => $code, 'members' => array() );
		};

		$picks     = is_array( $selection['items'] ?? null ) ? array_values( $selection['items'] ) : array();
		$rows      = array();
		$total     = 0.0;
		$has_member = false;
		// Separate-products scope prices every member by its own discount, so each
		// row carries a ratio that price_rows() honours. Read once, outside the loop.
		$separate = 'separate_products' === (string) ( $dc['fixedBundleDiscountScope'] ?? 'whole_bundle' );
		foreach ( array_values( (array) ( $dc['fixedBundleItems'] ?? array() ) ) as $i => $it ) {
			$p = is_array( $it['product'] ?? null ) ? $it['product'] : null;
			if ( empty( $p['id'] ) ) {
				continue;
			}
			$has_member = true;
			$pid        = absint( $p['id'] );
			$qty        = max( 1, (int) ( $it['quantity'] ?? 1 ) );
			$product    = wc_get_product( $pid );
			// Trash stays purchasable for editors, but the cart refuses it outright.
			if ( $product && 'trash' === $product->get_status() ) {
				return $fail( 'not_available' );
			}
			// Completeness: a configured member that can't be bought sinks the bundle.
			if ( ! $product || ! $product->is_purchasable() ) {
				return $fail( 'invalid_selection' );
			}
			$attrs = is_array( $picks[ $i ]['attributes'] ?? null ) ? $picks[ $i ]['attributes'] : array();
			list( $prod_id, $variation_id, $variation ) = self::resolve_member( $pid, $attrs );
			$resolved = wc_get_product( $variation_id ? $variation_id : $prod_id );
			if ( ! $resolved || ! $resolved->is_purchasable() ) {
				return $fail( 'invalid_selection' );
			}
			// The server backstop for an incomplete pick: rejected rather than
			// defaulted to a variation the shopper never chose.
			if ( ! $variation_id && $resolved->is_type( 'variable' ) ) {
				return $fail( 'invalid_selection' );
			}
			$unit   = Layer::raw_base_price( $resolved );
			$row    = array( 'product_id' => $prod_id, 'variation_id' => $variation_id, 'variation' => $variation, 'qty' => $qty, 'unit' => $unit );
			if ( $separate ) {
				// Off the line total, not each unit, matching Skins\Fixed_Bundle::apply
				// and the tier_unit convention.
				$row['ratio'] = self::discount_ratio(
					$unit * $qty,
					(string) ( $it['discountType'] ?? 'percentage' ),
					(float) ( $it['discountValue'] ?? 0 )
				);
			}
			$rows[] = $row;
			$total += $unit * $qty;
		}
		if ( empty( $rows ) ) {
			return $fail( $has_member ? 'invalid_selection' : 'empty_selection' );
		}
		// Separate scope already put a per-row ratio on every row, so the group
		// ratio is a no-op there.
		$ratio = $separate
			? 1.0
			: self::discount_ratio( $total, (string) ( $dc['fixedBundleDiscountType'] ?? 'percentage' ), (float) ( $dc['fixedBundleDiscountValue'] ?? 0 ) );
		$label = '' !== (string) ( $dc['fixedBundleTitle'] ?? '' ) ? Fixed_Bundle::default_text( $dc['fixedBundleTitle'] ) : __( 'Bundle', 'funnel-builder' );
		return array( 'code' => '', 'members' => self::price_rows( $rows, $ratio, $label ) );
	}

	/**
	 * Frequently Bought Together members: the host the widget sits on, then each
	 * ticked companion, priced as one set. A companion must be one the bundle
	 * shows on this host; the client names ids and variations, never a price.
	 *
	 * @param array $bundle  Resolved bundle.
	 * @param array $dc        Bundle discount config.
	 * @param array $selection Client selection: { product, variation, hostAttributes, hostOn, quantity, items: [ { id, attributes } ] }.
	 * @return array{code:string,members:array,detail?:string} `detail` names the product when one is out of stock.
	 */
	public static function members_fbt( $bundle, $dc, $selection ) {
		$fail = function ( $code ) {
			return array( 'code' => $code, 'members' => array() );
		};

		$host_id = absint( $selection['product'] ?? 0 );
		if ( ! $host_id || ! Bundle_Store::targets_product( is_array( $bundle ) ? $bundle : array(), (string) $host_id ) ) {
			return $fail( 'invalid_selection' );
		}
		$host = wc_get_product( $host_id );
		if ( ! $host || ! $host->is_purchasable() ) {
			return $fail( 'invalid_selection' );
		}
		$host_on                           = self::fbt_host_on( $selection );
		list( $host_vid, $host_variation ) = $host_on ? self::fbt_host_variation( $host, $selection ) : array( 0, array() );
		if ( $host_on && $host->is_type( 'variable' ) && ! $host_vid ) {
			return $fail( 'invalid_selection' );
		}
		$qty  = min( Bundle_Rules::QUANTITY_MAX, max( 1, (int) ( $selection['quantity'] ?? 1 ) ) );
		$rows = array();
		if ( $host_on ) {
			$rows[] = array(
				'product_id'   => $host_id,
				'variation_id' => $host_vid,
				'variation'    => $host_variation,
				'qty'          => $qty,
				'unit'         => Layer::raw_base_price( wc_get_product( $host_vid ? $host_vid : $host_id ) ),
				'role'         => 'fbt_host',
			);
		}

		$allowed = Frequently_Bought_Together::companion_ids( $dc, $host );
		$seen    = array();
		foreach ( (array) ( $selection['items'] ?? array() ) as $pick ) {
			$id = is_array( $pick ) ? absint( $pick['id'] ?? 0 ) : 0;
			if ( $id && ! in_array( $id, $allowed, true ) && ! isset( $seen[ $id ] ) ) {
				// A companion the set lists that has sold out is refused for its stock,
				// as a Fixed Bundle refuses the same product.
				$sold_out = in_array( $id, Frequently_Bought_Together::companion_ids( $dc, $host, false ), true ) ? wc_get_product( $id ) : null;
				if ( $sold_out && ! $sold_out->is_in_stock() ) {
					return array(
						'code'    => 'out_of_stock',
						'members' => array(),
						'detail'  => self::stock_message( 'out_of_stock', $sold_out, 0, $qty ),
					);
				}
			}
			if ( ! $id || ! in_array( $id, $allowed, true ) || isset( $seen[ $id ] ) ) {
				return $fail( 'invalid_selection' );
			}
			$seen[ $id ] = true;
			list( $prod_id, $vid, $variation ) = self::resolve_member( $id, is_array( $pick['attributes'] ?? null ) ? $pick['attributes'] : array() );
			$resolved = wc_get_product( $vid ? $vid : $prod_id );
			if ( ! $resolved || ! $resolved->is_purchasable() || ( ! $vid && $resolved->is_type( 'variable' ) ) ) {
				return $fail( 'invalid_selection' );
			}
			$rows[] = array(
				'product_id'   => $prod_id,
				'variation_id' => $vid,
				'variation'    => $variation,
				'qty'          => $qty,
				'unit'         => Layer::raw_base_price( $resolved ),
				'role'         => 'fbt_companion',
			);
		}

		$rule   = Frequently_Bought_Together::rule( $dc );
		$priced = Frequently_Bought_Together::price_set( self::fbt_lines( $rows ), $rule['scope'], $rule['mode'], $host_on ? $rule['value'] : 0.0, $rule['min'] );
		$label  = self::fbt_label( $dc );
		foreach ( $rows as $i => $row ) {
			$rows[ $i ]['pricedUnit'] = (float) $priced['lines'][ $i ]['final'];
			$rows[ $i ]['label']      = $label;
		}
		return array( 'code' => '', 'members' => $rows );
	}

	/**
	 * A set's priced lines, host first, or [] when it cannot be priced.
	 *
	 * @param array       $bundle  The set's bundle.
	 * @param \WC_Product $product   The product the set is bought from; a variation names the host's pick.
	 * @param array|null  $selection The widget's posted selection; null for the set's opening ticks.
	 * @return array[]
	 */
	public static function fbt_set_members( array $bundle, $product, $selection ) {
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}
		$host_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$host    = wc_get_product( $host_id );
		if ( ! $host instanceof \WC_Product ) {
			return array();
		}
		$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( null === $selection ) {
			$selection = array( 'items' => array() );
			foreach ( Frequently_Bought_Together::companion_ids( $dc, $host ) as $id ) {
				if ( ! Frequently_Bought_Together::starts_checked( $dc, $id ) ) {
					continue;
				}
				// The widget's picker opens on each axis's default; one with none waits for a pick.
				$row   = Repository::find( $id );
				$picks = array();
				foreach ( (array) ( is_array( $row ) ? ( $row['attributes'] ?? array() ) : array() ) as $axis ) {
					if ( '' === (string) ( $axis['default'] ?? '' ) ) {
						continue 2;
					}
					$picks[ (string) $axis['name'] ] = (string) $axis['default'];
				}
				$selection['items'][] = array(
					'id'         => $id,
					'attributes' => $picks,
				);
			}
		}
		if ( $product->is_type( 'variation' ) && empty( $selection['hostAttributes'] ) ) {
			$selection['hostAttributes'] = array();
			foreach ( $product->get_variation_attributes() as $key => $value ) {
				$selection['hostAttributes'][ substr( (string) $key, strlen( 'attribute_' ) ) ] = (string) $value;
			}
		}
		$selection['product']  = $host_id;
		$selection['quantity'] = 1;
		$built                 = self::members_fbt( $bundle, $dc, $selection );

		return '' === $built['code'] ? (array) $built['members'] : array();
	}

	/**
	 * The set a host line carries, as Product_Display::render_fbt_product() reads it, or null.
	 *
	 * @param string $cart_item_key The host's cart line.
	 * @return array|null
	 */
	public static function fbt_line_set( $cart_item_key ) {
		if ( '' === $cart_item_key || ! function_exists( 'WC' ) || empty( WC()->cart ) ) {
			return null;
		}
		$item  = WC()->cart->get_cart_item( $cart_item_key );
		$group = is_array( $item ) && 'fbt_host' === (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) ? (string) ( $item['wffn_bundles_bundle']['group'] ?? '' ) : '';
		if ( '' === $group ) {
			return null;
		}
		$set = array(
			'host'  => (array) ( $item['variation'] ?? array() ),
			'items' => array(),
		);
		foreach ( WC()->cart->get_cart() as $line ) {
			if ( 'fbt_companion' === (string) ( $line['wffn_bundles_bundle']['role'] ?? '' ) && (string) ( $line['wffn_bundles_bundle']['group'] ?? '' ) === $group ) {
				$set['items'][ (int) $line['product_id'] ] = (array) ( $line['variation'] ?? array() );
			}
		}
		return $set;
	}

	/**
	 * Whether a Frequently Bought Together selection buys the viewed product.
	 *
	 * Absent means yes: only the product page's own add lets the shopper untick it.
	 *
	 * @param array $selection Client selection.
	 * @return bool
	 */
	public static function fbt_host_on( array $selection ) {
		return ! array_key_exists( 'hostOn', $selection ) || ! empty( $selection['hostOn'] );
	}

	/**
	 * The host variation a Frequently Bought Together add names, and its attributes.
	 *
	 * Read off the widget's own "This item" picker, resolved like a companion's
	 * picks; the theme's form above is the product's own, separate purchase.
	 *
	 * @param \WC_Product $host      The viewed product.
	 * @param array       $selection Client selection.
	 * @return array{0:int,1:array} [ variation_id, attribute_map ], or [ 0, [] ].
	 */
	private static function fbt_host_variation( $host, array $selection ) {
		if ( ! $host->is_type( 'variable' ) ) {
			return array( 0, array() );
		}
		$picks                     = is_array( $selection['hostAttributes'] ?? null ) ? $selection['hostAttributes'] : array();
		list( , $vid, $variation ) = self::resolve_member( $host->get_id(), $picks );
		$product                   = $vid ? wc_get_product( $vid ) : null;
		if ( ! $product || ! $product->is_purchasable() ) {
			return array( 0, array() );
		}
		return array( (int) $vid, (array) $variation );
	}

	/**
	 * Rows as the set-pricing helper reads them.
	 *
	 * @param array[] $rows Rows carrying `unit` and `role`.
	 * @return array[]
	 */
	private static function fbt_lines( array $rows ) {
		$lines = array();
		foreach ( $rows as $row ) {
			$lines[] = array(
				'min'  => (float) $row['unit'],
				'max'  => (float) $row['unit'],
				'host' => 'fbt_host' === (string) $row['role'],
				'on'   => true,
			);
		}
		return $lines;
	}

	/**
	 * The name a Frequently Bought Together line carries in the cart.
	 *
	 * @param array $dc Bundle discount config.
	 * @return string
	 */
	private static function fbt_label( array $dc ) {
		$heading = trim( (string) ( $dc['bundleHeading'] ?? '' ) );
		return '' !== $heading ? $heading : __( 'Frequently bought together', 'funnel-builder' );
	}

	/**
	 * Live quote for a Frequently Bought Together widget.
	 *
	 * Every row is priced, ticked or not, so an unticked row shows the price it
	 * would join at. A variation not chosen yet quotes as a range.
	 *
	 * @param array $bundle  Resolved bundle.
	 * @param array $dc        Bundle discount config.
	 * @param array $selection Client selection; each item carries `on`.
	 * @return array{quote:array,lines:array[],totals:array}|null The widget payload, its ticked lines at set price, and the set totals.
	 */
	private static function quote_fbt( $bundle, $dc, $selection ) {
		$host_id = absint( $selection['product'] ?? 0 );
		if ( ! $host_id || ! Bundle_Store::targets_product( is_array( $bundle ) ? $bundle : array(), (string) $host_id ) ) {
			return null;
		}
		$host = wc_get_product( $host_id );
		if ( ! $host ) {
			return null;
		}
		list( $host_vid ) = self::fbt_host_variation( $host, $selection );
		if ( $host_vid ) {
			$host_min = Layer::raw_base_price( wc_get_product( $host_vid ) );
			$host_max = $host_min;
		} else {
			list( $host_min, $host_max ) = self::unit_range( $host_id, array() );
		}

		$host_on  = self::fbt_host_on( $selection );
		$lines    = array(
			array(
				'min'  => $host_min,
				'max'  => $host_max,
				'host' => true,
				'on'   => $host_on,
			),
		);
		$products = array( wc_get_product( $host_vid ? $host_vid : $host_id ) );
		$resolved = $products;
		$keys     = array( 'host' );
		$allowed = Frequently_Bought_Together::companion_ids( $dc, $host );
		foreach ( (array) ( $selection['items'] ?? array() ) as $pick ) {
			$id = is_array( $pick ) ? absint( $pick['id'] ?? 0 ) : 0;
			if ( ! $id || ! in_array( $id, $allowed, true ) || in_array( (string) $id, $keys, true ) ) {
				continue;
			}
			$attrs             = is_array( $pick['attributes'] ?? null ) ? $pick['attributes'] : array();
			list( $min, $max ) = self::unit_range( $id, $attrs );
			list( , $vid )     = self::resolve_member( $id, $attrs );
			$resolved[]        = wc_get_product( $vid ? $vid : $id );
			$lines[]           = array(
				'min'  => $min,
				'max'  => $max,
				'host' => false,
				'on'   => ! empty( $pick['on'] ),
			);
			$products[]        = wc_get_product( $id );
			$keys[]            = (string) $id;
		}

		$rule = Frequently_Bought_Together::rule( $dc );
		if ( ! $host_on ) {
			$rule['value'] = 0.0;
		}
		$priced = Frequently_Bought_Together::price_set( $lines, $rule['scope'], $rule['mode'], $rule['value'], $rule['min'], array_filter( $products ) );

		$rows = array();
		foreach ( $lines as $i => $line ) {
			$final  = $priced['lines'][ $i ];
			$strike = $line['on'] && $final['final'] < $line['min'];
			$html   = $strike ? '<span class="wffn-bundles-fbt-price-original">' . self::member_price_html( $line['min'], $line['max'] ) . '</span>' : '';
			$html  .= '<span class="wffn-bundles-fbt-price-final">' . self::member_price_html( $strike ? $final['final'] : $line['min'], $strike ? $final['finalMax'] : $line['max'] ) . '</span>';
			$rows[] = array(
				'key'  => $keys[ $i ],
				'html' => $html,
			);
		}

		$has_discount = $priced['final'] < $priced['orig'];
		$total        = '<span class="wffn-bundles-fbt-total-label">' . esc_html__( 'Total:', 'funnel-builder' ) . '</span> ';
		if ( $has_discount ) {
			$total .= '<span class="wffn-bundles-fbt-price-original">' . self::member_price_html( $priced['orig'], $priced['origMax'] ) . '</span> ';
		}
		$total .= '<span class="wffn-bundles-fbt-price-final wffn-bundles-fbt-total">' . self::member_price_html( $priced['final'], $priced['finalMax'] ) . '</span>';

		$note      = Frequently_Bought_Together::note( $priced, $rule, Pricing::format( $priced['orig'] - $priced['final'] ) );
		$note_html = '';
		if ( $note['saveShow'] ) {
			$note_html = '<span class="wffn-bundles-fbt-save">' . esc_html( $note['saveText'] ) . '</span>';
		} elseif ( $note['ruleShow'] ) {
			$note_html = '<span class="wffn-bundles-fbt-rule">' . esc_html( $note['ruleText'] ) . '</span>';
		}

		// Each ticked line at its set price, for a consumer that composes per cart line.
		$set_lines = array();
		foreach ( $lines as $i => $line ) {
			if ( ! empty( $line['on'] ) && $resolved[ $i ] instanceof \WC_Product ) {
				$set_lines[] = array(
					'product' => $resolved[ $i ],
					'qty'     => 1,
					'final'   => (float) $priced['lines'][ $i ]['final'],
				);
			}
		}

		return array(
			'quote'  => array(
				'rows'       => $rows,
				'totalHtml'  => $total,
				'noteHtml'   => $note_html,
				'buttonText' => Frequently_Bought_Together::button_text( ( $host_on ? 1 : 0 ) + (int) $priced['count'], (string) ( $dc['fbtButtonLabel'] ?? '' ) ),
			),
			'lines'  => $set_lines,
			'totals' => array(
				'origMin'   => $priced['orig'],
				'origMax'   => $priced['origMax'],
				'finalMin'  => $priced['final'],
				'finalMax'  => $priced['finalMax'],
				'bundleMin' => $priced['final'],
				'bundleMax' => $priced['finalMax'],
			),
		);
	}

	/**
	 * The Frequently Bought Together lines in a cart, by group.
	 *
	 * @param array $contents Cart contents.
	 * @return array<string,string[]> Group => cart item keys.
	 */
	private static function fbt_groups( array $contents ) {
		$groups = array();
		foreach ( $contents as $key => $ci ) {
			$b = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : null;
			if ( ! $b || Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER !== (int) ( $b['type'] ?? 0 ) || '' === (string) ( $b['group'] ?? '' ) ) {
				continue;
			}
			$groups[ (string) $b['group'] ][] = $key;
		}
		return $groups;
	}

	/**
	 * The unit price each line of one set is charged, from what is still in it.
	 *
	 * The set keeps its discount while its host is there and enough companions
	 * remain; otherwise every line is at its own price. A set whose bundle
	 * changed is never discounted again.
	 *
	 * @param array    $contents Cart contents.
	 * @param string[] $keys     The set's cart item keys.
	 * @return array{prices:array<string,float>,qualifies:bool}
	 */
	private static function fbt_group_prices( array $contents, array $keys ) {
		$rows  = array();
		$rule  = null;
		$stale = self::fbt_outdated( $contents, $keys );
		$host  = false;
		foreach ( $keys as $key ) {
			$b      = (array) $contents[ $key ]['wffn_bundles_bundle'];
			$rows[] = array(
				'unit' => (float) ( $b['unit'] ?? 0 ),
				'role' => (string) ( $b['role'] ?? '' ),
			);
			$rule   = $rule ?? ( is_array( $b['fbtRule'] ?? null ) ? $b['fbtRule'] : null );
			$stale  = $stale || ! empty( $b['fbtStale'] );
			$host   = $host || 'fbt_host' === (string) ( $b['role'] ?? '' );
		}

		$prices = array();
		if ( ! $host || $stale || null === $rule ) {
			foreach ( $keys as $i => $key ) {
				$prices[ $key ] = $stale ? self::fbt_live_unit( $contents[ $key ], $rows[ $i ]['unit'] ) : $rows[ $i ]['unit'];
			}
			return array( 'prices' => $prices, 'qualifies' => false );
		}
		$priced = Frequently_Bought_Together::price_set(
			self::fbt_lines( $rows ),
			(string) ( $rule['scope'] ?? 'companions' ),
			(string) ( $rule['mode'] ?? 'percentage' ),
			(float) ( $rule['value'] ?? 0 ),
			(int) ( $rule['min'] ?? 1 )
		);
		foreach ( $keys as $i => $key ) {
			$prices[ $key ] = (float) $priced['lines'][ $i ]['final'];
		}
		return array( 'prices' => $prices, 'qualifies' => (bool) $priced['qualifies'] );
	}

	/**
	 * Whether the bought-together set a cart line belongs to is still sold as a
	 * set. A set the cart re-priced at its products' own prices because its
	 * bundle was paused or changed is no longer a bundle sale; a set whose offer
	 * simply has no discount still is.
	 *
	 * @param string $cart_item_key A line of the set.
	 * @return bool True for a line of no set.
	 */
	public static function fbt_set_discounted( $cart_item_key ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return true;
		}
		$contents = WC()->cart->cart_contents;
		$group    = (string) ( $contents[ $cart_item_key ]['wffn_bundles_bundle']['group'] ?? '' );
		$keys     = '' !== $group ? ( self::fbt_groups( $contents )[ $group ] ?? array() ) : array();
		if ( empty( $keys ) ) {
			return true;
		}
		// Re-priced at regular prices because its bundle was paused or changed: no longer a set sale.
		// A set with no discount of its own (no_discount) is still a set.
		foreach ( $keys as $k ) {
			if ( ! empty( $contents[ $k ]['wffn_bundles_bundle']['fbtStale'] ) ) {
				return false;
			}
		}
		return ! self::fbt_outdated( $contents, $keys );
	}

	/**
	 * Has a set's bundle or any of its products changed since it was added?
	 *
	 * The same test drop_stale_bundles() runs on a cart or checkout page load, made
	 * here so the totals shown and the order charged already agree before it does.
	 *
	 * @param array    $contents Cart contents.
	 * @param string[] $keys     The set's cart item keys.
	 * @return bool
	 */
	private static function fbt_outdated( array $contents, array $keys ) {
		foreach ( $keys as $key ) {
			if ( self::base_price_changed( (array) $contents[ $key ] ) ) {
				return true;
			}
		}
		$first     = isset( $keys[0] ) ? (array) ( $contents[ $keys[0] ]['wffn_bundles_bundle'] ?? array() ) : array();
		$bundle_id = (string) ( $first['bundle'] ?? '' );
		if ( '' === $bundle_id ) {
			return false;
		}
		$bundle = Bundle_Store::get_config( $bundle_id );
		if ( ! $bundle || ! Bundle_Store::is_active_now( $bundle ) ) {
			return true;
		}
		$print = (string) ( $first['pricingFingerprint'] ?? '' );
		return '' !== $print && ! hash_equals( self::pricing_fingerprint( $bundle ), $print );
	}

	/**
	 * What a line of an outdated set costs now: its product's current price.
	 *
	 * @param array $item     Cart item.
	 * @param float $fallback Price the line was added at, for a product that no longer loads.
	 * @return float Base currency.
	 */
	private static function fbt_live_unit( array $item, $fallback ) {
		$id      = absint( $item['variation_id'] ?? 0 ) ? absint( $item['variation_id'] ) : absint( $item['product_id'] ?? 0 );
		$product = $id ? wc_get_product( $id ) : null;
		return $product instanceof \WC_Product ? Layer::raw_base_price( $product ) : (float) $fallback;
	}

	/**
	 * Keep every bought-together set priced by what is still in it.
	 *
	 * Removal, undo and a stale bundle all land here, on the next totals pass,
	 * so no removal path has to remember to reprice.
	 *
	 * @param \WC_Cart $cart Cart being recalculated.
	 * @return void
	 */
	public function reprice_fbt( $cart ) {
		if ( ! is_object( $cart ) || ! isset( $cart->cart_contents ) || self::is_foreign_cart( $cart ) ) {
			return;
		}
		foreach ( self::fbt_groups( $cart->cart_contents ) as $keys ) {
			$result = self::fbt_group_prices( $cart->cart_contents, $keys );
			foreach ( $result['prices'] as $key => $unit ) {
				$cart->cart_contents[ $key ]['wffn_bundles_bundle']['pricedUnit'] = $unit;
			}
		}
	}

	/**
	 * Take a set placed as one choice out with its host line.
	 *
	 * A checkout row places its set `whole`; a product-page set
	 * keeps its other lines when one leaves.
	 *
	 * @param string   $key  Cart line being removed.
	 * @param \WC_Cart $cart The cart.
	 * @return void
	 */
	public function remove_whole_set( $key, $cart ) {
		$item = is_object( $cart ) ? ( $cart->cart_contents[ $key ] ?? null ) : null;
		$b    = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
		if ( empty( $b['whole'] ) || 'fbt_host' !== (string) ( $b['role'] ?? '' ) ) {
			return;
		}
		foreach ( $cart->cart_contents as $k => $line ) {
			$other = is_array( $line['wffn_bundles_bundle'] ?? null ) ? $line['wffn_bundles_bundle'] : array();
			if ( $k !== $key && 'fbt_companion' === (string) ( $other['role'] ?? '' ) && (string) ( $other['group'] ?? '' ) === (string) ( $b['group'] ?? '' ) ) {
				unset( $cart->cart_contents[ $k ] );
			}
		}
	}

	/**
	 * Say so when removing a line ends the rest of its set's discount.
	 *
	 * @param string   $key  Cart item key being removed.
	 * @param \WC_Cart $cart The cart.
	 * @return void
	 */
	public function fbt_removal_notice( $key, $cart ) {
		$item = $cart->cart_contents[ $key ] ?? null;
		$b    = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
		if ( ! $b || Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER !== (int) ( $b['type'] ?? 0 ) ) {
			return;
		}
		$keys = self::fbt_groups( $cart->cart_contents )[ (string) ( $b['group'] ?? '' ) ] ?? array();
		$rest = array_values( array_diff( $keys, array( $key ) ) );
		if ( empty( $rest ) || ! self::fbt_group_prices( $cart->cart_contents, $keys )['qualifies'] ) {
			return;
		}
		if ( self::fbt_group_prices( $cart->cart_contents, $rest )['qualifies'] ) {
			return;
		}
		$product = $item['data'] ?? null;
		wc_add_notice(
			sprintf(
				/* translators: %s: name of the product removed from the cart. */
				__( 'Without "%s", the rest of that set is no longer discounted. Add it back to save again.', 'funnel-builder' ),
				$product instanceof \WC_Product ? $product->get_name() : ''
			),
			'notice'
		);
	}

	/**
	 * Is this companion line held to its set's quantity?
	 *
	 * Only while its host is in the cart: without one the line is an ordinary
	 * product at its own price, and the shopper's to change.
	 *
	 * @param array|null $item Cart item.
	 * @return bool
	 */
	public static function fbt_companion_locked( $item ) {
		$b = is_array( $item ) && is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
		if ( ! $b || 'fbt_companion' !== (string) ( $b['role'] ?? '' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		return null !== self::fbt_host_quantity( WC()->cart->cart_contents, (string) ( $b['group'] ?? '' ) );
	}

	/**
	 * Does this line head a bought-together set that still has companions in the cart?
	 *
	 * @param array|null $item Cart item.
	 * @return bool
	 */
	public static function fbt_leads( $item ) {
		$b = is_array( $item ) && is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
		if ( ! $b || 'fbt_host' !== (string) ( $b['role'] ?? '' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->cart_contents as $ci ) {
			$other = is_array( $ci['wffn_bundles_bundle'] ?? null ) ? $ci['wffn_bundles_bundle'] : array();
			if ( 'fbt_companion' === (string) ( $other['role'] ?? '' ) && (string) ( $other['group'] ?? '' ) === (string) ( $b['group'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The quantity of a set's host line, or null when the host is gone.
	 *
	 * @param array  $contents Cart contents.
	 * @param string $group    Set group.
	 * @return int|null
	 */
	private static function fbt_host_quantity( array $contents, $group ) {
		foreach ( $contents as $ci ) {
			$b = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : null;
			if ( $b && 'fbt_host' === (string) ( $b['role'] ?? '' ) && (string) ( $b['group'] ?? '' ) === (string) $group && '' !== (string) $group ) {
				return max( 1, (int) ( $ci['quantity'] ?? 1 ) );
			}
		}
		return null;
	}

	/**
	 * Apply a discount ratio to each row's unit price and tag it as a member.
	 *
	 * A row may carry its own `ratio`, which wins over the group one — that is how
	 * a separate-products Fixed Bundle prices each member. The key is dropped here
	 * so it never reaches the `wffn_bundles_bundle` cart-item data, where it would change
	 * the cart item hash.
	 *
	 * @param array  $rows  Member rows.
	 * @param float  $ratio Group discount ratio.
	 * @param string $label Offer label to tag each row with.
	 * @return array[]
	 */
	public static function price_rows( $rows, $ratio, $label ) {
		$members = array();
		foreach ( $rows as $r ) {
			$row_ratio = isset( $r['ratio'] ) ? (float) $r['ratio'] : $ratio;
			unset( $r['ratio'] );
			// Unrounded on purpose: apply_pricing() does the single rounding, on the
			// line total in base, so a large box cannot accumulate cents.
			$r['pricedUnit'] = $r['unit'] * $row_ratio;
			$r['role']       = 'member';
			$r['label']      = $label;
			$members[]       = $r;
		}
		return $members;
	}

	/**
	 * Match a variable product's variation from selected attributes
	 * (name => value, e.g. ["Size" => "Small"]).
	 *
	 * Public for the same reason fixed_bundle_members() is: a compat adapter
	 * resolving a variable anchor's own pick needs this label-to-taxonomy-key
	 * mapping rather than a naive reimplementation of it.
	 *
	 * @param int   $product_id Parent product.
	 * @param array $attributes Chosen attributes.
	 * @return array{0:int,1:array} [ variation_id, attribute_map ], or [ 0, [] ].
	 */
	public static function resolve_variation( $product_id, $attributes ) {
		static $memo = array();
		$attr_key = is_array( $attributes ) ? $attributes : array();
		ksort( $attr_key );
		$cache_key = $product_id . '|' . wp_json_encode( $attr_key );
		if ( isset( $memo[ $cache_key ] ) ) {
			return $memo[ $cache_key ];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) || empty( $attributes ) ) {
			$memo[ $cache_key ] = array( 0, array() );
			return $memo[ $cache_key ];
		}
		// The picker sends display labels, which for a global taxonomy attribute drop
		// the prefix ("Color", not "pa_color"), so each is mapped back to its key.
		$label_to_key = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( ! is_object( $attr ) || ! $attr->get_variation() ) {
				continue;
			}
			$raw                     = (string) $attr->get_name();
			$label_to_key[ wc_attribute_label( $raw, $product ) ] = $raw;
		}
		$variation = array();
		foreach ( $attributes as $name => $value ) {
			$key = isset( $label_to_key[ (string) $name ] ) ? $label_to_key[ (string) $name ] : (string) $name;
			$variation[ 'attribute_' . sanitize_title( $key ) ] = (string) $value;
		}
		// Every axis must be picked: find_matching_product_variation() matches on a
		// subset, so a half-completed pick would resolve to whatever matches first.
		foreach ( $label_to_key as $raw ) {
			$akey = 'attribute_' . sanitize_title( $raw );
			if ( ! isset( $variation[ $akey ] ) || '' === $variation[ $akey ] ) {
				$memo[ $cache_key ] = array( 0, array() );
				return $memo[ $cache_key ];
			}
		}
		$vid = self::match_variation( $product, $variation );
		$memo[ $cache_key ] = array( (int) $vid, $variation );
		return $memo[ $cache_key ];
	}

	/**
	 * The variation a full attribute pick lands on, preferring one that can be bought.
	 *
	 * find_matching_product_variation() returns the first match, so an unpriced
	 * "Green / L" would shadow a priced "Any colour / L"; WooCommerce's own picker
	 * only offers purchasable variations, and this matches it.
	 *
	 * @param \WC_Product $parent     Variable product.
	 * @param array       $attributes `attribute_*` => value.
	 * @return int Variation id, or 0 when none matches.
	 */
	public static function match_variation( $parent, array $attributes ) {
		$vid = (int) \WC_Data_Store::load( 'product' )->find_matching_product_variation( $parent, $attributes );
		$hit = $vid ? wc_get_product( $vid ) : null;
		if ( ! $vid || ( $hit && $hit->is_purchasable() ) ) {
			return $vid;
		}
		foreach ( $parent->get_children() as $cid ) {
			$own = wc_get_product_variation_attributes( $cid );
			$ok  = true;
			foreach ( $attributes as $key => $value ) {
				if ( isset( $own[ $key ] ) && '' !== (string) $own[ $key ] && strtolower( (string) $own[ $key ] ) !== strtolower( (string) $value ) ) {
					$ok = false;
					break;
				}
			}
			$candidate = $ok ? wc_get_product( $cid ) : null;
			if ( $candidate && $candidate->is_purchasable() ) {
				return (int) $cid;
			}
		}
		return $vid;
	}

	/**
	 * Resolve a picked member to [ product_id, variation_id, variation_attributes ]
	 * ready for WC()->cart->add_to_cart(), across every member shape:
	 *
	 *  - simple product           - [ pid, 0, [] ]
	 *  - variable product         - the chosen combo resolved to a variation
	 *  - variation (fixed)        - [ parent, variation, its concrete attributes ]
	 *  - variation with open axes - a partial variation, e.g. "Blue, Any Size":
	 *        its fixed attributes combined with what the picker completed.
	 *
	 * variation_id is 0 when a variable or partial-variation member is not fully
	 * chosen; the callers reject that rather than letting the add fail.
	 *
	 * @param int   $posted_id  Member product or variation id.
	 * @param array $attributes Chosen attributes.
	 * @return array{0:int,1:int,2:array}
	 */
	public static function resolve_member( $posted_id, $attributes ) {
		$posted_id  = (int) $posted_id;
		$attributes = is_array( $attributes ) ? $attributes : array();
		$product    = $posted_id ? wc_get_product( $posted_id ) : null;
		if ( ! $product ) {
			return array( $posted_id, 0, array() );
		}

		if ( $product->is_type( 'variable' ) ) {
			list( $vid, $variation ) = self::resolve_variation( $posted_id, $attributes );
			return array( $posted_id, $vid, $variation );
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
				return array( $posted_id, 0, array() );
			}
			// Map the picker's display labels ("Size") back to real attribute keys.
			$label_to_key = array();
			foreach ( $parent->get_attributes() as $attr ) {
				if ( is_object( $attr ) && $attr->get_variation() ) {
					$label_to_key[ wc_attribute_label( (string) $attr->get_name(), $parent ) ] = (string) $attr->get_name();
				}
			}
			$chosen = array();
			foreach ( $attributes as $name => $value ) {
				$key = isset( $label_to_key[ (string) $name ] ) ? $label_to_key[ (string) $name ] : (string) $name;
				$chosen[ 'attribute_' . sanitize_title( $key ) ] = (string) $value;
			}
			// Fixed axes from the variation; fill each open ("Any") axis from the pick.
			$combined = array();
			$missing  = false;
			foreach ( $product->get_variation_attributes() as $akey => $val ) {
				if ( '' !== $val ) {
					$combined[ $akey ] = (string) $val;
				} elseif ( isset( $chosen[ $akey ] ) && '' !== $chosen[ $akey ] ) {
					$combined[ $akey ] = $chosen[ $akey ];
				} else {
					$missing = true; // An open axis the shopper has not completed.
				}
			}
			if ( $missing ) {
				return array( $parent->get_id(), 0, $combined );
			}
			$vid = self::match_variation( $parent, $combined );
			return array( $parent->get_id(), $vid ? (int) $vid : $posted_id, $combined );
		}

		return array( $posted_id, 0, array() );
	}

	/**
	 * A sensible default variation for a variable product: the configured default
	 * attributes if set, else the first purchasable in-stock variation. Used so a
	 * Fixed Bundle's variable member stays addable with nothing chosen.
	 *
	 * @param \WC_Product $product Parent product.
	 * @return array{0:int,1:array} [ variation_id, attribute_map ], or [ 0, [] ].
	 */
	private static function default_variation( $product ) {
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array( 0, array() );
		}
		$defaults = $product->get_default_attributes();
		if ( $defaults ) {
			$attrs = array();
			foreach ( $defaults as $name => $value ) {
				$attrs[ 'attribute_' . $name ] = $value;
			}
			$vid = self::match_variation( $product, $attrs );
			if ( $vid ) {
				return array( (int) $vid, $attrs );
			}
		}
		foreach ( $product->get_children() as $cid ) {
			$v = wc_get_product( $cid );
			if ( $v && $v->is_purchasable() && $v->is_in_stock() ) {
				return array( (int) $cid, $v->get_variation_attributes() );
			}
		}
		return array( 0, array() );
	}

	/**
	 * Log when the currency the page was rendered in is not the one this request
	 * resolved to.
	 *
	 * The server always prices in its own resolved currency; a posted code is a
	 * claim, never an instruction. A switcher keeping the selection in an IP-hashed
	 * transient can genuinely disagree behind shared NAT or a CDN.
	 *
	 * @return void
	 */
	private static function assert_currency() {
		$posted = strtoupper( Controller::param( 'currency' ) );
		if ( '' === $posted ) {
			return;
		}
		$current = strtoupper( (string) Layer::get_current_currency() );
		if ( '' !== $current && $posted !== $current ) {
			Layer::log(
				sprintf(
					'AJAX currency mismatch: the page was rendered in %1$s but this request resolved to %2$s; pricing in %2$s.',
					$posted,
					$current
				)
			);
		}
	}

	/**
	 * Fraction of the original price to keep after a percentage or fixed discount.
	 *
	 * The return is unitless and must never be currency-converted: `$total` and a
	 * fixed `$value` are both base currency, so their quotient is a pure ratio.
	 *
	 * @param float  $total Undiscounted total, base currency.
	 * @param string $type  `percentage`, `fixed` or `no_discount`.
	 * @param float  $value Discount amount.
	 * @return float
	 */
	public static function discount_ratio( $total, $type, $value ) {
		if ( $total <= 0 ) {
			return 1.0;
		}
		if ( 'percentage' === $type && $value > 0 ) {
			return max( 0.0, 1 - $value / 100 );
		}
		if ( 'fixed' === $type && $value > 0 ) {
			return max( 0.0, ( $total - $value ) / $total );
		}
		return 1.0;
	}

	/**
	 * Re-hydrate the offer data from the session each request.
	 *
	 * @param array $item   Cart item being restored.
	 * @param array $values Session values.
	 * @return array
	 */
	public function restore_item( $item, $values ) {
		if ( isset( $values['wffn_bundles_bundle'] ) ) {
			$item['wffn_bundles_bundle'] = $values['wffn_bundles_bundle'];
			// Runtime-only, never saved: the Store API reads only the product's image
			// id, so the container's placeholder is pointed at the bundle thumbnail.
			if ( in_array( (string) ( $values['wffn_bundles_bundle']['role'] ?? '' ), array( 'container', 'heading' ), true ) && isset( $item['data'] ) && is_object( $item['data'] ) ) {
				$img = (int) ( $values['wffn_bundles_bundle']['thumbImgId'] ?? 0 );
				if ( ! $img ) {
					// A container added before thumbImgId was stored derives from the
					// first member, so old carts keep an image.
					$tid = (int) ( $values['wffn_bundles_bundle']['thumbId'] ?? 0 );
					$tp  = $tid ? wc_get_product( $tid ) : null;
					$img = $tp ? (int) $tp->get_image_id() : 0;
				}
				if ( $img ) {
					$item['data']->set_image_id( $img );
				}
				// Runtime-only: any get_image() on this line's product shows the composed image.
				if ( 'heading' === (string) ( $values['wffn_bundles_bundle']['role'] ?? '' ) && '' !== (string) ( $values['wffn_bundles_bundle']['thumbSrc'] ?? '' ) ) {
					$item['data']->add_meta_data( '_wffn_bundles_thumb_src', (string) $values['wffn_bundles_bundle']['thumbSrc'], true );
					self::$thumb_products[ $item['data']->get_id() ] = true;
				}
			}
			// Re-stamped every restore, so a cart predating the adapter is claimed.
			Discount_Layer::claim_line( $item );
		}
		return $item;
	}

	/**
	 * Load every cart line's bundle in one query, ahead of the per-group reads.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Cart $cart Cart being calculated.
	 * @return void
	 */
	public function prime_bundles( $cart ) {
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}
		$ids = array();
		foreach ( $cart->cart_contents as $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
			if ( $b && '' !== (string) ( $b['bundle'] ?? '' ) ) {
				$ids[ (string) $b['bundle'] ] = true;
			}
		}
		// Not for a cart with no bundle line: the call alone would load the bundle classes.
		if ( $ids ) {
			Bundle_Store::prime_configs( array_keys( $ids ) );
		}
	}

	/**
	 * Remove a bundle group whose bundle is no longer active, whose pricing
	 * was edited, or whose products changed price since it was added, so no
	 * group keeps charging the price it locked in at add time. Runs on
	 * cart/checkout page loads.
	 *
	 * @return void
	 */
	public function drop_stale_bundles() {
		$store_api                  = $this->store_api_validating;
		$this->store_api_validating = false;
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$bundle_by_group = array();
		$print_by_group    = array();
		$stale_groups      = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
			if ( ! $b || '' === (string) ( $b['group'] ?? '' ) || '' === (string) ( $b['bundle'] ?? '' ) ) {
				continue;
			}
			$group                       = (string) $b['group'];
			$bundle_by_group[ $group ] = (string) $b['bundle'];
			if ( '' !== (string) ( $b['pricingFingerprint'] ?? '' ) ) {
				$print_by_group[ $group ] = (string) $b['pricingFingerprint'];
			}
			if ( self::base_price_changed( $item ) ) {
				$stale_groups[ $group ] = true;
			}
		}
		if ( empty( $bundle_by_group ) ) {
			return;
		}
		Bundle_Store::prime_configs( array_values( $bundle_by_group ) );
		foreach ( $bundle_by_group as $group => $bundle_id ) {
			$bundle = Bundle_Store::get_config( $bundle_id );
			if ( ! $bundle || ! Bundle_Store::is_active_now( $bundle ) ) {
				$stale_groups[ $group ] = true;
				continue;
			}
			if ( isset( $print_by_group[ $group ] ) && ! hash_equals( self::pricing_fingerprint( $bundle ), $print_by_group[ $group ] ) ) {
				$stale_groups[ $group ] = true;
			}
		}
		if ( empty( $stale_groups ) ) {
			return;
		}
		$dropped   = array();
		$repriced  = array();
		$fbt_lines = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$b = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
			if ( ! $b || empty( $stale_groups[ (string) ( $b['group'] ?? '' ) ] ) ) {
				continue;
			}
			// A bought-together line is a product the shopper chose, so it stays at its own price.
			if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) ( $b['type'] ?? 0 ) ) {
				if ( empty( $b['fbtStale'] ) ) {
					$fbt_lines[] = $key;
					$product     = $item['data'] ?? null;
					$repriced[]  = $product instanceof \WC_Product ? $product->get_name() : (string) ( $item['product_id'] ?? 0 );
				}
				continue;
			}
			$product = $item['data'] ?? null;
			$title   = self::placeholder_title( $item );
			// A pack is named once, by its title, rather than member by member.
			if ( 'child' !== (string) ( $b['role'] ?? '' ) && ! self::is_nested( $b ) ) {
				$dropped[] = '' !== $title ? $title : ( $product instanceof \WC_Product ? $product->get_name() : (string) ( $item['product_id'] ?? 0 ) );
			}
			WC()->cart->remove_cart_item( $key );
		}
		// fbtStale means "the shopper has been told". A Store API call from a script
		// (a mini cart refreshing in the background) may never be shown, so only a page
		// the shopper is looking at marks it: the classic cart, or the block cart /
		// checkout page, whose server render reads the Store API without a REST request.
		$shown = ! $store_api || ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		if ( $shown ) {
			foreach ( $fbt_lines as $key ) {
				WC()->cart->cart_contents[ $key ]['wffn_bundles_bundle']['fbtStale'] = true;
			}
		}
		// Kept in the session now: that Store API read has already stored the cart.
		if ( $fbt_lines && $store_api && $shown ) {
			WC()->cart->calculate_totals();
		}
		if ( $repriced ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: comma-separated list of product names. */
					_n(
						'"%s" is no longer discounted as part of its set, so it is now at its regular price.',
						'"%s" are no longer discounted as part of their set, so they are now at their regular price.',
						count( $repriced ),
						'funnel-builder'
					),
					implode( ', ', $repriced )
				),
				// The set is re-priced once (fbtStale), so this is the only time the
				// shopper can be told; the Store API shows only errors.
				$store_api ? 'error' : 'notice'
			);
		}
		if ( empty( $dropped ) ) {
			return;
		}
		$text = sprintf(
			/* translators: %s: comma-separated list of product names. */
			_n(
				'"%s" is no longer available at that price and has been removed from your cart.',
				'"%s" are no longer available at that price and have been removed from your cart.',
				count( $dropped ),
				'funnel-builder'
			),
			implode( ', ', $dropped )
		);
		// The block clears this render's cart errors on its next cart read, which no longer has the group.
		if ( $store_api && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && self::tell_block_page( $text ) ) {
			return;
		}
		wc_add_notice( $text, 'error' );
		// The group is dropped once, so this is the only time the shopper can be told.
		// A Store API read turns the notice into its JSON errors, which a script such as
		// a mini cart refreshing in the background never shows: keep it for the next page.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! self::referred_by_cart_page() && WC()->session ) {
			$held          = (array) WC()->session->get( self::SESSION_HELD_DROPS, array() );
			$held[ $text ] = 'error';
			WC()->session->set( self::SESSION_HELD_DROPS, $held );
		}
	}

	/**
	 * Show a dropped group's sentence in the cart or checkout block's own notices,
	 * which stay until the shopper dismisses them.
	 *
	 * @param string $text The sentence.
	 * @return bool False when the page has neither block, so the caller queues it as usual.
	 */
	private static function tell_block_page( $text ) {
		$page = get_queried_object_id();
		if ( $page && has_block( 'woocommerce/cart', $page ) ) {
			$context = 'wc/cart';
		} elseif ( $page && has_block( 'woocommerce/checkout', $page ) ) {
			$context = 'wc/checkout';
		} else {
			return false;
		}
		$handle = 'wffn-bundles-dropped-notice';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, false, array( 'wp-data', 'wp-notices' ), WFFN_BUNDLES_VERSION, true );
		}
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			sprintf(
				'wp.data.dispatch("core/notices").createNotice("error",%1$s,{id:%2$s,context:%3$s});',
				wp_json_encode( wptexturize( wp_strip_all_tags( $text ) ) ),
				wp_json_encode( 'wffn-bundles-dropped-' . md5( $text ) ),
				wp_json_encode( $context )
			)
		);
		return true;
	}

	/**
	 * Run the stale check ahead of the FunnelKit checkout's notice printer.
	 *
	 * Its form prints the queued notices (display_top_notices(), priority 8), then
	 * fires woocommerce_check_cart_items; what that check queues is cleared unprinted
	 * (the cart-errors branch, or the order-summary refresh's undo message), so a group
	 * dropped there was never told. Checked here first, the drop's sentence is printed
	 * on the same page and the later check finds nothing left to drop.
	 * WooCommerce's own checkout is left as it is: its later check still shows the
	 * cart-errors page and keeps the sentence for the cart.
	 *
	 * @return void
	 */
	public function drop_stale_before_checkout_notices() {
		if ( did_action( 'wfacp_before_form' ) ) {
			$this->drop_stale_bundles();
		}
	}

	/**
	 * Run the stale check before the FunnelKit checkout rebuilds the cart.
	 *
	 * On a page load, for a shopper who has not yet pressed Place Order, the FunnelKit
	 * checkout empties the cart and re-adds the rows it is set up with
	 * (WFACP_Public::add_to_cart(), which fires wfacp_add_to_cart_init first). A row
	 * whose bundle is no longer live is not re-added, so the stale group just left
	 * the cart and no later check could tell the shopper. Checked here, its sentence
	 * is queued before the cart is emptied, and the form prints it on the same page.
	 * Ajax and REST requests do not rebuild the cart and keep the usual checks.
	 *
	 * @return void
	 */
	public function drop_stale_before_checkout_rebuild() {
		if ( ! did_action( 'wp_loaded' ) || wp_doing_ajax() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$this->drop_stale_bundles();
	}

	/**
	 * Whether the request came from the cart or checkout page, whose blocks show the
	 * Store API's cart errors themselves.
	 *
	 * @return bool
	 */
	private static function referred_by_cart_page() {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			return false;
		}
		$path = untrailingslashit( (string) wp_parse_url( $referer, PHP_URL_PATH ) );
		foreach ( array( 'cart', 'checkout' ) as $page ) {
			$url = wc_get_page_permalink( $page );
			if ( $url && untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) === $path ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Queue the drops a background Store API read answered, on the next page the
	 * shopper loads. WooCommerce keeps a queued notice until a page prints it.
	 *
	 * @return void
	 */
	public function tell_held_drops() {
		if ( wp_doing_ajax() || is_admin() || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$held = WC()->session->get( self::SESSION_HELD_DROPS );
		if ( empty( $held ) || ! is_array( $held ) ) {
			return;
		}
		WC()->session->set( self::SESSION_HELD_DROPS, null );
		foreach ( $held as $text => $type ) {
			if ( ! wc_has_notice( (string) $text, (string) $type ) ) {
				wc_add_notice( (string) $text, (string) $type );
			}
		}
	}

	/**
	 * Note that the Store API is about to run woocommerce_check_cart_items.
	 *
	 * CartController::validate_cart() fires this action, then (unless these errors
	 * stop it first) the check_cart_items pass, whose non-error notices it discards.
	 *
	 * @param \WP_Error $errors Cart errors collected so far.
	 * @return void
	 */
	public function note_store_api_validation( $errors ) {
		// Not on the checkout route: there an error refuses the order, and paying the
		// set's regular price is no error. The checkout shows the new total either way.
		$this->store_api_validating = ! ( $errors instanceof \WP_Error && $errors->has_errors() ) && ! self::is_store_api_checkout();
	}

	/**
	 * Whether this request is the Store API checkout route (placing the order).
	 *
	 * @return bool
	 */
	private static function is_store_api_checkout() {
		$route = Request_Context::raw_get( 'rest_route' );
		if ( ! is_string( $route ) || '' === $route ) {
			$route = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		}
		return (bool) preg_match( '#/wc/store(?:/v\d+)?/checkout#', (string) $route );
	}

	/**
	 * Hold every granted line at the quantity the offer granted it.
	 *
	 * A free add-on and a bundle member are both stored at `pricedUnit` 0 — the price
	 * lives on the container — so raising such a line hands out free goods.
	 * `woocommerce_cart_item_quantity` only swaps the input's markup, so clamping
	 * on recalculation is the one point every write route passes through.
	 *
	 * Deliberately not clamped: `container` and `single`, and a quantity-priced
	 * `member` line (the one the shopper edits to re-price). A Fixed Bundle
	 * `member` line is clamped by granted_quantity()'s own type check instead,
	 * since a quantity edit cannot re-price it.
	 *
	 * @param \WC_Cart $cart Cart being recalculated.
	 * @return void
	 */
	public function lock_quantities( $cart ) {
		if ( $this->locking_quantities || ! is_object( $cart ) || ! isset( $cart->cart_contents ) ) {
			return;
		}
		// A clone may hold only part of a group, so its containers are invisible and a
		// child would clamp to baseQty × 1. It inherited clamped quantities anyway.
		if ( self::is_foreign_cart( $cart ) ) {
			return;
		}
		$this->locking_quantities = true;

		// Container quantities first — a member's allowance is a multiple of them.
		$containers = array();
		foreach ( $cart->cart_contents as $ci ) {
			$b = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : null;
			if ( $b && 'container' === (string) ( $b['role'] ?? '' ) && ! empty( $b['group'] ) ) {
				$containers[ (string) $b['group'] ] = max( 1, (int) ( $ci['quantity'] ?? 1 ) );
			}
		}

		// A companion follows its set's host, so the host's quantity is its allowance.
		$fbt_hosts = array();
		foreach ( array_keys( self::fbt_groups( $cart->cart_contents ) ) as $group ) {
			$host_qty = self::fbt_host_quantity( $cart->cart_contents, $group );
			if ( null !== $host_qty ) {
				$fbt_hosts[ $group ] = $host_qty;
			}
		}

		foreach ( $cart->cart_contents as $key => $ci ) {
			if ( empty( $ci['wffn_bundles_bundle'] ) || ! is_array( $ci['wffn_bundles_bundle'] ) ) {
				continue;
			}
			$allowed = self::granted_quantity( $ci, $containers, $fbt_hosts );
			if ( null !== $allowed && (int) ( $ci['quantity'] ?? 0 ) !== $allowed ) {
				// Written straight into cart_contents: set_quantity() re-enters
				// calculate_totals(), and this runs inside its own before hook.
				$cart->cart_contents[ $key ]['quantity'] = $allowed;
			}
		}

		$this->locking_quantities = false;
	}

	/**
	 * Is this some other plugin's copy of the cart rather than the shopper's?
	 *
	 * Another plugin can price a clone of WC()->cart and call calculate_totals()
	 * on the copy, which re-enters our own hooks with a cart we must not treat as
	 * authoritative.
	 *
	 * @param mixed $cart Cart handed to a totals hook.
	 * @return bool
	 */
	private static function is_foreign_cart( $cart ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		return WC()->cart !== $cart;
	}

	/**
	 * The quantity a granted line is entitled to, or null when it is the shopper's
	 * to change.
	 *
	 * `baseQty` is what the line was added with; an add-on line placed before it
	 * was stored falls back to the bundle config.
	 *
	 * @param array $item       Cart item.
	 * @param int[] $containers Container quantity, keyed by group.
	 * @param int[] $fbt_hosts  Bought-together host quantity, keyed by group.
	 * @return int|null
	 */
	private static function granted_quantity( array $item, array $containers, array $fbt_hosts = array() ) {
		$b    = $item['wffn_bundles_bundle'];
		$role = (string) ( $b['role'] ?? '' );

		if ( 'fbt_companion' === $role ) {
			$group = (string) ( $b['group'] ?? '' );
			return isset( $fbt_hosts[ $group ] ) ? max( 1, (int) ( $b['baseQty'] ?? 1 ) ) * $fbt_hosts[ $group ] : null;
		}

		if ( 'child' === $role ) {
			$group = (string) ( $b['group'] ?? '' );
			return max( 1, (int) ( $b['baseQty'] ?? 1 ) ) * ( $containers[ $group ] ?? 1 );
		}
		// A single-product Fixed Bundle pack is not re-priced on a quantity edit, so
		// its discounted pricedUnit must stay pinned to the quantity it was granted at.
		if ( 'member' === $role && Bundle_Store::TYPE_FIXED_BUNDLES === (int) ( $b['type'] ?? 0 ) ) {
			return max( 1, (int) ( $b['baseQty'] ?? 1 ) );
		}
		if ( 'heading' === $role ) {
			return 1;
		}
		if ( self::nested_quantity_locked( $item ) ) {
			return max( 1, (int) ( $b['baseQty'] ?? 1 ) );
		}
		if ( ! in_array( $role, self::addon_roles(), true ) ) {
			return null;
		}
		if ( isset( $b['baseQty'] ) ) {
			return max( 1, (int) $b['baseQty'] );
		}
		/**
		 * Filters the quantity a pre-`baseQty` add-on line is held to.
		 *
		 * Only beta carts reach this: every line placed since stores `baseQty`.
		 *
		 * @param int    $qty  Quantity to lock the line to.
		 * @param array  $item Cart item.
		 * @param string $role The line's bundle role.
		 */
		return max( 1, (int) apply_filters( 'wffn_bundles_addon_granted_quantity', 1, $item, $role ) );
	}

	/**
	 * Set each bundle item's discounted unit price from its stored data, so the
	 * discount is identical on every recalculation and never compounds. Members
	 * carry a precomputed `pricedUnit`; a line carrying only a discount spec has
	 * its per-unit price derived from the line quantity.
	 *
	 * `wffn_bundles_cart_reprice` fires before the loop, so a type priced from the
	 * live quantity re-resolves rather than going stale.
	 *
	 * Everything here is base currency, so a switcher converts exactly once on the
	 * way out. The exception is a member whose price the switcher resolves itself
	 * — see price_custom_member().
	 *
	 * @param \WC_Cart $cart Cart being recalculated.
	 * @return void
	 */
	public function apply_pricing( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}
		// A third-party plugin can re-enter before_calculate_totals while this pass
		// runs; pricing comes from stored data, so a second pass is a no-op.
		if ( $this->applying_pricing ) {
			return;
		}
		$this->applying_pricing = true;
		// Rebuilt every pass so a departed line leaves no ratio behind, but additive
		// on a clone: the filters outlive the pass (see add_ratio_filters).
		if ( self::is_foreign_cart( $cart ) ) {
			if ( ! $this->custom_price_ratios instanceof \SplObjectStorage ) {
				$this->custom_price_ratios = new \SplObjectStorage();
			}
		} else {
			$this->custom_price_ratios = new \SplObjectStorage();
			$this->engine_absolutes    = array();
			/**
			 * Fires before the pricing pass reads the shopper's cart.
			 *
			 * A type whose discount follows the live line quantity re-resolves it
			 * here, so the pass below charges what the cart now holds.
			 *
			 * @since 1.0.0
			 *
			 * @param \WC_Cart $cart The cart being recalculated.
			 */
			do_action( 'wffn_bundles_cart_reprice', $cart );
		}
		$dp                        = self::base_decimals();
		$children                  = self::children_by_group( $cart );
		$containers                = array();
		$set_lines                 = array();
		$base_lines                = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( 'container' === (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) ) {
				$containers[ (string) ( $item['wffn_bundles_bundle']['group'] ?? '' ) ] = true;
			}
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['wffn_bundles_bundle'] ) || ! isset( $item['data'] ) ) {
				continue;
			}
			// A child is 0 only beside the container that charges its pack; alone it keeps its own price.
			if ( 'child' === (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) && ! isset( $containers[ (string) ( $item['wffn_bundles_bundle']['group'] ?? '' ) ] ) ) {
				continue;
			}
			// Written through cart_contents, not the loop variable: get_cart() returns
			// an array_filter copy, so a stamp on $item is discarded.
			if ( isset( $cart->cart_contents[ $key ] ) ) {
				Discount_Layer::claim_line( $cart->cart_contents[ $key ] );
			}
			$b   = $item['wffn_bundles_bundle'];
			$qty = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			// A push-style engine applies its rule on top of what is written; a
			// pull-style one never writes, so its rule is folded in here instead.
			$unfolded = self::stored_unit( $item );
			$unit     = $unfolded;
			if ( ! Discount_Layer::supplies_own_price() ) {
				$unit = self::fold_engine_discount( $item, $unfolded );
			} elseif ( Discount_Layer::is_active() ) {
				// The engine writes the charged unit after this pass; custom_price_ratio() reads it back.
				$this->add_ratio_filters();
			}
			if ( 'container' === (string) ( $b['role'] ?? '' ) ) {
				$fold_ratio = $unfolded > 0 ? $unit / $unfolded : 1.0;
				if ( $this->price_container( $item['data'], $children[ (string) ( $b['group'] ?? '' ) ] ?? array(), $fold_ratio ) ) {
					continue;
				}
			} elseif ( $this->price_custom_member( $item['data'], (float) ( $b['unit'] ?? 0 ), $unit ) ) {
				continue;
			}
			/**
			 * Filters a bundle line's final per-unit price before it is set on the cart.
			 *
			 * Covers both stored `pricedUnit` lines and quantity-derived anchor lines.
			 * Base currency in, base currency out.
			 *
			 * @since 1.0.0
			 *
			 * @param float    $unit Discounted unit price, base currency.
			 * @param array    $b    The line's stored `wffn_bundles_bundle` data.
			 * @param array    $item Cart item.
			 * @param \WC_Cart $cart Cart being recalculated.
			 */
			$unit = (float) apply_filters( 'wffn_bundles_cart_item_unit_price', (float) $unit, $b, $item, $cart );
			// The one rounding, on the line total in base, not per unit: a 12-item box
			// at 3.333333 must charge 40.00, not 12 × 3.33.
			$base_line = round( $unit * $qty, $dp );
			// Base, always: raw_base_price() and the container reconciliation read
			// this property back and must see base.
			$item['data']->set_price( $base_line / $qty );
			if ( in_array( (string) ( $b['role'] ?? '' ), array( 'member', 'fbt_host', 'fbt_companion' ), true ) && '' !== (string) ( $b['group'] ?? '' ) ) {
				$base_lines[ (string) $b['group'] ][] = array(
					'product' => $item['data'],
					'key'     => (string) $key,
					'qty'     => $qty,
					'base'    => $base_line,
					'raw'     => $unit * $qty,
				);
			}
			$converted = $this->price_converted_line( $item['data'], $base_line, $qty, $key );
			if ( null !== $converted && Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) ( $b['type'] ?? 0 ) ) {
				$set_lines[ (string) ( $b['group'] ?? '' ) ][] = array(
					'product' => $item['data'],
					'key'     => (string) $key,
					'qty'     => $qty,
					'base'    => $base_line,
					'unit'    => $converted,
				);
			}
		}
		foreach ( self::settle_base_cents( $base_lines, $dp ) as $line ) {
			$line['product']->set_price( $line['base'] / $line['qty'] );
			$converted = $this->price_converted_line( $line['product'], $line['base'], $line['qty'], $line['key'] );
			foreach ( $set_lines as $g => $lines ) {
				foreach ( $lines as $i => $set_line ) {
					if ( $set_line['key'] === $line['key'] && null !== $converted ) {
						$set_lines[ $g ][ $i ]['base'] = $line['base'];
						$set_lines[ $g ][ $i ]['unit'] = $converted;
					}
				}
			}
		}
		$this->settle_set_cents( $set_lines );
		$this->applying_pricing = false;
	}

	/**
	 * The lines whose rounded base total must move so each group charges its unrounded total, rounded once.
	 *
	 * Lines rounded one by one can drift a cent from the group's discounted total
	 * ($130 less $10 charging 119.99); the largest line absorbs the drift.
	 *
	 * @param array $groups Lines per group: product, key, qty, base (rounded), raw (unrounded).
	 * @param int   $dp     Base currency decimals.
	 * @return array[] The adjusted lines, `base` already corrected.
	 */
	public static function settle_base_cents( array $groups, $dp ) {
		$moved = array();
		$step  = 1 / pow( 10, (int) $dp );
		foreach ( $groups as $lines ) {
			if ( count( $lines ) < 2 ) {
				continue;
			}
			$raw     = 0.0;
			$rounded = 0.0;
			$big     = null;
			foreach ( $lines as $i => $line ) {
				$raw     += (float) $line['raw'];
				$rounded += (float) $line['base'];
				if ( null === $big || $line['base'] > $lines[ $big ]['base'] ) {
					$big = $i;
				}
			}
			$diff = round( round( $raw, $dp ) - $rounded, $dp );
			// Only rounding drift is settled; anything larger is a real price difference.
			if ( abs( $diff ) < $step / 2 || abs( $diff ) > $step * count( $lines ) ) {
				continue;
			}
			$line         = $lines[ $big ];
			$line['base'] = round( $line['base'] + $diff, $dp );
			if ( $line['base'] > 0 ) {
				$moved[] = $line;
			}
		}
		return $moved;
	}

	/**
	 * Discount a member whose price the currency switcher resolves itself.
	 *
	 * A manual per-currency, user-role or geo price replaces whatever the product
	 * object carries, so `set_price()` in base is discarded. For those lines the
	 * discount travels as a unitless ratio applied above the switcher's own filter.
	 *
	 * @param \WC_Product $product    The cart line's product object.
	 * @param float       $original   Undiscounted unit price, base currency.
	 * @param float       $discounted Discounted unit price, base currency.
	 * @return bool True when the line was handed to the ratio path.
	 */
	private function price_custom_member( $product, $original, $discounted ) {
		if ( ! Layer::has_custom_price( $product ) ) {
			return false;
		}
		$this->add_ratio_filters();
		$this->custom_price_ratios[ $product ] = array( 'ratio', $original > 0 ? $discounted / $original : 1.0 );
		return true;
	}

	/**
	 * A Fixed Bundle container's child lines, keyed by bundle group.
	 *
	 * @param \WC_Cart $cart Cart being recalculated.
	 * @return array<string,array<int,array>> group => [ {product, qty, unit, share} ]
	 */
	private static function children_by_group( $cart ) {
		$out = array();
		foreach ( $cart->get_cart() as $ci ) {
			$b = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : null;
			if ( ! $b || 'child' !== (string) ( $b['role'] ?? '' ) || empty( $b['group'] ) || ! isset( $ci['data'] ) ) {
				continue;
			}
			$unit                              = (float) ( $b['unit'] ?? 0 );
			$out[ (string) $b['group'] ][]     = array(
				'product' => $ci['data'],
				// Per container, not the live line quantity: buying two bundles has
				// already scaled every child's quantity by two.
				'qty'     => max( 1, (int) ( $b['baseQty'] ?? 1 ) ),
				'unit'    => $unit,
				'share'   => (float) ( $b['pricedShare'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Price a Fixed Bundle container whose members the switcher prices itself.
	 *
	 * The switcher has no manual price for the placeholder container, so it
	 * rate-converts a total built from the members' raw prices — the wrong number
	 * once a member's real price in this currency is a manual one, and one neither
	 * the per-member ratio nor `set_price()` can reach, because the line being
	 * charged is not the member's. So the container is priced directly from its
	 * members' display prices, each still carrying its own discount.
	 *
	 * @param \WC_Product $product    The container line's product object.
	 * @param array       $children   Child rows from children_by_group().
	 * @param float       $fold_ratio Pull-style engine's fold as a ratio; 1.0 when nothing folds.
	 * @return bool True when the line was handed to the override path.
	 */
	private function price_container( $product, array $children, $fold_ratio = 1.0 ) {
		if ( empty( $children ) || ! Layer::is_active() ) {
			return false;
		}
		// The stored base unit, not the live price: by the second pricing pass every
		// child has been set_price( 0 ), and resolving against zero prices nothing.
		$custom = false;
		foreach ( $children as $child ) {
			if ( Layer::has_custom_price( $child['product'], $child['unit'] ) ) {
				$custom = true;
				break;
			}
		}
		if ( ! $custom ) {
			return false;
		}
		// Each member's own discount, recovered from the share it contributed.
		$wanted = 0.0;
		foreach ( $children as $child ) {
			$ratio   = $child['unit'] > 0 ? $child['share'] / $child['unit'] : 0.0;
			$wanted += Layer::get_display_price( $child['product'], $child['unit'] ) * $child['qty'] * $ratio;
		}

		// Unitless, so exact under any switcher.
		$wanted *= $fold_ratio;

		if ( $wanted <= 0 ) {
			return false;
		}
		$this->add_ratio_filters();
		// Absolute, not a ratio: the placeholder's catalog price is 0, so there is
		// nothing to scale.
		$this->custom_price_ratios[ $product ] = array( 'absolute', $wanted );
		return true;
	}

	/**
	 * The unit price to write onto the cart line's product object.
	 *
	 * Base currency normally: the switcher converts once when the price is read and
	 * `price_converted_line()` pins the exact figure above it, which relies on the
	 * read going through filters. A discount engine can persist the line totals
	 * inside its own no-filter window, where the raw property alone decides what is
	 * charged — so with one active the raw price and the pinned figure are made to
	 * agree.
	 *
	 * @param float $base_unit Discounted unit price in base currency.
	 * @return float
	 */
	public static function charge_unit( $base_unit ) {
		if ( ! Discount_Layer::is_active() || ! Layer::is_active() ) {
			return (float) $base_unit;
		}
		// WOOCS converts whatever price it is shown, engine window or not, so a
		// converted figure written here would be converted twice.
		if ( Layer::reconverts_written_price() ) {
			return (float) $base_unit;
		}
		if ( Layer::get_current_currency() === Layer::get_base_currency() ) {
			return (float) $base_unit;
		}
		return Layer::to_float( Layer::convert( $base_unit ) );
	}

	/**
	 * Hand a bundle line its own converted price, at full precision.
	 *
	 * The line is rounded once in base; a switcher then rounds the converted unit
	 * to the display currency inside its own filter, and WooCommerce multiplies
	 * that by the quantity — so the sub-cent residue is paid once per unit.
	 * Supplying the converted price above the switcher's filter leaves WooCommerce
	 * the one rounding. Still exactly one conversion; only its priority moves.
	 *
	 * The base unit is converted, not the base line: a non-linear adapter must
	 * round the figure the product page advertised, or the line total stops
	 * equalling quantity times the displayed unit.
	 *
	 * @param \WC_Product $product   The cart line's product object.
	 * @param float       $base_line The line's discounted total in base currency.
	 * @param int         $qty       Line quantity.
	 * @param string      $key       Cart item key, for the engine fallback map.
	 * @return float|null The converted unit pinned on the line, or null when none was.
	 */
	private function price_converted_line( $product, $base_line, $qty, $key = '' ) {
		if ( $base_line <= 0 || ! Layer::is_active() ) {
			return null;
		}
		if ( Layer::get_current_currency() === Layer::get_base_currency() ) {
			return null;
		}
		$this->add_ratio_filters();
		$converted                             = Layer::convert( $base_line / max( 1, (int) $qty ) );
		$this->custom_price_ratios[ $product ] = array( 'absolute', $converted );
		if ( '' !== $key ) {
			$this->engine_absolutes[ $key ] = $converted;
		}
		return (float) $converted;
	}

	/**
	 * Move a bought-together set's rounding cent onto one line, so the set charges its converted total.
	 *
	 * The set is split and rounded in base, then each line is converted and rounded
	 * again, so two half cents can both round up: 17.145 + 12.855 charged 30.01 for
	 * a set the widget quotes at 30.00.
	 *
	 * @param array $groups Converted lines per set group: product, key, qty, base, unit.
	 * @return void
	 */
	private function settle_set_cents( array $groups ) {
		$dp   = (int) Layer::get_decimals();
		$cent = 0.5 / pow( 10, $dp );
		foreach ( $groups as $lines ) {
			if ( count( $lines ) < 2 ) {
				continue;
			}
			$base    = 0.0;
			$charged = 0.0;
			foreach ( $lines as $line ) {
				$base    += $line['base'];
				$charged += round( $line['unit'] * $line['qty'], $dp );
			}
			$diff = round( Layer::to_float( Layer::convert( $base ) ), $dp ) - $charged;
			if ( abs( $diff ) < $cent ) {
				continue;
			}
			$last  = end( $lines );
			$total = round( round( $last['unit'] * $last['qty'], $dp ) + $diff, $dp );
			if ( $total <= 0 ) {
				continue;
			}
			$unit                                          = $total / $last['qty'];
			$this->custom_price_ratios[ $last['product'] ] = array( 'absolute', $unit );
			if ( '' !== $last['key'] ) {
				$this->engine_absolutes[ $last['key'] ] = $unit;
			}
		}
	}

	/**
	 * Register the priority-10000 ratio filters, once per request.
	 *
	 * Above the switcher's own 9999, so what is scaled is the price it resolved.
	 * They stay registered for the rest of the request: the cart, mini-cart,
	 * checkout and order review all read `get_price()` long after
	 * `calculate_totals()` returned. Keying on a specific cart line's product
	 * object keeps the reach narrow.
	 *
	 * @return void
	 */
	private function add_ratio_filters() {
		if ( $this->ratio_filters_added ) {
			return;
		}
		$this->ratio_filters_added = true;
		foreach ( self::PRICE_HOOKS as $hook ) {
			add_filter( $hook, array( $this, 'custom_price_ratio' ), 10000, 2 );
		}
		foreach ( self::SALE_PRICE_HOOKS as $hook ) {
			add_filter( $hook, array( $this, 'custom_sale_price_ratio' ), 10000, 2 );
		}
	}

	/**
	 * Scale a switcher-resolved sale price by this cart line's discount ratio.
	 *
	 * Ratio entries only — see SALE_PRICE_HOOKS.
	 *
	 * @param mixed       $price   Sale price as the switcher left it.
	 * @param \WC_Product $product Product being read.
	 * @return mixed
	 */
	public function custom_sale_price_ratio( $price, $product ) {
		if ( ! $product instanceof \WC_Product || '' === $price || null === $price
			|| null === $this->custom_price_ratios || ! isset( $this->custom_price_ratios[ $product ] ) ) {
			return $price;
		}
		list( $mode, $value ) = $this->custom_price_ratios[ $product ];
		return 'ratio' === $mode ? Layer::to_float( $price ) * $value : $price;
	}

	/**
	 * Scale a switcher-resolved price by this cart line's discount ratio.
	 *
	 * @param mixed       $price   Price as the switcher left it (may be a string).
	 * @param \WC_Product $product Product being read.
	 * @return mixed
	 */
	public function custom_price_ratio( $price, $product ) {
		if ( ! $product instanceof \WC_Product || null === $this->custom_price_ratios ) {
			return $price;
		}
		// Our pins predate the engine's pricing, so its charged unit outranks them.
		$charged = $this->engine_charged_unit( $product );
		if ( null !== $charged ) {
			return $charged;
		}
		if ( ! isset( $this->custom_price_ratios[ $product ] ) ) {
			$absolute = $this->engine_absolute_for( $product );
			return null === $absolute ? $price : $absolute;
		}
		list( $mode, $value ) = $this->custom_price_ratios[ $product ];
		if ( 'absolute' === $mode ) {
			return $value;
		}
		// A member with no price at all has nothing to discount.
		if ( '' === $price || null === $price ) {
			return $price;
		}
		return Layer::to_float( $price ) * $value;
	}

	/**
	 * The unit a push-style discount engine charged this bundle line, or null.
	 *
	 * Identity against the live cart, as in engine_absolute_for(). Display only:
	 * mid-calculation the engine's record is from the previous pass, so totals keep
	 * reading the figures our own pricing pass writes.
	 *
	 * @param \WC_Product $product Product being priced.
	 * @return float|null Unit in the shopper's currency, or null when no engine priced this line.
	 */
	private function engine_charged_unit( $product ) {
		if ( did_action( 'woocommerce_before_calculate_totals' ) > did_action( 'woocommerce_after_calculate_totals' ) ) {
			return null;
		}
		if ( ! Discount_Layer::is_active() || ! Discount_Layer::supplies_own_price() ) {
			return null;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return null;
		}
		foreach ( WC()->cart->cart_contents as $item ) {
			if ( empty( $item['wffn_bundles_bundle'] ) || ! isset( $item['data'] ) || $item['data'] !== $product ) {
				continue;
			}
			return Discount_Layer::get_charged_unit( $item );
		}
		return null;
	}

	/**
	 * This line's pinned price when a discount engine has swapped the object.
	 *
	 * The object-keyed map cannot follow a clone, so the cart itself is asked
	 * which line this object now belongs to. Identity against the live cart, not
	 * a product id, so a standalone line of the same product stays untouched.
	 *
	 * @param \WC_Product $product Product being priced.
	 * @return float|null Pinned price, or null when this is not an engine-swapped bundle line.
	 */
	private function engine_absolute_for( $product ) {
		if ( empty( $this->engine_absolutes ) || ! Discount_Layer::is_active() ) {
			return null;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return null;
		}
		foreach ( WC()->cart->cart_contents as $key => $item ) {
			if ( ! isset( $this->engine_absolutes[ $key ], $item['data'] ) ) {
				continue;
			}
			if ( $item['data'] === $product ) {
				return $this->engine_absolutes[ $key ];
			}
		}
		return null;
	}

	/**
	 * Decimal places of the store's base currency.
	 *
	 * `wc_get_price_decimals()` answers for the display currency once a switcher
	 * is filtering it, which is the wrong precision for base-currency arithmetic.
	 *
	 * @return int
	 */
	private static function base_decimals() {
		return Layer::get_decimals( Layer::get_base_currency() );
	}

	/**
	 * Show the offer on the cart/checkout line. Grouped members already sit under
	 * the container title, so only add-ons keep their label.
	 *
	 * @param array $data Item meta rows so far.
	 * @param array $item Cart item.
	 * @return array
	 */
	public function item_meta( $data, $item ) {
		$role = (string) ( $item['wffn_bundles_bundle']['role'] ?? '' );
		// A bought-together set reads as ordinary lines, so neither host nor companion carries the offer.
		$in_fbt = in_array( $role, array( 'fbt_host', 'fbt_companion' ), true );
		// An upsell add-on reads as an ordinary product line; a free gift keeps its label, which explains its price.
		if ( ! empty( $item['wffn_bundles_bundle']['label'] ) && ! self::is_grouped_role( $role ) && ! $in_fbt && 'upsell' !== $role ) {
			$data[] = array(
				'key' => __( 'Offer', 'funnel-builder' ),
				// Wrapped so the pair can be recognised: WooCommerce prints item meta
				// as a shared `dl.variation` and only ours should be restyled.
				'value' => sprintf(
					'<span class="wffn-bundles-cart-offer">%s</span>',
					esc_html( self::offer_label( (string) $item['wffn_bundles_bundle']['label'], $role ) )
				),
			);
		}
		// The block cart has no quantity hook of its own for a locked line, so its Store API
		// carries the count as a row; the classic cart and the drawer print it through item_quantity().
		if ( self::in_store_api() ) {
			$qty = trim( wp_strip_all_tags( $this->item_quantity( '', '', $item ) ) );
			if ( '' !== $qty ) {
				$data[] = array(
					'key'   => self::qty_label(),
					'value' => trim( (string) preg_replace( '/^[^:]*:/', '', $qty ) ),
				);
			}
		}
		return $data;
	}

	/**
	 * An add-on line's offer label in the shopper's language.
	 *
	 * The label is saved when the line is added; the add-on that placed it may
	 * give its current wording, so a translation added later reaches it too.
	 *
	 * @param string $label Saved label.
	 * @param string $role  The line's role.
	 * @return string
	 */
	public static function offer_label( $label, $role ) {
		/**
		 * The offer label to show for an add-on line.
		 *
		 * @param string $label Label saved on the line.
		 * @param string $role  The line's role, e.g. 'gift'.
		 */
		$shown = apply_filters( 'wffn_bundles_offer_label', $label, (string) $role );

		return is_string( $shown ) && '' !== $shown ? $shown : $label;
	}

	/**
	 * Is the cart being serialised for the Store API (block cart, mini-cart, checkout)?
	 *
	 * Also true for the cart data the block pages preload during the page render,
	 * which runs the schema in-process, where REST_REQUEST is not set.
	 *
	 * @return bool
	 */
	private static function in_store_api() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- read only, to tell which caller is serialising the cart.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $frame ) {
			if ( isset( $frame['class'] ) && false !== strpos( $frame['class'], 'StoreApi' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Tag bundle lines so the classic cart/checkout can group them; the block cart
	 * gets the same classes from the Store API extension and checkout filters.
	 *
	 * @param string $class Row classes so far.
	 * @param array  $item  Cart item.
	 * @return string
	 */
	public function item_class( $class, $item ) {
		if ( ! empty( $item['wffn_bundles_bundle'] ) ) {
			$class .= ' wffn-bundles-cart-bundle';
			$role   = (string) ( $item['wffn_bundles_bundle']['role'] ?? '' );
			if ( in_array( $role, array( 'container', 'parent', 'heading' ), true ) ) {
				$class .= ' wffn-bundles-cart-parent';
				$class .= 'heading' === $role ? ' wffn-bundles-cart-heading' : '';
			} elseif ( 'child' === $role ) {
				$class .= ' wffn-bundles-cart-child';
			} elseif ( self::is_nested( $item['wffn_bundles_bundle'] ) ) {
				$class .= ' wffn-bundles-cart-child wffn-bundles-cart-nested';
			}
			// A bought-together set carries no parent or child class: its lines are not indented.
			if ( ! empty( $item['wffn_bundles_bundle']['group'] ) ) {
				$class .= ' wffn-bundles-cart-group-' . sanitize_html_class( (string) $item['wffn_bundles_bundle']['group'] );
			}
		}
		return $class;
	}

	/**
	 * In the classic cart/checkout the container line shows the bundle title.
	 *
	 * @param string $name Name so far.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function item_name( $name, $item ) {
		$role = (string) ( $item['wffn_bundles_bundle']['role'] ?? '' );
		if ( in_array( $role, array( 'container', 'parent', 'heading' ), true ) && ! empty( $item['wffn_bundles_bundle']['bundleTitle'] ) ) {
			return esc_html( (string) $item['wffn_bundles_bundle']['bundleTitle'] );
		}
		return $name;
	}

	/**
	 * The bundle title a placeholder line stands for, or '' for any other line.
	 *
	 * @param array $item Cart item, live or removed.
	 * @return string
	 */
	public static function placeholder_title( $item ) {
		$title = (string) ( $item['wffn_bundles_bundle']['bundleTitle'] ?? '' );
		if ( '' === $title || self::container_id() !== (int) ( $item['product_id'] ?? 0 ) ) {
			return '';
		}
		return $title;
	}

	/**
	 * Give each placeholder line's own product object its bundle's title.
	 *
	 * The object is the line's private copy, so the placeholder product itself is untouched.
	 *
	 * @return void
	 */
	public function name_container_lines() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->cart_contents as $item ) {
			$title = self::placeholder_title( $item );
			if ( '' !== $title && ( $item['data'] ?? null ) instanceof \WC_Product && $item['data']->get_name( 'edit' ) !== $title ) {
				$item['data']->set_name( $title );
			}
		}
	}

	/**
	 * Name a removed placeholder line by its bundle in the "removed. Undo?" notice.
	 *
	 * @param string $title Quoted item name so far.
	 * @param array  $item  The removed cart item.
	 * @return string
	 */
	public function removed_title( $title, $item ) {
		$bundle_title = self::placeholder_title( is_array( $item ) ? $item : array() );
		/* translators: %s: bundle title. */
		return '' === $bundle_title ? $title : sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'funnel-builder' ), esc_html( $bundle_title ) );
	}

	/**
	 * Members are read-only: show a static "Qty: N" instead of a quantity input.
	 *
	 * Add-on lines join members here: lock_quantities() holds them all to what
	 * the offer granted, so an editable input would only snap back.
	 *
	 * @param string     $html Quantity markup so far.
	 * @param string     $key  Cart item key.
	 * @param array|null $item Cart item, when the caller has it.
	 * @return string
	 */
	public function item_quantity( $html, $key, $item = null ) {
		$item = is_array( $item ) ? $item : ( WC()->cart->cart_contents[ $key ] ?? array() );
		$b    = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
		$role = (string) ( $b['role'] ?? '' );
		// A single-product Fixed Bundle pack is not re-priced by quantity, unlike a
		// quantity-priced 'member' line; see granted_quantity().
		$fixed_member = 'member' === $role && Bundle_Store::TYPE_FIXED_BUNDLES === (int) ( $b['type'] ?? 0 );
		if ( 'heading' === $role ) {
			return '';
		}
		$fixed_member = $fixed_member || self::nested_quantity_locked( $item );
		$fixed_member = $fixed_member || self::fbt_companion_locked( $item );
		if ( ! $fixed_member && ! in_array( $role, array_merge( array( 'child' ), self::addon_roles() ), true ) ) {
			return $html;
		}
		$qty = max( 1, (int) ( $item['quantity'] ?? 1 ) );
		// An add-on stands on its own row, where "Qty: 1" is clutter; a member is
		// indented under its container, where the count reads as part of the group.
		if ( 1 === $qty && 'child' !== $role ) {
			return '';
		}
		return '<span class="wffn-bundles-cart-childqty">' . esc_html( self::qty_text( $qty ) ) . '</span>';
	}

	/**
	 * "Qty: N", in the store's language.
	 *
	 * Until this plugin's own translation exists, WooCommerce's own "Qty: 1" sets the
	 * wording and spacing (French "Qté : 1"), with the count put in its place.
	 *
	 * @param int $qty Quantity.
	 * @return string
	 */
	public static function qty_text( $qty ) {
		/* translators: 1: the "Qty" label, 2: a quantity. */
		$pattern = _x( '%1$s: %2$d', 'quantity label and count', 'funnel-builder' );
		if ( '%1$s: %2$d' === $pattern && 'Qty' === __( 'Qty', 'funnel-builder' ) ) {
			$woo = (string) get_translations_for_domain( 'woocommerce' )->translate( 'Qty: 1' );
			if ( 'Qty: 1' !== $woo && 1 === preg_match( '/^(.*\D)1$/su', $woo, $m ) ) {
				return $m[1] . (int) $qty;
			}
		}
		return sprintf( $pattern, self::qty_label(), (int) $qty );
	}

	/**
	 * The "Qty" label, as WooCommerce words it.
	 *
	 * Until this plugin's own translation of it exists, the store's WooCommerce
	 * translation of the same word is used, so a translated store shows it in its
	 * language from the start.
	 *
	 * @return string
	 */
	public static function qty_label() {
		$label = __( 'Qty', 'funnel-builder' );
		if ( 'Qty' !== $label ) {
			return $label;
		}
		$woo = get_translations_for_domain( 'woocommerce' )->translate( 'Qty' );
		return '' !== (string) $woo ? (string) $woo : $label;
	}

	/**
	 * Members cannot be removed individually, only the whole bundle.
	 *
	 * @param string $html Remove-link markup so far.
	 * @param string $key  Cart item key.
	 * @return string
	 */
	public function item_remove_link( $html, $key ) {
		$item = WC()->cart->cart_contents[ $key ] ?? array();
		if ( 'child' === (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) || self::is_nested( (array) ( $item['wffn_bundles_bundle'] ?? array() ) ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * The total sits on the container; members are priced 0, so hide their price.
	 *
	 * @param string $html Price markup so far.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function item_price( $html, $item ) {
		$role = (string) ( $item['wffn_bundles_bundle']['role'] ?? '' );
		if ( 'child' === $role ) {
			return '';
		}
		if ( 'heading' === $role ) {
			return wc_price( self::heading_total( $item ) );
		}
		return $html;
	}

	/**
	 * Whether a bundle line is a member nested under a heading line.
	 *
	 * @param array $b The line's `wffn_bundles_bundle` data.
	 * @return bool
	 */
	public static function is_nested( array $b ) {
		return 'member' === (string) ( $b['role'] ?? '' ) && ! empty( $b['nested'] );
	}

	/**
	 * Whether a member nested under a heading is held to the quantity it was added with.
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function nested_quantity_locked( $item ) {
		$b = is_array( $item ) && is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
		if ( ! self::is_nested( $b ) ) {
			return false;
		}
		/**
		 * Filters whether a nested bundle line's quantity is the shopper's to change.
		 *
		 * A type that re-prices its whole group from the live quantity answers true.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $editable Whether the quantity can change. False by default.
		 * @param array $b        The line's `wffn_bundles_bundle` data.
		 * @param array $item     The cart item.
		 */
		return ! apply_filters( 'wffn_bundles_nested_quantity_editable', false, $b, $item );
	}

	/**
	 * The sum a heading line stands for: its nested members' priced lines, as displayed.
	 *
	 * The heading itself is priced 0, so the figure is read off the members, which
	 * already carry the switcher's conversion and the shop's tax display setting.
	 *
	 * @param array $item The heading cart item.
	 * @return float
	 */
	public static function heading_total( array $item ) {
		$group = (string) ( $item['wffn_bundles_bundle']['group'] ?? '' );
		$total = 0.0;
		if ( '' === $group || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $total;
		}
		foreach ( WC()->cart->get_cart() as $ci ) {
			$cb = is_array( $ci['wffn_bundles_bundle'] ?? null ) ? $ci['wffn_bundles_bundle'] : array();
			if ( (string) ( $cb['group'] ?? '' ) !== $group || ! self::is_nested( $cb ) || ! isset( $ci['data'] ) ) {
				continue;
			}
			// A computed subtotal already carries whatever re-priced the line after the bundle did.
			if ( isset( $ci['line_subtotal'] ) ) {
				$total += (float) $ci['line_subtotal'] + ( WC()->cart->display_prices_including_tax() ? (float) ( $ci['line_subtotal_tax'] ?? 0 ) : 0.0 );
				continue;
			}
			$total += (float) wc_get_price_to_display( $ci['data'], array( 'qty' => max( 1, (int) $ci['quantity'] ) ) );
		}
		return $total;
	}

	/**
	 * The container shows the bundle's lead image, not the placeholder's (classic
	 * cart). The configured URL renders directly, so it matches even when it is
	 * not a local attachment; then the resolved attachment, then a member image.
	 *
	 * @param string $thumbnail Thumbnail markup so far.
	 * @param array  $item      Cart item.
	 * @return string
	 */
	public function item_thumbnail( $thumbnail, $item ) {
		// An offer image can sit on a lone bundle line too, not only on a container.
		$own = (string) ( $item['wffn_bundles_bundle']['thumbSrc'] ?? '' );
		if ( '' !== $own && '' !== (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ) ) {
			return '<img src="' . esc_url( $own ) . '" alt="" class="woocommerce-placeholder wp-post-image" />';
		}
		if ( in_array( (string) ( $item['wffn_bundles_bundle']['role'] ?? '' ), array( 'container', 'heading' ), true ) ) {
			$src = (string) ( $item['wffn_bundles_bundle']['thumbSrc'] ?? '' );
			if ( '' !== $src ) {
				return '<img src="' . esc_url( $src ) . '" alt="" class="woocommerce-placeholder wp-post-image" />';
			}
			$img = (int) ( $item['wffn_bundles_bundle']['thumbImgId'] ?? 0 );
			if ( $img ) {
				return wp_get_attachment_image( $img, 'woocommerce_thumbnail' );
			}
			$tid = (int) ( $item['wffn_bundles_bundle']['thumbId'] ?? 0 );
			$p   = $tid ? wc_get_product( $tid ) : null;
			if ( $p ) {
				return $p->get_image( 'woocommerce_thumbnail' );
			}
		}
		return $thumbnail;
	}

	/**
	 * Block cart / mini-cart container image (Store API filter, WooCommerce 9.6+).
	 *
	 * The block cart can render an arbitrary URL here, unlike restore_item()'s
	 * attachment-only image id. A container with no stored URL is left untouched,
	 * so its member-image fallback stands.
	 *
	 * @param array $images    Store API image objects for the item.
	 * @param array $cart_item The cart item.
	 * @return array
	 */
	public function store_api_item_images( $images, $cart_item ) {
		$b   = is_array( $cart_item['wffn_bundles_bundle'] ?? null ) ? $cart_item['wffn_bundles_bundle'] : array();
		$src = (string) ( $b['thumbSrc'] ?? '' );
		if ( '' === (string) ( $b['role'] ?? '' ) || '' === $src ) {
			return $images;
		}
		$img            = new \stdClass();
		$img->id        = (int) ( $b['thumbImgId'] ?? 0 );
		$img->src       = $src;
		$img->thumbnail = $src;
		$img->srcset    = '';
		$img->sizes     = '';
		$img->name      = (string) ( $b['bundleTitle'] ?? '' );
		$img->alt       = (string) ( $b['bundleTitle'] ?? '' );
		return array( $img );
	}

	/**
	 * A bundle line's discounted unit price from its stored data alone.
	 *
	 * The single source of the figure the cart charges and the one a discount
	 * adapter hands back to its engine, so the two can never disagree. Base
	 * currency in, base currency out, and no engine folding — see
	 * fold_engine_discount().
	 *
	 * @param array $item Cart item carrying `wffn_bundles_bundle`.
	 * @return float
	 */
	public static function stored_unit( array $item ) {
		$b   = isset( $item['wffn_bundles_bundle'] ) ? (array) $item['wffn_bundles_bundle'] : array();
		$qty = max( 1, (int) ( $item['quantity'] ?? 1 ) );
		if ( isset( $b['pricedUnit'] ) && null !== $b['pricedUnit'] ) {
			return (float) $b['pricedUnit'];
		}
		return self::tier_unit(
			(float) ( $b['unit'] ?? 0 ),
			$qty,
			(string) ( $b['discountType'] ?? 'no_discount' ),
			(float) ( $b['discountAmount'] ?? 0 )
		);
	}

	/**
	 * Compound a third-party discount engine's own rule into our unit price.
	 *
	 * Two callers, decided by `Discount_Layer::supplies_own_price()`: for a
	 * push-style engine this is display only, because persisting the result would
	 * hand it a figure to compound against; for a pull-style one the cart persists
	 * it, because nothing else applies the rule.
	 *
	 * Safe to repeat either way, which is the invariant: the base comes from stored
	 * `wffn_bundles_bundle` data via `stored_unit()`, never from `$item['data']`.
	 *
	 * @param array $item Cart item carrying `wffn_bundles_bundle`.
	 * @param float $unit Our discounted unit price, base currency.
	 * @return float Folded unit price, base currency.
	 */
	public static function fold_engine_discount( array $item, $unit ) {
		if ( (float) $unit <= 0 ) {
			return (float) $unit;
		}
		$theirs = 0.0;
		foreach ( Discount_Layer::get_applied_discounts( $item ) as $discount ) {
			if ( 'percentage' === (string) ( $discount['type'] ?? '' ) ) {
				$theirs = max( $theirs, (float) ( $discount['amount'] ?? 0 ) );
			}
		}
		if ( $theirs <= 0 ) {
			return (float) $unit;
		}
		// Straight onto the bundle price, not the catalog one: the engine is
		// handed this figure and discounts exactly it.
		return max( 0.0, (float) $unit * ( 1 - $theirs / 100 ) );
	}

	/**
	 * Discounted unit price for a line priced off its quantity: a fixed amount
	 * comes off the whole line total, so it spreads across the units. Base
	 * currency in, base currency out.
	 *
	 * @param float  $unit   Undiscounted unit price.
	 * @param int    $qty    Line quantity.
	 * @param string $type   `percentage`, `fixed` or `no_discount`.
	 * @param float  $amount Discount amount.
	 * @return float
	 */
	private static function tier_unit( $unit, $qty, $type, $amount ) {
		$qty      = max( 1, (int) $qty );
		$original = $unit * $qty;
		$final    = $original;
		if ( 'percentage' === $type && $amount > 0 ) {
			$final = max( 0, $original - ( $original * $amount / 100 ) );
		} elseif ( 'fixed' === $type && $amount > 0 ) {
			$final = max( 0, $original - $amount );
		}
		return $final / $qty;
	}
}
