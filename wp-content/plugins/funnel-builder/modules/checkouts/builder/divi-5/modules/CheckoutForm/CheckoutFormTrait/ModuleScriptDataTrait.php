<?php
/**
 * CheckoutForm::module_script_data()
 *
 * @package WFACP\Modules\CheckoutForm
 * @since 1.0.0
 */

namespace WFACP\Modules\CheckoutForm\CheckoutFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Element\ElementScriptData;

trait ModuleScriptDataTrait {

	/**
	 * Set script data of used module options.
	 *
	 * This function is equivalent of JS component ModuleScriptData located in
	 * src/components/checkout-form/module-script-data.tsx.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string $id       Module id.
	 *   @type string $selector Module selector.
	 *   @type array  $attrs    Module attributes.
	 *   @type string $storeInstance Store instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? array();
		$store_instance = $args['storeInstance'] ?? null;

		// Module decoration attributes.
		$module_decoration_attrs = $attrs['module']['decoration'] ?? array();

		// Element Script Data Options.
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
