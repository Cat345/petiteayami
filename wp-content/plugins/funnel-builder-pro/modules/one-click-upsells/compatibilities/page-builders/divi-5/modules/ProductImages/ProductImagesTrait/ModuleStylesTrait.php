<?php
/**
 * ProductImages::module_styles().
 *
 * @package WFOCU\Modules\ProductImages
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductImages\ProductImagesTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WFOCU\Modules\ProductImages\ProductImagesTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Product Images module's style components.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Style arguments.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? array();
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? array();
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? array();
		$order_class                 = $args['orderClass'] ?? '';

		// Merge module.json defaults with current attributes.
		$default_attrs = array();
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfocu/product-images' );
			} catch ( \Exception $e ) {
				// Continue without defaults.
			}
		}
		$attrs = array_replace_recursive( $default_attrs, $attrs );

		// Remove border/boxShadow from module-level (only apply to featureImage/thumbnailImage).
		unset( $attrs['module']['decoration']['border'], $attrs['module']['decoration']['boxShadow'] );

		$text_align_attr = $attrs['textAlign'] ?? array( 'desktop' => array( 'value' => 'left' ) );

		$module_decoration_defaults = $default_printed_style_attrs['module']['decoration'] ?? array();
		if ( is_array( $module_decoration_defaults ) ) {
			unset( $module_decoration_defaults['border'], $module_decoration_defaults['boxShadow'] );
		}

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
								'defaultPrintedStyleAttrs' => $module_decoration_defaults,
								'disabledOn'               => array(
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								),
								'advancedStyles'           => array(
									array(
										'componentName' => 'divi/common',
										'props'         => array(
											'selector' => $order_class . ' .wfocu-product-gallery',
											'attr'     => $text_align_attr,
											'property' => 'text-align',
										),
									),
									array(
										'componentName' => 'divi/common',
										'props'         => array(
											'selector' => $order_class . ' .wfocu-product-gallery .wfocu-carousel-cell>a>img, ' . $order_class . ' .wfocu-product-gallery>img',
											'attr'     => $text_align_attr,
											'declarationFunction' => array( self::class, 'image_display_declaration' ),
										),
									),
								),
							),
						)
					),
					$elements->style( array( 'attrName' => 'featureImage' ) ),
					$elements->style( array( 'attrName' => 'featureImageGallery' ) ),
					$elements->style( array( 'attrName' => 'thumbnailImage' ) ),
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
	 * Makes images inline-block so text-align controls alignment.
	 *
	 * The base CSS (divi.css) sets display:block + margin:auto for D4 backward
	 * compat. D5 overrides with inline-block for all alignments so text-align
	 * on the parent gallery container works for left/center/right.
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function image_display_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			array(
				'returnType' => 'string',
				'important'  => false,
			)
		);

		$style_declarations->add( 'display', 'inline-block' );

		return $style_declarations->value();
	}
}
