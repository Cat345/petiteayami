<?php
/**
 * Server-side value rules for a bundle payload.
 *
 * Covers the value RANGES the editor enforces in
 * `admin/app/src/pages/bundles/edit/schema.ts`: discount bounds, title length,
 * member quantities, and whether each offer can hold what it asks
 * for. An add-on validates the types it renders through
 * `wffn_bundles_bundle_rules_issues`.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Storage;

use WFFN\Bundles\Includes\Products\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Validates bundle payload value ranges, independently of the admin app.
 */
class Bundle_Rules {

	/** Longest offer title the editor accepts. */
	const TITLE_MAX = 60;

	/** Longest bundle name the editor accepts. */
	/** Longest bundle name, from the schema, which takes it from the column. */
	const NAME_MAX = Bundles_Table::NAME_LENGTH;

	/**
	 * Largest quantity one offer may ask for.
	 *
	 * The tier quantity, not the shopper, decides how many units a Quantity
	 * Breaks add puts in the cart, so a mistyped 10000 adds ten thousand units
	 * on a product that does not manage stock.
	 */
	const QUANTITY_MAX = 999;

	/** Most companions a Frequently Bought Together widget shows beside the viewed product. */
	const FBT_MAX_COMPANIONS = 5;

	/**
	 * Every rule violation in a (merged) bundle payload.
	 *
	 * @param array $p Bundle payload, post-sanitize.
	 * @return array<int,array{path:string,message:string}> Field path + message, empty when valid.
	 */
	public static function validate( array $p ) {
		$issues = array();
		$type   = (int) ( $p['discountType'] ?? 0 );
		$dc     = is_array( $p['discountConfig'] ?? null ) ? $p['discountConfig'] : array();

		self::check_name( $p, $issues );

		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			self::check_fixed_bundle( $dc, $issues );
		} elseif ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			self::check_frequently_bought_together( $dc, $issues );
		}

		/**
		 * Filters the validation issues found in a bundle payload.
		 *
		 * An add-on validates the types it owns here.
		 *
		 * @since 1.0.0
		 *
		 * @param array $issues Field path + message pairs found so far.
		 * @param array $dc     Bundle discount config.
		 * @param int   $type   Bundle type.
		 * @param array $p      The whole bundle payload, for settings stored outside the discount config.
		 */
		return (array) apply_filters( 'wffn_bundles_bundle_rules_issues', $issues, $dc, $type, $p );
	}

	/**
	 * What stands between this bundle and going live.
	 *
	 * Run when a bundle is being activated, never on a draft save. Mirrors the
	 * editor's activation gate so the list toggle, bulk actions and the REST API
	 * cannot activate what the editor would refuse.
	 *
	 * @param array $p Bundle as it would be stored.
	 * @return array<int,array{path:string,message:string}>
	 */
	public static function activation_issues( array $p ) {
		$issues = array();
		$type   = (int) ( $p['discountType'] ?? 0 );
		$dc     = is_array( $p['discountConfig'] ?? null ) ? $p['discountConfig'] : array();

		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			$listed = 0;
			foreach ( (array) ( $dc['fixedBundleItems'] ?? array() ) as $item ) {
				if ( is_array( $item ) && ! empty( $item['product'] ) ) {
					++$listed;
				}
			}
			if ( $listed < 1 ) {
				$issues[] = array(
					'path'    => 'fixedBundleItems',
					'message' => __( 'Add at least one product before activating this bundle.', 'funnel-builder' ),
				);
			}
		} elseif ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			self::check_fbt_activation( $p, $dc, $issues );
		}

		/**
		 * Filters what stops a bundle from being activated.
		 *
		 * An add-on checks the types it owns here.
		 *
		 * @since 1.0.0
		 *
		 * @param array $issues Field path + message pairs found so far.
		 * @param array $p      Bundle as it would be stored.
		 * @param int   $type   Bundle type.
		 */
		return (array) apply_filters( 'wffn_bundles_bundle_activation_issues', $issues, $p, $type );
	}

	/**
	 * Frequently Bought Together needs companions: listed by hand, or linked to a placed product.
	 *
	 * @param array $p      Bundle.
	 * @param array $dc     Discount config.
	 * @param array $issues Issue list, by reference.
	 */
	private static function check_fbt_activation( array $p, array $dc, array &$issues ) {
		$fallback = (string) ( $dc['fbtFallback'] ?? 'cross_sells' );
		$listed   = false;
		foreach ( (array) ( $dc['fbtItems'] ?? array() ) as $row ) {
			if ( is_array( $row ) && ! empty( $row['product'] ) ) {
				$listed = true; // Listed products win over any fallback.
				break;
			}
		}

		if ( ! $listed && 'none' === $fallback ) {
			$issues[] = array(
				'path'    => 'fbtItems',
				'message' => __( "Add at least one product, or switch to the viewed product's cross-sells before activating this bundle.", 'funnel-builder' ),
			);
			return;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$hosts = self::placed_product_ids( $p );
		if ( $listed && empty( $hosts ) ) {
			return; // A missing placement is reported on its own.
		}
		$most = 0;
		foreach ( $hosts as $id ) {
			$host = wc_get_product( $id );
			if ( $host ) {
				$most = max( $most, count( \WFFN\Bundles\Includes\Skins\Frequently_Bought_Together::companion_ids( $dc, $host ) ) );
			}
		}

		if ( $most > 0 ) {
			$min = max( 1, (int) ( $dc['fbtMinCompanions'] ?? 1 ) );
			if ( $min > $most && 'none' !== (string) ( $dc['fbtDiscountScope'] ?? 'companions' ) && (float) ( $dc['fbtDiscountValue'] ?? 0 ) > 0 ) {
				$issues[] = array(
					'path'    => 'fbtMinCompanions',
					/* translators: 1: most products a page can show with the viewed one, 2: products needed for the discount. */
					'message' => sprintf( _n( 'At most %1$d product can be shown with the viewed one, but the discount starts from %2$d. Lower "Minimum items added for discount" or add products.', 'At most %1$d products can be shown with the viewed one, but the discount starts from %2$d. Lower "Minimum items added for discount" or add products.', $most, 'funnel-builder' ), $most, $min ),
				);
			}
			return;
		}

		if ( $listed ) {
			$issues[] = array(
				'path'    => 'fbtItems',
				'message' => __( 'None of the listed products can be bought right now (hidden or out of stock), so the page would show no set.', 'funnel-builder' ),
			);
			return;
		}

		$what = 'cross_sells_upsells' === $fallback ? __( 'cross-sells or upsells', 'funnel-builder' ) : __( 'cross-sells', 'funnel-builder' );
		$issues[] = array(
			'path'    => 'fbtFallback',
			/* translators: %s: "cross-sells" or "cross-sells or upsells". */
			'message' => sprintf( __( 'None of the products this bundle is placed on have %s set, so its page would show no set. Add them under Linked Products, or choose products manually.', 'funnel-builder' ), $what ),
		);
	}

	/**
	 * Product ids a bundle is placed on; for categories, the first products of those categories.
	 *
	 * @param array $p Bundle.
	 * @return int[]
	 */
	private static function placed_product_ids( array $p ) {
		$sel  = is_array( $p['productSelection'] ?? null ) ? $p['productSelection'] : array();
		$mode = (int) ( $sel['mode'] ?? Bundle_Store::MODE_NONE );
		if ( Bundle_Store::MODE_SPECIFIC_CATEGORIES !== $mode ) {
			$ids = array();
			foreach ( (array) ( $sel['products'] ?? array() ) as $pr ) {
				$id = absint( is_array( $pr ) ? ( $pr['id'] ?? 0 ) : $pr );
				if ( $id ) {
					$ids[] = $id;
				}
			}
			return array_values( array_unique( $ids ) );
		}
		$terms = array_filter( array_map( 'absint', (array) ( $sel['selectedCategories'] ?? array() ) ) );
		if ( empty( $terms ) ) {
			return array();
		}
		// The same arguments get_posts() would add: no filters, no sticky posts, newest first.
		return array_map( 'absint', ( new \WP_Query( array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => 200,
			'no_found_rows'       => true,
			'suppress_filters'    => true,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'tax_query'           => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array_values( $terms ) ) ),
		) ) )->posts );
	}

	/**
	 * The first violation as a sentence, for the single-message REST envelope.
	 *
	 * @param array<int,array{path:string,message:string}> $issues Result of validate().
	 * @return string Empty when there is nothing to report.
	 */
	public static function first_message( array $issues ) {
		return isset( $issues[0]['message'] ) ? (string) $issues[0]['message'] : '';
	}

	/**
	 * Bundle name: present, and within the column's own limit.
	 *
	 * @param array $p      Bundle payload.
	 * @param array $issues Issue list, by reference.
	 */
	private static function check_name( array $p, array &$issues ) {
		if ( ! array_key_exists( 'name', $p ) ) {
			return;
		}
		$name = trim( (string) $p['name'] );
		if ( '' === $name ) {
			$issues[] = array(
				'path'    => 'bundleName',
				'message' => __( 'Name is required.', 'funnel-builder' ),
			);
		} elseif ( mb_strlen( $name ) > self::NAME_MAX ) {
			$issues[] = array(
				'path'    => 'bundleName',
				'message' => sprintf(
					/* translators: %d: maximum number of characters. */
					__( 'Name is too long (max %d characters).', 'funnel-builder' ),
					self::NAME_MAX
				),
			);
		}
	}

	/**
	 * Is this discount value legal for its mode?
	 *
	 * @param string $mode  Discount type as stored.
	 * @param mixed  $value Discount amount.
	 * @return bool True when the value is out of range.
	 */
	public static function bad_discount( $mode, $value ) {
		if ( 'no_discount' === (string) $mode ) {
			return false;
		}
		if ( ! is_numeric( $value ) ) {
			return true;
		}
		$value = (float) $value;
		if ( $value < 0 ) {
			return true;
		}
		return in_array( (string) $mode, array( 'fixed', 'fixed_amount_off' ), true ) ? false : $value > 100;
	}

	/**
	 * Offer quantity ceiling. The floor is the offer list's own rule.
	 *
	 * @param array  $row    Offer row.
	 * @param string $path   Field path.
	 * @param array  $issues Issue list, by reference.
	 */
	public static function check_quantity( array $row, $path, array &$issues ) {
		if ( isset( $row['quantity'] ) && (int) $row['quantity'] > self::QUANTITY_MAX ) {
			$issues[] = array(
				'path'    => $path,
				'message' => sprintf(
					/* translators: %d: the largest quantity an offer may ask for. */
					__( 'Offer quantity is too high (max %d).', 'funnel-builder' ),
					self::QUANTITY_MAX
				),
			);
		}
	}

	/**
	 * Offer title length. Emptiness is the offer list's own rule.
	 *
	 * @param array  $row    Offer row.
	 * @param string $key    Title key on the row.
	 * @param string $path   Field path prefix.
	 * @param array  $issues Issue list, by reference.
	 */
	public static function check_title( array $row, $key, $path, array &$issues ) {
		$title = trim( (string) ( $row[ $key ] ?? '' ) );
		if ( mb_strlen( $title ) > self::TITLE_MAX ) {
			$issues[] = array(
				'path'    => $path,
				'message' => sprintf(
					/* translators: %d: maximum number of characters. */
					__( 'Offer title is too long (max %d characters).', 'funnel-builder' ),
					self::TITLE_MAX
				),
			);
		}
	}

	/**
	 * Fixed Bundle members and the bundle-wide discount.
	 *
	 * @param array $dc     Discount config.
	 * @param array $issues Issue list, by reference.
	 */
	private static function check_fixed_bundle( array $dc, array &$issues ) {
		$items = is_array( $dc['fixedBundleItems'] ?? null ) ? array_values( $dc['fixedBundleItems'] ) : array();
		$bundle_total = 0.0;
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$bundle_total += self::item_total( $item );
			}
		}

		// Drafts included, so a draft never holds a bundle that cannot go live. A create
		// that sends no list yet is left alone; the editor seeds its starter rows.
		if ( isset( $dc['fixedBundleItems'] ) && count( $items ) < 1 ) {
			$issues[] = array(
				'path'    => 'fixedBundleItems',
				'message' => __( 'Add at least one product.', 'funnel-builder' ),
			);
		}

		if ( self::bad_discount( $dc['fixedBundleDiscountType'] ?? 'percentage', $dc['fixedBundleDiscountValue'] ?? 0 ) ) {
			$issues[] = array(
				'path'    => 'fixedBundleDiscountValue',
				'message' => __( 'Enter a valid discount. A percentage must be between 0 and 100.', 'funnel-builder' ),
			);
		} elseif ( self::over_priced( $dc['fixedBundleDiscountType'] ?? 'percentage', $dc['fixedBundleDiscountValue'] ?? 0, $bundle_total ) ) {
			$issues[] = array(
				'path'    => 'fixedBundleDiscountValue',
				'message' => __( 'The fixed discount cannot be more than the bundle price.', 'funnel-builder' ),
			);
		}

		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$path = 'fixedBundleItems.' . $i;
			if ( isset( $item['quantity'] ) && (int) $item['quantity'] < 1 ) {
				$issues[] = array(
					'path'    => $path . '.quantity',
					'message' => __( 'Product quantity must be at least 1.', 'funnel-builder' ),
				);
			}
			// The cart refuses more than one of a sold-individually product, so such a bundle could never be added.
			$solo = function_exists( 'wc_get_product' ) ? wc_get_product( (int) ( $item['product']['id'] ?? 0 ) ) : null;
			if ( (int) ( $item['quantity'] ?? 1 ) > 1 && $solo instanceof \WC_Product && $solo->is_sold_individually() ) {
				$issues[] = array(
					'path'    => $path . '.quantity',
					/* translators: %s: product name. */
					'message' => sprintf( __( '%s is sold individually, so its quantity must be 1.', 'funnel-builder' ), $solo->get_name() ),
				);
			}
			if ( self::bad_discount( $item['discountType'] ?? 'percentage', $item['discountValue'] ?? 0 ) ) {
				$issues[] = array(
					'path'    => $path . '.discountValue',
					'message' => __( 'Enter a valid discount. A percentage must be between 0 and 100.', 'funnel-builder' ),
				);
			} elseif ( self::over_priced( $item['discountType'] ?? 'percentage', $item['discountValue'] ?? 0, self::item_total( $item ) ) ) {
				$issues[] = array(
					'path'    => $path . '.discountValue',
					'message' => __( 'The fixed discount cannot be more than the product price.', 'funnel-builder' ),
				);
			}
		}
	}

	/**
	 * Frequently Bought Together: the discount, the companion cap and the unlock count.
	 *
	 * @param array $dc     Discount config.
	 * @param array $issues Issue list, by reference.
	 */
	private static function check_frequently_bought_together( array $dc, array &$issues ) {
		if ( 'none' !== ( $dc['fbtDiscountScope'] ?? 'companions' ) && self::bad_discount( $dc['fbtDiscountType'] ?? 'percentage', $dc['fbtDiscountValue'] ?? 0 ) ) {
			$issues[] = array(
				'path'    => 'fbtDiscountValue',
				'message' => __( 'Enter a valid discount. A percentage must be between 0 and 100.', 'funnel-builder' ),
			);
		}

		$max = (int) ( $dc['fbtMaxItems'] ?? self::FBT_MAX_COMPANIONS );
		if ( $max < 1 || $max > self::FBT_MAX_COMPANIONS ) {
			$issues[] = array(
				'path'    => 'fbtMaxItems',
				/* translators: %d: the most companions the widget can show. */
				'message' => sprintf( __( 'Show between 1 and %d items.', 'funnel-builder' ), self::FBT_MAX_COMPANIONS ),
			);
		}

		$min = (int) ( $dc['fbtMinCompanions'] ?? 1 );
		// The widget shows up to the maximum whatever a stored fbtMaxItems says.
		if ( $min < 1 || $min > self::FBT_MAX_COMPANIONS ) {
			$issues[] = array(
				'path'    => 'fbtMinCompanions',
				'message' => __( 'The number of items needed for the discount cannot be more than the items shown.', 'funnel-builder' ),
			);
		}
	}

	/**
	 * A Fixed Bundle item's own line total, at the product's current price.
	 *
	 * @param array $item Fixed Bundle item row.
	 * @return float
	 */
	private static function item_total( array $item ) {
		// The editor posts products without a price, so the snapshot is only a fallback.
		$row   = Repository::find( absint( $item['product']['id'] ?? 0 ) );
		$price = (float) ( $row['priceRaw'] ?? $item['product']['priceRaw'] ?? 0 );
		$qty   = max( 1, (int) ( $item['quantity'] ?? 1 ) );
		return $price * $qty;
	}

	/**
	 * Whether a fixed-amount discount would zero out or exceed what it applies to.
	 * A `0` total (products with no price yet) is not flagged, so an unpriced
	 * product never blocks an otherwise-valid save.
	 *
	 * @param string $mode  Discount type as stored.
	 * @param mixed  $value Discount amount.
	 * @param float  $total Price the discount is taken off.
	 * @return bool
	 */
	private static function over_priced( $mode, $value, $total ) {
		if ( ! in_array( (string) $mode, array( 'fixed', 'fixed_amount_off' ), true ) || $total <= 0 ) {
			return false;
		}
		return is_numeric( $value ) && (float) $value >= $total;
	}
}
