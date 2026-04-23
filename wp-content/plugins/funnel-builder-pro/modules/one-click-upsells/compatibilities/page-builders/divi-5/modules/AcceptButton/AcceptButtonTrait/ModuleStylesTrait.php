<?php
/**
 * AcceptButton::module_styles().
 *
 * @package WFOCU\Modules\AcceptButton
 * @since 1.0.0
 */

namespace WFOCU\Modules\AcceptButton\AcceptButtonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFOCU\Modules\AcceptButton\AcceptButtonTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Accept Button module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/accept-button/styles.tsx.
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
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/accept-button' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// CRITICAL: Remove any duplicate/incorrect padding structure (spacing.desktop.value.padding)
		// This can interfere with the correct padding structure (spacing.padding.desktop.value)
		// Only remove if it exists - don't interfere with normal padding structure
		if ( isset( $merged_attrs['button']['decoration']['spacing']['desktop'] )
			&& isset( $merged_attrs['button']['decoration']['spacing']['padding'] ) ) {
			// Only remove duplicate if correct padding structure exists
			unset( $merged_attrs['button']['decoration']['spacing']['desktop'] );
		}

		// CRITICAL: Remove border and boxShadow from module-level to prevent them applying to wrapper
		// These should only apply to button element, not module wrapper
		if ( isset( $merged_attrs['module']['decoration']['border'] ) ) {
			unset( $merged_attrs['module']['decoration']['border'] );
		}
		if ( isset( $merged_attrs['module']['decoration']['boxShadow'] ) ) {
			unset( $merged_attrs['module']['decoration']['boxShadow'] );
		}

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		$text_align_attr = $attrs['buttonAlign'] ?? array( 'desktop' => array( 'value' => 'center' ) );
		$icon_align      = $attrs['iconAlign']['desktop']['value'] ?? 'left';
		$icon_spacing    = $attrs['iconSpacing'] ?? array( 'desktop' => array( 'value' => '5px' ) );

		// CRITICAL: Exclude border and boxShadow from module-level to prevent them applying to wrapper
		// These should only apply to button element, not module wrapper
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
											'selector' => $order_class . ' .wfocu-button-wrapper',
											'attr'     => $text_align_attr,
											'property' => 'text-align',
										),
									),
									array(
										'componentName' => 'divi/common',
										'props'         => array(
											'selector' => $order_class . ' .wfocu-button-wrapper .wfocu-button-icon',
											'attr'     => $icon_spacing,
											'property' => 'left' === $icon_align ? 'margin-right' : 'margin-left',
										),
									),
								),
							),
						)
					),
					// Title text element styles (typography, colors)
					$elements->style(
						array(
							'attrName' => 'text',
						)
					),
					// Subtitle text element styles (typography, colors)
					$elements->style(
						array(
							'attrName' => 'subtitle',
						)
					),
					// Text margin element styles (spacing between title and subtitle)
					$elements->style(
						array(
							'attrName' => 'textMargin',
						)
					),
					// Button width
					$elements->style(
						[
							'attrName' => 'buttonWidth',
						]
					),
					// Button element styles (background, border, box shadow, padding)
					$elements->style(
						array(
							'attrName' => 'button',
						)
					),
					// Icon color element styles (normal/hover states)
					$elements->style(
						array(
							'attrName' => 'iconColor',
						)
					),
					// Button Colors - Background Color
					// Selector: {{selector}} .wfocu-button-wrapper a.wfocu_upsell
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_button_background_color',
						)
					),
					// Button Colors - Background Hover Color
					// Selector: {{selector}} .wfocu-button-wrapper a.wfocu_upsell:hover
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_button_background_hover_color',
						)
					),
					// Button Colors - Icon Color
					// Selector: {{selector}} .wfocu-button-wrapper .wfocu-button-content-wrapper .wfocu-button-icon.et-pb-icon
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_button_icon_color',
						)
					),
					// Button Colors - Icon Hover Color
					// Selector: {{selector}}:hover .wfocu-button-content-wrapper .wfocu-button-icon.et-pb-icon
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_button_icon_hover_color',
						)
					),
					// Title Spacing - Title Margin
					// Selector: {{selector}} .wfocu-button-wrapper .wfocu-button-text
					$elements->style(
						array(
							'attrName' => 'text_margin',
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
