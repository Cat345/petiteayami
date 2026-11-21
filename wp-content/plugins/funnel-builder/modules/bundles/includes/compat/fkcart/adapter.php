<?php
/** Adapter — FunnelKit Cart (slide-out cart) compatibility. */
namespace WFFN\Bundles\Includes\Compat\Fkcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Ajax\Controller;
use WFFN\Bundles\Includes\Frontend\Cart as Frontend_Cart;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Order_Items;
use WFFN\Bundles\Includes\Helpers\Request_Context;
use WFFN\Bundles\Includes\Helpers\Markup;

/**
 * FunnelKit Cart drawer compatibility.
 *
 * FKCart assembles every drawer row itself and never applies
 * `woocommerce_cart_item_class`, so the block cart's grouping classes cannot
 * reach it; each row decision is answered through one of FKCart's own filters
 * from `wffn_bundles_bundle.role`, with cart-fkcart.css indenting the members.
 *
 * No `\FKCart\*` symbol is named at file scope, so a deactivated FunnelKit Cart
 * cannot fatal us.
 */
class Adapter implements Integration {

	/** Asset handles and paths, relative to the plugin root. */
	const STYLE             = 'wffn-bundles-cart-fkcart';
	const SCRIPT            = 'wffn-bundles-cart-fkcart';
	const STYLE_PATH        = 'assets/skins/cart-fkcart.css';
	const SCRIPT_PATH       = 'assets/skins/cart-fkcart.js';
	const LAYOUT_STYLE      = 'wffn-bundles-compat-fkcart';
	const LAYOUT_STYLE_PATH = 'assets/compat/fkcart.css';
	const QUICK_VIEW_SCRIPT = 'wffn-bundles-compat-fkcart-quick-view';
	const QUICK_VIEW_PATH   = 'assets/compat/fkcart-quick-view.js';

	/** The line fronting a grouped pack. `parent` is the pre-container spelling. */
	const CONTAINER_ROLES = array( Order_Items::ROLE_CONTAINER, 'parent', 'heading' );

	/** Roles the offer holds at a fixed quantity (Frontend\Store_API::FIXED_QTY_ROLES). */
	const FIXED_QTY_ROLES = array( Order_Items::ROLE_CHILD );

	/** Folded to zero by the pricing pass, so FKCart's own badge would read 100%. */
	const FOLDED_ROLES = array( Order_Items::ROLE_CHILD, Order_Items::ROLE_GIFT );

	/**
	 * Is FunnelKit Cart loaded? It defines this before Manager::boot runs.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return defined( 'FKCART_VERSION' );
	}

	/**
	 * Register this adapter's hooks. Only ever called when is_enable() is true.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'fkcart_is_child_item', array( $this, 'is_child_item' ), 10, 2 );
		add_filter( 'fkcart_cart_item_is_sold_individually', array( $this, 'lock_quantity' ), 10, 2 );
		add_filter( 'fkcart_item_hide_you_saved_text', array( $this, 'hide_saving' ), 10, 2 );
		add_filter( 'fkcart_you_saved_prices', array( $this, 'container_saving' ), 10, 2 );
		add_filter( 'fkcart_allow_select_options', array( $this, 'block_variation_swap' ), 10, 3 );
		// A product with a bundle opens the drawer's quick view, the way a variable product does.
		add_filter( 'fkcart_product_needs_options', array( $this, 'bundle_needs_options' ), 10, 2 );
		// A bundle's lead line in the cart reopens that bundle in the quick view, the way a variable line reopens its options.
		add_filter( 'fkcart_quick_view_product_id', array( $this, 'edit_product_id' ), 10, 3 );
		add_filter( 'wffn_bundles_fbt_set', array( $this, 'edit_fbt_set' ), 10, 2 );
		add_filter( 'wffn_bundles_fragment_render', array( $this, 'is_fragment' ) );
		add_action( 'fkcart_quick_before_view_content', array( $this, 'quick_view_begin' ) );
		add_action( 'fkcart_quick_after_view_content', array( $this, 'quick_view_assets' ), 20 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'quick_view_submit' ), 20 );
		// A variable product's variation form fires no add-to-cart button hook until it has variations.
		add_action( 'woocommerce_after_variations_form', array( $this, 'quick_view_submit' ), 20 );
		// The product page's own placement hooks do not all fire in the quick view; this places the widget when they have not.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'quick_view_widget' ), 5 );
		add_action( 'woocommerce_after_variations_form', array( $this, 'quick_view_widget' ), 5 );
		add_filter( 'fkcart_enable_item_link', array( $this, 'hide_container_link' ), 10, 3 );
		add_action( 'fkcart_before_cart_item', array( $this, 'mark_row' ), 10, 2 );
		add_action( 'fkcart_after_item_meta', array( $this, 'fixed_quantity' ), 10, 2 );
		// A drawer upsell sells the product itself; an add-on may tag it as its own bundle line.
		add_filter( 'wffn_bundles_allow_bare_add', array( $this, 'allow_upsell_add' ) );
		// Ahead of Frontend\Cart::add_cart_item_data (10), which stamps the tag's fingerprint.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'tier_upsell_line' ), 9, 4 );
		// After Frontend\Cart::item_thumbnail at 10, which builds the container image.
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'thumbnail_class' ), 20, 2 );
		// FKCart enqueues its own drawer assets on this hook at 13.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/** Style and script handles already queued when the quick view started rendering. */
	private $queued_before = array( 'styles' => array(), 'scripts' => array() );

