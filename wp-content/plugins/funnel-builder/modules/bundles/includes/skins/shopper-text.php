<?php
/** Shopper text — the words the bundle widgets print, translated once for the storefront and the editor preview. */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Helpers\WooCommerce;

/**
 * The storefront skins and the editor's preview read their fixed words from here,
 * so the preview shows exactly what a shopper sees, in the store's language.
 */
class Shopper_Text {

	/**
	 * Every shopper text by key, per locale.
	 *
	 * @var array<string,array<string,string>>
	 */
	private static $all = array();

	/**
	 * Every shopper text by key.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		$locale = determine_locale();
		if ( isset( self::$all[ $locale ] ) ) {
			return self::$all[ $locale ];
		}
		$text = array(
			'fixedTitle'    => __( 'Multi-product offer', 'funnel-builder' ),
			'fixedEmpty'    => __( 'Pick products in the Offer section to preview the bundle.', 'funnel-builder' ),
			'fixedBadge'    => WooCommerce::translated_or_woo( __( 'Most Popular', 'funnel-builder' ), 'Most Popular', 'Most popular' ),
			'fixedTotal'    => __( 'Bundle price', 'funnel-builder' ),
			'selectOptions' => WooCommerce::translated_or_woo( __( 'Select options', 'funnel-builder' ), 'Select options', 'Select options' ),
			/* translators: %s: attribute name, e.g. Size. */
			'selectAxis'    => __( 'Select %s', 'funnel-builder' ),
			'chooseOption'  => WooCommerce::translated_or_woo( __( 'Choose an option', 'funnel-builder' ), 'Choose an option', 'Choose an option' ),
		);

		/**
		 * The add-on's shopper texts, by key, beside these.
		 *
		 * @param array<string,string> $text Shopper texts so far.
		 */
		$all = apply_filters( 'wffn_bundles_shopper_text', $text );

		self::$all[ $locale ] = is_array( $all ) ? array_merge( $text, array_filter( $all, 'is_string' ) ) : $text;

		return self::$all[ $locale ];
	}

	/**
	 * One shopper text.
	 *
	 * @param string $key Text key.
	 * @return string
	 */
	public static function get( $key ) {
		$all = self::all();

		return (string) ( $all[ $key ] ?? '' );
	}
}
