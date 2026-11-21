<?php
/** WooCommerce helper utilities. */
namespace WFFN\Bundles\Includes\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small WooCommerce lookups shared across the plugin.
 */
class WooCommerce {

	/**
	 * A shopper string in the store's language, borrowing WooCommerce's translation.
	 *
	 * While this plugin has no translation of its own for the string, the store's
	 * WooCommerce translation of the same phrase is used, so a translated store shows
	 * it in its language from the start. An English store keeps this plugin's wording.
	 *
	 * @param string $translated  This plugin's gettext result.
	 * @param string $original    This plugin's English source string.
	 * @param string $woo_msgid   WooCommerce's English string for the same phrase.
	 * @return string
	 */
	public static function translated_or_woo( $translated, $original, $woo_msgid ) {
		if ( $translated !== $original ) {
			return $translated;
		}
		$woo = (string) get_translations_for_domain( 'woocommerce' )->translate( $woo_msgid );
		return '' !== $woo && $woo !== $woo_msgid ? $woo : $translated;
	}

	/**
	 * Product image URL for an attachment id, falling back to the plugin's
	 * placeholder when the attachment is missing or has no valid URL.
	 *
	 * @param int    $image_id WP attachment id (0/false = no image).
	 * @param string $size     Image size slug. Default 'thumbnail'.
	 * @return string Absolute image URL.
	 */
	public static function get_product_image_url( $image_id, $size = 'thumbnail' ) {
		$url = $image_id ? wp_get_attachment_image_url( $image_id, $size ) : false;

		return $url ?: WFFN_BUNDLES_URL . 'admin/assets/img/product-image-placeholder.png';
	}

	/**
	 * A registered image size name, or 'thumbnail' for anything else.
	 *
	 * @param mixed $size Requested size name.
	 * @return string
	 */
	public static function image_size( $size ) {
		$size = is_string( $size ) ? $size : '';
		return 'full' === $size || in_array( $size, get_intermediate_image_sizes(), true ) ? $size : 'thumbnail';
	}

	/**
	 * A hydrated product row with its own and its variations' images at another size.
	 *
	 * A row without an attachment id, or an attachment with no file at that size,
	 * keeps the URL it was shaped with.
	 *
	 * @param array|null $row  Hydrated product row.
	 * @param string     $size Image size name.
	 * @return array|null
	 */
	public static function with_image_size( $row, $size ) {
		$size = self::image_size( $size );
		if ( ! is_array( $row ) || 'thumbnail' === $size ) {
			return $row;
		}
		$variations = is_array( $row['variations'] ?? null ) ? $row['variations'] : array();
		$ids        = array_filter( array_map( 'absint', array_merge( array( $row['imageId'] ?? 0 ), array_column( $variations, 'imageId' ) ) ) );
		if ( $ids ) {
			_prime_post_caches( array_unique( $ids ), false, true );
		}

		$row['image'] = self::sized_url( $row['image'] ?? '', $row['imageId'] ?? 0, $size );
		foreach ( $variations as $i => $variation ) {
			$row['variations'][ $i ]['image'] = self::sized_url( $variation['image'] ?? '', $variation['imageId'] ?? 0, $size );
		}
		return $row;
	}

	/**
	 * An attachment's URL at a size, else the URL given.
	 *
	 * @param string $url      URL to fall back to.
	 * @param int    $image_id Attachment id.
	 * @param string $size     Image size name.
	 * @return string
	 */
	private static function sized_url( $url, $image_id, $size ) {
		$image_id = absint( $image_id );
		$sized    = $image_id ? wp_get_attachment_image_url( $image_id, $size ) : false;
		return $sized ? $sized : (string) $url;
	}
}
