<?php
/**
 * ProductShortDescription::module_styles().
 *
 * @package WFOCU\Modules\ProductShortDescription
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductShortDescription\ProductShortDescriptionTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFOCU\Modules\ProductShortDescription\ProductShortDescriptionTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Product Short Description module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/product-short-description/styles.tsx.
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
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/product-short-description' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// CRITICAL: Remove border and boxShadow from module-level to prevent them applying to wrapper
		// These should only apply to description element, not module wrapper
		if ( isset( $merged_attrs['module']['decoration']['border'] ) ) {
			unset( $merged_attrs['module']['decoration']['border'] );
		}
		if ( isset( $merged_attrs['module']['decoration']['boxShadow'] ) ) {
			unset( $merged_attrs['module']['decoration']['boxShadow'] );
		}

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		$text_align_attr = $attrs['textAlign'] ?? array( 'desktop' => array( 'value' => 'left' ) );

		// CRITICAL: Exclude border and boxShadow from module-level to prevent them applying to wrapper
		// These should only apply to description element, not module wrapper
		$module_decoration_defaults = $default_printed_style_attrs['module']['decoration'] ?? array();
		// Remove border and boxShadow from module-level defaults
		if ( is_array( $module_decoration_defaults ) ) {
			unset( $module_decoration_defaults['border'] );
			unset( $module_decoration_defaults['boxShadow'] );
		}

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array(
					// Module decoration styles
					$elements->style(
						array(
							'attrName'   => 'module',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $module_decoration_defaults,
								'disabledOn'               => array(
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								),
								'advancedStyles'           => array(
									array(
										'componentName' => 'divi/common',
										'props'         => array(
											'selector' => $order_class . ' .wfocu-widget-container p',
											'attr'     => $text_align_attr,
											'property' => 'text-align',
										),
									),
								),
							),
						)
					),
					// Description element styles (typography, colors, border, box shadow)
					$elements->style(
						array(
							'attrName' => 'description',
						)
					),
					// WF Description Background Color
					// Selector: {{selector}} .wfocu-widget-container
					$elements->style(
						array(
							'attrName' => 'wfocu_product_short_desc_title_bg_color',
						)
					),
					// WF Description Border
					// Selector: {{selector}} .wfocu-widget-container
					$elements->style(
						array(
							'attrName' => 'wfocu_product_short_desc_border',
						)
					),
					// WF Description Box Shadow
					// Selector: {{selector}} .wfocu-widget-container
					$elements->style(
						array(
							'attrName' => 'wfocu_product_short_desc_box_shadow',
						)
					),
					// WF Description Spacing (Margin + Padding)
					// Selector: {{selector}} .wfocu-widget-container
					$elements->style(
						array(
							'attrName' => 'wfocu_product_short_desc_margin_padding',
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
