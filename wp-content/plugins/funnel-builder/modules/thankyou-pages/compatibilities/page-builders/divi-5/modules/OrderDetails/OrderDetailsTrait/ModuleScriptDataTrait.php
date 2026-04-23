<?php
/**
 * OrderDetails::module_script_data()
 *
 * @package WFTY\Modules\OrderDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\OrderDetails\OrderDetailsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Element\ElementScriptData;

trait ModuleScriptDataTrait {

	public static function module_script_data( array $args ): void {
		$id                      = $args['id'] ?? '';
		$selector                = $args['selector'] ?? '';
		$attrs                   = $args['attrs'] ?? array();
		$store_instance          = $args['storeInstance'] ?? null;
		$module_decoration_attrs = $attrs['module']['decoration'] ?? array();
		ElementScriptData::set(
			array(
				'id'            => $id,
				'selector'      => $selector,
				'attrs'         => $module_decoration_attrs,
				'storeInstance' => $store_instance,
			)
		);
	}
}
