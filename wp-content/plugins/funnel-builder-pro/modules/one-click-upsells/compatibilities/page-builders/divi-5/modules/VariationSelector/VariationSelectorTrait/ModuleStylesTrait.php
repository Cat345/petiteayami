<?php
/**
 * VariationSelector::module_styles().
 *
 * @package WFOCU\Modules\VariationSelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\VariationSelector\VariationSelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFOCU\Modules\VariationSelector\VariationSelectorTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Variation Selector module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/variation-selector/styles.tsx.
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
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/variation-selector' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		// Check if stacked is enabled (attr_value_block = 'on') - matching Divi 4 lines 141-149
		$stacked_enabled = false;
		$value           = '';
		if ( isset( $attrs['attr_value_block']['desktop']['value'] ) ) {
			$value = $attrs['attr_value_block']['desktop']['value'];
		} elseif ( isset( $attrs['attr_value_block']['desktop'] ) && is_string( $attrs['attr_value_block']['desktop'] ) ) {
			$value = $attrs['attr_value_block']['desktop'];
		} elseif ( isset( $attrs['attr_value_block'] ) && is_string( $attrs['attr_value_block'] ) ) {
			$value = $attrs['attr_value_block'];
		}
		$stacked_enabled = $value === true || $value === 'on' || $value === 'Yes' || $value === '1' || $value === 'yes';

		$text_align_attr = $attrs['textAlign'] ?? $attrs['align'] ?? array( 'desktop' => array( 'value' => 'left' ) );

		$styles_array = array(
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
									'selector' => $order_class . ' .variations',
									'attr'     => array( 'desktop' => array( 'value' => 'inline-table' ) ),
									'property' => 'display',
								),
							),
						),
					),
				)
			),
			// Attribute Label element styles (typography, colors, spacing)
			$elements->style(
				array(
					'attrName' => 'attributeLabel',
				)
			),
			// Attribute Value element styles (typography, colors, border, spacing)
			// Selector: {{selector}} .variations .value select
			$elements->style(
				array(
					'attrName' => 'attributeValue',
				)
			),
			// Attribute Value Width (on wrapper)
			$elements->style(
				array(
					'attrName' => 'attributeValueWidth',
				)
			),
			// Attribute Value Height (on select element)
			$elements->style(
				array(
					'attrName' => 'attributeValueHeight',
				)
			),
			// Module-level Spacing (on module root)
			$elements->style(
				array(
					'attrName' => 'wfocu_variation_selector_module_spacing',
				)
			),
			// Module-level Border (on module root)
			$elements->style(
				array(
					'attrName' => 'wfocu_variation_selector_module_border',
				)
			),
			// Attribute Background Color element styles
			$elements->style(
				array(
					'attrName' => 'wfocu_variation_selector_attribute_bg_color',
				)
			),
			// Attribute Value Background Color element styles
			$elements->style(
				array(
					'attrName' => 'wfocu_variation_selector_attr_value_bg_color',
				)
			),
		);

		// Note: Stacked layout styles are handled via CSS classes (wfocu-variation-block / wfocu-variation-inline)
		// added to the form wrapper in RenderCallbackTrait, similar to Quantity Selector pattern
		// CSS is defined in divi-5/css/divi.css

		// Add custom CSS (must be last to allow overrides)
		$styles_array[] = CssStyle::style(
			array(
				'selector'  => $order_class,
				'attr'      => $attrs['css'] ?? array(),
				'cssFields' => self::custom_css(),
			)
		);

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => $styles_array,
			)
		);
	}
}
