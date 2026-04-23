<?php
/**
 * AcceptLink::module_styles().
 *
 * @package WFOCU\Modules\AcceptLink
 * @since 1.0.0
 */

namespace WFOCU\Modules\AcceptLink\AcceptLinkTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFOCU\Modules\AcceptLink\AcceptLinkTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Accept Link module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/accept-link/styles.tsx.
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
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/accept-link' );
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

		// Process border color attributes (requires manual CSS fallback)
		// Generate manual CSS for border hover color if needed
		$border_hover_color_css = self::get_border_color_css( $attrs, $order_class, $elements, 'wfocu_accept_link_hover_border_color', '.wfocu-wfocu-accept:hover' );

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
					// Accept Link Color - Background Color
					// Selector: {{selector}} .wfocu-wfocu-accept
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_bg_color',
						)
					),
					// Accept Link Color - Text Color
					// Selector: {{selector}} .wfocu-wfocu-accept
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_text_color',
						)
					),
					// Accept Link Color - Background Hover Color
					// Selector: {{selector}} .wfocu-wfocu-accept:hover
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_background_hover_color',
						)
					),
					// Accept Link Color - Text Hover Color
					// Selector: {{selector}}:hover .wfocu-wfocu-accept
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_text_hover_color',
						)
					),
					// Accept Link Spacing - Margin and Padding (combined)
					// Selector: {{selector}} .wfocu-wfocu-accept
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_spacing',
						)
					),
					// Accept Link Border
					// Selector: {{selector}} .wfocu-wfocu-accept
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_border',
						)
					),
					// Accept Link Border - Border Hover Color
					// Selector: {{selector}} .wfocu-wfocu-accept:hover
					// Note: Manual CSS fallback is included if Border component doesn't generate CSS
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_hover_border_color',
						)
					),
					// Manual CSS fallback for border hover color (if Border component didn't generate it)
					// This ensures border-color CSS is always generated on frontend
					$border_hover_color_css,
					// Accept Link Box Shadow
					// Selector: {{selector}} .wfocu-wfocu-accept
					$elements->style(
						array(
							'attrName' => 'wfocu_accept_link_box_shadow',
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

	/**
	 * Generate manual CSS for border color attribute.
	 *
	 * The Border component doesn't properly handle deeply nested color-picker paths,
	 * so we generate manual CSS as a fallback to ensure it works on frontend.
	 *
	 * @param array  $attrs      Module attributes.
	 * @param string $order_class Order class with VB prefixes.
	 * @param mixed  $elements    Elements object for style generation.
	 * @param string $key         Attribute key (e.g., 'wfocu_accept_link_hover_border_color').
	 * @param string $selector    CSS selector without order class (e.g., '.wfocu-wfocu-accept:hover').
	 *
	 * @return array|null Manual CSS entry or null if not needed.
	 */
	private static function get_border_color_css( array $attrs, string $order_class, $elements, string $key, string $selector ): ?array {
		$border_color_attr = $attrs[ $key ] ?? null;
		if ( empty( $border_color_attr ) || empty( $order_class ) ) {
			return null;
		}

		// Extract color value from responsive or default path
		$color_value = null;
		if ( isset( $border_color_attr['decoration']['border']['border']['desktop']['value']['styles']['all']['desktop']['value']['color'] ) ) {
			$color_value = $border_color_attr['decoration']['border']['border']['desktop']['value']['styles']['all']['desktop']['value']['color'];
		} elseif ( isset( $border_color_attr['decoration']['border']['border']['desktop']['value']['styles']['all']['color'] ) ) {
			$color_value = $border_color_attr['decoration']['border']['border']['desktop']['value']['styles']['all']['color'];
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		// Try to generate CSS using Border component
		$border_color_style_result = $elements->style(
			array(
				'attrName' => $key,
			)
		);

		// Check if Border component generated border-color CSS
		$style_result_str = is_array( $border_color_style_result ) ? json_encode( $border_color_style_result ) : '';
		$has_border_css   = ! empty( $style_result_str ) && ( strpos( $style_result_str, 'border-color' ) !== false || strpos( $style_result_str, 'borderColor' ) !== false );

		// Generate manual CSS fallback if Border component didn't generate it
		if ( empty( $border_color_style_result ) || ! $has_border_css ) {
			$full_selector = $order_class . ' ' . $selector;

			return array(
				array(
					'selector'    => $full_selector,
					'declaration' => 'border-color: ' . esc_attr( $color_value ) . ' !important;',
				),
			);
		}

		return null;
	}
}
