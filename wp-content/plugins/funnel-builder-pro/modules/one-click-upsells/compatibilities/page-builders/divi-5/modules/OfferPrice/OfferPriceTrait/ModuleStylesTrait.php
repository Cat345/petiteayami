<?php
/**
 * OfferPrice::module_styles().
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice\OfferPriceTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

trait ModuleStylesTrait {
	use CustomCssTrait;

	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? array();
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? array();
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? array();
		$order_class                 = $args['orderClass'] ?? '';

		$default_attrs = array();
		if ( class_exists( 'ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/offer-price' );
			} catch ( \Exception $e ) {
				$default_attrs = array();
			}
		}
		$attrs = array_replace_recursive( $default_attrs, $attrs );

		$text_align_attr = $attrs['textAlign'] ?? array( 'desktop' => array( 'value' => 'left' ) );

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array(
					$elements->style(
						array(
							'attrName'   => 'module',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? array(),
								'disabledOn'               => array(
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								),
								'advancedStyles'           => array(
									array(
										'componentName' => 'divi/common',
										'props'         => array(
											'selector' => $order_class . ' .wfocu-price-wrapper',
											'attr'     => $text_align_attr,
											'property' => 'text-align',
										),
									),
								),
							),
						)
					),
					$elements->style( array( 'attrName' => 'salePriceMargin' ) ),
					$elements->style( array( 'attrName' => 'regLabelStyle' ) ),
					$elements->style( array( 'attrName' => 'offerLabelStyle' ) ),
					$elements->style( array( 'attrName' => 'regularPriceStyle' ) ),
					$elements->style( array( 'attrName' => 'offerPriceStyle' ) ),
					$elements->style( array( 'attrName' => 'signupLabelStyle' ) ),
					$elements->style( array( 'attrName' => 'signupPriceStyle' ) ),
					$elements->style( array( 'attrName' => 'recurringLabelStyle' ) ),
					$elements->style( array( 'attrName' => 'recurringPriceStyle' ) ),
					CssStyle::style(
						array(
							'selector'  => $order_class,
							'attr'      => $attrs['css'] ?? array(),
							'cssFields' => self::custom_css(),
						)
					),
				),
			)
		);
	}
}
