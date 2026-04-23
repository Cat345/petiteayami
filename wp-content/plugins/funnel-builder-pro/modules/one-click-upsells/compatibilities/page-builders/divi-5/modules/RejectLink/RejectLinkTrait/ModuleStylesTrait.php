<?php
/**
 * RejectLink::module_styles().
 *
 * @package WFOCU\Modules\RejectLink
 * @since 1.0.0
 */

namespace WFOCU\Modules\RejectLink\RejectLinkTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFOCU\Modules\RejectLink\RejectLinkTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Reject Link module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/reject-link/styles.tsx.
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
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/reject-link' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		// Text alignment attribute (standalone, using divi/common)
		$text_align_attr = $attrs['textAlign'] ?? array( 'desktop' => array( 'value' => 'center' ) );

		// Merge defaults for text color if not set (fallback)
		if ( empty( $attrs['text']['decoration']['colors']['normal']['color']['desktop']['value']['hex'] ) ) {
			$attrs['text']['decoration']['colors']['normal']['color']['desktop']['value']['hex'] = '#777777';
		}

		// Merge defaults for text decoration if not set (fallback)
		if ( empty( $attrs['text']['decoration']['font']['font']['desktop']['value']['textDecoration'] ) ) {
			$attrs['text']['decoration']['font']['font']['desktop']['value']['textDecoration'] = 'underline';
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
								),
							),
						)
					),
					// Text element styles (includes colors, typography, etc.)
					// $attrs now contains merged defaults, so elements->style() will use them
					$elements->style(
						array(
							'attrName' => 'text',
						)
					),
					// Reject Link Color - Background Color
					// Selector: {{selector}} .wfocu-reject
					$elements->style(
						array(
							'attrName' => 'wfocu_reject_link_background_color',
						)
					),
					// Reject Link Color - Background Hover Color
					// Selector: {{selector}} .wfocu-reject:hover
					$elements->style(
						array(
							'attrName' => 'wfocu_reject_link_background_hover_color',
						)
					),
					// Reject Link Color - Text Hover Color
					// Selector: {{selector}} .wfocu-reject:hover
					$elements->style(
						array(
							'attrName' => 'wfocu_reject_link_text_hover_color',
						)
					),
					// Reject Link Border
					// Selector: {{selector}} .wfocu-reject
					$elements->style(
						array(
							'attrName' => 'wfocu_reject_link_border',
						)
					),
					// Reject Link Box Shadow
					// Selector: {{selector}} .wfocu-reject
					$elements->style(
						array(
							'attrName' => 'wfocu_reject_link_box_shadow',
						)
					),
					// Reject Link Spacing - Margin and Padding (combined)
					// Selector: {{selector}} .wfocu-reject
					$elements->style(
						array(
							'attrName' => 'wfocu_reject_link_spacing',
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
