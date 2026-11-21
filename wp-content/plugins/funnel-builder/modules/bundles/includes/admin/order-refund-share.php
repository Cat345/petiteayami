<?php
/**
 * A Fixed Bundle member's share of its bundle price on the admin order screen.
 *
 * The bundle line carries the whole price and its members sit at 0, so a member
 * refunded on its own would otherwise be refunded at 0. Each member is shown its
 * share, split by list price, and the refund form fills it in when the member is
 * ticked. Nothing is stored: the share is read from the order's own lines.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Admin;

use WFFN\Bundles\Includes\Storage\Order_Items;
use WFFN\Bundles\Includes\Storage\Order_Attribution;
use WFFN\Bundles\Includes\Storage\Order_Data;

defined( 'ABSPATH' ) || exit;

/**
 * Prints each member's share and loads the refund form helper.
 */
class Order_Refund_Share {

	/** Shares per order id, item id => [ 'total' => float, 'tax' => [ rate id => float ] ]. */
	private static $shares = array();

	/**
	 * Hook in. Admin only.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'print_share' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Print the figures the refund form reads under a Fixed Bundle line.
	 *
	 * A member shows its share; a bundle line, once money went back through its
	 * members, shows that amount and offers only what is left of it.
	 *
	 * @param int            $item_id Order item id.
	 * @param \WC_Order_Item $item    The order item.
	 * @return void
	 */
	public static function print_share( $item_id, $item ) {
		$role  = $item instanceof \WC_Order_Item_Product ? (string) Order_Data::get( $item, Order_Attribution::META_ROLE ) : '';
		$order = '' !== $role ? $item->get_order() : null;
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$price = array( 'currency' => $order->get_currency() );

		if ( Order_Items::ROLE_CHILD === $role ) {
			$shares = self::shares( $order );
			if ( ! isset( $shares[ $item_id ] ) ) {
				return;
			}
			$total = $shares[ $item_id ]['total'];
			/* translators: %s: this product's share of its bundle's price. */
			$label = sprintf( __( 'Bundle share: %s', 'funnel-builder' ), wc_price( $total, $price ) );
			// Shown in the Cost and Total columns in place of the stored 0; the stored amounts stay as they are.
			$columns = array(
				'unit' => wc_price( $total / max( 1, (int) $item->get_quantity() ), $price ),
				'line' => wc_price( $total, $price ),
			);
			self::print_figures( $total, $shares[ $item_id ]['tax'], $item, $label, $columns, (string) Order_Data::get( $item, Order_Attribution::META_GROUP ) );
			return;
		}

		if ( Order_Items::ROLE_CONTAINER !== $role ) {
			return;
		}
		// Marks the pack row, so a pack refund leaves its members' money out of the form.
		printf( '<span class="wffn-bundles-pack" data-group="%s" hidden></span>', esc_attr( (string) Order_Data::get( $item, Order_Attribution::META_GROUP ) ) );
		$back = self::member_refunds( $order, (string) Order_Data::get( $item, Order_Attribution::META_GROUP ) );
		if ( $back['total'] <= 0 ) {
			return;
		}
		$taxes = $item->get_taxes();
		$left  = array();
		foreach ( (array) ( $taxes['total'] ?? array() ) as $rate_id => $amount ) {
			$left[ $rate_id ] = max( 0.0, (float) $amount - ( $back['tax'][ $rate_id ] ?? 0.0 ) );
		}
		/* translators: %s: money already refunded on this bundle's products. */
		$label = sprintf( __( 'Refunded through its products: %s', 'funnel-builder' ), wc_price( $back['total'], $price ) );
		self::print_figures( max( 0.0, (float) $item->get_total() - $back['total'] ), (object) $left, $item, $label );
	}

	/**
	 * The figures block one refund form row reads.
	 *
	 * @param float          $total What a full refund of the line comes to.
	 * @param object         $tax   Rate id => tax a full refund comes to.
	 * @param \WC_Order_Item $item  The order item.
	 * @param string         $label   Text shown under the line, already formatted.
	 * @param array          $columns Cost and Total cell figures to show instead of the stored ones, empty for none.
	 * @param string         $group   A member's bundle group, empty for the bundle line.
	 * @return void
	 */
	private static function print_figures( $total, $tax, $item, $label, array $columns = array(), $group = '' ) {
		printf(
			'<div class="wffn-bundles-share" data-share="%1$s" data-tax="%2$s" data-qty="%3$s" data-unit="%4$s" data-line="%5$s" data-group="%7$s">%6$s</div>',
			esc_attr( wc_format_decimal( $total ) ),
			esc_attr( wp_json_encode( $tax ) ),
			esc_attr( (string) max( 1, (int) $item->get_quantity() ) ),
			esc_attr( $columns['unit'] ?? '' ),
			esc_attr( $columns['line'] ?? '' ),
			wp_kses_post( $label ),
			esc_attr( $group )
		);
	}

