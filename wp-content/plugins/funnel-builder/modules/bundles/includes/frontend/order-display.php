<?php
/**
 * Order_Display — reproduce the cart's grouped-bundle look on the order side.
 *
 * The cart grouping is built from `woocommerce_cart_item_*` filters, none of
 * which touch order line items. This is the order-item mirror, keyed off the
 * durable `_wffn_bundles_*` meta copied at checkout (Order_Attribution::copy_item_meta)
 * and emitting the same classes, so assets/skins/cart-blocks.css applies
 * verbatim. Prices are display-only: stored order totals are never touched.
 *
 * Covers the Thank-You page, the classic View-Order page, order emails, and
 * the wp-admin Edit Order screen (its own separate filter pair). Emails
 * cannot load an external stylesheet, so the rules also go through
 * `woocommerce_email_styles` for WooCommerce's inliner.
 */
namespace WFFN\Bundles\Includes\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Storage\Order_Attribution;
use WFFN\Bundles\Includes\Storage\Order_Data;

class Order_Display {

	private static $instance = null;

	/** True while Funnel Builder draws its Thank You order table. */
	private $in_ty_table = false;

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
	 * Register the order-item display filters.
	 */
	private function __construct() {
		// One registration covers every surface: the classic order-details template
		// and the email order-items template share these filters.
		add_filter( 'woocommerce_order_item_class', array( $this, 'item_class' ), 10, 3 );
		// FunnelKit's Thank-You page draws its own item rows and reaches neither of
		// WooCommerce's own hooks; see bin/docs/integrations/funnel-builder/PATCHES.md.
		add_filter( 'wfty_order_item_class', array( $this, 'item_class' ), 10, 3 );
		// Released Funnel Builder has no such filter, so each Thank You row also carries a role marker.
		add_action( 'wffn_start_order_details_table', array( $this, 'enter_ty_table' ) );
		add_action( 'wffn_end_order_details_table', array( $this, 'leave_ty_table' ) );
		add_action( 'woocommerce_order_item_meta_start', array( $this, 'ty_row_marker' ), 10, 3 );
		add_filter( 'wffn_thankyou_order_item_thumbnail', array( $this, 'item_thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_order_item_thumbnail', array( $this, 'item_thumbnail' ), 10, 2 );
		// The wp-admin Edit Order screen has its own hooks, separate from the
		// frontend/email pair above.
		add_filter( 'woocommerce_admin_html_order_item_class', array( $this, 'item_class' ), 10, 3 );
		add_filter( 'woocommerce_admin_order_item_thumbnail', array( $this, 'admin_item_thumbnail' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );
		add_filter( 'woocommerce_order_item_name', array( $this, 'item_name' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'offer_label' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'line_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_order_item_quantity_html', array( $this, 'quantity_html' ), 10, 2 );
		add_filter( 'woocommerce_email_order_item_quantity', array( $this, 'email_quantity' ), 10, 2 );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'print_heading_sum' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
		// Emails inline their CSS — feed the grouping rules to WooCommerce's inliner.
		add_filter( 'woocommerce_email_styles', array( $this, 'email_styles' ), 10, 2 );
	}

	/**
	 * Start marking rows: Funnel Builder's Thank You order table is being drawn.
	 *
	 * @return void
	 */
	public function enter_ty_table() {
		$this->in_ty_table = true;
	}

	/**
	 * Stop marking rows once that table is done.
	 *
	 * @return void
	 */
	public function leave_ty_table() {
		$this->in_ty_table = false;
	}

	/**
	 * Print a hidden marker carrying a bundle line's grouping classes inside its Thank You row.
	 *
	 * The row itself takes classes only through `wfty_order_item_class`, which
	 * released Funnel Builder lacks, so the stylesheet finds the row by this marker.
	 *
	 * @param int            $item_id Order item id.
	 * @param object         $item    The order item.
	 * @param \WC_Order|null $order   The order.
	 * @return void
	 */
	public function ty_row_marker( $item_id, $item, $order = null ) {
		if ( ! $this->in_ty_table ) {
			return;
		}
		$class = $this->item_class( '', $item, $order );
		if ( '' === $class ) {
			return;
		}
		if ( $this->next_is_member( $item, $order ) ) {
			$class .= ' wffn-bundles-ty-before-member';
		}
		printf( '<span class="wffn-bundles-ty-row%s" hidden></span>', esc_attr( $class ) );
	}

	/**
	 * Whether the next visible line of the order is a bundle member.
	 *
	 * @param object         $item  The order item.
	 * @param \WC_Order|null $order The order.
	 * @return bool
	 */
	private function next_is_member( $item, $order ) {
		if ( ! $order instanceof \WC_Order || ! method_exists( $item, 'get_id' ) ) {
			return false;
		}
		$found = false;
		foreach ( $order->get_items() as $line ) {
			if ( ! $found ) {
				$found = $line->get_id() === $item->get_id();
				continue;
			}
			if ( ! apply_filters( 'woocommerce_order_item_visible', true, $line ) ) {
				continue;
			}
			return false !== strpos( $this->item_class( '', $line, $order ), 'wffn-bundles-cart-child' );
		}
		return false;
	}

	/**
	 * A grouped-bundle role read off an order item, or '' for a non-bundle line.
	 *
	 * @param object $item The order item.
	 * @return string
	 */
	private static function role( $item ) {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
			return '';
		}
		return (string) Order_Data::get( $item, Order_Attribution::META_ROLE );
	}

	/**
	 * Whether an order item is a member nested under a heading line.
	 *
	 * @param object $item The order item.
	 * @return bool
	 */
	private static function is_nested( $item ) {
		return 'member' === self::role( $item ) && '' !== (string) Order_Data::get( $item, Order_Attribution::META_NESTED );
	}

	/**
	 * Tag bundle order rows with the classes the cart uses, so the shared
	 * grouping CSS styles them identically. Only the Fixed Bundle
	 * container/child pair is grouped, exactly as in the cart.
	 *
	 * @param string         $class Row classes so far.
	 * @param object         $item  The order item.
	 * @param \WC_Order|null $order The order.
	 * @return string
	 */
	public function item_class( $class, $item, $order = null ) {
		$role = self::role( $item );
		if ( '' === $role ) {
			return $class;
		}
		$class .= ' wffn-bundles-cart-bundle';
		if ( in_array( $role, array( 'container', 'parent', 'heading' ), true ) ) {
			$class .= ' wffn-bundles-cart-parent';
		} elseif ( 'child' === $role ) {
			$class .= ' wffn-bundles-cart-child';
		} elseif ( self::is_nested( $item ) ) {
			$class .= ' wffn-bundles-cart-child wffn-bundles-cart-nested';
		}
		// A bought-together set carries no parent or child class: its lines are not indented.
		$group = (string) Order_Data::get( $item, Order_Attribution::META_GROUP );
		if ( '' !== $group ) {
			$class .= ' wffn-bundles-cart-group-' . sanitize_html_class( $group );
		}
		return $class;
	}

	/**
	 * Give a pack's container line a picture of what it sells.
	 *
	 * The container is a shared placeholder product with no image of its own, so
	 * the first member of the group stands in — as it does in the cart.
	 *
	 * @param string $html The image markup WooCommerce resolved.
	 * @param object $item The order item.
	 * @return string
	 */
	public function item_thumbnail( $html, $item ) {
		$role  = self::role( $item );
		$thumb = is_callable( array( $item, 'get_meta' ) ) ? (string) Order_Data::get( $item, Order_Attribution::META_THUMB ) : '';
		if ( '' !== $thumb ) {
			return '<img src="' . esc_url( $thumb ) . '" alt="" class="attachment-shop_thumbnail size-shop_thumbnail" width="100" height="100" />';
		}
		if ( ! in_array( $role, array( 'container', 'parent', 'heading' ), true ) || ! method_exists( $item, 'get_order' ) ) {
			return $html;
		}
		$group = (string) Order_Data::get( $item, Order_Attribution::META_GROUP );
		$order = $item->get_order();
		if ( '' === $group || ! $order instanceof \WC_Order ) {
			return $html;
		}

		foreach ( $order->get_items() as $line ) {
			if ( ( 'child' !== self::role( $line ) && ! self::is_nested( $line ) ) || (string) Order_Data::get( $line, Order_Attribution::META_GROUP ) !== $group ) {
				continue;
			}
			$product = $line->get_product();
			if ( $product instanceof \WC_Product && $product->get_image_id() ) {
				return $product->get_image( 'shop_thumbnail' );
			}
		}

		return $html;
	}

	/**
	 * Admin equivalent of item_thumbnail() — same borrow-the-first-child logic,
	 * against the wp-admin Edit Order screen's own thumbnail filter, which
	 * passes the item id/object rather than an order and doesn't share the
	 * frontend filter's args.
	 *
	 * @param string $html    The image markup WooCommerce resolved.
	 * @param int    $item_id The order item id.
	 * @param object $item    The order item.
	 * @return string
	 */
	public function admin_item_thumbnail( $html, $item_id, $item ) {
		return $this->item_thumbnail( $html, $item );
	}

	/**
	 * Show an add-on line's offer label on every order view, as its cart line did.
	 *
	 * @param array  $formatted Formatted meta entries.
	 * @param object $item      The order item.
	 * @return array
	 */
	public function offer_label( $formatted, $item ) {
		$label = is_object( $item ) && method_exists( $item, 'get_meta' ) ? (string) Order_Data::get( $item, Order_Attribution::META_OFFER_LABEL ) : '';
		// As in the cart, an upsell add-on shows no offer label; the label stays stored for reports.
		$role  = '' === $label ? '' : (string) Order_Data::get( $item, Order_Attribution::META_ROLE );
		if ( '' === $label || 'upsell' === $role ) {
			return $formatted;
		}
		$label = Cart::offer_label( $label, $role );
		$formatted['wffn_bundles_offer'] = (object) array(
			'key'           => '_wffn_bundles_offer_display',
			'value'         => $label,
			'display_key'   => __( 'Offer', 'funnel-builder' ),
			'display_value' => '<span class="wffn-bundles-cart-offer">' . esc_html( $label ) . '</span>',
		);
		return $formatted;
	}

	/**
	 * Swap the container placeholder line's name to the bundle title. The
	 * bundle name is the fallback for orders placed before the title snapshot
	 * existed. Members keep their own product name.
	 *
	 * @param string $name Name WooCommerce resolved.
	 * @param object $item The order item.
	 * @return string
	 */
	public function item_name( $name, $item ) {
		$role = self::role( $item );
		if ( ! in_array( $role, array( 'container', 'parent', 'heading' ), true ) ) {
			return $name;
		}
		$title = (string) Order_Data::get( $item, Order_Attribution::META_BUNDLE_TITLE );
		if ( '' === $title ) {
			$title = (string) Order_Data::get( $item, Order_Attribution::META_BUNDLE_NAME );
		}
		// Raw like the product name it replaces: callers escape for their own output, and plain-text emails print it as is.
		return '' !== $title ? $title : $name;
	}

	/**
	 * Blank a Fixed Bundle child's $0 line subtotal: the bundle's price sits on
	 * the container line. Display only — stored totals, tax and refunds are
	 * untouched.
	 *
	 * @param string         $subtotal Formatted subtotal.
	 * @param object         $item     The order item.
	 * @param \WC_Order|null $order    The order.
	 * @return string
	 */
	public function line_subtotal( $subtotal, $item, $order = null ) {
		$role = self::role( $item );
		if ( 'heading' === $role && $order instanceof \WC_Order ) {
			return $this->heading_subtotal( $item, $order );
		}
		return 'child' === $role ? '' : $subtotal;
	}

	/**
	 * How many items sit under a heading line.
	 *
	 * @param object $item The heading order item.
	 * @return int
	 */
	private static function nested_count( $item ) {
		$order = method_exists( $item, 'get_order' ) ? $item->get_order() : null;
		$group = (string) Order_Data::get( $item, Order_Attribution::META_GROUP );
		$count = 0;
		if ( $order instanceof \WC_Order && '' !== $group ) {
			foreach ( $order->get_items() as $line ) {
				if ( self::is_nested( $line ) && (string) Order_Data::get( $line, Order_Attribution::META_GROUP ) === $group ) {
					$count += (int) $line->get_quantity();
				}
			}
		}
		return max( 1, $count );
	}

	/**
	 * A heading line counts the items in its box, not the one placeholder.
	 *
	 * @param string $html Quantity markup so far.
	 * @param object $item The order item.
	 * @return string
	 */
	public function quantity_html( $html, $item ) {
		if ( 'heading' !== self::role( $item ) ) {
			return $html;
		}
		$qty      = self::nested_count( $item );
		$order    = method_exists( $item, 'get_order' ) ? $item->get_order() : null;
		$refunded = $order instanceof \WC_Order ? -1 * self::nested_refunds( $item, $order )[0] : 0;
		$shown    = $refunded ? '<del>' . esc_html( (string) $qty ) . '</del> <ins>' . esc_html( (string) max( 0, $qty - $refunded ) ) . '</ins>' : esc_html( (string) $qty );
		// The label is Cart::qty_text() without its count, so both read alike in every language.
		$label = trim( (string) preg_replace( '/\d+$/', '', Cart::qty_text( 1 ) ) );
		return ' <strong class="product-quantity">' . esc_html( $label ) . '&nbsp;' . $shown . '</strong>';
	}

	/**
	 * Quantity and amount refunded across a heading's nested members, both negative.
	 *
	 * @param object    $item  The heading order item.
	 * @param \WC_Order $order The order.
	 * @return array{0:int,1:float}
	 */
	private static function nested_refunds( $item, $order ) {
		$group  = (string) Order_Data::get( $item, Order_Attribution::META_GROUP );
		$qty    = 0;
		$amount = 0.0;
		foreach ( $order->get_items() as $line_id => $line ) {
			if ( self::is_nested( $line ) && (string) Order_Data::get( $line, Order_Attribution::META_GROUP ) === $group ) {
				$qty    += (int) $order->get_qty_refunded_for_item( $line_id );
				$amount -= (float) $order->get_total_refunded_for_item( $line_id );
			}
		}
		return array( $qty, $amount );
	}

	/**
	 * Email counterpart of quantity_html().
	 *
	 * @param string $qty  Quantity text so far.
	 * @param object $item The order item.
	 * @return string
	 */
	public function email_quantity( $qty, $item ) {
		return 'heading' === self::role( $item ) ? (string) self::nested_count( $item ) : $qty;
	}

	/**
	 * Hand the Edit Order screen what a heading line should show in its cells.
	 *
	 * The screen prints price, quantity and total straight from the stored line,
	 * so a small script swaps them for the box's item count and total.
	 *
	 * @param int    $item_id Order item id.
	 * @param object $item    The order item.
	 * @return void
	 */
	public function print_heading_sum( $item_id, $item ) {
		$order = is_object( $item ) && method_exists( $item, 'get_order' ) ? $item->get_order() : null;
		if ( 'heading' !== self::role( $item ) || ! $order instanceof \WC_Order ) {
			return;
		}
		list( $refunded_qty, $refunded ) = self::nested_refunds( $item, $order );
		$refunded_html                   = $refunded ? wc_price( $refunded, array( 'currency' => $order->get_currency() ) ) : '';
		printf(
			'<span class="wffn-bundles-heading-sum" hidden data-qty="%1$d" data-total="%2$s" data-refunded-qty="%3$d" data-refunded="%4$s"></span>',
			(int) self::nested_count( $item ),
			esc_attr( $this->heading_total_html( $item, $order, false ) ),
			(int) $refunded_qty,
			esc_attr( $refunded_html )
		);
	}

	/**
	 * A heading line's displayed total: what its nested members add up to.
	 *
	 * @param object    $item  The heading order item.
	 * @param \WC_Order $order The order.
	 * @return string
	 */
	private function heading_subtotal( $item, $order ) {
		return $this->heading_total_html( $item, $order, true );
	}

	/**
	 * The formatted sum of a heading's nested members.
	 *
	 * @param object    $item     The heading order item.
	 * @param \WC_Order $order    The order.
	 * @param bool      $storefront True for shopper pages, which follow the tax display setting.
	 * @return string
	 */
	private function heading_total_html( $item, $order, $storefront ) {
		$group     = (string) Order_Data::get( $item, Order_Attribution::META_GROUP );
		$total     = 0.0;
		$incl_tax  = 'incl' === get_option( 'woocommerce_tax_display_cart' );
		foreach ( $order->get_items() as $line ) {
			if ( ! self::is_nested( $line ) || (string) Order_Data::get( $line, Order_Attribution::META_GROUP ) !== $group ) {
				continue;
			}
			$total += $storefront
				? (float) $order->get_line_subtotal( $line, $incl_tax, true )
				: (float) $line->get_total();
		}
		return wc_price( $total, array( 'currency' => $order->get_currency() ) );
	}

	/**
	 * Load the grouping stylesheet on the Thank-You / View-Order pages.
	 *
	 * @return void
	 */
	public function enqueue_css() {
		if ( ! function_exists( 'is_order_received_page' ) ) {
			return;
		}
		// FunnelKit's Thank-You is its own page type, so WooCommerce's own endpoint
		// tests are false there while the very same grouped rows are on screen.
		$funnelkit_ty = function_exists( 'is_singular' ) && is_singular( 'wffn_ty' );
		if ( ! is_order_received_page() && ! is_wc_endpoint_url( 'view-order' ) && ! $funnelkit_ty ) {
			return;
		}
		$rel  = 'assets/skins/cart-blocks.css';
		$path = WFFN_BUNDLES_DIR . '/' . $rel;
		wp_enqueue_style( 'wffn-bundles-cart-grouping', WFFN_BUNDLES_URL . $rel, array(), (string) ( file_exists( $path ) ? filemtime( $path ) : '1' ) );
		// The order table indents members as the cart does, so it needs the same
		// per-theme correction layer.
		Cart::enqueue_theme_compat_css();
	}

	/**
	 * Load the grouping stylesheet on the wp-admin Edit Order screen, classic
	 * and HPOS alike.
	 *
	 * @return void
	 */
	public function enqueue_admin_css() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) || ! \Automattic\WooCommerce\Utilities\OrderUtil::is_order_edit_screen() ) {
			return;
		}
		$rel = 'assets/skins/admin-order-items.css';
		$css = WFFN_BUNDLES_DIR . '/' . $rel;
		wp_enqueue_style( 'wffn-bundles-admin-order-grouping', WFFN_BUNDLES_URL . $rel, array(), file_exists( $css ) ? (string) filemtime( $css ) : WFFN_BUNDLES_VERSION );
		$js   = 'assets/skins/admin-order-items.js';
		$path = WFFN_BUNDLES_DIR . '/' . $js;
		wp_enqueue_script( 'wffn-bundles-admin-order-grouping', WFFN_BUNDLES_URL . $js, array(), file_exists( $path ) ? (string) filemtime( $path ) : WFFN_BUNDLES_VERSION, true );
	}

	/**
	 * Inject the grouping rules into WooCommerce's email CSS for its inliner.
	 *
	 * Email conveys the grouping with an indent, as the order tables do.
	 *
	 * @param string      $css   Email CSS so far.
	 * @param object|null $email The email being sent.
	 * @return string
	 */
	public function email_styles( $css, $email = null ) {
		$side = is_rtl() ? 'right' : 'left';
		$css .= '
			.order_item.wffn-bundles-cart-child td { padding-top: 2px; padding-bottom: 2px; }
			.order_item.wffn-bundles-cart-child td.td:first-child { padding-left: 24px; font-size: 0.92em; }
			#body_content table .email-order-details tr.order_item.wffn-bundles-cart-child > td.td:first-child { padding-' . $side . ': 24px; }
		';
		return $css;
	}
}
