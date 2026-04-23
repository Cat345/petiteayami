<?php
/**
 * Module Registry for WFOCU Divi 5 Modules.
 *
 * This class provides a centralized way to register and manage all WFOCU modules.
 * It automatically discovers and loads modules based on configuration.
 *
 * @package WFOCU\Modules
 * @since 1.0.0
 */

namespace WFOCU\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module Registry Class.
 *
 * Manages registration of all WFOCU Divi 5 modules.
 *
 * @since 1.0.0
 */
class ModuleRegistry {

	/**
	 * Module configuration array.
	 *
	 * Format:
	 * [
	 *   'module_name' => [
	 *     'namespace' => 'WFOCU\Modules\ModuleName',
	 *     'class'     => 'ModuleName',
	 *     'dir'       => 'ModuleName', // Relative to modules folder
	 *   ],
	 * ]
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $modules = array(
		'RejectLink'              => array(
			'namespace' => 'WFOCU\Modules\RejectLink',
			'class'     => 'RejectLink',
			'dir'       => 'RejectLink',
		),
		'AcceptLink'              => array(
			'namespace' => 'WFOCU\Modules\AcceptLink',
			'class'     => 'AcceptLink',
			'dir'       => 'AcceptLink',
		),
		'AcceptButton'            => array(
			'namespace' => 'WFOCU\Modules\AcceptButton',
			'class'     => 'AcceptButton',
			'dir'       => 'AcceptButton',
		),
		'RejectButton'            => array(
			'namespace' => 'WFOCU\Modules\RejectButton',
			'class'     => 'RejectButton',
			'dir'       => 'RejectButton',
		),
		'ProductTitle'            => array(
			'namespace' => 'WFOCU\Modules\ProductTitle',
			'class'     => 'ProductTitle',
			'dir'       => 'ProductTitle',
		),
		'ProductShortDescription' => array(
			'namespace' => 'WFOCU\Modules\ProductShortDescription',
			'class'     => 'ProductShortDescription',
			'dir'       => 'ProductShortDescription',
		),
		'ProductImages'           => array(
			'namespace' => 'WFOCU\Modules\ProductImages',
			'class'     => 'ProductImages',
			'dir'       => 'ProductImages',
		),
		'QtySelector'             => array(
			'namespace' => 'WFOCU\Modules\QtySelector',
			'class'     => 'QtySelector',
			'dir'       => 'QtySelector',
		),
		'VariationSelector'       => array(
			'namespace' => 'WFOCU\Modules\VariationSelector',
			'class'     => 'VariationSelector',
			'dir'       => 'VariationSelector',
		),
		'OfferPrice'              => array(
			'namespace' => 'WFOCU\Modules\OfferPrice',
			'class'     => 'OfferPrice',
			'dir'       => 'OfferPrice',
		),
	);

	/**
	 * Non-standard D4 attribute mappings per module.
	 *
	 * WFOCU D4 modules use custom naming conventions that differ from Divi's standard:
	 * 1. Font family uses `_typograhy` suffix (typo) instead of standard `_font`
	 * 2. Box shadow uses `{key}_shadow_{prop}` instead of `box_shadow_{prop}_{key}`
	 *
	 * These mappings are merged into the conversion map so Divi's converter
	 * doesn't demote our modules to divi/shortcode-module due to unknownAttributes.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $nonstandard_attr_maps = array(
		'wfocu/accept-button'             => array(
			'attributeMap'              => array(
				// Font family: D4 uses _typograhy (typo), Divi expects _font
				'wfocu_accept_buttontitle_typography_typograhy' => 'text.decoration.font.font.*',
				'wfocu_accept_buttonsubtitle_typography_typograhy' => 'subtitle.decoration.font.font.*',
				// Box shadow: D4 uses {key}_shadow_{prop}, Divi expects box_shadow_{prop}_{key}
				'wfocu_accept_button_box_shadow_shadow_style' => 'button.decoration.boxShadow.*.style',
				'wfocu_accept_button_box_shadow_shadow_horizontal' => 'button.decoration.boxShadow.*.horizontal',
				'wfocu_accept_button_box_shadow_shadow_vertical' => 'button.decoration.boxShadow.*.vertical',
				'wfocu_accept_button_box_shadow_shadow_blur' => 'button.decoration.boxShadow.*.blur',
				'wfocu_accept_button_box_shadow_shadow_spread' => 'button.decoration.boxShadow.*.spread',
				'wfocu_accept_button_box_shadow_shadow_color' => 'button.decoration.boxShadow.*.color',
				'wfocu_accept_button_box_shadow_shadow_position' => 'button.decoration.boxShadow.*.position',
				// Border: D4 uses {key}_border_{prop}, Divi expects border_{prop}_all_{key}
				'wfocu_accept_button_border_border_type'   => 'button.decoration.border.border.*.styles.all.style',
				'wfocu_accept_button_border_border_width'  => 'button.decoration.border.border.*.styles.all.width',
				'wfocu_accept_button_border_border_color'  => 'button.decoration.border.border.*.styles.all.color',
				'wfocu_accept_button_border_border_radius' => 'button.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'wfocu_accept_buttontitle_typography_typograhy'    => 'convertFont',
				'wfocu_accept_buttonsubtitle_typography_typograhy' => 'convertFont',
			),
		),
		'wfocu/reject-button'             => array(
			'attributeMap'              => array(
				'wfocu_reject_button_typography_typograhy' => 'text.decoration.font.font.*',
				'wfocu_reject_button_box_shadow_shadow_style' => 'button.decoration.boxShadow.*.style',
				'wfocu_reject_button_box_shadow_shadow_horizontal' => 'button.decoration.boxShadow.*.horizontal',
				'wfocu_reject_button_box_shadow_shadow_vertical' => 'button.decoration.boxShadow.*.vertical',
				'wfocu_reject_button_box_shadow_shadow_blur' => 'button.decoration.boxShadow.*.blur',
				'wfocu_reject_button_box_shadow_shadow_spread' => 'button.decoration.boxShadow.*.spread',
				'wfocu_reject_button_box_shadow_shadow_color' => 'button.decoration.boxShadow.*.color',
				'wfocu_reject_button_box_shadow_shadow_position' => 'button.decoration.boxShadow.*.position',
				'wfocu_reject_button_border_border_type'   => 'button.decoration.border.border.*.styles.all.style',
				'wfocu_reject_button_border_border_width'  => 'button.decoration.border.border.*.styles.all.width',
				'wfocu_reject_button_border_border_color'  => 'button.decoration.border.border.*.styles.all.color',
				'wfocu_reject_button_border_border_radius' => 'button.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'wfocu_reject_button_typography_typograhy' => 'convertFont',
			),
		),
		'wfocu/accept-link'               => array(
			'attributeMap'              => array(
				'typography_typograhy'         => 'text.decoration.font.font.*',
				'box_shadow_shadow_style'      => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.style',
				'box_shadow_shadow_horizontal' => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.horizontal',
				'box_shadow_shadow_vertical'   => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.vertical',
				'box_shadow_shadow_blur'       => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.blur',
				'box_shadow_shadow_spread'     => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.spread',
				'box_shadow_shadow_color'      => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.color',
				'box_shadow_shadow_position'   => 'wfocu_accept_link_box_shadow.decoration.boxShadow.*.position',
				'border_border_type'           => 'wfocu_accept_link_border.decoration.border.border.*.styles.all.style',
				'border_border_width'          => 'wfocu_accept_link_border.decoration.border.border.*.styles.all.width',
				'border_border_color'          => 'wfocu_accept_link_border.decoration.border.border.*.styles.all.color',
				'border_border_radius'         => 'wfocu_accept_link_border.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'typography_typograhy' => 'convertFont',
			),
		),
		'wfocu/reject-link'               => array(
			'attributeMap'              => array(
				'typography_typograhy'         => 'text.decoration.font.font.*',
				'box_shadow_shadow_style'      => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.style',
				'box_shadow_shadow_horizontal' => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.horizontal',
				'box_shadow_shadow_vertical'   => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.vertical',
				'box_shadow_shadow_blur'       => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.blur',
				'box_shadow_shadow_spread'     => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.spread',
				'box_shadow_shadow_color'      => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.color',
				'box_shadow_shadow_position'   => 'wfocu_reject_link_box_shadow.decoration.boxShadow.*.position',
				'border_border_type'           => 'wfocu_reject_link_border.decoration.border.border.*.styles.all.style',
				'border_border_width'          => 'wfocu_reject_link_border.decoration.border.border.*.styles.all.width',
				'border_border_color'          => 'wfocu_reject_link_border.decoration.border.border.*.styles.all.color',
				'border_border_radius'         => 'wfocu_reject_link_border.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'typography_typograhy' => 'convertFont',
			),
		),
		'wfocu/product-title'             => array(
			'attributeMap'              => array(
				'wfocu_product_title_typography_typograhy' => 'title.decoration.font.font.*',
				'wfocu_product_title_box_shadow_shadow_style' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.style',
				'wfocu_product_title_box_shadow_shadow_horizontal' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.horizontal',
				'wfocu_product_title_box_shadow_shadow_vertical' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.vertical',
				'wfocu_product_title_box_shadow_shadow_blur' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.blur',
				'wfocu_product_title_box_shadow_shadow_spread' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.spread',
				'wfocu_product_title_box_shadow_shadow_color' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.color',
				'wfocu_product_title_box_shadow_shadow_position' => 'wfocu_product_title_box_shadow.decoration.boxShadow.*.position',
				'wfocu_product_title_border_border_type'   => 'wfocu_product_title_border.decoration.border.border.*.styles.all.style',
				'wfocu_product_title_border_border_width'  => 'wfocu_product_title_border.decoration.border.border.*.styles.all.width',
				'wfocu_product_title_border_border_color'  => 'wfocu_product_title_border.decoration.border.border.*.styles.all.color',
				'wfocu_product_title_border_border_radius' => 'wfocu_product_title_border.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'wfocu_product_title_typography_typograhy' => 'convertFont',
			),
		),
		'wfocu/offer-price'               => array(
			'attributeMap'              => array(
				'reg_label_typography_typograhy'           => 'regLabelStyle.decoration.font.font.*',
				'reg_price_typography_typograhy'           => 'regularPriceStyle.decoration.font.font.*',
				'offer_label_typography_typograhy'         => 'offerLabelStyle.decoration.font.font.*',
				'offer_price_typography_typograhy'         => 'offerPriceStyle.decoration.font.font.*',
				'signup_label_typography_typograhy'        => 'signupLabelStyle.decoration.font.font.*',
				'signup_price_typography_typograhy'        => 'signupPriceStyle.decoration.font.font.*',
				'rec_label_typography_typograhy'           => 'recurringLabelStyle.decoration.font.font.*',
				'rec_price_typography_typograhy'           => 'recurringPriceStyle.decoration.font.font.*',
				'wfocu_offer_price_box_shadow_shadow_style' => 'module.decoration.boxShadow.*.style',
				'wfocu_offer_price_box_shadow_shadow_horizontal' => 'module.decoration.boxShadow.*.horizontal',
				'wfocu_offer_price_box_shadow_shadow_vertical' => 'module.decoration.boxShadow.*.vertical',
				'wfocu_offer_price_box_shadow_shadow_blur' => 'module.decoration.boxShadow.*.blur',
				'wfocu_offer_price_box_shadow_shadow_spread' => 'module.decoration.boxShadow.*.spread',
				'wfocu_offer_price_box_shadow_shadow_color' => 'module.decoration.boxShadow.*.color',
				'wfocu_offer_price_box_shadow_shadow_position' => 'module.decoration.boxShadow.*.position',
				// Border: D4 uses {key}_border_{prop}, Divi expects border_{prop}_all_{key}
				'wfocu_offer_price_border_border_type'     => 'module.decoration.border.border.*.styles.all.style',
				'wfocu_offer_price_border_border_width'    => 'module.decoration.border.border.*.styles.all.width',
				'wfocu_offer_price_border_border_color'    => 'module.decoration.border.border.*.styles.all.color',
				'wfocu_offer_price_border_border_radius'   => 'module.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'reg_label_typography_typograhy'    => 'convertFont',
				'reg_price_typography_typograhy'    => 'convertFont',
				'offer_label_typography_typograhy'  => 'convertFont',
				'offer_price_typography_typograhy'  => 'convertFont',
				'signup_label_typography_typograhy' => 'convertFont',
				'signup_price_typography_typograhy' => 'convertFont',
				'rec_label_typography_typograhy'    => 'convertFont',
				'rec_price_typography_typograhy'    => 'convertFont',
			),
		),
		'wfocu/qty-selector'              => array(
			'attributeMap'              => array(
				'wfocu_qty_selector_text_typography_typograhy' => 'label.decoration.font.font.*',
				'wfocu_qty_selector_qty_select_typograhy' => 'dropdown.decoration.font.font.*',
				'wfocu_qty_selector_box_shadow_shadow_style' => 'module.decoration.boxShadow.*.style',
				'wfocu_qty_selector_box_shadow_shadow_horizontal' => 'module.decoration.boxShadow.*.horizontal',
				'wfocu_qty_selector_box_shadow_shadow_vertical' => 'module.decoration.boxShadow.*.vertical',
				'wfocu_qty_selector_box_shadow_shadow_blur' => 'module.decoration.boxShadow.*.blur',
				'wfocu_qty_selector_box_shadow_shadow_spread' => 'module.decoration.boxShadow.*.spread',
				'wfocu_qty_selector_box_shadow_shadow_color' => 'module.decoration.boxShadow.*.color',
				'wfocu_qty_selector_box_shadow_shadow_position' => 'module.decoration.boxShadow.*.position',
				// Border: D4 uses {key}_border_{prop}, Divi expects border_{prop}_all_{key}
				'wfocu_qty_selector_border_border_type'   => 'module.decoration.border.border.*.styles.all.style',
				'wfocu_qty_selector_border_border_width'  => 'module.decoration.border.border.*.styles.all.width',
				'wfocu_qty_selector_border_border_color'  => 'module.decoration.border.border.*.styles.all.color',
				'wfocu_qty_selector_border_border_radius' => 'module.decoration.border.border.*.radius',
				'qty_dropdown_border_border_type'         => 'dropdown.decoration.border.border.*.styles.all.style',
				'qty_dropdown_border_border_width'        => 'dropdown.decoration.border.border.*.styles.all.width',
				'qty_dropdown_border_border_color'        => 'dropdown.decoration.border.border.*.styles.all.color',
				'qty_dropdown_border_border_radius'       => 'dropdown.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'wfocu_qty_selector_text_typography_typograhy' => 'convertFont',
				'wfocu_qty_selector_qty_select_typograhy' => 'convertFont',
			),
		),
		'wfocu/product-images'            => array(
			'attributeMap' => array(
				// Border: D4 uses {key}_border_{prop}, Divi expects border_{prop}_all_{key}
				'image_border_border_type'    => 'featureImage.decoration.border.border.*.styles.all.style',
				'image_border_border_width'   => 'featureImage.decoration.border.border.*.styles.all.width',
				'image_border_border_color'   => 'featureImage.decoration.border.border.*.styles.all.color',
				'image_border_border_radius'  => 'featureImage.decoration.border.border.*.radius',
				'thumbs_border_border_type'   => 'thumbnailImage.decoration.border.border.*.styles.all.style',
				'thumbs_border_border_width'  => 'thumbnailImage.decoration.border.border.*.styles.all.width',
				'thumbs_border_border_color'  => 'thumbnailImage.decoration.border.border.*.styles.all.color',
				'thumbs_border_border_radius' => 'thumbnailImage.decoration.border.border.*.radius',
			),
		),
		'wfocu/product-short-description' => array(
			'attributeMap'              => array(
				'wfocu_product_short_desc_typography_typograhy' => 'description.decoration.font.font.*',
				'wfocu_product_short_desc_box_shadow_shadow_style'      => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.style',
				'wfocu_product_short_desc_box_shadow_shadow_horizontal' => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.horizontal',
				'wfocu_product_short_desc_box_shadow_shadow_vertical'   => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.vertical',
				'wfocu_product_short_desc_box_shadow_shadow_blur'       => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.blur',
				'wfocu_product_short_desc_box_shadow_shadow_spread'     => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.spread',
				'wfocu_product_short_desc_box_shadow_shadow_color'      => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.color',
				'wfocu_product_short_desc_box_shadow_shadow_position'   => 'wfocu_product_short_desc_box_shadow.decoration.boxShadow.*.position',
				'wfocu_product_short_desc_border_border_type'   => 'description.decoration.border.border.*.styles.all.style',
				'wfocu_product_short_desc_border_border_width'  => 'description.decoration.border.border.*.styles.all.width',
				'wfocu_product_short_desc_border_border_color'  => 'description.decoration.border.border.*.styles.all.color',
				'wfocu_product_short_desc_border_border_radius' => 'description.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'wfocu_product_short_desc_typography_typograhy' => 'convertFont',
			),
		),
		'wfocu/variation-selector'        => array(
			'attributeMap'              => array(
				'wfocu_variation_selector_text_typography_typograhy'      => 'attributeLabel.decoration.font.font.*',
				'wfocu_variation_selector_attr_value_typography_typograhy' => 'attributeValue.decoration.font.font.*',
				'wfocu_variation_selector_box_shadow_shadow_style'      => 'module.decoration.boxShadow.*.style',
				'wfocu_variation_selector_box_shadow_shadow_horizontal' => 'module.decoration.boxShadow.*.horizontal',
				'wfocu_variation_selector_box_shadow_shadow_vertical'   => 'module.decoration.boxShadow.*.vertical',
				'wfocu_variation_selector_box_shadow_shadow_blur'       => 'module.decoration.boxShadow.*.blur',
				'wfocu_variation_selector_box_shadow_shadow_spread'     => 'module.decoration.boxShadow.*.spread',
				'wfocu_variation_selector_box_shadow_shadow_color'      => 'module.decoration.boxShadow.*.color',
				'wfocu_variation_selector_box_shadow_shadow_position'   => 'module.decoration.boxShadow.*.position',
				'wfocu_variation_selector_border_border_type'   => 'module.decoration.border.border.*.styles.all.style',
				'wfocu_variation_selector_border_border_width'  => 'module.decoration.border.border.*.styles.all.width',
				'wfocu_variation_selector_border_border_color'  => 'module.decoration.border.border.*.styles.all.color',
				'wfocu_variation_selector_border_border_radius' => 'module.decoration.border.border.*.radius',
				'wfocu_variation_selector_attr_value_border_border_type'   => 'wfocu_variation_selector_attr_value_border.decoration.border.border.*.styles.all.style',
				'wfocu_variation_selector_attr_value_border_border_width'  => 'wfocu_variation_selector_attr_value_border.decoration.border.border.*.styles.all.width',
				'wfocu_variation_selector_attr_value_border_border_color'  => 'wfocu_variation_selector_attr_value_border.decoration.border.border.*.styles.all.color',
				'wfocu_variation_selector_attr_value_border_border_radius' => 'wfocu_variation_selector_attr_value_border.decoration.border.border.*.radius',
			),
			'valueExpansionFunctionMap' => array(
				'wfocu_variation_selector_text_typography_typograhy'       => 'convertFont',
				'wfocu_variation_selector_attr_value_typography_typograhy' => 'convertFont',
			),
		),
	);

	/**
	 * Register all modules with dependency tree.
	 *
	 * @since 1.0.0
	 * @param object $dependency_tree Dependency tree instance.
	 * @return void
	 */
	public static function register_modules( $dependency_tree ): void {
		// CRITICAL: Check if Divi classes are available before loading module files
		if ( ! interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
			return;
		}

		$modules_dir = plugin_dir_path( __FILE__ );

		// Load and register each module
		foreach ( self::$modules as $module_name => $module_config ) {
			$module_file = $modules_dir . $module_config['dir'] . '/' . $module_config['class'] . '.php';

			// Load module file
			if ( file_exists( $module_file ) ) {
				require_once $module_file;
			} else {
				continue;
			}

			// Register module with dependency tree
			$module_class = $module_config['namespace'] . '\\' . $module_config['class'];
			if ( class_exists( $module_class ) ) {
				$module_instance = new $module_class();
				if ( $dependency_tree && method_exists( $dependency_tree, 'add_dependency' ) ) {
					$dependency_tree->add_dependency( $module_instance );
				}
				// Call load() to register the module with ModuleRegistration
				if ( method_exists( $module_instance, 'load' ) ) {
					$module_instance->load();
				}
			}
		}

		// Patch conversion maps for non-standard D4 attribute naming.
		// Priority 99 ensures this runs AFTER process_conversion_outline() callbacks
		// (registered at default priority 10 during divi_visual_builder_before_d4_conversion)
		// have populated the conversion map with outline data.
		add_filter( 'divi.conversion.moduleLibrary.conversionMap', array( __CLASS__, 'patch_conversion_maps' ), 99 );

		// Register convertFont in the global value expansion function map so it can
		// be referenced by string name from our patched conversion maps.
		add_filter( 'divi.moduleLibrary.conversion.valueExpansionFunctionMap', array( __CLASS__, 'register_value_expansion_functions' ), 99 );

		// Hook into VB content loading to re-convert shortcode-module blocks.
		// Divi's has_shortcode() only checks for [et_pb_*] patterns, so content
		// already saved with divi/shortcode-module wrappers around [et_wfocu_*]
		// shortcodes never triggers maybeConvertContent(). This filter catches that case.
		add_filter( 'et_fb_load_raw_post_content', array( __CLASS__, 'maybe_convert_shortcode_modules' ), 10, 2 );

		// Auto-setup offer product data for VB server-side rendering.
		// In D4, the page load flow populated template_loader->product_data before
		// module render. In D5, render_callbacks fire via SSR without that setup,
		// so default_product_key('0') can't auto-select the first product.
		add_action( 'wp', array( __CLASS__, 'maybe_setup_offer_for_vb' ) );
	}