	/** Cart key of the bundle line the quick view was opened from, '' for a plain "Select options". */
	private $edit_key = '';

	/** Whether the quick view is rendering right now. */
	private $in_quick_view = false;

	/** Whether this quick view already has its proxy add button. */
	private $proxy_printed = false;

	/** Whether this response already carries the drawer asset loader. */
	private $loader_printed = false;

	/**
	 * Does this product carry a bundle whose widget the drawer must show before the add?
	 *
	 * @param bool        $needs   Whether FKCart already asks for options (a variable product).
	 * @param \WC_Product $product Upsell or add-on product.
	 * @return bool
	 */
	public function bundle_needs_options( $needs, $product = null ) {
		if ( $needs || ! $product instanceof \WC_Product ) {
			return $needs;
		}
		return self::has_widget( $product->get_id() );
	}

	/**
	 * Would the product page draw a bundle widget for this product?
	 *
	 * Same gates as the page: the bundle slot (no flags when the product has no bundle, or a
	 * Fixed Bundle holds a broken member) and the bought-together slot, which a set fills on its own.
	 *
	 * @param int $product_id Product id.
	 * @return bool
	 */
	private static function has_widget( $product_id ) {
		return array() !== Product_Display::flags_for_product( $product_id )
			|| null !== Bundle_Store::active_for_product( $product_id, Bundle_Store::SLOT_FBT );
	}

	/**
	 * Remember what is queued, so quick_view_assets() can tell which handles the widget added.
	 *
	 * @return void
	 */
	public function quick_view_begin() {
		$this->in_quick_view = true;
		$this->proxy_printed = false;
		$this->queued_before = array(
			'styles'  => wp_styles()->queue,
			'scripts' => wp_scripts()->queue,
		);
	}

	/**
	 * Draw the bundle widget in the quick view when the merchant's placement did not.
	 *
	 * A placement on `woocommerce_single_product_summary` never fires in the quick view's form, which
	 * would leave a bundle product with no widget and the drawer adding its bare product.
	 *
	 * @return void
	 */
	public function quick_view_widget() {
		$product = $GLOBALS['product'] ?? null;
		if ( ! $this->in_quick_view || ! $product instanceof \WC_Product || Product_Display::is_rendered( $product->get_id() ) || array() === Product_Display::flags_for_product( $product->get_id() ) ) {
			return;
		}
		Markup::output( Product_Display::get_instance()->render_product( $product ) );
	}

	/**
	 * The button the bundle widget intercepts, for the drawer's "Add to cart" to press.
	 *
	 * Only a bundle that is added through the widget gets one; a bought-together set has its own
	 * add button and leaves the product's own add to the drawer.
	 *
	 * @return void
	 */
	public function quick_view_submit() {
		$product = $GLOBALS['product'] ?? null;
		if ( ! $this->in_quick_view || $this->proxy_printed || ! $product instanceof \WC_Product || array() === Product_Display::flags_for_product( $product->get_id() ) ) {
			return;
		}
		$this->proxy_printed = true;
		echo '<button type="submit" class="single_add_to_cart_button wffn-bundles-fkcart-submit" hidden aria-hidden="true" tabindex="-1">' . esc_html( $product->single_add_to_cart_text() ) . '</button>';
	}

