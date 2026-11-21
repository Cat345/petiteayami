<?php
/**
 * The durable `_wffn_bundles_*` order-item meta a bundle line carries.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Currency\Layer;

/**
 * Copies a bundle cart line's data to hidden order-item meta at checkout.
 *
 * That meta outlives the bundle it came from, so an order still renders as a
 * bundle after the bundle is edited or deleted. Also keeps "order again" from
 * rebuilding a bundle out of raw lines.
 */
class Order_Attribution {

	/** Order-item meta keys; the leading underscore hides them from the item-meta UI. */
	const META_BUNDLE_ID    = '_wffn_bundles_bundle_id';
	const META_TYPE           = '_wffn_bundles_type';
	const META_ROLE           = '_wffn_bundles_role';
	const META_GROUP          = '_wffn_bundles_group';
	/** What the line sold on: a tier, a pick-size, a Fixed Bundle slot, or an add-on card. */
	const META_OFFER          = '_wffn_bundles_offer';
	// Base-currency unit prices this plugin no longer writes; listed so lines carrying them stay hidden.
	const META_UNIT           = '_wffn_bundles_unit';
	const META_PRICED_UNIT    = '_wffn_bundles_priced_unit';
	const META_BUNDLE_NAME  = '_wffn_bundles_bundle_name';
	// Base-currency discount charged and its kind, so the saving is reconstructible.
	const META_DISCOUNT_AMOUNT = '_wffn_bundles_discount_amount';
	const META_DISCOUNT_TYPE   = '_wffn_bundles_discount_type';

	/** Roles that carry money and can therefore have been discounted. */
	const DISCOUNT_ROLES = array( 'anchor', 'container', 'member', 'fbt_host', 'fbt_companion' );

	/**
	 * Display snapshots captured at checkout, read only by the order display layer.
	 *
	 * The cart carries these in `wffn_bundles_bundle` and nothing else persists them.
	 */
	const META_BUNDLE_TITLE = '_wffn_bundles_bundle_title';
	/** No longer written; kept so orders that still carry it keep it hidden. */
	const META_BASE_QTY     = '_wffn_bundles_base_qty';
	/** Set on a member that sits under a heading line. */
	const META_NESTED       = '_wffn_bundles_nested';
	/** The image a heading line showed in the cart. */
	const META_THUMB        = '_wffn_bundles_thumb';
	/** The offer label an add-on line showed in the cart, e.g. "Add-on" or "Free gift". */
	const META_OFFER_LABEL  = '_wffn_bundles_offer_label';

	/** @var self|null Singleton instance. */
	private static $instance = null;

	/** Per-request cache of raw bundle configs, id => array|null (null = deleted). */
	private $configs = array();

	/** Lines a refund in flight took with its bundle, order id => [item id => ['qty' => n]]. */
	private $refunded_lines = array();

