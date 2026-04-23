<?php
/**
 * MiniCart::render_callback()
 *
 * @package WFACP\Modules\MiniCart
 * @since 1.0.0
 */

namespace WFACP\Modules\MiniCart\MiniCartTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFACP\Modules\MiniCart\MiniCart;

trait RenderCallbackTrait {

	/**
	 * Mini Cart module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * CRITICAL: This must produce EXACT same HTML structure as Divi 4:
	 * <div class='wfacp_form_divi_container'>
	 *     <div class='wfacp_divi_forms'>
	 *         <!-- Mini cart widget HTML -->
	 *     </div>
	 * </div>
	 *
	 * @since 1.0.0
	 * @param array          $attrs                       Block attributes that were saved by VB.
	 * @param string         $content                     Block content.
	 * @param \WP_Block      $block                       Parsed block object that being rendered.
	 * @param mixed          $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Mini Cart module.
	 */
	public static function render_callback( array $attrs, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = [] ): string {
		try {
			// Bail early when WC cart is not available (e.g. Gutenberg REST preload,
			// admin context). Mini cart templates require WC()->cart to render.
			if ( is_null( WC()->cart ) ) {
				return '';
			}

			// Ensure attrs is an array
			if ( ! is_array( $attrs ) ) {
				$attrs = [];
			}

			// Ensure block and parsed_block exist
			if ( ! isset( $block->parsed_block ) || ! isset( $block->block_type ) ) {
				throw new \Exception( 'Invalid block data: missing parsed_block or block_type' );
			}

			// CRITICAL STEP 1: Merge defaults from module.json BEFORE processing attributes
			// This ensures empty values get filled with defaults from module.json
			$default_attrs = [];
			if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
				try {
					$default_attrs = ModuleRegistration::get_default_attrs( 'wfacp/mini-cart' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// CRITICAL STEP 2: Get template instance
			// Use global namespace for wfacp_template() function
			$template = \wfacp_template();
			if ( is_null( $template ) ) {
				return ''; // Return empty if no template
			}

			// CRITICAL STEP 3: Get widget ID (same as Divi 4)
			// In Divi 4: $this->get_id() returns 'wfacp_order_summary_widget'
			// In Divi 5: Use block ID from parsed_block, fallback to default
			$module_id = $block->parsed_block['id'] ?? '';

			// If no module_id, use default widget ID (same as Divi 4)
			if ( empty( $module_id ) ) {
				$module_id = 'wfacp_order_summary_widget';
			}
			$widget_id = $module_id;

			// CRITICAL STEP 4: Store attributes in session (same as Divi 4 setup_data)
			// The template reads settings from session using WFACP_Common::get_session( $widget_id )
			// Map Divi 5 attribute names to template property names (matching Divi 4 structure)
			$mini_cart_settings = [];

			// CRITICAL: Always set toggle values from attrs (even if 'off' or default)
			// This ensures the session is updated with current toggle state
			// Map attributes to settings (matching Divi 4 module property names)
			// Always set values from merged attrs (which includes defaults)
			// CRITICAL: Extract raw values and ensure they're strings ('on' or 'off')
			$enable_product_image_raw = $attrs['enableProductImage']['desktop']['value'] ?? 'on';
			$enable_quantity_box_raw = $attrs['enableQuantityBox']['desktop']['value'] ?? 'on';
			$enable_delete_item_raw = $attrs['enableDeleteItem']['desktop']['value'] ?? 'on';
			
			// Normalize to 'on' or 'off' strings (handle boolean true/false, 'yes'/'no', etc.)
			$mini_cart_settings['enable_product_image'] = ( $enable_product_image_raw === 'on' || $enable_product_image_raw === true || $enable_product_image_raw === 'yes' || $enable_product_image_raw === '1' || $enable_product_image_raw === 1 ) ? 'on' : 'off';
			$mini_cart_settings['enable_quantity_box'] = ( $enable_quantity_box_raw === 'on' || $enable_quantity_box_raw === true || $enable_quantity_box_raw === 'yes' || $enable_quantity_box_raw === '1' || $enable_quantity_box_raw === 1 ) ? 'on' : 'off';
			$mini_cart_settings['enable_delete_item'] = ( $enable_delete_item_raw === 'on' || $enable_delete_item_raw === true || $enable_delete_item_raw === 'yes' || $enable_delete_item_raw === '1' || $enable_delete_item_raw === 1 ) ? 'on' : 'off';
			
			$mini_cart_settings['enable_coupon'] = $attrs['enableCoupon']['desktop']['value'] ?? 'off';
			$mini_cart_settings['enable_coupon_collapsible'] = $attrs['enableCouponCollapsible']['desktop']['value'] ?? 'off';
			if ( isset( $attrs['miniCartCouponButtonText']['innerContent']['desktop']['value'] ) ) {
				$mini_cart_settings['mini_cart_coupon_button_text'] = $attrs['miniCartCouponButtonText']['innerContent']['desktop']['value'];
			}
			$mini_cart_settings['mini_cart_enable_strike_through_price'] = $attrs['miniCartEnableStrikeThroughPrice']['desktop']['value'] ?? 'off';
			$mini_cart_settings['mini_cart_enable_low_stock_trigger'] = $attrs['miniCartEnableLowStockTrigger']['desktop']['value'] ?? 'off';
			$low_stock_msg = $attrs['miniCartLowStockMessage']['innerContent']['desktop']['value'] ?? '';
			$mini_cart_settings['mini_cart_low_stock_message'] = '' !== $low_stock_msg ? $low_stock_msg : '{{quantity}} left in stock';

			$mini_cart_settings['mini_cart_enable_saving_price_message'] = $attrs['miniCartEnableSavingPriceMessage']['desktop']['value'] ?? 'off';
			$saving_msg = $attrs['miniCartSavingPriceMessage']['innerContent']['desktop']['value'] ?? '';
			$mini_cart_settings['mini_cart_saving_price_message'] = '' !== $saving_msg ? $saving_msg : 'You saved {{saving_amount}} ({{saving_percentage}}) on this order';

			// Extract heading value from merged attrs (which includes defaults from module.json)
			$heading_value = '';
			if ( isset( $attrs['miniCartHeading']['innerContent']['desktop']['value'] ) ) {
				$heading_value = trim( (string) $attrs['miniCartHeading']['innerContent']['desktop']['value'] );
			}
			// Respect empty value — if user cleared the heading, don't force a default
			$mini_cart_settings['mini_cart_heading'] = $heading_value;

			// Store settings in session (same as Divi 4)
			// CRITICAL: Use global namespace prefix \WFACP_Common (not namespaced)
			// CRITICAL: Always update session, even if settings array is not empty
			// This ensures toggle changes are immediately reflected
			if ( class_exists( '\WFACP_Common' ) ) {
				// Ensure WooCommerce session is initialized and ready
				if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
					if ( ! WC()->session ) {
						WC()->initialize_session();
					}
					// Force session to save data immediately
					if ( method_exists( WC()->session, 'save_data' ) ) {
						WC()->session->save_data();
					}
				}
				
				\WFACP_Common::set_session( $widget_id, $mini_cart_settings );
			}

			// Track widget IDs for this template type (same logic as Divi 4)
			if ( class_exists( '\WFACP_Common' ) ) {
				$key     = 'wfacp_mini_cart_widgets_' . $template->get_template_type();
				$widgets = \WFACP_Common::get_session( $key );
				if ( ! is_array( $widgets ) ) {
					$widgets = [];
				}

				// Add this widget ID to the list if not already present
				if ( ! empty( $widget_id ) && ! in_array( $widget_id, $widgets ) ) {
					$widgets[] = $widget_id;
					\WFACP_Common::set_session( $key, $widgets );
				}
			}

			// CRITICAL STEP 5: Check if we're in Visual Builder and ensure cart has content for preview
			$is_visual_builder = false;

			// Check if Visual Builder is active (multiple methods for compatibility)
			if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
				$is_visual_builder = true;
			} elseif ( defined( 'ET_FB_ENABLED' ) && ET_FB_ENABLED ) {
				$is_visual_builder = true;
			} elseif ( isset( $_GET['et_fb'] ) && $_GET['et_fb'] == '1' ) {
				$is_visual_builder = true;
			}

			// CRITICAL: Ensure WooCommerce is fully initialized
			if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
				// Ensure WooCommerce is loaded
				if ( ! did_action( 'woocommerce_init' ) ) {
					do_action( 'woocommerce_init' );
				}

				// Ensure cart is initialized
				if ( ! WC()->cart ) {
					wc_load_cart();
				}

				// Ensure session is started
				if ( ! WC()->session ) {
					WC()->initialize_session();
				}
			}

			// If in Visual Builder and cart is empty, add demo product for preview
			if ( $is_visual_builder && class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->cart ) {
				// If cart is empty, try to add a demo product for preview
				if ( WC()->cart->is_empty() ) {
					// Try to get any published simple product
					$demo_products = wc_get_products( array(
						'limit'   => 1,
						'status'  => 'publish',
						'type'    => 'simple',
						'orderby' => 'date',
						'order'   => 'DESC',
					) );

					if ( ! empty( $demo_products ) ) {
						$demo_product = reset( $demo_products );
						// Check if product is already in cart to avoid duplicates
						$cart_item_key = WC()->cart->find_product_in_cart( $demo_product->get_id() );
						if ( ! $cart_item_key ) {
							WC()->cart->add_to_cart( $demo_product->get_id(), 1 );
						}
					}
				}
			}

			// CRITICAL: Before rendering, verify session is readable and force refresh template's mini_cart_data
			// This ensures we're reading the latest session values, not cached ones
			if ( class_exists( '\WFACP_Common' ) ) {
				$fresh_session = \WFACP_Common::get_session( $widget_id );

				// Force template to use fresh session data by calling set_mini_cart_data
				$template->set_mini_cart_data( $fresh_session );
			}

			ob_start();
			?>
			<div class='wfacp_form_divi_container'>
				<div class='wfacp_divi_forms'>
					<?php
					// CRITICAL: Call get_mini_cart_widget with widget_id (same as Divi 4)
					// This method reads settings from session and renders the cart HTML
					$template->get_mini_cart_widget( $widget_id );
					?>
				</div>
			</div>
			<?php
			$cart_html = ob_get_clean();

			// CRITICAL STEP 6: Check if we're in REST API context (elements is null)
			// In REST API, we don't need Module::render() - just return the HTML directly
			$is_rest_api = ( $elements === null );

			if ( $is_rest_api ) {
				// For REST API, return HTML directly without Module::render()
				// This avoids the need for proper elements object
				return $cart_html;
			}

			// For frontend/VB rendering, use Module::render() for proper styling
			$rendered_output = Module::render(
				[
					// FE only.
					'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
					'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,

					// VB equivalent.
					'attrs'               => $attrs,
					'elements'            => $elements,
					'id'                  => $module_id,
					'name'                => $block->block_type->name ?? 'wfacp/mini-cart',
					'moduleCategory'      => $block->block_type->category ?? 'module',
					'classnamesFunction'  => [ MiniCart::class, 'module_classnames' ],
					'stylesComponent'     => [ MiniCart::class, 'module_styles' ],
					'scriptDataComponent' => [ MiniCart::class, 'module_script_data' ],
					'parentAttrs'         => [],
					'parentId'            => '',
					'parentName'          => '',
					'children'            => [
						$cart_html . $content,
					],
				]
			);
			return $rendered_output;
		} catch ( \Exception $e ) {
			// Return simple fallback HTML matching Divi 4 structure
			$template = \wfacp_template();
			if ( is_null( $template ) ) {
				return '';
			}

			$fallback_id = $block->parsed_block['id'] ?? 'wfacp_order_summary_widget';

			ob_start();
			?>
			<div class='wfacp_form_divi_container'>
				<div class='wfacp_divi_forms'>
					<?php $template->get_mini_cart_widget( $fallback_id ); ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