	/**
	 * Print the widget's styles and scripts with the quick view.
	 *
	 * The quick view is an AJAX fragment: it never reaches the page's print hooks, so what
	 * the bundle widget queued while rendering would be dropped. Only handles queued since
	 * quick_view_begin() print, so nothing the page already carries is sent again.
	 *
	 * @return void
	 */
	public function quick_view_assets() {
		$this->in_quick_view = false;
		$product = $GLOBALS['product'] ?? null;
		if ( ! $product instanceof \WC_Product || ! self::has_widget( $product->get_id() ) ) {
			return;
		}
		Product_Display::get_instance()->enqueue_for_product( $product->get_id() );
		Product_Display::get_instance()->enqueue_fbt_for_product( $product->get_id() );
		$this->print_asset_loader();
		$this->print_edit_state();

		$styles  = array_values( array_diff( wp_styles()->queue, $this->queued_before['styles'] ) );
		$scripts = array_values( array_diff( wp_scripts()->queue, $this->queued_before['scripts'] ) );
		if ( $styles ) {
			wp_print_styles( $styles );
		}
		if ( $scripts ) {
			// The footer's copy of this object never reaches a fragment.
			$json = wp_json_encode( Controller::config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			wp_print_inline_script_tag( 'window.wffnBundlesCart=window.wffnBundlesCart||' . ( $json ? $json : '{}' ) . ';', array( 'id' => 'wffn-bundles-cart-quick-view' ) );
			// The page runs jQuery already; a second copy would cut every handler bound to the first off its events.
			wp_scripts()->done = array_merge( wp_scripts()->done, array( 'jquery', 'jquery-core', 'jquery-migrate' ) );
			wp_print_scripts( $scripts );
		}
	}

	/**
	 * Whether this request is FKCart Pro adding one of its drawer upsells.
	 *
	 * Its add endpoint also serves the product page's AJAX add (flagged
	 * `fkcart_single_product_add_to_cart`) and its special add-on.
	 *
	 * @return bool
	 */
	private static function is_drawer_upsell_add() {
		return doing_action( 'wc_ajax_fkcart_add_item' )
			&& null === Request_Context::raw_post( 'fkcart_single_product_add_to_cart' )
			&& empty( Request_Context::raw_post( 'fkcart_spl_product_id' ) );
	}

	/**
	 * Let a drawer upsell add a Fixed Bundle product as the product alone.
	 *
	 * @param bool $allow Whether the bare add goes through.
	 * @return bool
	 */
	public function allow_upsell_add( $allow ) {
		return $allow || self::is_drawer_upsell_add();
	}

	/**
	 * Let an add-on tag a product added from the drawer's upsells as its own bundle line.
	 *
	 * @param array $data         Cart item data being built.
	 * @param int   $product_id   Product being added.
	 * @param int   $variation_id Variation being added, if any.
	 * @param int   $quantity     Quantity being added.
	 * @return array
	 */
	public function tier_upsell_line( $data, $product_id, $variation_id = 0, $quantity = 1 ) {
		if ( ! is_array( $data ) || isset( $data['wffn_bundles_bundle'] ) || ! self::is_drawer_upsell_add() ) {
			return $data;
		}

		/**
		 * Filters the cart item data of a product added from the cart drawer's upsells.
		 *
		 * An add-on whose bundle type prices by quantity tags the line here, so its
		 * price follows the quantity it reaches, as one added from the product page does.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data         Cart item data being built.
		 * @param int   $product_id   Product being added.
		 * @param int   $variation_id Variation being added, or 0.
		 * @param int   $quantity     Quantity being added.
		 */
		$tagged = apply_filters( 'wffn_bundles_fkcart_upsell_line', $data, absint( $product_id ), absint( $variation_id ), max( 1, (int) $quantity ) );

		return is_array( $tagged ) ? $tagged : $data;
	}

	/**
	 * A read-only member of a grouped pack.
	 *
	 * FKCart derives price, remove icon, stepper and "Select options" from this one
	 * flag, so it stays limited to `child`; gifts and upsells keep all but the stepper.
	 *
	 * @param bool  $status    Whether FKCart already treats this as a child row.
	 * @param array $cart_item WooCommerce cart item.
	 * @return bool
	 */
	public function is_child_item( $status, $cart_item = null ) {
		return Order_Items::ROLE_CHILD === self::role_of( $cart_item ) ? true : $status;
	}

	/**
	 * Hold the quantity of a line the offer granted.
	 *
	 * FKCart only treats `woocommerce_cart_item_quantity` as a lock when it returns
	 * a number, and Frontend\Cart::item_quantity() returns markup.
	 *
	 * @param bool  $status    Whether the row is already sold individually.
	 * @param array $cart_item WooCommerce cart item.
	 * @return bool
	 */
	public function lock_quantity( $status, $cart_item = null ) {
		if ( 'heading' === self::role_of( $cart_item ) || Frontend_Cart::nested_quantity_locked( self::raw_of( $cart_item ) ) || self::in_set( $cart_item ) ) {
			return true;
		}
		return in_array( self::role_of( $cart_item ), array_merge( self::FIXED_QTY_ROLES, Frontend_Cart::addon_roles() ), true ) ? true : $status;
	}

	/**
	 * Is this row a member nested under a heading line?
	 *
	 * @param array|null $cart_item FKCart's row array.
	 * @return bool
	 */
	private static function is_nested( $cart_item ) {
		return Frontend_Cart::is_nested( self::bundle_of( $cart_item ) );
	}

	/**
	 * Is this row a companion still held to its bought-together set's host?
	 *
	 * @param array|null $cart_item Cart item, in either shape.
	 * @return bool
	 */
	private static function in_set( $cart_item ) {
		return Frontend_Cart::fbt_companion_locked( self::raw_of( $cart_item ) );
	}

	/**
	 * Does this row head a bought-together set that still has companions?
	 *
	 * @param array|null $cart_item Cart item, in either shape.
	 * @return bool
	 */
	private static function leads_set( $cart_item ) {
		return Frontend_Cart::fbt_leads( self::raw_of( $cart_item ) );
	}

	/**
	 * Suppress the saving badge on a line the bundle priced at zero.
	 *
	 * Other roles keep their regular price through the pricing pass, so FKCart's
	 * own figure is correct there. See Frontend\Cart::apply_pricing.
	 *
	 * @param bool  $status    Whether the badge is already hidden.
	 * @param array $cart_item WooCommerce cart item.
	 * @return bool
	 */
	public function hide_saving( $status, $cart_item = null ) {
		return in_array( self::role_of( $cart_item ), self::FOLDED_ROLES, true ) ? true : $status;
	}

	/**
	 * The pack's real saving, for the container line that holds its money.
	 *
	 * The container is a shared placeholder with no regular price, so the
	 * pre-discount total is rebuilt from the child rows' `unit` prices. An empty
	 * array tells FKCart to print no badge.
	 *
	 * @param array $prices    FKCart's own regular/sale pair.
	 * @param array $cart_item WooCommerce cart item.
	 * @return array
	 */
	public function container_saving( $prices, $cart_item = null ) {
		if ( ! in_array( self::role_of( $cart_item ), self::CONTAINER_ROLES, true ) ) {
			return $prices;
		}
		$bundle  = self::bundle_of( $cart_item );
		$charged = (float) ( $bundle['pricedUnit'] ?? $bundle['unit'] ?? 0 );
		$regular = self::group_regular_total( (string) ( $bundle['group'] ?? '' ) );
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( $charged <= 0 || $regular <= $charged || ! $product instanceof \WC_Product ) {
			return array();
		}
		return array(
			'regular_price' => self::display_price( $product, $regular ),
			'sale_price'    => self::display_price( $product, $charged ),
		);
	}

	/**
	 * Offer "Select options" on a bundle's lead line, and nowhere else on a bundle.
	 *
	 * A variation swap on a line the bundle priced and locked would leave the cart holding a
	 * product the offer never quoted, so the link stays off every bundle row except the one
	 * that heads its group: that one reopens the bundle, and the new pick replaces the old.
	 *
	 * @param bool         $allowed   Whether FKCart would offer the link.
	 * @param \WC_Product  $product   Row product.
	 * @param array        $cart_item FKCart's row array.
	 * @return bool
	 */
	public function block_variation_swap( $allowed, $product = null, $cart_item = null ) {
		$key = (string) ( $cart_item['is_cart_item'] ?? '' );
		if ( '' === self::role_of( $cart_item ) ) {
			// A plain line of a product that has a bundle (what is left of a set once its companions are gone) can be turned back into the bundle.
			return $allowed || ( ! self::is_gift_line( $cart_item ) && null !== self::edit_anchor( $cart_item, $key ) );
		}
		return null !== self::edit_anchor( $cart_item, $key );
	}

	/**
	 * Is this a line the store hands out (a free gift, or a line FunnelKit Cart priced for free)?
	 *
	 * @param array|null $item Cart item, in either shape.
	 * @return bool
	 */
	private static function is_gift_line( $item ) {
		$raw = self::raw_of( $item );
		return isset( $raw['_fkcart_free_gift'] ) || isset( $raw['_fkcart_variation_gift'] );
	}

	/**
	 * The product whose bundle a lead line reopens in the quick view.
	 *
	 * A container line is a shared placeholder, so the product the pack is sold on is read
	 * from the line's bundle data instead.
	 *
	 * @param int    $product_id Product FKCart would open.
	 * @param array  $cart_item  WooCommerce cart item.
	 * @param string $cart_key   Cart key.
	 * @return int
	 */
	public function edit_product_id( $product_id, $cart_item = null, $cart_key = '' ) {
		$anchor         = self::edit_anchor( $cart_item, (string) $cart_key );
		$this->edit_key = null === $anchor ? '' : (string) $cart_key;
		return null === $anchor ? $product_id : $anchor;
	}

	/**
	 * The quick view is a fragment dropped onto a page that may hold the same widget.
	 *
	 * @param bool $fragment Whether the render is already known to be a fragment.
	 * @return bool
	 */
	public function is_fragment( $fragment ) {
		return $fragment || $this->in_quick_view;
	}

	/**
	 * The set a bought-together host line carries, for the widget to open on.
	 *
	 * Without it the widget sees its companions already in the cart and offers only the host,
	 * and the swap would drop them.
	 *
	 * @param array|null $set Set the caller supplied.
	 * @param int        $pid Product the widget renders for.
	 * @return array|null
	 */
	public function edit_fbt_set( $set, $pid = 0 ) {
		if ( null !== $set || '' === $this->edit_key || ! $this->in_quick_view ) {
			return $set;
		}
		$line = WC()->cart ? WC()->cart->get_cart_item( $this->edit_key ) : array();
		if ( absint( $line['product_id'] ?? 0 ) !== absint( $pid ) ) {
			return $set;
		}
		$found = Frontend_Cart::fbt_line_set( $this->edit_key );
		return is_array( $found ) && ! empty( $found['items'] ) ? $found : $set;
	}

	/**
	 * What the shopper chose on a bundle line, as the widget's controls would read it.
	 *
	 * Rebuilt from the cart: the offer the lead line names, the variation each variable member
	 * and free gift carries, and whether an add-on came with it. The quick view script presses
	 * the same controls, so reopening a bundle shows what is in the cart, not its defaults.
	 *
	 * @param string $cart_key Cart key of the edited line.
	 * @return array Empty when the line holds no bundle choice to restore.
	 */
	private static function edit_state( $cart_key ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		$line   = WC()->cart->get_cart_item( $cart_key );
		$bundle = self::bundle_of( $line );
		$group  = (string) ( $bundle['group'] ?? '' );
		if ( '' === $group ) {
			return array();
		}
		$state = array(
			'offer'   => '',
			'members' => array(),
			'gifts'   => array(),
			'chosen'  => array(),
			'addons'  => false,
			'addonIds' => array(),
			'picks'   => array(),
		);
		/**
		 * Whether a bundle's widget picks options per unit, so a line of several units fills that many rows.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $per_unit Whether each unit has its own option row.
		 * @param array $bundle   The edited line's bundle data.
		 */
		$per_unit = (bool) apply_filters( 'wffn_bundles_edit_per_unit', false, $bundle );
		foreach ( WC()->cart->get_cart() as $other ) {
			$variation = array();
			foreach ( (array) ( $other['variation'] ?? array() ) as $name => $value ) {
				$variation[ (string) preg_replace( '/^attribute_(pa_)?/', '', (string) $name ) ] = (string) $value;
			}
			$b = self::bundle_of( $other );
			if ( self::is_gift_line( $other ) || (string) ( $b['group'] ?? '' ) !== $group ) {
				continue;
			}
			$role = (string) ( $b['role'] ?? '' );
			if ( Order_Items::ROLE_GIFT === $role ) {
				if ( $variation ) {
					$state['gifts'][] = $variation;
				}
				$gift = wc_get_product( absint( $other['product_id'] ?? 0 ) );
				if ( $gift instanceof \WC_Product ) {
					// Keyed by label ("Size"), as the widget's picker posts them; the add resolves a variation from those.
					$labelled = array();
					foreach ( (array) ( $other['variation'] ?? array() ) as $name => $value ) {
						$labelled[ wc_attribute_label( (string) preg_replace( '/^attribute_/', '', (string) $name ), $gift ) ] = (string) $value;
					}
					$state['chosen'][] = array(
						'id'         => $gift->get_id(),
						'name'       => $gift->get_name(),
						'image'      => (string) wp_get_attachment_image_url( $gift->get_image_id(), 'woocommerce_thumbnail' ),
						'attributes' => $labelled,
					);
				}
				continue;
			}
			if ( in_array( $role, Frontend_Cart::addon_roles(), true ) ) {
				$state['addons']     = true;
				$state['addonIds'][] = absint( $other['product_id'] ?? 0 );
				continue;
			}
			if ( in_array( $role, self::CONTAINER_ROLES, true ) ) {
				$state['offer'] = (string) ( $b['offer'] ?? '' );
				continue;
			}
			if ( '' === $state['offer'] ) {
				$state['offer'] = (string) ( $b['offer'] ?? '' );
			}
			$copies = $per_unit ? max( 1, (int) ( $other['quantity'] ?? 1 ) ) : 1;
			for ( $n = 0; $variation && $n < $copies; $n++ ) {
				$state['members'][] = $variation;
			}
			$pid                    = (string) absint( $other['product_id'] ?? 0 );
			$state['picks'][ $pid ] = ( $state['picks'][ $pid ] ?? 0 ) + max( 1, (int) ( $other['quantity'] ?? 1 ) );
		}
		return $state;
	}

	/**
	 * Hand the edited line's choices to the quick view script.
	 *
	 * @return void
	 */
	private function print_edit_state() {
		$key            = $this->edit_key;
		$this->edit_key = '';
		if ( '' === $key ) {
			return;
		}
		$state = self::edit_state( $key );
		if ( $state ) {
			echo '<script type="application/json" class="wffn-bundles-edit-state">' . wp_json_encode( $state ) . '</script>';
		}
	}

	/**
	 * The product a cart line's bundle is reopened on, or null when the line is not a lead line
	 * or the cart has no quick view that can reopen a bundle.
	 *
	 * The lead is the line that fronts its group: a container or heading, a bought-together
	 * set's host, or the first line of a quantity-priced group. A product with no widget to
	 * show has nothing to reopen.
	 *
	 * @param array|null $item     Cart item, in either shape.
	 * @param string     $cart_key Cart key of the line.
	 * @return int|null
	 */
	private static function edit_anchor( $item, $cart_key ) {
		// A cart without this helper opens every "Select options" product as a variable one and fatals on a bundle.
		if ( ! function_exists( 'fkcart_product_needs_options' ) ) {
			return null;
		}
		$bundle = self::bundle_of( $item );
		$role   = (string) ( $bundle['role'] ?? '' );
		$raw    = self::raw_of( $item );
		if ( '' === $role ) {
			$anchor = absint( $raw['product_id'] ?? 0 );
			return $anchor && self::has_widget( $anchor ) ? $anchor : null;
		}
		if ( '' === (string) ( $bundle['group'] ?? '' ) || self::is_nested( $item ) || self::in_set( $item ) || in_array( $role, array_merge( self::FIXED_QTY_ROLES, Frontend_Cart::addon_roles() ), true ) ) {
			return null;
		}
		if ( in_array( $role, self::CONTAINER_ROLES, true ) ) {
			$anchor = absint( $bundle['anchor'] ?? 0 );
		} elseif ( self::leads_set( $item ) || self::first_of_group( (string) $bundle['group'], $cart_key ) ) {
			$anchor = absint( $raw['product_id'] ?? 0 );
		} else {
			return null;
		}
		return $anchor && self::has_widget( $anchor ) ? $anchor : null;
	}

	/**
	 * Is this the first line of its group in the cart?
	 *
	 * @param string $group    Bundle group id.
	 * @param string $cart_key Cart key of the line.
	 * @return bool
	 */
	private static function first_of_group( $group, $cart_key ) {
		if ( '' === $cart_key || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $key => $line ) {
			if ( (string) ( $line['wffn_bundles_bundle']['group'] ?? '' ) === $group ) {
				return (string) $key === $cart_key;
			}
		}
		return false;
	}

	/**
	 * Stop the container row linking to the shared placeholder product.
	 *
	 * @param bool   $show          Whether FKCart would link the row.
	 * @param string $cart_item_key Cart key.
	 * @param array  $cart_item     WooCommerce cart item.
	 * @return bool
	 */
	public function hide_container_link( $show, $cart_item_key = '', $cart_item = null ) {
		return in_array( self::role_of( $cart_item ), self::CONTAINER_ROLES, true ) ? false : $show;
	}

	/**
	 * Mark a bundle row so the stylesheet can act on what the row is.
	 *
	 * FKCart has no filter on the row's wrapper class, so CSS reaches this marker
	 * element with `:has()`. The action fires first inside `.fkcart--item-parent`,
	 * which is what makes the child combinator in cart-fkcart.css hold.
	 *
	 * @param array  $cart_item     FKCart's row array.
	 * @param string $cart_item_key Cart key.
	 * @return void
	 */
	public function mark_row( $cart_item = null, $cart_item_key = '' ) {
		$role = self::role_of( $cart_item );
		if ( '' === $role ) {
			return;
		}
		// A bought-together set is not a pack: its host and companions are ordinary
		// priced lines, so they carry no container or member look.
		$in_fbt = self::leads_set( $cart_item ) || self::in_set( $cart_item );
		if ( $in_fbt ) {
			$role = 'fbt';
		}
		$is_container = ! $in_fbt && in_array( $role, self::CONTAINER_ROLES, true );
		$is_nested    = ! $in_fbt && self::is_nested( $cart_item );
		$bundle       = self::bundle_of( $cart_item );
		$group        = (string) ( $bundle['group'] ?? '' );
		if ( wp_doing_ajax() ) {
			$this->print_asset_loader();
		}
		printf(
			'<i class="wffn-bundles-fkcart-mark" data-role="%s" data-group="%s" data-nested="%s" data-qty="%s" aria-hidden="true"></i>',
			esc_attr( $is_container ? Order_Items::ROLE_CONTAINER : ( $is_nested ? Order_Items::ROLE_CHILD : $role ) ),
			esc_attr( sanitize_html_class( $group ) ),
			esc_attr( $is_nested ? '1' : '0' ),
			esc_attr( $is_nested && ! Frontend_Cart::nested_quantity_locked( self::raw_of( $cart_item ) ) ? '1' : '0' )
		);
	}

	/**
	 * The flat "Qty: n" a granted line shows in place of a stepper.
	 *
	 * Rendered through Frontend\Cart::item_quantity() so the drawer, block cart and
	 * shortcode cart cannot disagree on wording or on which lines get a count.
	 *
	 * @param array  $cart_item     FKCart's row array.
	 * @param string $cart_item_key Cart key.
	 * @return void
	 */
	public function fixed_quantity( $cart_item = null, $cart_item_key = '' ) {
		if ( ! Frontend_Cart::nested_quantity_locked( self::raw_of( $cart_item ) ) && ! self::in_set( $cart_item ) && ! in_array( self::role_of( $cart_item ), array_merge( self::FIXED_QTY_ROLES, Frontend_Cart::addon_roles() ), true ) ) {
			return;
		}
		$raw = is_array( $cart_item ) && isset( $cart_item['cart_item'] ) ? (array) $cart_item['cart_item'] : array();
		echo wp_kses_post( Frontend_Cart::get_instance()->item_quantity( '', (string) $cart_item_key, $raw ) );
	}

	/**
	 * Give the container thumbnail the class FKCart's own stylesheet requires.
	 *
	 * FKCart hides `.fkcart-image-wrapper img:not(.fkcart-image)`, so the lead image
	 * Frontend\Cart::item_thumbnail() builds would not render at all.
	 *
	 * @param string $html      Thumbnail markup.
	 * @param array  $cart_item WooCommerce cart item.
	 * @return string
	 */
	public function thumbnail_class( $html, $cart_item = null ) {
		if ( ! in_array( self::role_of( $cart_item ), self::CONTAINER_ROLES, true ) ) {
			return $html;
		}
		return self::with_class( (string) $html, 'fkcart-image' );
	}

	/**
	 * The drawer stylesheet and its `:has()` fallback script.
	 *
	 * The drawer opens on every page, so these load here rather than through
	 * Frontend\Cart::enqueue_cart_css(); `fkcart-style` as a dependency keeps our
	 * rules after theirs.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! wp_style_is( 'fkcart-style', 'enqueued' ) ) {
			return;
		}
		if ( Frontend_Cart::has_bundle_line() ) {
			$this->enqueue_drawer_assets();
			return;
		}
		// No bundle line yet: only a widget on this page adds one in place; elsewhere the drawer's own fragments load them.
		if ( Bundle_Store::has_active() ) {
			add_action( 'wp_footer', array( $this, 'enqueue_for_widget' ), 1 );
		}
	}

	/**
	 * Load the drawer assets on a page whose content drew a bundle widget.
	 *
	 * @return void
	 */
	public function enqueue_for_widget() {
		if ( wp_script_is( Assets::HANDLE, 'enqueued' ) ) {
			$this->enqueue_drawer_assets();
		}
	}

	/**
	 * Queue the drawer's bundle styles and scripts.
	 *
	 * @return void
	 */
	private function enqueue_drawer_assets() {
		wp_enqueue_style( self::STYLE, WFFN_BUNDLES_URL . self::STYLE_PATH, array( 'fkcart-style' ), Assets::ver( self::STYLE_PATH ) );
		wp_enqueue_script( self::SCRIPT, WFFN_BUNDLES_URL . self::SCRIPT_PATH, array( 'jquery' ), Assets::ver( self::SCRIPT_PATH ), true );

		// The floating toggler renders storefront-wide, not just in the drawer.
		wp_enqueue_script( self::QUICK_VIEW_SCRIPT, WFFN_BUNDLES_URL . self::QUICK_VIEW_PATH, array( 'jquery' ), Assets::ver( self::QUICK_VIEW_PATH ), true );
		wp_enqueue_style( self::LAYOUT_STYLE, WFFN_BUNDLES_URL . self::LAYOUT_STYLE_PATH, array( 'fkcart-style' ), Assets::ver( self::LAYOUT_STYLE_PATH ) );
	}

	/**
	 * Print, once per response, a loader that adds the drawer assets to a page that went without them.
	 *
	 * A drawer fragment can bring the first bundle onto a page that had none; each asset already present is skipped by its id.
	 *
	 * @return void
	 */
	private function print_asset_loader() {
		if ( $this->loader_printed ) {
			return;
		}
		$this->loader_printed = true;
		$assets = array(
			array( 'link', self::STYLE . '-css', add_query_arg( 'ver', Assets::ver( self::STYLE_PATH ), WFFN_BUNDLES_URL . self::STYLE_PATH ) ),
			array( 'link', self::LAYOUT_STYLE . '-css', add_query_arg( 'ver', Assets::ver( self::LAYOUT_STYLE_PATH ), WFFN_BUNDLES_URL . self::LAYOUT_STYLE_PATH ) ),
			array( 'script', self::SCRIPT . '-js', add_query_arg( 'ver', Assets::ver( self::SCRIPT_PATH ), WFFN_BUNDLES_URL . self::SCRIPT_PATH ) ),
			array( 'script', self::QUICK_VIEW_SCRIPT . '-js', add_query_arg( 'ver', Assets::ver( self::QUICK_VIEW_PATH ), WFFN_BUNDLES_URL . self::QUICK_VIEW_PATH ) ),
		);
		$js     = '(function(a){a.forEach(function(x){if(document.getElementById(x[1]))return;var e=document.createElement(x[0]);e.id=x[1];if("link"===x[0]){e.rel="stylesheet";e.href=x[2];}else{e.src=x[2];e.async=false;}document.head.appendChild(e);});})(' . wp_json_encode( $assets ) . ');';
		wp_print_inline_script_tag( $js, array( 'class' => 'wffn-bundles-fkcart-assets' ) );
	}

	/**
	 * What the members of a group cost before the offer moved the price.
	 *
	 * @param string $group Bundle group id.
	 * @return float
	 */
	private static function group_regular_total( $group ) {
		if ( '' === $group || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}
		$total = 0.0;
		foreach ( WC()->cart->get_cart_contents() as $item ) {
			$bundle = self::bundle_of( $item );
			if ( (string) ( $bundle['group'] ?? '' ) !== $group || Order_Items::ROLE_CHILD !== (string) ( $bundle['role'] ?? '' ) ) {
				continue;
			}
			$qty    = max( 1, (int) ( $bundle['baseQty'] ?? ( $item['quantity'] ?? 1 ) ) );
			$total += (float) ( $bundle['unit'] ?? 0 ) * $qty;
		}
		return $total;
	}

	/**
	 * One price in the mode the shop displays, as FKCart resolves its own.
	 *
	 * @param \WC_Product $product Row product.
	 * @param float       $amount  Raw amount.
	 * @return float
	 */
	private static function display_price( $product, $amount ) {
		$args = array( 'price' => $amount );
		return 'incl' === get_option( 'woocommerce_tax_display_shop' )
			? wc_get_price_including_tax( $product, $args )
			: wc_get_price_excluding_tax( $product, $args );
	}

	/**
	 * Add a class to the first `<img>` in a fragment, whether or not it has one.
	 *
	 * @param string $html      Markup.
	 * @param string $css_class Class to add.
	 * @return string
	 */
	private static function with_class( $html, $css_class ) {
		if ( false === strpos( $html, '<img' ) || false !== strpos( $html, $css_class ) ) {
			return $html;
		}
		if ( preg_match( '/<img[^>]*\sclass=("|\')/', $html ) ) {
			return preg_replace( '/(<img[^>]*\sclass=("|\'))/', '${1}' . $css_class . ' ', $html, 1 );
		}
		return preg_replace( '/<img\b/', '<img class="' . $css_class . '"', $html, 1 );
	}

	/**
	 * A row's bundle data, from either shape a FunnelKit Cart hook passes.
	 *
	 * FKCart's filters pass the WooCommerce cart item; its template actions pass a
	 * row array nesting that item under `cart_item`.
	 *
	 * @param array $item Cart item, in either shape.
	 * @return array
	 */
	private static function bundle_of( $item ) {
		if ( ! is_array( $item ) ) {
			return array();
		}
		$bundle = $item['wffn_bundles_bundle'] ?? ( $item['cart_item']['wffn_bundles_bundle'] ?? null );
		return is_array( $bundle ) ? $bundle : array();
	}

	/**
	 * The WooCommerce cart item behind either shape a FunnelKit Cart hook passes.
	 *
	 * @param array $item Cart item, in either shape.
	 * @return array
	 */
	private static function raw_of( $item ) {
		if ( ! is_array( $item ) ) {
			return array();
		}
		return is_array( $item['cart_item'] ?? null ) ? $item['cart_item'] : $item;
	}

	/**
	 * A row's bundle role, or an empty string when the row is not ours.
	 *
	 * @param array $item Cart item, in either shape.
	 * @return string
	 */
	private static function role_of( $item ) {
		$bundle = self::bundle_of( $item );
		return (string) ( $bundle['role'] ?? '' );
	}
}