	/**
	 * Singleton accessor.
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
	 * Hook the checkout meta copy and the "order again" exclusions.
	 */
	private function __construct() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'copy_item_meta' ), 10, 4 );
		add_action( 'woocommerce_after_order_object_save', array( Order_Data::class, 'flush' ) );
		// "Order again" would re-add a bundle's lines ungrouped at full catalog price:
		// they are tagged on the way in, then rebuilt as one bundle once the cart is loaded.
		add_filter( 'woocommerce_order_again_cart_item_data', array( $this, 'flag_order_again_item' ), 10, 3 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'rebuild_order_again_bundles' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_itemmeta' ) );
		// A bundle's $0 lines carry the stock its priced lines sold.
		add_action( 'woocommerce_create_refund', array( $this, 'refund_bundle_lines' ), 10, 2 );
		add_filter( 'woocommerce_order_is_partially_refunded', array( $this, 'is_partially_refunded' ), 10, 2 );
		add_action( 'woocommerce_refund_created', array( $this, 'restock_bundle_lines' ), 10, 2 );
	}

	/**
	 * Keep every `_wffn_bundles_*` order-item meta key off the admin Order Details
	 * screen. WooCommerce 11.0 stopped hiding underscore-prefixed keys by
	 * default (`OrderItemMetaUtil::get_hidden_keys()`, called with an empty
	 * hideprefix) — hiding now works only through this explicit list, so
	 * without this filter every key below renders as its own visible row
	 * under each line item.
	 */
	public function hide_order_itemmeta( $hidden ) {
		return array_merge(
			$hidden,
			array(
				self::META_BUNDLE_ID,
				self::META_TYPE,
				self::META_ROLE,
				self::META_GROUP,
				self::META_OFFER,
				self::META_UNIT,
				self::META_PRICED_UNIT,
				self::META_BUNDLE_NAME,
				self::META_DISCOUNT_AMOUNT,
				self::META_DISCOUNT_TYPE,
				self::META_BUNDLE_TITLE,
				self::META_BASE_QTY,
				self::META_NESTED,
				self::META_THUMB,
				self::META_OFFER_LABEL,
				// Written by an add-on on its lines; kept hidden after the add-on is switched off.
				'_wffn_bundles_upsell_offered',
				// Unprefixed fallback: OCU's own default line-add copies these raw
				// posted field names onto the item verbatim whenever a compat
				// adapter's own write path declines the row — never customer-facing.
				'wffn_bundles_bundle_id',
				'wffn_bundles_offer',
				'wffn_bundles_anchor',
				'wffn_bundles_selection',
			)
		);
	}

	/**
	 * Is this request rebuilding a cart from a past order?
	 *
	 * Never cleared: no request both reorders and takes a bare add-to-cart path.
	 * See the bare-add guard in includes/frontend/cart.php.
	 *
	 * @var bool
	 */
	public static $in_order_again = false;

	/**
	 * Whether woocommerce_order_again_cart_item_data has run on this request.
	 *
	 * @return bool
	 */
	public static function is_order_again() {
		return self::$in_order_again;
	}

	/**
	 * Tag a bundle order-item so rebuild_order_again_bundles() can replace it.
	 *
	 * @param array                  $data  Cart item data being assembled.
	 * @param \WC_Order_Item_Product $item  The past order's line.
	 * @param \WC_Order              $order The order being reordered.
	 * @return array
	 */
	public function flag_order_again_item( $data, $item, $order ) {
		// Set first and unconditionally: an ordinary line reaching validation with
		// the flag unset would be dropped from the reorder by the bare-add guard.
		self::$in_order_again = true;
		if ( $item instanceof \WC_Order_Item_Product && '' !== (string) Order_Data::get( $item, self::META_BUNDLE_ID ) ) {
			$data['_wffn_bundles_order_again'] = array(
				'order' => (int) $order->get_id(),
				'group' => (string) Order_Data::get( $item, self::META_GROUP ),
			);
		}
		return $data;
	}

	/**
	 * Replace the tagged lines of a reorder with their bundles, once the cart is loaded.
	 *
	 * WooCommerce has put each tagged line in at its own price (so its count of
	 * what came back is right). Each bundle group comes back as one bundle at
	 * today's price while that bundle is live; otherwise its products come back
	 * as ordinary products at their normal prices. Never a half pack, never the
	 * price the old order paid.
	 *
	 * @param \WC_Cart $cart The cart just loaded.
	 * @return void
	 */
	public function rebuild_order_again_bundles( $cart ) {
		if ( ! self::$in_order_again || ! $cart instanceof \WC_Cart ) {
			return;
		}
		$groups = array();
		foreach ( $cart->get_cart() as $key => $line ) {
			$tag = $line['_wffn_bundles_order_again'] ?? null;
			if ( ! is_array( $tag ) ) {
				continue;
			}
			$groups[ (int) ( $tag['order'] ?? 0 ) ][ (string) ( $tag['group'] ?? '' ) ] = true;
			unset( $cart->cart_contents[ $key ] );
		}
		foreach ( $groups as $order_id => $group_ids ) {
			$order = $order_id ? wc_get_order( $order_id ) : null;
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$by_group = array();
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof \WC_Order_Item_Product || '' === (string) Order_Data::get( $item, self::META_BUNDLE_ID ) ) {
					continue;
				}
				$group = (string) Order_Data::get( $item, self::META_GROUP );
				if ( isset( $group_ids[ $group ] ) ) {
					$by_group[ $group ][] = $item;
				}
			}
			foreach ( $by_group as $items ) {
				\WFFN\Bundles\Includes\Frontend\Cart::reorder_group( $items );
			}
		}
	}

	/**
	 * The base-currency unit price a bundle line was charged at.
	 *
	 * The cart line's own figure when it carried one, else the line's subtotal per
	 * unit: get_subtotal(), not get_total(), since the total is already net of coupons.
	 *
	 * @param \WC_Order_Item_Product $item   The order line.
	 * @param array                  $bundle The cart line's `wffn_bundles_bundle` data.
	 * @param \WC_Order              $order  The order being created.
	 * @return float
	 */
	public static function priced_unit( $item, array $bundle, $order ) {
		if ( isset( $bundle['pricedUnit'] ) && null !== $bundle['pricedUnit'] ) {
			return (float) $bundle['pricedUnit'];
		}
		return Layer::get_order_base_amount( $order, (float) $item->get_subtotal() ) / max( 1, (int) $item->get_quantity() );
	}

	/**
	 * Copy a bundle cart line's `wffn_bundles_bundle` data to hidden `_wffn_bundles_*` item meta.
	 *
	 * The bundle name is snapshotted while the bundle still exists. A Quantity
	 * Breaks anchor has no precomputed `pricedUnit`, so it derives from the line total.
	 *
	 * @param \WC_Order_Item_Product $item          The order line being created.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item values.
	 * @param \WC_Order              $order         The order being created.
	 * @return void
	 */
	public function copy_item_meta( $item, $cart_item_key, $values, $order ) {
		$bundle = isset( $values['wffn_bundles_bundle'] ) && is_array( $values['wffn_bundles_bundle'] ) ? $values['wffn_bundles_bundle'] : null;
		if ( null === $bundle ) {
			return;
		}
		$bundle_id = absint( $bundle['bundle'] ?? 0 );
		if ( ! $bundle_id ) {
			return;
		}
		// A bought-together set the cart re-priced at its products' own prices
		// (its bundle paused or changed) is sold as ordinary products: no bundle on the order.
		if ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) ( $bundle['type'] ?? 0 ) && ! \WFFN\Bundles\Includes\Frontend\Cart::fbt_set_discounted( (string) $cart_item_key ) ) {
			return;
		}
		$config = $this->config( $bundle_id );
		$qty    = max( 1, (int) $item->get_quantity() );
		$priced = self::priced_unit( $item, $bundle, $order );

		$item->add_meta_data( self::META_BUNDLE_ID, $bundle_id, true );
		$item->add_meta_data( self::META_TYPE, (int) ( $bundle['type'] ?? 0 ), true );
		$item->add_meta_data( self::META_ROLE, (string) ( $bundle['role'] ?? '' ), true );
		$item->add_meta_data( self::META_GROUP, (string) ( $bundle['group'] ?? '' ), true );
		// The tier or card id; both vanish from the config once deleted.
		$offer = (string) ( $bundle['offer'] ?? '' );
		// A Fixed Bundle pack is added without an id, but it can only be the `fb-bundle` offer.
		if ( '' === $offer && Bundle_Store::TYPE_FIXED_BUNDLES === (int) ( $bundle['type'] ?? 0 ) && in_array( (string) ( $bundle['role'] ?? '' ), array( Order_Items::ROLE_CONTAINER, Order_Items::ROLE_CHILD, Order_Items::ROLE_MEMBER ), true ) ) {
			$offer = 'fb-bundle';
		}
		if ( '' !== $offer ) {
			$item->add_meta_data( self::META_OFFER, $offer, true );
		}
		$item->add_meta_data( self::META_BUNDLE_NAME, Order_Items::truncate_name( (string) ( $config['name'] ?? '' ) ), true );

		// A child's value sits on its container.
		$role = (string) ( $bundle['role'] ?? '' );
		if ( in_array( $role, self::DISCOUNT_ROLES, true ) ) {
			$unit     = (float) ( $bundle['unit'] ?? 0 );
			$discount = round( max( 0.0, ( $unit - $priced ) * $qty ), Layer::get_decimals( Layer::get_base_currency() ) );
			$item->add_meta_data( self::META_DISCOUNT_AMOUNT, $discount, true );
			$item->add_meta_data( self::META_DISCOUNT_TYPE, (string) ( $bundle['discountType'] ?? 'no_discount' ), true );
		}

		// Stored only when present, so a non-grouped line adds no empty meta.
		$bundle_title = (string) ( $bundle['bundleTitle'] ?? '' );
		if ( '' !== $bundle_title ) {
			$item->add_meta_data( self::META_BUNDLE_TITLE, $bundle_title, true );
			// The admin item table reads the stored name, not the display filter.
			if ( 'heading' === $role ) {
				$item->set_name( $bundle_title );
			}
		}
		if ( ! empty( $bundle['nested'] ) ) {
			$item->add_meta_data( self::META_NESTED, 1, true );
		}
		if ( '' !== (string) ( $bundle['thumbSrc'] ?? '' ) ) {
			$item->add_meta_data( self::META_THUMB, sanitize_url( (string) $bundle['thumbSrc'] ), true );
		}
		if ( '' !== (string) ( $bundle['label'] ?? '' ) && in_array( $role, \WFFN\Bundles\Includes\Frontend\Cart::addon_roles(), true ) ) {
			$item->add_meta_data( self::META_OFFER_LABEL, sanitize_text_field( (string) $bundle['label'] ), true );
		}

		/**
		 * Fires once the core bundle meta is on the line.
		 *
		 * An add-on records the snapshots its own lines need here.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order_Item_Product $item   The order line.
		 * @param array                  $bundle The cart line's `wffn_bundles_bundle` data.
		 * @param array                  $config The bundle config.
		 * @param \WC_Order              $order  The order being created.
		 */
		do_action( 'wffn_bundles_order_line_meta', $item, $bundle, $config, $order );

		if ( Order_Data::enabled() ) {
			Order_Data::stage( $item );
		}
	}














	/**
	 * Raw bundle config, per-request cached including "deleted" nulls.
	 *
	 * @param int|string $bundle_id Bundle id.
	 * @return array|null
	 */
	private function config( $bundle_id ) {
		$bundle_id = (string) absint( $bundle_id );
		if ( ! array_key_exists( $bundle_id, $this->configs ) ) {
			$this->configs[ $bundle_id ] = Bundle_Store::get_config( $bundle_id );
		}
		return $this->configs[ $bundle_id ];
	}

	/**
	 * Refund a bundle's $0 lines with the priced lines they sold with.
	 *
	 * Children follow their container's ratio; filtered roles follow a fully refunded bundle.
	 *
	 * @param \WC_Order_Refund $refund The refund being created.
	 * @param array            $args   The wc_create_refund() arguments.
	 * @return void
	 */
	public function refund_bundle_lines( $refund, $args ) {
		$order_id = absint( $args['order_id'] ?? 0 );
		$lines    = is_array( $args['line_items'] ?? null ) ? $args['line_items'] : array();
		$order    = $order_id && $lines ? wc_get_order( $order_id ) : false;

		unset( $this->refunded_lines[ $order_id ] );
		if ( ! $order instanceof \WC_Order || ! $refund instanceof \WC_Order_Refund ) {
			return;
		}

		$items  = $order->get_items();
		$follow = array();
		foreach ( $items as $container_id => $container ) {
			$refund_qty = wc_stock_amount( $lines[ $container_id ]['qty'] ?? 0 );
			$group      = (string) Order_Data::get( $container, self::META_GROUP );
			if ( $refund_qty <= 0 || '' === $group || Order_Items::ROLE_CONTAINER !== (string) Order_Data::get( $container, self::META_ROLE ) ) {
				continue;
			}
			$container_qty = max( 1, (int) $container->get_quantity() );

			foreach ( $items as $child_id => $child ) {
				if ( Order_Items::ROLE_CHILD !== (string) Order_Data::get( $child, self::META_ROLE ) || (string) Order_Data::get( $child, self::META_GROUP ) !== $group || ! empty( $lines[ $child_id ]['qty'] ) ) {
					continue;
				}
				$follow[ $child_id ] = min(
					self::unrefunded_qty( $order, $child_id, $child ),
					(int) round( $refund_qty * (int) $child->get_quantity() / $container_qty )
				);
			}
		}

		/**
		 * Filters the order-line roles that are refunded with their bundle once
		 * every priced line of that bundle has been refunded.
		 *
		 * @since 1.0.0
		 *
		 * @param string[]  $roles Line roles, none by default.
		 * @param \WC_Order $order The order being refunded.
		 */
		$roles = (array) apply_filters( 'wffn_bundles_refund_with_bundle_roles', array(), $order );
		// A heading only labels its box, so it goes back with the box.
		$roles[]  = 'heading';
		$returned = self::returned_groups( $order, $items, $lines, $refund->get_id() );
		foreach ( $items as $item_id => $item ) {
			if ( ! empty( $lines[ $item_id ]['qty'] ) || ! in_array( (string) Order_Data::get( $item, self::META_ROLE ), $roles, true ) || ! in_array( (string) Order_Data::get( $item, self::META_GROUP ), $returned, true ) ) {
				continue;
			}
			$follow[ $item_id ] = self::unrefunded_qty( $order, $item_id, $item );
		}

		foreach ( $follow as $item_id => $qty ) {
			if ( $qty <= 0 ) {
				unset( $follow[ $item_id ] );
				continue;
			}
			$refunded = new \WC_Order_Item_Product( $items[ $item_id ] );
			$refunded->set_id( 0 );
			$refunded->add_meta_data( '_refunded_item_id', $item_id, true );
			$refunded->set_quantity( $qty * -1 );
			$refunded->set_subtotal( 0 );
			$refunded->set_total( 0 );
			$refunded->set_taxes( array() );
			$refund->add_item( $refunded );

			$follow[ $item_id ] = array( 'qty' => $qty );
		}

		if ( $follow ) {
			$this->refunded_lines[ $order_id ] = $follow;
		}
	}

	/**
	 * Recount a refund that took bundle lines: WooCommerce counts only the lines the admin gave.
	 *
	 * @param bool $partial  Whether WooCommerce judged the order partially refunded.
	 * @param int  $order_id Order id.
	 * @return bool
	 */
	public function is_partially_refunded( $partial, $order_id ) {
		$order = empty( $this->refunded_lines[ $order_id ] ) ? false : wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return $partial;
		}
		return $order->get_remaining_refund_amount() > 0 || ( $order->has_free_item() && $order->get_remaining_refund_items() > 0 );
	}

	/**
	 * Restock the lines refund_bundle_lines() added, when the admin asked to restock.
	 *
	 * @param int   $refund_id Refund id.
	 * @param array $args      The wc_create_refund() arguments.
	 * @return void
	 */
	public function restock_bundle_lines( $refund_id, $args ) {
		$order_id = absint( $args['order_id'] ?? 0 );
		$lines    = $this->refunded_lines[ $order_id ] ?? array();
		unset( $this->refunded_lines[ $order_id ] );

		$order = $lines && ! empty( $args['restock_items'] ) ? wc_get_order( $order_id ) : false;
		if ( $order instanceof \WC_Order ) {
			wc_restock_refunded_items( $order, $lines );
		}
	}

	/**
	 * Quantity of an order line not yet refunded.
	 *
	 * @param \WC_Order      $order   The order.
	 * @param int            $item_id Order item id.
	 * @param \WC_Order_Item $item    The order item.
	 * @param int            $skip    Refund id to leave out of the count, 0 for none.
	 * @return int
	 */
	private static function unrefunded_qty( $order, $item_id, $item, $skip = 0 ) {
		$refunded = (int) $order->get_qty_refunded_for_item( $item_id );
		foreach ( $skip ? $order->get_refunds() : array() as $refund ) {
			if ( (int) $skip !== $refund->get_id() ) {
				continue;
			}
			foreach ( $refund->get_items() as $refunded_item ) {
				if ( absint( $refunded_item->get_meta( '_refunded_item_id' ) ) === (int) $item_id ) {
					$refunded -= (int) $refunded_item->get_quantity();
				}
			}
		}
		return (int) $item->get_quantity() + $refunded;
	}

	/**
	 * Bundle groups this refund touches whose priced lines it leaves fully refunded.
	 *
	 * @param \WC_Order $order     The order.
	 * @param array     $items     The order's line items.
	 * @param array     $lines     The refund's line_items argument.
	 * @param int       $refund_id The refund being created; WooCommerce has already saved it, so its lines are counted once, from $lines.
	 * @return string[]
	 */
	private static function returned_groups( $order, $items, $lines, $refund_id = 0 ) {
		$left    = array();
		$touched = array();
		foreach ( $items as $item_id => $item ) {
			$group = (string) Order_Data::get( $item, self::META_GROUP );
			$now   = wc_stock_amount( $lines[ $item_id ]['qty'] ?? 0 );
			if ( '' !== $group && $now > 0 ) {
				$touched[ $group ] = true;
			}
			if ( '' === $group || ! in_array( (string) Order_Data::get( $item, self::META_ROLE ), self::DISCOUNT_ROLES, true ) ) {
				continue;
			}
			$left[ $group ] = ( $left[ $group ] ?? 0 ) + self::unrefunded_qty( $order, $item_id, $item, $refund_id ) - $now;
		}

		$returned = array();
		foreach ( $left as $group => $qty ) {
			if ( $qty <= 0 && isset( $touched[ $group ] ) ) {
				$returned[] = (string) $group;
			}
		}
		return $returned;
	}
}
