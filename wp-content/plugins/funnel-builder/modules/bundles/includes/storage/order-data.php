<?php
/**
 * A bundle order's line values, kept in one order meta.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the `_wffn_bundles_*` values of an order's bundle lines in the order's
 * `_wffn_bundles` meta instead of one order-item meta row per value per line.
 *
 * A line keeps none of them, its bundle id included: the order meta is the only
 * record. Values every line of a bundle shares are stored once, under the
 * bundle's group. Orders placed before this keep their per-line meta, and get()
 * reads it from there.
 */
class Order_Data {

	/** Order meta key holding the lines. */
	const META = '_wffn_bundles';

	/** Layout version of the stored array. */
	const VERSION = 1;

	/** Prefix of the per-line keys this class takes over. */
	const PREFIX = '_wffn_bundles_';

	/** @var array[] Lines waiting for their order to be saved, by object id: [ item, values ]. */
	private static $staged = array();

	/** @var array[] Stored data by order id, for this request. */
	private static $orders = array();

	/**
	 * Whether bundle lines store their values in the order meta.
	 *
	 * A listener on wffn_bundles_order_line_meta writes line values of its own and
	 * may read them back per line, so the per-line layout stays until it opts in.
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Filters whether bundle lines keep their values in the order's `_wffn_bundles` meta.
		 *
		 * Answer true once every value written on wffn_bundles_order_line_meta is
		 * read back through Order_Data::get().
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled True when nothing listens on wffn_bundles_order_line_meta.
		 */
		return (bool) apply_filters( 'wffn_bundles_store_order_data', ! has_action( 'wffn_bundles_order_line_meta' ) );
	}

	/**
	 * A bundle line's value: from the order meta, else from the line's own meta.
	 *
	 * @param \WC_Order_Item|mixed $item Order line.
	 * @param string               $key  A `_wffn_bundles_*` meta key.
	 * @return mixed '' when the line has no such value.
	 */
	public static function get( $item, $key ) {
		if ( ! $item instanceof \WC_Order_Item ) {
			return '';
		}
		$line  = self::line( $item );
		$field = self::field( $key );
		if ( null !== $line && array_key_exists( $field, $line ) ) {
			return $line[ $field ];
		}
		// A new line read before its order is saved: its values are still staged.
		$staged = self::$staged[ spl_object_id( $item ) ][1] ?? null;
		if ( is_array( $staged ) && array_key_exists( $field, $staged ) ) {
			return $staged[ $field ];
		}

		return $item->get_meta( $key );
	}

	/**
	 * Move a new line's `_wffn_bundles_*` values off the line until its order is saved.
	 *
	 * Called before those values are saved, so the ones it drops were never written.
	 *
	 * @param \WC_Order_Item $item Order line.
	 * @return void
	 */
	public static function stage( $item ) {
		if ( ! $item instanceof \WC_Order_Item ) {
			return;
		}
		$values = array();
		foreach ( $item->get_meta_data() as $meta ) {
			$key = (string) $meta->key;
			if ( 0 !== strpos( $key, self::PREFIX ) ) {
				continue;
			}
			$values[ self::field( $key ) ] = $meta->value;
			$item->delete_meta_data( $key );
		}
		if ( $values ) {
			self::$staged[ spl_object_id( $item ) ] = array( $item, $values );
		}
	}

	/**
	 * Write the staged lines of a saved order into its `_wffn_bundles` meta.
	 *
	 * Runs on woocommerce_after_order_object_save, once the lines have ids.
	 *
	 * @param \WC_Order|mixed $order Order just saved.
	 * @return void
	 */
	public static function flush( $order ) {
		if ( ! self::$staged || ! $order instanceof \WC_Order ) {
			return;
		}
		$order_id = (int) $order->get_id();
		$new      = array();
		foreach ( self::$staged as $object_id => $staged ) {
			list( $item, $values ) = $staged;
			if ( (int) $item->get_id() && (int) $item->get_order_id() === $order_id ) {
				$new[ (int) $item->get_id() ] = $values;
				unset( self::$staged[ $object_id ] );
			}
		}
		if ( ! $new ) {
			return;
		}

		// A block checkout rebuilds its draft order's lines on every update; drop the ones it removed.
		$present = array();
		foreach ( $order->get_items() as $line ) {
			$present[ (int) $line->get_id() ] = true;
		}
		$lines = array_intersect_key( self::expand( $order->get_meta( self::META ) ), $present );
		foreach ( $new as $item_id => $values ) {
			$lines[ $item_id ] = $values;
		}

		$packed = self::pack( $lines );
		$order->update_meta_data( self::META, $packed );
		// Follows the order's own save; past a second boundary HPOS would otherwise save the whole order again.
		add_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', array( __CLASS__, 'skip_resave' ) );
		$order->save_meta_data();
		remove_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', array( __CLASS__, 'skip_resave' ) );
		self::$orders[ $order_id ] = $packed;
	}

	/**
	 * Answer for HPOS's save-after-meta-change check while flush() writes.
	 *
	 * @return bool
	 */
	public static function skip_resave() {
		return false;
	}

	/**
	 * A line's values from its order's meta, group values included; null when it is not stored there.
	 *
	 * @param \WC_Order_Item $item Order line.
	 * @return array|null
	 */
	private static function line( $item ) {
		$item_id  = (int) $item->get_id();
		$order_id = (int) $item->get_order_id();
		if ( ! $item_id || ! $order_id ) {
			return null;
		}
		if ( ! array_key_exists( $order_id, self::$orders ) ) {
			$order                     = wc_get_order( $order_id );
			self::$orders[ $order_id ] = $order instanceof \WC_Abstract_Order ? $order->get_meta( self::META ) : array();
		}
		$data = self::$orders[ $order_id ];
		if ( ! is_array( $data ) || ! isset( $data['lines'][ $item_id ] ) || ! is_array( $data['lines'][ $item_id ] ) ) {
			return null;
		}
		$line  = $data['lines'][ $item_id ];
		$group = (string) ( $line['group'] ?? '' );

		return '' !== $group && isset( $data['groups'][ $group ] ) && is_array( $data['groups'][ $group ] ) ? $line + $data['groups'][ $group ] : $line;
	}

	/**
	 * Every stored line with its group's values merged back in, by item id.
	 *
	 * @param mixed $data Stored `_wffn_bundles` meta.
	 * @return array[]
	 */
	private static function expand( $data ) {
		$lines = array();
		if ( ! is_array( $data ) || ! is_array( $data['lines'] ?? null ) ) {
			return $lines;
		}
		$groups = is_array( $data['groups'] ?? null ) ? $data['groups'] : array();
		foreach ( $data['lines'] as $item_id => $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$group               = (string) ( $line['group'] ?? '' );
			$lines[ (int) $item_id ] = '' !== $group && is_array( $groups[ $group ] ?? null ) ? $line + $groups[ $group ] : $line;
		}

		return $lines;
	}

	/**
	 * Store each bundle's shared values once, under its group.
	 *
	 * Lines with no group are left whole: they may belong to different bundles.
	 *
	 * @param array[] $lines Values by item id.
	 * @return array
	 */
	private static function pack( array $lines ) {
		$by_group = array();
		foreach ( $lines as $item_id => $line ) {
			$group = (string) ( $line['group'] ?? '' );
			if ( '' !== $group ) {
				$by_group[ $group ][ $item_id ] = $line;
			}
		}

		$groups = array();
		foreach ( $by_group as $group => $members ) {
			if ( count( $members ) < 2 ) {
				continue;
			}
			$shared = null;
			foreach ( $members as $line ) {
				$shared = null === $shared ? $line : self::same( $shared, $line );
			}
			unset( $shared['group'] );
			if ( ! $shared ) {
				continue;
			}
			$groups[ $group ] = $shared;
			foreach ( array_keys( $members ) as $item_id ) {
				$lines[ $item_id ] = array_diff_key( $lines[ $item_id ], $shared );
			}
		}

		return array(
			'v'      => self::VERSION,
			'groups' => $groups,
			'lines'  => $lines,
		);
	}

	/**
	 * The keys two lines hold with the identical value.
	 *
	 * @param array $a One line.
	 * @param array $b Another.
	 * @return array
	 */
	private static function same( array $a, array $b ) {
		$same = array();
		foreach ( $a as $key => $value ) {
			if ( array_key_exists( $key, $b ) && $b[ $key ] === $value ) {
				$same[ $key ] = $value;
			}
		}

		return $same;
	}

	/**
	 * The stored name of a `_wffn_bundles_*` meta key.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private static function field( $key ) {
		$key = (string) $key;

		return 0 === strpos( $key, self::PREFIX ) ? substr( $key, strlen( self::PREFIX ) ) : $key;
	}
}
