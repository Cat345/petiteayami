<?php
/**
 * QtySelector::module_styles().
 *
 * @package WFOCU\Modules\QtySelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\QtySelector\QtySelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFOCU\Modules\QtySelector\QtySelectorTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Qty Selector module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/qty-selector/styles.tsx.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name              Module name.
	 *      @type string $attrs             Module attributes.
	 *      @type string $parentAttrs       Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings          Custom settings.
	 *      @type string $state             Attributes state.
	 *      @type string $mode              Style mode.
	 *      @type ModuleElements $elements         ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? array();
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? array();
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? array();
		$order_class                 = $args['orderClass'] ?? '';

		// CRITICAL: Get default attributes from module.json and merge with current attributes
		// This ensures defaults are always applied, even when attributes are empty
		$default_attrs = array();
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/qty-selector' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		// Alignment via margin (works when module has reduced width via sizing)
		$text_align_attr  = $attrs['textAlign'] ?? array( 'desktop' => array( 'value' => 'left' ) );
		$text_align_value = $text_align_attr['desktop']['value'] ?? 'left';
		$alignment_styles = array();
		if ( 'center' === $text_align_value ) {
			$alignment_styles[] = array(
				'componentName' => 'divi/common',
				'props'         => array(
					'selector' => $order_class,
					'attr'     => array( 'desktop' => array( 'value' => '0 auto' ) ),
					'property' => 'margin',
				),
			);
		} elseif ( 'right' === $text_align_value ) {
			$alignment_styles[] = array(
				'componentName' => 'divi/common',
				'props'         => array(
					'selector' => $order_class,
					'attr'     => array( 'desktop' => array( 'value' => 'auto' ) ),
					'property' => 'margin-left',
				),
			);
			$alignment_styles[] = array(
				'componentName' => 'divi/common',
				'props'         => array(
					'selector' => $order_class,
					'attr'     => array( 'desktop' => array( 'value' => '0' ) ),
					'property' => 'margin-right',
				),
			);
		}

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array(
					// Module decoration styles (border, box shadow, spacing)
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
											'selector' => $order_class,
											'attr'     => $text_align_attr,
											'property' => 'text-align',
										),
									),
									array(
										'componentName' => 'divi/common',
										'props'         => array(
											'selector' => $order_class . ' .wfocu-prod-qty-wrapper',
											'attr'     => array( 'desktop' => array( 'value' => 'inline-block' ) ),
											'property' => 'display',
										),
									),
								),
							),
						)
					),
					// Label element styles (typography, colors, spacing)
					$elements->style(
						array(
							'attrName' => 'label',
						)
					),
					// Dropdown width (on wrapper)
					$elements->style(
						array(
							'attrName' => 'dropdownWidth',
						)
					),
					// Dropdown height (on select element)
					$elements->style(
						array(
							'attrName' => 'dropdownHeight',
						)
					),
					// Dropdown element styles (typography, colors, border, spacing - padding)
					$elements->style(
						array(
							'attrName' => 'dropdown',
						)
					),
					// WF Quantity Border
					// Selector: {{selector}} .wfocu-prod-qty-wrapper .wfocu-select-qty-input
					$elements->style(
						array(
							'attrName' => 'qty_dropdown_border',
						)
					),
					// Custom CSS (must be last to allow overrides)
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
