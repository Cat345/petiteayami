<?php
/** Frequently Bought Together — view-model builder, set pricing and companion resolution. */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Storage\Bundle_Rules;
use WFFN\Bundles\Includes\Storage\Bundle_Store;

/**
 * The viewed product plus the companions bought with it, sold as one set.
 *
 * The host is always part of the set and never unticked; companions are
 * optional. The price helper here is the one the cart charges with, so the
 * widget, the live quote and the cart line agree.
 */
class Frequently_Bought_Together {

	/** Companions the widget shows at most. */
	const DEFAULT_MAX_ITEMS = Bundle_Rules::FBT_MAX_COMPANIONS;

	/**
	 * Price a set: the host and whichever companions are ticked.
	 *
	 * Each line is `[ min, max, host, on ]`: base-currency unit prices (equal
	 * unless a variation is still open), whether it is the host, whether it is
	 * in the set. The discount lands on the companions, or on the whole set,
	 * once `$min` companions are in; a fixed amount spreads by price across the
	 * discounted lines.
	 *
	 * @param array[] $lines Lines as above.
	 * @param string  $scope `companions` or `set`.
	 * @param string  $mode  `percentage` or `fixed`.
	 * @param float   $value Discount value.
	 * @param int     $min   Companions needed before the discount applies.
	 * @param array   $products For a displayed price only: each line's product, so an
	 *                          active discount engine's rule folds in as the cart will
	 *                          charge it. Left empty when pricing the cart itself.
	 * @return array{lines:array[],orig:float,origMax:float,final:float,finalMax:float,qualifies:bool,count:int,need:int}
	 */
	public static function price_set( array $lines, $scope, $mode, $value, $min, array $products = array() ) {
		$min   = max( 1, (int) $min );
		$count = 0;
		foreach ( $lines as $l ) {
			if ( empty( $l['host'] ) && ! empty( $l['on'] ) ) {
				++$count;
			}
		}
		$qualifies = $count >= $min && (float) $value > 0;

		$disc_min = 0.0;
		$disc_max = 0.0;
		foreach ( $lines as $l ) {
			if ( ! empty( $l['on'] ) && self::discounted( $l, $scope ) ) {
				$disc_min += (float) $l['min'];
				$disc_max += (float) $l['max'];
			}
		}
		$ratio_min = $qualifies ? Cart::discount_ratio( $disc_min, (string) $mode, (float) $value ) : 1.0;
		$decimals  = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$ratio_max = $qualifies ? Cart::discount_ratio( $disc_max, (string) $mode, (float) $value ) : 1.0;

		$out = array(
			'lines'     => array(),
			'orig'      => 0.0,
			'origMax'   => 0.0,
			'final'     => 0.0,
			'finalMax'  => 0.0,
			'qualifies' => $qualifies,
			'count'     => $count,
			'need'      => max( 0, $min - $count ),
		);
		foreach ( $lines as $i => $l ) {
			$lo   = (float) $l['min'];
			$hi   = max( $lo, (float) $l['max'] );
			$cut  = self::discounted( $l, $scope );
			// Rounded per line as the cart rounds each line, so the set's total matches the cart's.
			$f_lo = $cut ? round( $lo * $ratio_min, $decimals ) : $lo;
			$f_hi = $cut ? round( $hi * $ratio_max, $decimals ) : $hi;
			if ( isset( $products[ $i ] ) ) {
				$f_lo = round( Pricing::fold_engine( $f_lo, $lo, 1, $products[ $i ] ), $decimals );
				$f_hi = round( Pricing::fold_engine( $f_hi, $hi, 1, $products[ $i ] ), $decimals );
			}

			$out['lines'][] = array(
				'final'    => $f_lo,
				'finalMax' => $f_hi,
			);
			if ( empty( $l['on'] ) ) {
				continue;
			}
			$out['orig']     += $lo;
			$out['origMax']  += $hi;
			$out['final']    += $f_lo;
			$out['finalMax'] += $f_hi;
		}
		// Per-line rounding can leave the set a cent off its discounted total; the largest line absorbs it.
		foreach ( array( 'final' => $ratio_min, 'finalMax' => $ratio_max ) as $end => $ratio ) {
			$settle = array();
			foreach ( $lines as $i => $l ) {
				$raw = 'final' === $end ? (float) $l['min'] : max( (float) $l['min'], (float) $l['max'] );
				// A line the engine re-priced, or one left out of the set, keeps its own figure.
				if ( empty( $l['on'] ) || ! self::discounted( $l, $scope ) || round( $raw * $ratio, $decimals ) !== $out['lines'][ $i ][ $end ] ) {
					continue;
				}
				$settle[] = array(
					'key'  => $i,
					'qty'  => 1,
					'base' => $out['lines'][ $i ][ $end ],
					'raw'  => $raw * $ratio,
				);
			}
			foreach ( Cart::settle_base_cents( array( $settle ), $decimals ) as $moved ) {
				$out[ $end ]                          += $moved['base'] - $out['lines'][ $moved['key'] ][ $end ];
				$out['lines'][ $moved['key'] ][ $end ] = $moved['base'];
			}
		}
		return $out;
	}