	/**
	 * Money already refunded on a bundle's members, which sit at 0 and so carry none of their own.
	 *
	 * @param \WC_Order $order The order.
	 * @param string    $group The bundle's group.
	 * @return array [ 'total' => float, 'tax' => [ rate id => float ] ].
	 */
	private static function member_refunds( $order, $group ) {
		$back = array(
			'total' => 0.0,
			'tax'   => array(),
		);
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( Order_Items::ROLE_CHILD !== (string) Order_Data::get( $item, Order_Attribution::META_ROLE ) || (string) Order_Data::get( $item, Order_Attribution::META_GROUP ) !== $group ) {
				continue;
			}
			$back['total'] += (float) $order->get_total_refunded_for_item( $item_id );
			foreach ( $order->get_taxes() as $tax_item ) {
				$rate_id                 = $tax_item->get_rate_id();
				$back['tax'][ $rate_id ] = ( $back['tax'][ $rate_id ] ?? 0.0 ) + (float) $order->get_tax_refunded_for_item( $item_id, $rate_id );
			}
		}
		return $back;
	}

	/**
	 * Load the refund form helper on the order edit screen.
	 *
	 * @return void
	 */
	public static function enqueue() {
		// The orders list shares the edit screen's id, so ask for the edit screen itself.
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) || ! \Automattic\WooCommerce\Utilities\OrderUtil::is_order_edit_screen() ) {
			return;
		}
		$file  = 'assets/admin/order-refund-share.js';
		$mtime = file_exists( WFFN_BUNDLES_DIR . '/' . $file ) ? filemtime( WFFN_BUNDLES_DIR . '/' . $file ) : false;
		wp_enqueue_script( 'wffn-bundles-order-refund-share', WFFN_BUNDLES_URL . $file, array( 'jquery', 'accounting', 'wc-admin-order-meta-boxes' ), $mtime ? WFFN_BUNDLES_VERSION . '.' . $mtime : WFFN_BUNDLES_VERSION, true );
		// WordPress's own secondary text colour, so a share never reads as money charged on its line.
		wp_add_inline_style( 'woocommerce_admin_styles', '#woocommerce-order-items .wffn-bundles-share-in{color:#646970}' );
	}

	/**
	 * A bundle member's unit list price, as recorded at checkout or, when the order
	 * did not record one, the product's current regular price.
	 *
	 * @param \WC_Order_Item_Product $item The member line.
	 * @return float 0 when neither is known.
	 */
	private static function list_price( $item ) {
		$unit = (float) Order_Data::get( $item, Order_Attribution::META_UNIT );
		if ( $unit > 0 ) {
			return $unit;
		}
		$product = $item->get_product();
		return $product ? max( 0.0, (float) $product->get_regular_price() ) : 0.0;
	}

	/**
	 * Each Fixed Bundle member's share of its bundle line, split by list price.
	 *
	 * Rounded so a bundle's shares add up to its line exactly.
	 *
	 * @param \WC_Order $order The order.
	 * @return array Item id => [ 'total' => float, 'tax' => [ rate id => float ] ].
	 */
	public static function shares( $order ) {
		$order_id = $order->get_id();
		if ( isset( self::$shares[ $order_id ] ) ) {
			return self::$shares[ $order_id ];
		}

		$containers = array();
		$members    = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$group = (string) Order_Data::get( $item, Order_Attribution::META_GROUP );
			$role  = (string) Order_Data::get( $item, Order_Attribution::META_ROLE );
			if ( '' === $group ) {
				continue;
			}
			if ( Order_Items::ROLE_CONTAINER === $role ) {
				$containers[ $group ] = $item;
			} elseif ( Order_Items::ROLE_CHILD === $role ) {
				$members[ $group ][ $item_id ] = self::list_price( $item ) * max( 1, (int) $item->get_quantity() );
			}
		}

		$shares   = array();
		$decimals = wc_get_price_decimals();
		foreach ( $containers as $group => $container ) {
			if ( empty( $members[ $group ] ) ) {
				continue;
			}
			$weights = $members[ $group ];
			// No list prices recorded: split evenly.
			if ( array_sum( $weights ) <= 0 ) {
				$weights = array_fill_keys( array_keys( $weights ), 1.0 );
			}
			$taxes = $container->get_taxes();
			$total = self::split( (float) $container->get_total(), $weights, $decimals );
			$tax   = array();
			foreach ( (array) ( $taxes['total'] ?? array() ) as $rate_id => $amount ) {
				$tax[ $rate_id ] = self::split( (float) $amount, $weights, $decimals );
			}
			foreach ( $total as $item_id => $amount ) {
				$shares[ $item_id ] = array(
					'total' => $amount,
					'tax'   => (object) array_map(
						static function ( $split ) use ( $item_id ) {
							return $split[ $item_id ];
						},
						$tax
					),
				);
			}
		}

		self::$shares[ $order_id ] = $shares;
		return $shares;
	}

	/**
	 * Split an amount by weight, handing the rounding pennies to the largest remainders.
	 *
	 * @param float $amount   Amount to split.
	 * @param array $weights  Key => weight, at least one above 0.
	 * @param int   $decimals Decimals to round to.
	 * @return array Key => amount.
	 */
	private static function split( $amount, array $weights, $decimals ) {
		$unit   = pow( 10, $decimals );
		$cents  = (int) round( $amount * $unit );
		$sum    = array_sum( $weights );
		$out    = array();
		$remain = array();
		foreach ( $weights as $key => $weight ) {
			$exact          = $cents * $weight / $sum;
			$out[ $key ]    = (int) floor( $exact );
			$remain[ $key ] = $exact - $out[ $key ];
		}
		arsort( $remain );
		foreach ( array_slice( array_keys( $remain ), 0, $cents - array_sum( $out ) ) as $key ) {
			++$out[ $key ];
		}
		return array_map(
			static function ( $value ) use ( $unit ) {
				return $value / $unit;
			},
			$out
		);
	}
}
