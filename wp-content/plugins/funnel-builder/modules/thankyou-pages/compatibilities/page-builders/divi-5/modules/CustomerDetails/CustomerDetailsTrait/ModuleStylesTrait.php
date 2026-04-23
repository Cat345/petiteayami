<?php
/**
 * CustomerDetails::module_styles()
 *
 * @package WFTY\Modules\CustomerDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\CustomerDetails\CustomerDetailsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

trait ModuleStylesTrait {

	public static function module_styles( array $args ): void {
		$attrs         = $args['attrs'] ?? array();
		$elements      = $args['elements'];
		$default_attrs = array();
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfty/customer-details' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}
		$attrs = array_replace_recursive( $default_attrs, $attrs );

		$styles = array(
			$elements->style( array( 'attrName' => 'module' ) ),
			$elements->style( array( 'attrName' => 'wfty_customer_details_heading_typography' ) ),
			$elements->style( array( 'attrName' => 'wfty_customer_details_det_heading_typography' ) ),
			$elements->style( array( 'attrName' => 'wfty_customer_details_det_text_typography' ) ),
		);

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'] ?? 0,
				'storeInstance' => $args['storeInstance'] ?? null,
				'styles'        => $styles,
			)
		);
	}
}
