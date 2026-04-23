<?php
/**
 * OfferPrice::module_script_data().
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice\OfferPriceTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Element\ElementScriptData;

trait ModuleScriptDataTrait {

	public static function module_script_data( array $args ): void {
		$id             = $args['id'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? array();
		$store_instance = $args['storeInstance'] ?? null;

		ElementScriptData::set(
			array(
				'id'            => $id,
				'selector'      => $selector,
				'attrs'         => $attrs['module']['decoration'] ?? array(),
				'storeInstance' => $store_instance,
			)
		);
	}
}
