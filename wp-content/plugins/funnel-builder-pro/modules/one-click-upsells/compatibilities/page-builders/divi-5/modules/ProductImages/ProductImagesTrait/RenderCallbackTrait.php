<?php
/**
 * ProductImages::render_callback()
 *
 * @package WFOCU\Modules\ProductImages
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductImages\ProductImagesTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\ProductImages\ProductImages;

trait RenderCallbackTrait {

	/**
	 * Get product key from selected product.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string Product key.
	 */
	private static function get_product_key( array $attrs ): string {
		\WFOCU\Modules\ModuleRegistry::ensure_product_data();

		// Safely extract selected product with multiple fallback methods
		$selected_product = '';

		if ( isset( $attrs['selectedProduct']['desktop']['value'] ) ) {
			$selected_product = $attrs['selectedProduct']['desktop']['value'];
		} elseif ( isset( $attrs['selectedProduct']['desktop'] ) && is_string( $attrs['selectedProduct']['desktop'] ) ) {
			$selected_product = $attrs['selectedProduct']['desktop'];
		} elseif ( isset( $attrs['selectedProduct'] ) && is_string( $attrs['selectedProduct'] ) ) {
			$selected_product = $attrs['selectedProduct'];
		}

		if ( empty( $selected_product ) ) {
			$selected_product = '0';
		}

		// Get product key from WFOCU template loader
		if ( class_exists( 'WFOCU_Core' ) ) {
			$wfocu_core = WFOCU_Core();
			if ( $wfocu_core && isset( $wfocu_core->template_loader ) && method_exists( $wfocu_core->template_loader, 'default_product_key' ) ) {
				try {
					$product_key = $wfocu_core->template_loader->default_product_key( $selected_product );

					// Verify the key exists in the offer's products — template imports
					// may have stale hashes that don't match the current offer config.
					if ( ! empty( $product_key ) && isset( $wfocu_core->template_loader->product_data->products ) ) {
						$products = (array) $wfocu_core->template_loader->product_data->products;
						if ( ! isset( $products[ $product_key ] ) ) {
							$product_key = $wfocu_core->template_loader->default_product_key( 0 );
						}
					}

					if ( ! empty( $product_key ) ) {
						return $product_key;
					}
				} catch ( \Exception $e ) {
					// Continue with fallback
				}
			}
		}

		return $selected_product;
	}

	/**
	 * Get slider enabled status from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string 'on' or 'off'.
	 */
	private static function get_slider_enabled( array $attrs ): string {
		$slider_enabled = 'on';

		// Top-level sliderEnabled attribute (current path)
		if ( isset( $attrs['sliderEnabled']['desktop']['value'] ) ) {
			$slider_enabled = $attrs['sliderEnabled']['desktop']['value'];
		} elseif ( isset( $attrs['sliderEnabled']['desktop'] ) && is_string( $attrs['sliderEnabled']['desktop'] ) ) {
			$slider_enabled = $attrs['sliderEnabled']['desktop'];
		} elseif ( isset( $attrs['sliderEnabled'] ) && is_string( $attrs['sliderEnabled'] ) ) {
			$slider_enabled = $attrs['sliderEnabled'];
		}
		// Fallback to old path (module.advanced.sliderEnabled) for backward compatibility
		elseif ( isset( $attrs['module']['advanced']['sliderEnabled']['desktop']['value'] ) ) {
			$slider_enabled = $attrs['module']['advanced']['sliderEnabled']['desktop']['value'];
		} elseif ( isset( $attrs['module']['advanced']['sliderEnabled'] ) && is_string( $attrs['module']['advanced']['sliderEnabled'] ) ) {
			$slider_enabled = $attrs['module']['advanced']['sliderEnabled'];
		}

		return $slider_enabled === 'on' ? 'on' : 'off';
	}

	/**
	 * Get product images HTML.
	 * Matching Divi 4 implementation (lines 37-150).
	 *
	 * @since 1.0.0
	 *
	 * @param string $product_key Product key.
	 * @param string $slider_enabled Slider enabled status ('on' or 'off').
	 * @return string Product images HTML.
	 */
	private static function get_product_images_html( string $product_key, string $slider_enabled ): string {
		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader ) {
			return '';
		}

		if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
			return '';
		}

		$product_data = WFOCU_Core()->template_loader->product_data->products;

		// If no product selected or selected product not in current set, fallback to first product (matching D4 behavior).
		if ( empty( $product_key ) || $product_key === '0' || ! isset( $product_data->{$product_key} ) ) {
			$product_key = key( (array) $product_data );
		}

		if ( empty( $product_key ) || ! isset( $product_data->{$product_key} ) ) {
			return '';
		}

		$product_obj = $product_data->{$product_key}->data;
		$product     = $product_data->{$product_key};

		if ( ! $product_obj instanceof \WC_Product ) {
			return '';
		}

		$main_img    = $product_obj->get_image_id();
		$gallery_img = $product_obj->get_gallery_image_ids();

		$gallery      = array();
		$images_taken = array();
		if ( ! empty( $main_img ) ) {
			$gallery[]['gallery'] = (int) $main_img;
			$images_taken[]       = (int) $main_img;
		}

		if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 && 'on' === $slider_enabled ) {
			foreach ( $gallery_img as $gallerys ) {
				$gallery[]['gallery'] = (int) $gallerys;
				$images_taken[]       = (int) $gallerys;
			}
		}

		/**
		 * Variation images to be bunch with the other gallery images
		 */
		if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) && 'on' === $slider_enabled ) {
			foreach ( $product->variations_data['images'] as $id ) {
				if ( false === in_array( $id, $images_taken, true ) ) {
					$gallery[]['gallery'] = (int) $id;
				}
			}
		}

		if ( empty( $main_img ) ) {
			// Return placeholder image HTML (matching Divi 4 lines 114-148)
			ob_start();
			?>
			<div class="wfocu-widget-container">
				<div class="wfocu-product-gallery ">
					<div class="wfocu-product-carousel wfocu-product-image-single ">
						<div class="wfocu-carousel-cell">
							<a><img src="<?php echo esc_url( wc_placeholder_img_src( 'thumbnail' ) ); ?>" alt="" title=""></a>
						</div>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		// Render using slider template (matching Divi 4 lines 102-108)
		ob_start();

		// Load flickity styles if doing AJAX (matching Divi 4 lines 46-50)
		if ( wp_doing_ajax() ) {
			$flickity_css_url        = esc_url( plugin_dir_url( WFOCU_PLUGIN_FILE ) . 'assets/flickity/flickity.css' );
			$flickity_common_css_url = esc_url( plugin_dir_url( WFOCU_PLUGIN_FILE ) . 'assets/css/flickity-common.css' );
			?>
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Styles must be output inline for AJAX responses ?>
			<link rel="stylesheet" id="flickity-css" href="<?php echo esc_url( $flickity_css_url ); ?>" type="text/css" media="all">
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Styles must be output inline for AJAX responses ?>
			<link rel="stylesheet" id="flickity-common-css" href="<?php echo esc_url( $flickity_common_css_url ); ?>" type="text/css" media="all">
			<?php
		}

		WFOCU_Core()->template_loader->get_template_part(
			'product/slider',
			array(
				'key'     => $product_key,
				'gallery' => $gallery,
				'product' => $product_obj,
				'title'   => '',
				'style'   => 2,
			)
		);

		// Print flickity styles if not doing AJAX (matching Divi 4 lines 151-153)
		if ( ! wp_doing_ajax() ) {
			wp_print_styles( array( 'flickity', 'flickity-common' ) );
		}

		return ob_get_clean();
	}

	/**
	 * Product Images module render callback which outputs server side rendered HTML on the Front-End.
	 * Matching Divi 4 implementation (lines 37-156).
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Product Images module.
	 */
	public static function render_callback( array $attrs, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {
			// Ensure attrs is an array
			if ( ! is_array( $attrs ) ) {
				$attrs = array();
			}

			// Ensure block and parsed_block exist
			if ( ! isset( $block->parsed_block ) || ! isset( $block->block_type ) ) {
				throw new \Exception( 'Invalid block data: missing parsed_block or block_type' );
			}

			// CRITICAL: Merge defaults from module.json BEFORE processing
			// This ensures empty values get filled with defaults from module.json
			$default_attrs = array();
			if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
				try {
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/product-images' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Get product key and slider enabled status
			$product_key    = self::get_product_key( $attrs );
			$slider_enabled = self::get_slider_enabled( $attrs );

			// get_product_images_html() handles fallback to first product (matching D4).
			$images_html = self::get_product_images_html( $product_key, $slider_enabled );

			if ( empty( $images_html ) ) {
				$wrapper_html = HTMLUtility::render(
					array(
						'tag'               => 'div',
						'attributes'        => array(
							'class' => 'wfocu-widget-container',
						),
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => esc_html( __( 'No Product Selected', 'woofunnels-upstroke-one-click-upsell' ) ),
					)
				);
			} else {
				$wrapper_html = HTMLUtility::render(
					array(
						'tag'               => 'div',
						'attributes'        => array(
							'class' => 'wfocu-widget-container',
						),
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => $images_html,
					)
				);
			}

			// Get parent block (following Divi 5 pattern)
			$parent       = null;
			$parent_attrs = array();
			$parent_id    = '';
			$parent_name  = '';

			if ( isset( $block->parsed_block['id'] ) && isset( $block->parsed_block['storeInstance'] ) ) {
				try {
					$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
					if ( $parent ) {
						$parent_attrs = $parent->attrs ?? array();
						$parent_id    = $parent->id ?? '';
						$parent_name  = $parent->blockName ?? '';
					}
				} catch ( \Exception $e ) {
					// Parent not found, continue without parent data
				}
			}

			// Use Module::render() following Divi 5 pattern
			try {
				return Module::render(
					array(
						// FE only.
						'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
						'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,

						// VB equivalent.
						'attrs'               => $attrs,
						'elements'            => $elements,
						'id'                  => $block->parsed_block['id'] ?? '',
						'name'                => $block->block_type->name ?? 'wfocu/product-images',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( ProductImages::class, 'module_classnames' ),
						'stylesComponent'     => array( ProductImages::class, 'module_styles' ),
						'scriptDataComponent' => array( ProductImages::class, 'module_script_data' ),
						'parentAttrs'         => $parent_attrs,
						'parentId'            => $parent_id,
						'parentName'          => $parent_name,
						'children'            => array(
							ElementComponents::component(
								array(
									'attrs'         => $attrs['module']['decoration'] ?? array(),
									'id'            => $block->parsed_block['id'] ?? '',

									// FE only.
									'orderIndex'    => $block->parsed_block['orderIndex'] ?? 0,
									'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
								)
							),
							$wrapper_html . $content,
						),
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $wrapper_html;
			}
		} catch ( \Exception $e ) {
			// Return simple fallback HTML (matching Divi 4 structure)
			$product_key    = self::get_product_key( $attrs );
			$slider_enabled = self::get_slider_enabled( $attrs );
			$images_html    = self::get_product_images_html( $product_key, $slider_enabled );

			if ( empty( $images_html ) ) {
				return '<div class="wfocu-widget-container">' . esc_html( __( 'No Product Selected', 'woofunnels-upstroke-one-click-upsell' ) ) . '</div>';
			}

			return '<div class="wfocu-widget-container">' . wp_kses_post( $images_html ) . '</div>';
		}
	}
}