	/**
	 * Setup offer product data when the Visual Builder loads an offer page.
	 *
	 * Hooked on `wp` as a best-effort early setup. The real safety net is
	 * ensure_product_data() called from each module's render path, which
	 * also handles REST API / SSR contexts where `wp` doesn't fire.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_setup_offer_for_vb(): void {
		self::ensure_product_data();
	}

	/**
	 * Ensure offer product data is loaded before any render_callback needs it.
	 *
	 * Handles the REST API / SSR context (used by D5 VB for server-side
	 * re-rendering after attribute changes) where the `wp` action doesn't fire.
	 * No-op when product data is already loaded.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ensure_product_data(): void {
		if ( ! class_exists( 'WFOCU_Core' ) ) {
			return;
		}

		$loader = WFOCU_Core()->template_loader;
		if ( ! $loader ) {
			return;
		}

		// Already loaded — nothing to do.
		if ( isset( $loader->product_data->products ) && ! empty( (array) $loader->product_data->products ) ) {
			return;
		}

		$offer_id = self::resolve_offer_id( $loader );
		if ( empty( $offer_id ) ) {
			return;
		}

		$loader->offer_id = $offer_id;
		if ( method_exists( $loader, 'setup_complete_offer_setup_manual' ) ) {
			$loader->setup_complete_offer_setup_manual( $offer_id );
		}
	}

	/**
	 * Resolve the current offer ID from available context.
	 *
	 * Checks multiple sources: global $post, REST request postId parameter,
	 * HTTP referer, and the template_loader itself.
	 *
	 * @since 1.0.0
	 * @param object $loader The WFOCU template loader instance.
	 * @return int Offer post ID, or 0 if not found.
	 */
	private static function resolve_offer_id( $loader ): int {
		// Collect candidate IDs from multiple sources (following the same
		// pattern used by the checkout module's RestApiTrait).
		$candidates = array();

		// 1. Global $post.
		global $post;
		if ( $post ) {
			$candidates[] = $post->ID;
		}

		// 2. Request parameters (various naming conventions used by VB / REST).
		foreach ( array( 'postId', 'post_id', 'et_post_id' ) as $param ) {
			if ( ! empty( $_REQUEST[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				$candidates[] = absint( wp_unslash( $_REQUEST[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}

		// 3. HTTP_REFERER — parse common ID query params from the VB page URL.
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Match ?post=, ?post_id=, ?et_post_id=, or ?p= (standard WP permalink param).
			if ( preg_match( '/[?&](?:post|post_id|et_post_id|p)=(\d+)/', $referer, $m ) ) {
				$candidates[] = absint( $m[1] );
			}

			// Pretty-permalink fallback: resolve the URL path to a post ID.
			// Works because wfocu_offer is publicly_queryable with rewrite rules.
			if ( function_exists( 'url_to_postid' ) ) {
				$base_url = strtok( $referer, '?' );
				if ( $base_url ) {
					$resolved = url_to_postid( $base_url );
					if ( $resolved ) {
						$candidates[] = $resolved;
					}
				}
			}
		}

		// Evaluate candidates — first one that is a valid wfocu_offer wins.
		foreach ( $candidates as $candidate ) {
			$pid = absint( $candidate );
			if ( $pid && 'wfocu_offer' === get_post_type( $pid ) ) {
				return $pid;
			}
		}

		// 4. Fallback — template_loader might already know the offer ID.
		if ( method_exists( $loader, 'get_offer_id' ) ) {
			$fallback = absint( $loader->get_offer_id() );
			if ( $fallback ) {
				return $fallback;
			}
		}

		return 0;
	}

	/**
	 * Patch module conversion maps with non-standard D4 attribute mappings.
	 *
	 * WFOCU D4 modules use naming conventions that differ from Divi's standard:
	 * - Font family: `_typograhy` (typo) instead of `_font`
	 * - Box shadow: `{key}_shadow_{prop}` instead of `box_shadow_{prop}_{key}`
	 *
	 * Without these patches, Divi's converter sees them as unknownAttributes
	 * and demotes the module back to divi/shortcode-module.
	 *
	 * @since 1.0.0
	 * @param array $conversion_map The module library conversion map.
	 * @return array Patched conversion map.
	 */
	public static function patch_conversion_maps( $conversion_map ) {
		foreach ( self::$nonstandard_attr_maps as $module_name => $patches ) {
			if ( ! isset( $conversion_map[ $module_name ] ) ) {
				continue;
			}

			if ( ! empty( $patches['attributeMap'] ) ) {
				// Auto-expand per-side border widths and per-corner radii from base mappings.
				// D4 uses {key}_border_width_top etc., which we derive from the base _width mapping.
				$expanded = self::expand_border_side_attrs( $patches['attributeMap'] );

				$conversion_map[ $module_name ]['attributeMap'] = array_merge(
					$conversion_map[ $module_name ]['attributeMap'] ?? array(),
					$expanded
				);

				// Auto-add convertBorderWidth for expanded border width/radius attrs.
				// D4 stores these as unitless numbers; D5 requires units (e.g., "3" → "3px").
				$auto_vef = array();
				foreach ( $expanded as $d4_attr => $d5_path ) {
					if ( false !== strpos( $d5_path, '.width' ) || false !== strpos( $d5_path, '.radius' ) ) {
						$auto_vef[ $d4_attr ] = 'convertBorderWidth';
					}
				}
				if ( ! empty( $auto_vef ) ) {
					$conversion_map[ $module_name ]['valueExpansionFunctionMap'] = array_merge(
						$conversion_map[ $module_name ]['valueExpansionFunctionMap'] ?? array(),
						$auto_vef
					);
				}
			}

			if ( ! empty( $patches['valueExpansionFunctionMap'] ) ) {
				$conversion_map[ $module_name ]['valueExpansionFunctionMap'] = array_merge(
					$conversion_map[ $module_name ]['valueExpansionFunctionMap'] ?? array(),
					$patches['valueExpansionFunctionMap']
				);
			}
		}

		return $conversion_map;
	}

	/**
	 * Expand border attribute maps to include per-side widths and per-corner radii.
	 *
	 * From a base width mapping like:
	 *   {key}_border_width → *.styles.all.width
	 * Generates:
	 *   {key}_border_width_top    → *.styles.top.width
	 *   {key}_border_width_right  → *.styles.right.width
	 *   {key}_border_width_bottom → *.styles.bottom.width
	 *   {key}_border_width_left   → *.styles.left.width
	 *
	 * From a base radius mapping like:
	 *   {key}_border_radius → *.radius
	 * Generates:
	 *   {key}_border_radius_top_left     → *.radius.topLeft
	 *   {key}_border_radius_top_right    → *.radius.topRight
	 *   {key}_border_radius_bottom_left  → *.radius.bottomLeft
	 *   {key}_border_radius_bottom_right → *.radius.bottomRight
	 *
	 * @since 1.0.0
	 * @param array $attr_map The base attribute map.
	 * @return array Expanded attribute map.
	 */
	private static function expand_border_side_attrs( $attr_map ) {
		$extra = array();

		foreach ( $attr_map as $d4_attr => $d5_path ) {
			// Per-side border widths: *.styles.all.width → *.styles.{side}.width
			if ( substr( $d5_path, -strlen( '.styles.all.width' ) ) === '.styles.all.width' ) {
				$base = substr( $d5_path, 0, -strlen( 'all.width' ) );
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					$extra[ $d4_attr . '_' . $side ] = $base . $side . '.width';
				}
			}

			// Per-corner border radii: *.radius → *.radius.{corner}
			if ( substr( $d5_path, -strlen( '.radius' ) ) === '.radius' ) {
				// Standard Divi naming: {key}_top_left, {key}_top_right, etc.
				$corners = array(
					'top_left'     => 'topLeft',
					'top_right'    => 'topRight',
					'bottom_left'  => 'bottomLeft',
					'bottom_right' => 'bottomRight',
				);
				foreach ( $corners as $d4_corner => $d5_corner ) {
					$extra[ $d4_attr . '_' . $d4_corner ] = $d5_path . '.' . $d5_corner;
				}

				// WFOCU custom naming: add_border() uses single-word corners
				// {key}_top = topLeft, {key}_right = topRight,
				// {key}_bottom = bottomLeft, {key}_left = bottomRight.
				$wfocu_corners = array(
					'top'    => 'topLeft',
					'right'  => 'topRight',
					'bottom' => 'bottomLeft',
					'left'   => 'bottomRight',
				);
				foreach ( $wfocu_corners as $d4_corner => $d5_corner ) {
					$extra[ $d4_attr . '_' . $d4_corner ] = $d5_path . '.' . $d5_corner;
				}
			}
		}

		return array_merge( $attr_map, $extra );
	}

	/**
	 * Register convertFont in the global value expansion function map.
	 *
	 * The `_typograhy` attributes store font values in D4's pipe-separated format
	 * (e.g., "Open Sans|700|||||||") and need the same convertFont function that
	 * Divi uses for standard `_font` attributes.
	 *
	 * @since 1.0.0
	 * @param array $function_map The value expansion function map.
	 * @return array Updated function map.
	 */
	public static function register_value_expansion_functions( $function_map ) {
		if ( ! isset( $function_map['convertFont'] ) ) {
			$function_map['convertFont'] = 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertFont';
		}

		return $function_map;
	}

	/**
	 * Re-convert divi/shortcode-module blocks containing WFOCU shortcodes.
	 *
	 * When a D4 page was first opened in Divi 5 VB without conversion outlines,
	 * WFOCU modules were wrapped in divi/shortcode-module blocks. Now that
	 * conversion outlines exist, this filter triggers re-conversion so those
	 * blocks become native D5 blocks.
	 *
	 * @since 1.0.0
	 * @param string $content  Post content.
	 * @param int    $post_id  Post ID.
	 * @return string Possibly converted content.
	 */
	public static function maybe_convert_shortcode_modules( $content, $post_id ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// Only act on content that has shortcode-module blocks with our shortcodes
		if ( false === strpos( $content, 'divi/shortcode-module' ) ) {
			return $content;
		}

		if ( false === strpos( $content, 'et_wfocu_' ) ) {
			return $content;
		}

		// Ensure Conversion class is available
		if ( ! class_exists( 'ET\Builder\Packages\Conversion\Conversion' ) ) {
			return $content;
		}

		// Initialize shortcode framework and fire the conversion hook
		// so that conversion outlines are loaded before we convert
		\ET\Builder\Packages\Conversion\Conversion::initialize_shortcode_framework();
		do_action( 'divi_visual_builder_before_d4_conversion' );

		$result = \ET\Builder\Packages\Conversion\Conversion::maybeConvertContent( $content, false );

		return $result;
	}

	/**
	 * Get all registered modules.
	 *
	 * @since 1.0.0
	 * @return array Module configuration array.
	 */
	public static function get_modules(): array {
		return self::$modules;
	}

	/**
	 * Add a module to the registry.
	 *
	 * @since 1.0.0
	 * @param string $module_name Module name (key).
	 * @param array  $config      Module configuration.
	 * @return void
	 */
	public static function add_module( string $module_name, array $config ): void {
		self::$modules[ $module_name ] = $config;
	}
}
