<?php
/** Fixed Bundle — view-model builder. */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Helpers\WooCommerce;

/**
 * View-model builder and starter content for the Fixed Bundle type.
 */
class Fixed_Bundle {

	/**
	 * Build the root view-model.
	 *
	 * @param array $args View-model input args.
	 * @return array
	 */
	public static function view_model( array $args ) {
		$heading      = (string) ( $args['heading'] ?? '' );
		$subheading   = (string) ( $args['subheading'] ?? '' );
		$bundle_title = (string) ( $args['title'] ?? '' );
		$bundle_sub   = (string) ( $args['subtitle'] ?? '' );
		$single_title = (string) ( $args['singleTitle'] ?? '' );
		$single_sub   = (string) ( $args['singleSubtitle'] ?? '' );
		$show_single  = ! empty( $args['showSingle'] );
		$design       = isset( $args['design'] ) && is_array( $args['design'] ) ? $args['design'] : array();
		$scope        = isset( $args['scope'] ) ? (string) $args['scope'] : 'whole_bundle';
		$mode         = isset( $args['mode'] ) ? (string) $args['mode'] : 'percentage';
		$value        = isset( $args['value'] ) ? (float) $args['value'] : 0.0;
		$items_in     = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
		$selected     = isset( $args['selected'] ) ? (string) $args['selected'] : 'bundle';
		$show_images  = ! empty( $args['showImages'] );
		$bundle_image = (string) ( $args['bundleImage'] ?? '' );
		$single_image = (string) ( $args['singleImage'] ?? '' );

		// The page product the cart charges for the `single` offer, not items[0].
		$anchor = isset( $args['anchor'] ) && is_array( $args['anchor'] ) ? $args['anchor'] : array();

		$separate  = ( 'separate_products' === $scope );
		// A pack of two or more is charged on a container line (Cart::fold_engine_discount).
		$solo      = count( array_filter( $items_in, 'is_array' ) ) < 2;
		$total     = 0.0;
		$total_max = 0.0;
		$final       = 0.0;
		$final_max   = 0.0;
		$items       = array();

		// "min – max" when the ends differ, a single formatted price otherwise.
		$fmt = function ( $lo, $hi ) {
			return $hi > $lo ? Pricing::format( $lo ) . ' – ' . Pricing::format( $hi ) : Pricing::format( $lo );
		};

		foreach ( array_values( $items_in ) as $item ) {
			$item = is_array( $item ) ? $item : array();
			$name = (string) ( $item['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$qty       = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$price     = (float) ( $item['price'] ?? 0 );
			$price_max = max( $price, (float) ( $item['priceMax'] ?? $price ) );
			$line      = $price * $qty;
			$line_max  = $price_max * $qty;
			$total    += $line;
			$total_max += $line_max;

			$dtype          = (string) ( $item['discountType'] ?? 'percentage' );
			$dval           = (float) ( $item['discountValue'] ?? 0 );
			$line_final     = $separate ? self::apply( $line, $dtype, $dval ) : $line;
			$line_final_max = $separate ? self::apply( $line_max, $dtype, $dval ) : $line_max;
			if ( $separate ) {
				// Folded only where the member will be an ordinary cart line.
				if ( $solo ) {
					$member         = function_exists( 'wc_get_product' )
						? wc_get_product( (int) ( $item['productId'] ?? 0 ) )
						: null;
					$line_final     = Pricing::fold_engine( $line_final, $line, $qty, $member ? $member : null );
					$line_final_max = Pricing::fold_engine( $line_final_max, $line_max, $qty, $member ? $member : null );
				}
				$final     += $line_final;
				$final_max += $line_final_max;
			}

			$image = Variants::start_image( $item );
			$image = '' !== $image ? $image : (string) ( $item['image'] ?? '' );
			$cat   = (string) ( $item['category'] ?? '' );

			// A preview seed carries no 'available' key, so it counts as in stock.
			$m_available = ! array_key_exists( 'available', $item ) || ! empty( $item['available'] );
			$m_oos_label = (string) ( $item['oosLabel'] ?? '' );

			$items[] = array_merge(
				array(
					'name'          => $name,
					'qtyLabel'      => $qty . '×',
					'qtyShow'       => $qty > 1,
					'image'         => $image,
					'imageShow'     => '' !== $image,
					'subtitle'      => $cat,
					'subtitleShow'  => '' !== $cat,
					'priceOriginal' => $fmt( $line, $line_max ),
					'priceFinal'    => $fmt( $separate ? $line_final : $line, $separate ? $line_final_max : $line_max ),
					'showStrike'    => $separate && $line_final < $line,
					'oosItemClass'  => $m_available ? '' : 'wffn-bundles-fb-member--oos',
					'oosLabel'      => $m_oos_label,
					'oosLabelShow'  => ! $m_available && '' !== $m_oos_label,
				),
				Variants::picker( $item )
			);
		}

		if ( ! $separate ) {
			$final     = self::apply( $total, $mode, $value );
			$final_max = self::apply( $total_max, $mode, $value );
		}

		// One container line, so the engine's rule lands once whatever the scope.
		if ( ! $solo ) {
			$container = Cart::container_product();
			$final     = Pricing::fold_engine( $final, $total, 1, $container );
			$final_max = Pricing::fold_engine( $final_max, $total_max, 1, $container );
		}

		$has_discount = $final < $total;
		$on_bundle    = ( 'bundle' === $selected );
		// No anchor means nothing to offer on its own, so render the bundle alone.
		$has_anchor = '' !== (string) ( $anchor['name'] ?? '' );
		$has_choice = $show_single && $has_anchor;

		$anchor_name      = (string) ( $anchor['name'] ?? '' );
		$anchor_image     = (string) ( $anchor['image'] ?? '' );
		$anchor_price     = (float) ( $anchor['price'] ?? 0 );
		$anchor_price_max = max( $anchor_price, (float) ( $anchor['priceMax'] ?? $anchor_price ) );
		// Every axis opens pre-selected (Repository::first_available_combo), so quote
		// the resolved variation rather than the parent's whole range.
		$anchor_default = self::default_variation_price( $anchor );
		if ( null !== $anchor_default ) {
			$anchor_price     = $anchor_default;
			$anchor_price_max = $anchor_default;
		}
		$anchor_picker    = Variants::picker( $anchor );
		$eff_single_image = '' !== $single_image ? $single_image : $anchor_image;
		$eff_title = '' !== $bundle_title ? $bundle_title : Shopper_Text::get( 'fixedTitle' );

		// Cart::validate_stock rejects the pack when a member can't supply its quantity.
		$out_of_stock = ! empty( $args['outOfStock'] );

		// Capped at two names plus "+N more"; falls back to the generic sentence.
		$oos_names = isset( $args['oosNames'] ) && is_array( $args['oosNames'] )
			? array_values( array_filter( array_map( 'strval', $args['oosNames'] ), 'strlen' ) )
			: array();
		$oos_text = __( 'This bundle is currently out of stock.', 'funnel-builder' );
		if ( $out_of_stock && ! empty( $oos_names ) ) {
			$shown = array_slice( $oos_names, 0, 2 );
			$more  = count( $oos_names ) - count( $shown );
			$list  = implode( ', ', $shown );
			if ( $more > 0 ) {
				$list .= ' ' . sprintf(
					/* translators: %d: number of additional out-of-stock items */
					_n( '+%d more', '+%d more', $more, 'funnel-builder' ),
					$more
				);
			}
			/* translators: %s: comma-separated names of the out-of-stock bundle items */
			$oos_text = sprintf( __( 'This bundle can’t be added right now. Unavailable: %s.', 'funnel-builder' ), $list );
		}

		$vm = array(
			'rootStyle'  => Css_Vars::for_design( $design ),

			// Widget heading, from the same Layout fields the other types use.
			'heading'        => $heading,
			'headingShow'    => '' !== trim( $heading ),
			'subheading'     => $subheading,
			'subheadingShow' => '' !== trim( $subheading ),

			'flatMode'   => ! $show_single,
			'showSingle' => $show_single,
			'hasChoice'  => $has_choice,
			'hasItems'   => ! empty( $items ),
			'itemsEmpty' => empty( $items ),
			'emptyText'  => Shopper_Text::get( 'fixedEmpty' ),

			'singleSelectedClass' => $on_bundle ? '' : 'wffn-bundles-fb-single--selected',
			'singleSelected'      => ! $on_bundle,
			'singleTitle'         => '' !== $single_title ? $single_title : $anchor_name,
			'singleSubtitle'      => $single_sub,
			'singleSubtitleShow'  => '' !== $single_sub,
			'singleImage'         => $eff_single_image,
			'singleImageShow'     => $show_images && '' !== $eff_single_image,
			'singlePrice'         => $fmt( $anchor_price, $anchor_price_max ),
			'variantShow'         => $anchor_picker['variantShow'],
			'variantAxes'         => $anchor_picker['variantAxes'],
			'variantMatrix'       => $anchor_picker['variantMatrix'] ?? '',
			'variantInfo'         => $anchor_picker['variantInfo'] ?? '',
			'variantSummary'      => $anchor_picker['variantSummary'] ?? '',
			'variantHead'         => $anchor_picker['variantHead'] ?? '',

			'bundleSelectedClass' => $on_bundle ? 'wffn-bundles-fb-card--selected' : '',
			'bundleSelected'      => $on_bundle,
			'badgeShow'           => $has_choice,
			// Flat and stack branches are mutually exclusive, so each needs its own flag.
			'flatBadgeShow'       => ! $show_single && ! empty( $items ),
			'badge'               => Shopper_Text::get( 'fixedBadge' ),
			'leadImageShow'       => $show_images && '' !== $bundle_image,
			'leadImage'           => $bundle_image,
			'bundleTitle'         => $eff_title,
			'bundleSubtitle'      => $bundle_sub,
			'bundleSubtitleShow'  => '' !== $bundle_sub,
			'totalOriginal'       => $fmt( $total, $total_max ),
			'totalFinal'          => $fmt( $final, $final_max ),
			'totalShowStrike'     => $has_discount,

			// Aliases for layouts Registry no longer registers; nothing reads them today.
			'title'       => $eff_title,
			'titleShow'   => true,
			'subtitle'    => $bundle_sub,
			'subtitleShow' => '' !== $bundle_sub,
			'totalLabel'  => Shopper_Text::get( 'fixedTotal' ),

			'items'     => $items,

			// Out-of-stock: some member can't supply its quantity.
			'oos'      => $out_of_stock,
			'oosShow'  => $out_of_stock,
			'oosClass' => $out_of_stock ? 'wffn-bundles-fb--oos' : '',
			'oosText'  => $oos_text,
		);
		// The strip quotes the pack total; hand it the figures already computed.
		$args['subTotals'] = array(
			'final'    => $final,
			'finalMax' => $final_max,
		);
		/**
		 * Filters a bundle type's assembled view-model scope before stamping.
		 *
		 * Applied identically by every type's view_model(), so an add-on can
		 * merge extra template keys off the input args.
		 *
		 * @since 1.0.0
		 *
		 * @param array $vm   The view-model scope.
		 * @param array $args The view-model's input args.
		 * @param int   $type Bundle_Store::TYPE_* id.
		 */
		return apply_filters( 'wffn_bundles_view_model', $vm, $args, \WFFN\Bundles\Includes\Storage\Bundle_Store::TYPE_FIXED_BUNDLES );
	}

	/**
	 * Apply a percentage or fixed discount to an amount, never below 0.
	 *
	 * @param float  $amount Amount to discount.
	 * @param string $type   'percentage' or 'fixed'.
	 * @param float  $value  Discount value.
	 * @return float
	 */
	private static function apply( $amount, $type, $value ) {
		$amount = (float) $amount;
		$value  = (float) $value;
		if ( $value <= 0 ) {
			return $amount;
		}
		if ( 'percentage' === $type ) {
			return max( 0, $amount - ( $amount * $value / 100 ) );
		}
		return max( 0, $amount - $value );
	}

	/**
	 * The price of the variation an anchor's pre-selected axes resolve to.
	 *
	 * Null unless every axis has a default and one variation matches them all: a
	 * partly-resolved product is still a range. An empty combo value is
	 * WooCommerce's "any" wildcard.
	 *
	 * @param array $anchor Hydrated product row (attributes + variations).
	 * @return float|null Resolved price, or null when the product stays a range.
	 */
	private static function default_variation_price( $anchor ) {
		$axes = is_array( $anchor['attributes'] ?? null ) ? $anchor['attributes'] : array();
		$rows = is_array( $anchor['variations'] ?? null ) ? $anchor['variations'] : array();
		if ( ! $axes || ! $rows ) {
			return null;
		}
		$wanted = array();
		foreach ( $axes as $axis ) {
			$default = (string) ( $axis['default'] ?? '' );
			if ( '' === $default ) {
				return null;
			}
			$wanted[ (string) ( $axis['name'] ?? '' ) ] = $default;
		}
		foreach ( $rows as $row ) {
			if ( empty( $row['purchasable'] ) || ! is_array( $row['attributes'] ?? null ) ) {
				continue;
			}
			$fits = true;
			foreach ( $wanted as $label => $value ) {
				$actual = (string) ( $row['attributes'][ $label ] ?? '' );
				if ( '' !== $actual && sanitize_title( $actual ) !== sanitize_title( $value ) ) {
					$fits = false;
					break;
				}
			}
			if ( $fits ) {
				// `price` is display-formatted by Repository::price; `priceRaw` is base.
				return array_key_exists( 'priceRaw', $row ) ? (float) $row['priceRaw'] : null;
			}
		}
		return null;
	}

	/**
	 * A saved offer text, translated while it is still the English default a new bundle starts with.
	 *
	 * Text the merchant changed is theirs and is returned as saved.
	 *
	 * @param mixed $value Saved text.
	 * @return string
	 */
	public static function default_text( $value ) {
		$value = (string) $value;
		switch ( trim( $value ) ) {
			case 'Multi-product offer':
				return __( 'Multi-product offer', 'funnel-builder' );
			case 'Complete the pack':
				return __( 'Complete the pack', 'funnel-builder' );
			case 'Buy 1':
				return __( 'Buy 1', 'funnel-builder' );
			case 'Regular price':
				return WooCommerce::translated_or_woo( __( 'Regular price', 'funnel-builder' ), 'Regular price', 'Regular price' );
		}

		return $value;
	}

	/**
	 * Starter content for the type gallery and preview seed.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'design'         => array(),
			'title'          => __( 'Multi-product offer', 'funnel-builder' ),
			'subtitle'       => __( 'Complete the pack', 'funnel-builder' ),
			'singleTitle'    => __( 'Buy 1', 'funnel-builder' ),
			'singleSubtitle' => __( 'Regular price', 'funnel-builder' ),
			'showSingle'     => true,
			// Stands in for the page product; a Fixed Bundle does show on member pages.
			'anchor'         => array( 'name' => __( 'Product A', 'funnel-builder' ), 'price' => 38.0, 'image' => '' ),
			'selected'       => 'bundle',
			'scope'          => 'whole_bundle',
			'mode'           => 'percentage',
			'value'          => 15,
			'items'          => array(
				array( 'name' => __( 'Product A', 'funnel-builder' ), 'price' => 38.0, 'image' => '', 'category' => '', 'quantity' => 1, 'discountType' => 'percentage', 'discountValue' => 0 ),
				array( 'name' => __( 'Product B', 'funnel-builder' ), 'price' => 52.0, 'image' => '', 'category' => '', 'quantity' => 1, 'discountType' => 'percentage', 'discountValue' => 0 ),
				array( 'name' => __( 'Product C', 'funnel-builder' ), 'price' => 24.0, 'image' => '', 'category' => '', 'quantity' => 1, 'discountType' => 'percentage', 'discountValue' => 0 ),
			),
		);
	}
}
