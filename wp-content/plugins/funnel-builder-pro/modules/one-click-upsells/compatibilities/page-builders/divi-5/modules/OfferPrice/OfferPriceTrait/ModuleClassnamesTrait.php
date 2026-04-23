<?php
/**
 * OfferPrice::module_classnames().
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice\OfferPriceTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Text\TextClassnames;

trait ModuleClassnamesTrait {

	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? array() );
		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}

		$stacked = false;
		$value   = null;
		if ( isset( $attrs['offerSliderEnabled']['desktop']['value'] ) ) {
			$value = $attrs['offerSliderEnabled']['desktop']['value'];
			if ( is_array( $value ) ) {
				if ( isset( $value['value'] ) && ! is_array( $value['value'] ) ) {
					$value = $value['value'];
				} elseif ( isset( $value['text'] ) && ! is_array( $value['text'] ) ) {
					$value = $value['text'];
				}
			}
		} elseif ( isset( $attrs['offerSliderEnabled']['desktop'] ) && ! is_array( $attrs['offerSliderEnabled']['desktop'] ) ) {
			$value = $attrs['offerSliderEnabled']['desktop'];
		} elseif ( isset( $attrs['offerSliderEnabled'] ) && ! is_array( $attrs['offerSliderEnabled'] ) ) {
			$value = $attrs['offerSliderEnabled'];
		}
		$stacked = true === $value || 'on' === $value || '1' === $value || 'yes' === $value || 'Yes' === $value;

		$classnames_instance->add( $stacked ? 'wfocu-price_block-yes' : 'wfocu-price_block-no', true );
	}
}