	/**
	 * Does the set discount apply to this line?
	 *
	 * @param array  $line  Priced line.
	 * @param string $scope `companions` or `set`.
	 * @return bool
	 */
	private static function discounted( array $line, $scope ) {
		return 'set' === (string) $scope || empty( $line['host'] );
	}

	/**
	 * The companion product ids a host shows, in display order.
	 *
	 * The bundle's own list when it has one, otherwise the host's linked
	 * products. Anything that cannot be added from the widget is dropped
	 * before the cap, so the cap counts real rows. The cart validates a posted
	 * companion against this same list.
	 *
	 * @param array       $dc       Bundle discount config (hydrated or stored).
	 * @param \WC_Product $host     The viewed product.
	 * @param bool        $in_stock False keeps the companions that are out of stock.
	 * @return int[]
	 */
	public static function companion_ids( array $dc, $host, $in_stock = true ) {
		if ( ! $host instanceof \WC_Product ) {
			return array();
		}
		$host_id = (int) $host->get_id();

		$ids = array();
		foreach ( (array) ( $dc['fbtItems'] ?? array() ) as $row ) {
			$id = is_array( $row ) && is_array( $row['product'] ?? null ) ? absint( $row['product']['id'] ?? 0 ) : 0;
			if ( $id ) {
				$ids[] = $id;
			}
		}
		if ( empty( $ids ) ) {
			$fallback = (string) ( $dc['fbtFallback'] ?? 'cross_sells' );
			if ( 'none' !== $fallback ) {
				$ids = array_map( 'absint', (array) $host->get_cross_sell_ids() );
				if ( 'cross_sells_upsells' === $fallback ) {
					$ids = array_merge( $ids, array_map( 'absint', (array) $host->get_upsell_ids() ) );
				}
			}
		}

		// A stored fbtMaxItems is ignored: no setting sets it, and older saves hold a default of 3.
		$max = self::DEFAULT_MAX_ITEMS;

		$out = array();
		\WFFN\Bundles\Includes\Products\Repository::prime_posts( $ids );
		foreach ( array_unique( $ids ) as $id ) {
			if ( $id === $host_id || in_array( $id, $out, true ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! self::addable( $product, $in_stock ) ) {
				continue;
			}
			$out[] = $id;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Can the widget add this product as a companion?
	 *
	 * A grouped or external product has no line of its own to add, and a
	 * hidden or unbuyable one must not be offered.
	 *
	 * @param mixed $product  Product object.
	 * @param bool  $in_stock Whether it must be in stock too.
	 * @return bool
	 */
	private static function addable( $product, $in_stock = true ) {
		if ( ! $product instanceof \WC_Product || $product->is_type( array( 'grouped', 'external' ) ) ) {
			return false;
		}
		return $product->is_visible() && $product->is_purchasable() && ( ! $in_stock || $product->is_in_stock() );
	}

	/**
	 * Whether a stored row asks for its companion to start ticked.
	 *
	 * @param array $dc         Bundle discount config.
	 * @param int   $product_id Companion id.
	 * @return bool
	 */
	public static function starts_checked( array $dc, $product_id ) {
		foreach ( (array) ( $dc['fbtItems'] ?? array() ) as $row ) {
			$id = is_array( $row ) && is_array( $row['product'] ?? null ) ? absint( $row['product']['id'] ?? 0 ) : 0;
			if ( $id === (int) $product_id ) {
				return ! array_key_exists( 'checked', $row ) || ! empty( $row['checked'] );
			}
		}
		// A linked product the merchant never listed starts ticked.
		return true;
	}

	/**
	 * The discount settings a set is priced by, with every value in range.
	 *
	 * @param array $dc Bundle discount config.
	 * @return array{scope:string,mode:string,value:float,min:int}
	 */
	public static function rule( array $dc ) {
		$mode = (string) ( $dc['fbtDiscountType'] ?? 'percentage' );
		$none = 'none' === (string) ( $dc['fbtDiscountScope'] ?? 'companions' );
		return array(
			'scope' => 'set' === (string) ( $dc['fbtDiscountScope'] ?? 'companions' ) ? 'set' : 'companions',
			'mode'  => in_array( $mode, array( 'percentage', 'fixed' ), true ) ? $mode : 'percentage',
			'value' => $none ? 0.0 : max( 0.0, (float) ( $dc['fbtDiscountValue'] ?? 0 ) ),
			'min'   => max( 1, (int) ( $dc['fbtMinCompanions'] ?? 1 ) ),
		);
	}

	/**
	 * The savings or unlock sentence under the total.
	 *
	 * @param array  $priced Result of price_set().
	 * @param array  $rule   Result of rule().
	 * @param string $saved  Formatted saving.
	 * @return array{saveShow:bool,saveText:string,ruleShow:bool,ruleText:string}
	 */
	public static function note( array $priced, array $rule, $saved ) {
		if ( $priced['qualifies'] && $priced['final'] < $priced['orig'] ) {
			return array(
				'saveShow' => true,
				/* translators: %s: the amount saved on the set. */
				'saveText' => sprintf( __( 'You save %s', 'funnel-builder' ), $saved ),
				'ruleShow' => false,
				'ruleText' => '',
			);
		}
		if ( $rule['value'] <= 0 ) {
			return array( 'saveShow' => false, 'saveText' => '', 'ruleShow' => false, 'ruleText' => '' );
		}
		$need   = max( 1, (int) $priced['need'] );
		$amount = 'percentage' === $rule['mode'] ? self::percent( $rule['value'] ) : Pricing::format( $rule['value'] );
		if ( $priced['count'] > 0 ) {
			/* translators: 1: items still to add, 2: the discount, e.g. "10%". */
			$text = sprintf( _n( 'Add %1$d more item to save %2$s', 'Add %1$d more items to save %2$s', $need, 'funnel-builder' ), $need, $amount );
		} else {
			/* translators: 1: items to add, 2: the discount, e.g. "10%". */
			$text = sprintf( _n( 'Add %1$d item to save %2$s', 'Add %1$d items to save %2$s', $need, 'funnel-builder' ), $need, $amount );
		}
		return array( 'saveShow' => false, 'saveText' => '', 'ruleShow' => true, 'ruleText' => $text );
	}

	/**
	 * A percentage as the widget prints it, without trailing zeros.
	 *
	 * @param float $value Percentage.
	 * @return string
	 */
	private static function percent( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	/**
	 * The button label for a set of `$count` products.
	 *
	 * @param int    $count  Products the click adds, the host included.
	 * @param string $custom The merchant's own label, when set.
	 * @return string
	 */
	public static function button_text( $count, $custom = '' ) {
		if ( '' !== trim( (string) $custom ) ) {
			return (string) $custom;
		}
		$count = max( 1, (int) $count );
		/* translators: %d: number of products the button adds. */
		return sprintf( _n( 'Add %d item to cart', 'Add %d items to cart', $count, 'funnel-builder' ), $count );
	}

	/**
	 * Build the root view-model.
	 *
	 * @param array $args View-model input args.
	 * @return array
	 */
	public static function view_model( array $args ) {
		$heading    = (string) ( $args['heading'] ?? '' );
		$subheading = (string) ( $args['subheading'] ?? '' );
		$design     = isset( $args['design'] ) && is_array( $args['design'] ) ? $args['design'] : array();
		$host       = isset( $args['host'] ) && is_array( $args['host'] ) ? $args['host'] : array();
		$items_in   = isset( $args['items'] ) && is_array( $args['items'] ) ? array_values( $args['items'] ) : array();
		$rule       = array(
			'scope' => 'set' === (string) ( $args['scope'] ?? 'companions' ) ? 'set' : 'companions',
			'mode'  => 'fixed' === (string) ( $args['mode'] ?? 'percentage' ) ? 'fixed' : 'percentage',
			'value' => max( 0.0, (float) ( $args['value'] ?? 0 ) ),
			'min'   => max( 1, (int) ( $args['minCompanions'] ?? 1 ) ),
		);

		$fmt = function ( $lo, $hi ) {
			return Pricing::format_range( $lo, $hi );
		};

		$lines = array(
			array(
				'min'  => (float) ( $host['price'] ?? 0 ),
				'max'  => max( (float) ( $host['price'] ?? 0 ), (float) ( $host['priceMax'] ?? 0 ) ),
				'host' => true,
				'on'   => true,
			),
		);
		foreach ( $items_in as $item ) {
			$lines[] = array(
				'min'  => (float) ( $item['price'] ?? 0 ),
				'max'  => max( (float) ( $item['price'] ?? 0 ), (float) ( $item['priceMax'] ?? 0 ) ),
				'host' => false,
				'on'   => ! empty( $item['checked'] ),
			);
		}
		$products = array();
		foreach ( array_merge( array( $host ), $items_in ) as $i => $src ) {
			$pid = absint( $src['productId'] ?? 0 );
			if ( $pid ) {
				$products[ $i ] = wc_get_product( $pid );
			}
		}
		$priced = self::price_set( $lines, $rule['scope'], $rule['mode'], $rule['value'], $rule['min'], array_filter( $products ) );

		$this_label = __( 'This item:', 'funnel-builder' );
		$rows       = array();
		$sources    = array_merge( array( $host ), $items_in );
		foreach ( $sources as $i => $src ) {
			$is_host = 0 === $i;
			$on      = $is_host || ! empty( $src['checked'] );
			$line    = $lines[ $i ];
			$final   = $priced['lines'][ $i ];
			$image   = (string) ( $src['image'] ?? '' );
			$strike  = $on && $final['final'] < $line['min'];
			$in_cart = ! $is_host && ! empty( $src['inCart'] );

			$rows[] = array_merge(
				array(
					'key'           => $is_host ? 'host' : (string) absint( $src['productId'] ?? 0 ),
					'isHost'        => $is_host,
					'thisLabel'     => $this_label,
					'name'          => (string) ( $src['name'] ?? '' ),
					'image'         => $image,
					'imageShow'     => '' !== $image,
					'plusShow'      => ! $is_host,
					'checked'       => $on,
					'rowClass'      => ( $is_host ? 'is-host' : '' ) . ( $on ? '' : ' is-off' ),
					'offClass'      => $on ? '' : 'is-off',
					'priceOriginal' => $fmt( $line['min'], $line['max'] ),
					'priceFinal'    => $fmt( $strike ? $final['final'] : $line['min'], $strike ? $final['finalMax'] : $line['max'] ),
					'showStrike'    => $strike,
					'inCart'        => $in_cart,
					'inCartLabel'   => __( 'Already in your cart', 'funnel-builder' ),
				),
				Variants::picker( $src )
			);
		}

		$has_discount = $priced['final'] < $priced['orig'];
		$note         = self::note( $priced, $rule, Pricing::format( $priced['orig'] - $priced['final'] ) );
		$count        = 1 + (int) $priced['count'];

		$vm = array_merge(
			array(
				'rootStyle'       => Css_Vars::for_design( $design ),
				'bundleId'      => (string) ( $args['bundleId'] ?? '' ),
				'productId'       => (string) absint( $host['productId'] ?? 0 ),
				'minCompanions'   => (string) $rule['min'],
				'heading'         => $heading,
				'headingShow'     => '' !== trim( $heading ),
				'subheading'      => $subheading,
				'subheadingShow'  => '' !== trim( $subheading ),
				'rows'            => $rows,
				'totalLabel'      => __( 'Total:', 'funnel-builder' ),
				'totalOriginal'   => $fmt( $priced['orig'], $priced['origMax'] ),
				'totalFinal'      => $fmt( $priced['final'], $priced['finalMax'] ),
				'totalShowStrike' => $has_discount,
				'buttonText'      => self::button_text( $count, (string) ( $args['buttonLabel'] ?? '' ) ),
				'buttonShow'      => ! isset( $args['buttonShow'] ) || ! empty( $args['buttonShow'] ),
			),
			$note
		);

		/** This filter is documented in includes/skins/fixed-bundle.php */
		return apply_filters( 'wffn_bundles_view_model', $vm, $args, Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER );
	}

	/**
	 * Starter content for the editor preview seed.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'design'        => array(),
			'heading'       => __( 'Frequently bought together', 'funnel-builder' ),
			'scope'         => 'companions',
			'mode'          => 'percentage',
			'value'         => 10,
			'minCompanions' => 1,
			'host'          => array( 'name' => __( 'Product A', 'funnel-builder' ), 'price' => 32.0, 'image' => '' ),
			'items'         => array(
				array( 'name' => __( 'Product B', 'funnel-builder' ), 'price' => 8.0, 'image' => '', 'checked' => true ),
				array( 'name' => __( 'Product C', 'funnel-builder' ), 'price' => 64.0, 'image' => '', 'checked' => true ),
			),
		);
	}
}
