<?php
/**
 * CheckoutForm::render_callback()
 *
 * @package WFACP\Modules\CheckoutForm
 * @since 1.0.0
 */

namespace WFACP\Modules\CheckoutForm\CheckoutFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFACP\Modules\CheckoutForm\CheckoutForm;

trait RenderCallbackTrait {

	/**
	 * Checkout Form module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * CRITICAL: This must produce EXACT same HTML structure as Divi 4:
	 * <div class='wfacp_form_divi_container'>
	 *     <div class='wfacp_divi_forms' id='wfacp-e-form'>
	 *         <!-- Checkout form HTML -->
	 *     </div>
	 * </div>
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Checkout Form module.
	 */
	public static function render_callback( array $attrs, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {
			// Bail early when WC cart is not available (e.g. Gutenberg REST preload,
			// admin context). Checkout templates require WC()->cart to render.
			if ( is_null( WC()->cart ) ) {
				return '';
			}

			// Ensure attrs is an array
			if ( ! is_array( $attrs ) ) {
				$attrs = array();
			}

			// Ensure block and parsed_block exist
			if ( ! isset( $block->parsed_block ) || ! isset( $block->block_type ) ) {
				throw new \Exception( 'Invalid block data: missing parsed_block or block_type' );
			}

			// CRITICAL STEP 1: Merge defaults from module.json BEFORE processing attributes
			// This ensures empty values get filled with defaults from module.json
			$default_attrs = array();
			if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
				try {
					$default_attrs = ModuleRegistration::get_default_attrs( 'wfacp/checkout-form' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			// CRITICAL: For nested object attributes like dynamic_field_classes, we need to ensure
			// that saved values in $attrs take precedence over defaults
			if ( ! empty( $default_attrs ) ) {
				// CRITICAL: Use array_replace_recursive to preserve nested structures like dynamic_field_classes
				// This ensures that nested object attributes are properly merged
				// IMPORTANT: array_replace_recursive merges arrays, but for nested objects, we want
				// $attrs values to override defaults, so we merge defaults first, then attrs
				$attrs = array_replace_recursive( $default_attrs, $attrs );

				// CRITICAL: For dynamic_field_classes, ensure that if attrs has any values, they override defaults completely
				// This prevents empty defaults from overwriting saved values
				if ( isset( $attrs['dynamic_field_classes'] ) && is_array( $attrs['dynamic_field_classes'] ) ) {
					// Check if we had values before merge
					$had_values_before_merge = false;
					foreach ( $attrs['dynamic_field_classes'] as $section_key => $section_fields ) {
						if ( is_array( $section_fields ) && ! empty( $section_fields ) ) {
							foreach ( $section_fields as $field_key => $field_data ) {
								if ( isset( $field_data['desktop']['value'] ) && ! empty( $field_data['desktop']['value'] ) ) {
									$had_values_before_merge = true;
									break 2;
								}
							}
						}
					}
				}
			}

			// CRITICAL STEP 2: Get template instance
			// Use global namespace for wfacp_template() function
			$template = \wfacp_template();
			if ( is_null( $template ) ) {
				return ''; // Return empty if no template
			}

			// Note: We'll set form_data later, so template instance will have the data
			// The Divi 5 override in payment_sub_heading() will handle empty subheading correctly

			// CRITICAL STEP 3: Get widget ID (same as Divi 4)
			// In Divi 4: $this->get_id() returns widget ID
			// In Divi 5: Use block ID from parsed_block, generate UUID if needed
			$module_id = $block->parsed_block['id'] ?? '';

			// Generate unique widget ID if not present (same pattern as Divi 4)
			if ( empty( $module_id ) ) {
				// Generate UUID v4 for widget ID (same as Divi 4 pattern)
				$module_id = sprintf(
					'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
					wp_rand( 0, 0xffff ),
					wp_rand( 0, 0xffff ),
					wp_rand( 0, 0xffff ),
					wp_rand( 0, 0x0fff ) | 0x4000,
					wp_rand( 0, 0x3fff ) | 0x8000,
					wp_rand( 0, 0xffff ),
					wp_rand( 0, 0xffff ),
					wp_rand( 0, 0xffff )
				);
			}
			$widget_id = $module_id;

			// CRITICAL STEP 4: Ensure WooCommerce session is initialized BEFORE any session operations
			// This is critical for Divi 5 REST API context where session might not be initialized
			if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
				if ( ! WC()->session ) {
					WC()->initialize_session();
				}
				// Force session to save data immediately
				if ( method_exists( WC()->session, 'save_data' ) ) {
					WC()->session->save_data();
				}
			}

			// CRITICAL STEP 5: Set exchange_keys BEFORE storing form_data in session
			// This ensures get_ajax_exchange_keys() (called via hook) loads from the correct session key
			if ( class_exists( '\WFACP_Common' ) && property_exists( '\WFACP_Common', 'exchange_keys' ) ) {
				\WFACP_Common::$exchange_keys['divi']['wfacp_form'] = $widget_id;
			}

			// CRITICAL STEP 6: Map Divi 5 attributes to form_data structure (matching Divi 4)
			// Store attributes in session (same as Divi 4 setup_data)
			// The template reads settings from session using WFACP_Common::get_session( $widget_id )
			$form_data = array();

			// Map all attributes to form_data (matching Divi 4 structure)
			// Progress bar settings
			// Progress bar toggle — viewport-wrapped in D5 but NOT device-specific (matches Elementor)
			// Extract desktop value as plain string for D4 template compatibility
			// D4 template uses enable_progress_bar + _tablet/_phone to add wfacp_desktop/wfacp_tablet/wfacp_mobile CSS classes
			// Since this is non-responsive, apply desktop value to all devices
			$form_data['enable_progress_bar']        = $attrs['enable_progress_bar']['desktop']['value'] ?? 'off';
			$form_data['enable_progress_bar_tablet'] = $form_data['enable_progress_bar'];
			$form_data['enable_progress_bar_phone']  = $form_data['enable_progress_bar'];

			// Select type for progress bar (tab, bredcrumb, progress_bar)
			if ( isset( $attrs['select_type']['desktop']['value'] ) ) {
				$form_data['select_type'] = $attrs['select_type']['desktop']['value'];
			}

			// Step attributes (heading, subheading, bredcrumb, progress_bar) for each step (0, 1, 2)
			// CRITICAL: All step attributes use innerContent.desktop.value for text fields
			for ( $i = 0; $i < 3; $i++ ) {
				// Step headings and subheadings (for tab type)
				if ( isset( $attrs[ 'step_' . $i . '_heading' ]['innerContent']['desktop']['value'] ) ) {
					$form_data[ 'step_' . $i . '_heading' ] = $attrs[ 'step_' . $i . '_heading' ]['innerContent']['desktop']['value'];
				}
				if ( isset( $attrs[ 'step_' . $i . '_subheading' ]['innerContent']['desktop']['value'] ) ) {
					$form_data[ 'step_' . $i . '_subheading' ] = $attrs[ 'step_' . $i . '_subheading' ]['innerContent']['desktop']['value'];
				}

				// Step bredcrumb (for bredcrumb type)
				if ( isset( $attrs[ 'step_' . $i . '_bredcrumb' ]['innerContent']['desktop']['value'] ) ) {
					$form_data[ 'step_' . $i . '_bredcrumb' ] = $attrs[ 'step_' . $i . '_bredcrumb' ]['innerContent']['desktop']['value'];
				}

				// Step progress_bar (for progress_bar type)
				if ( isset( $attrs[ 'step_' . $i . '_progress_bar' ]['innerContent']['desktop']['value'] ) ) {
					$form_data[ 'step_' . $i . '_progress_bar' ] = $attrs[ 'step_' . $i . '_progress_bar' ]['innerContent']['desktop']['value'];
				}
			}

			// Cart link settings
			if ( isset( $attrs['step_cart_link_enable']['desktop']['value'] ) ) {
				$form_data['step_cart_link_enable'] = $attrs['step_cart_link_enable']['desktop']['value'];
			}

			// Cart link text for different types (uses innerContent for text fields)
			if ( isset( $attrs['step_cart_progress_bar_link']['innerContent']['desktop']['value'] ) ) {
				$form_data['step_cart_progress_bar_link'] = $attrs['step_cart_progress_bar_link']['innerContent']['desktop']['value'];
			}
			if ( isset( $attrs['step_cart_bredcrumb_link']['innerContent']['desktop']['value'] ) ) {
				$form_data['step_cart_bredcrumb_link'] = $attrs['step_cart_bredcrumb_link']['innerContent']['desktop']['value'];
			}

			// Collapsible order summary settings
			// CRITICAL: Attribute name is snake_case: enable_callapse_order_summary (not camelCase)
			$form_data['enable_callapse_order_summary']        = $attrs['enable_callapse_order_summary']['desktop']['value'] ?? 'off';
			$form_data['enable_callapse_order_summary_tablet'] = $attrs['enable_callapse_order_summary']['tablet']['value'] ?? 'off';
			$form_data['enable_callapse_order_summary_phone']  = $attrs['enable_callapse_order_summary']['phone']['value'] ?? 'off';

			// CRITICAL: All attribute names are snake_case in module.json (not camelCase)
			$form_data['order_summary_enable_product_image_collapsed'] = $attrs['order_summary_enable_product_image_collapsed']['desktop']['value'] ?? 'off';

			$form_data['enable_order_field_collapsed']        = $attrs['enable_order_field_collapsed']['desktop']['value'] ?? 'off';
			$form_data['enable_order_field_collapsed_tablet'] = $attrs['enable_order_field_collapsed']['tablet']['value'] ?? 'off';
			$form_data['enable_order_field_collapsed_phone']  = $attrs['enable_order_field_collapsed']['phone']['value'] ?? 'off';

			// Collapsible titles
			if ( isset( $attrs['cart_collapse_title']['innerContent']['desktop']['value'] ) ) {
				$form_data['cart_collapse_title'] = $attrs['cart_collapse_title']['innerContent']['desktop']['value'];
			}
			if ( isset( $attrs['cart_expanded_title']['innerContent']['desktop']['value'] ) ) {
				$form_data['cart_expanded_title'] = $attrs['cart_expanded_title']['innerContent']['desktop']['value'];
			}

			// Coupon settings
			$form_data['collapse_enable_coupon']             = $attrs['collapse_enable_coupon']['desktop']['value'] ?? 'off';
			$form_data['collapse_enable_coupon_collapsible'] = $attrs['collapse_enable_coupon_collapsible']['desktop']['value'] ?? 'off';
			if ( isset( $attrs['collapse_coupon_button_text']['innerContent']['desktop']['value'] ) ) {
				$form_data['collapse_coupon_button_text'] = $attrs['collapse_coupon_button_text']['innerContent']['desktop']['value'];
			}

			// Quantity switcher and delete item
			$form_data['collapse_order_quantity_switcher'] = $attrs['collapse_order_quantity_switcher']['desktop']['value'] ?? 'off';
			$form_data['collapse_order_delete_item']       = $attrs['collapse_order_delete_item']['desktop']['value'] ?? 'off';

			// Order Summary fields
			$form_data['order_summary_enable_product_image'] = $attrs['order_summary_enable_product_image']['desktop']['value'] ?? 'on';

			// Payment Gateway settings
			// Divi 5: Always set in form_data (even if empty) so Divi 5 override can return empty string instead of defaults
			// This ensures that when fields are blank, they return empty string instead of default text
			$payment_fields = array(
				'wfacp_payment_method_heading_text' => 'wfacp_payment_method_heading_text',
				'wfacp_payment_method_subheading'   => 'wfacp_payment_method_subheading',
			);

			foreach ( $payment_fields as $form_data_key => $attr_key ) {
				if ( isset( $attrs[ $attr_key ]['innerContent']['desktop']['value'] ) ) {
					$form_data[ $form_data_key ] = trim( $attrs[ $attr_key ]['innerContent']['desktop']['value'] );
				} else {
					// Explicitly set empty string so Divi 5 override knows it's blank
					$form_data[ $form_data_key ] = '';
				}
			}

			// Checkout button settings (text, sub text, icons, return links).
			$get_text_value = static function ( array $attrs_map, string $attr_key ) {
				if ( isset( $attrs_map[ $attr_key ]['innerContent']['desktop']['value'] ) ) {
					return trim( (string) $attrs_map[ $attr_key ]['innerContent']['desktop']['value'] );
				}
				return null;
			};
			$get_value      = static function ( array $attrs_map, string $attr_key ) {
				if ( isset( $attrs_map[ $attr_key ]['desktop']['value'] ) ) {
					return (string) $attrs_map[ $attr_key ]['desktop']['value'];
				}
				return null;
			};

			// Determine step count for button text defaults.
			$step_count = method_exists( $template, 'get_step_count' ) ? absint( $template->get_step_count() ) : 1;
			if ( $step_count < 1 || $step_count > 3 ) {
				$step_count = 1;
			}

			for ( $i = 1; $i <= 3; $i++ ) {
				$button_text_attr = 'wfacp_payment_button_' . $i . '_text';
				$button_text      = $get_text_value( $attrs, $button_text_attr );
				if ( null !== $button_text ) {
					$form_data[ $button_text_attr ] = $button_text;
				}

				$sub_text_attr = 'step_' . $i . '_text_after_place_order';
				$sub_text      = $get_text_value( $attrs, $sub_text_attr );
				if ( null !== $sub_text ) {
					$form_data[ $sub_text_attr ] = $sub_text;
				}

				$icon_toggle_attr = 'enable_icon_with_place_order_' . $i;
				$icon_toggle      = $get_value( $attrs, $icon_toggle_attr );
				if ( null !== $icon_toggle ) {
					$form_data[ $icon_toggle_attr ] = $icon_toggle;
				}

				$icon_select_attr = 'icons_with_place_order_list_' . $i;
				$icon_select      = $get_value( $attrs, $icon_select_attr );
				if ( null !== $icon_select ) {
					$form_data[ $icon_select_attr ] = $icon_select;
				} elseif ( 'on' === ( $form_data[ $icon_toggle_attr ] ?? '' ) ) {
					// Default to Lock 1 icon when icon is enabled but no icon selected
					$form_data[ $icon_select_attr ] = 'aero-e901';
				}
			}

			// Align D5 last-step button attr with D4 template key.
			// Check step-count button first (user edits this in VB), fall back to legacy D4 attr.
			$last_button_attr  = 'wfacp_payment_button_' . $step_count . '_text';
			$last_button_value = $get_text_value( $attrs, $last_button_attr );
			if ( null !== $last_button_value ) {
				$form_data['wfacp_payment_place_order_text'] = $last_button_value;
			} else {
				$legacy_place_order_text = $get_text_value( $attrs, 'wfacp_payment_place_order_text' );
				if ( null !== $legacy_place_order_text ) {
					$form_data['wfacp_payment_place_order_text'] = $legacy_place_order_text;
				}
			}

			// Ensure place order text always has a value so the template can
			// append the price when enable_price_in_place_order_button is on.
			if ( empty( $form_data['wfacp_payment_place_order_text'] ) ) {
				$form_data['wfacp_payment_place_order_text'] = __( 'Place order', 'funnel-builder' );
			}

			// Price toggle on place order button (D4 conversion).
			$price_toggle = $get_value( $attrs, 'enable_price_in_place_order_button' );
			if ( null !== $price_toggle ) {
				$form_data['enable_price_in_place_order_button'] = $price_toggle;
			}

			$place_sub_text = $get_text_value( $attrs, 'step_place_order_text_after_place_order' );
			if ( null !== $place_sub_text ) {
				$form_data['step_place_order_text_after_place_order'] = $place_sub_text;
			}

			$text_below_btn = $get_text_value( $attrs, 'text_below_placeorder_btn' );
			if ( null !== $text_below_btn ) {
				$form_data['text_below_placeorder_btn'] = $text_below_btn;
			}

			$place_icon_toggle = $get_value( $attrs, 'enable_icon_with_place_order_place_order' );
			if ( null !== $place_icon_toggle ) {
				$form_data['enable_icon_with_place_order_place_order'] = $place_icon_toggle;
			}

			$place_icon_select = $get_value( $attrs, 'icons_with_place_order_list_place_order' );
			if ( null !== $place_icon_select ) {
				$form_data['icons_with_place_order_list_place_order'] = $place_icon_select;
			} elseif ( 'on' === ( $form_data['enable_icon_with_place_order_place_order'] ?? '' ) ) {
				// Default to Lock 1 icon when icon is enabled but no icon selected
				$form_data['icons_with_place_order_list_place_order'] = 'aero-e901';
			}

			$return_to_cart_text = $get_text_value( $attrs, 'return_to_cart_text' );
			if ( null !== $return_to_cart_text ) {
				$form_data['return_to_cart_text'] = $return_to_cart_text;
			}

			for ( $i = 2; $i <= 3; $i++ ) {
				$back_text_attr = 'payment_button_back_' . $i . '_text';
				$back_text      = $get_text_value( $attrs, $back_text_attr );
				if ( null !== $back_text ) {
					$form_data[ $back_text_attr ] = $back_text;
				}
			}

			// Coupon button text (form coupon field)
			// CRITICAL: Extract from innerContent.desktop.value structure (same as other text fields)
			if ( isset( $attrs['form_coupon_button_text']['innerContent']['desktop']['value'] ) ) {
				$form_data['form_coupon_button_text'] = trim( $attrs['form_coupon_button_text']['innerContent']['desktop']['value'] );
			} else {
				// Set default "Apply" if not set (matching Divi 4 default)
				$form_data['form_coupon_button_text'] = __( 'Apply', 'funnel-builder' );
			}

			// Dynamic field classes (mapped from dynamic_field_classes attribute)
			// Format: dynamic_field_classes[sectionKey][fieldKey] = class_value
			// Maps to form_data: wfacp_{template_slug}_{field_key}_field = class_value
			// Matching Divi 4: $this->add_select( $tab_id, 'wfacp_' . $template_slug . '_' . $field_key . '_field', ... )
			// CRITICAL: Visual Builder preview might send attributes in different formats, so we need to handle both:
			// 1. Nested structure: dynamic_field_classes[sectionKey][fieldKey][desktop][value]
			// 2. Flattened structure: dynamic_field_classes[sectionKey][fieldKey] = value (with desktop wrapper added by defaults)
			if ( isset( $attrs['dynamic_field_classes'] ) && is_array( $attrs['dynamic_field_classes'] ) ) {
				$template_slug = $template->get_template_slug();
				foreach ( $attrs['dynamic_field_classes'] as $section_key => $section_fields ) {
					if ( is_array( $section_fields ) ) {
						foreach ( $section_fields as $field_key => $field_data ) {
							// Handle both nested structure and direct value
							$class_value = null;
							if ( isset( $field_data['desktop']['value'] ) ) {
								$class_value = $field_data['desktop']['value'];
							} elseif ( isset( $field_data['value'] ) ) {
								// Fallback for flattened structure
								$class_value = $field_data['value'];
							} elseif ( is_string( $field_data ) ) {
								// Direct string value (shouldn't happen but handle it)
								$class_value = $field_data;
							}

							if ( ! empty( $class_value ) ) {
								$form_data_key               = 'wfacp_' . $template_slug . '_' . $field_key . '_field';
								$form_data[ $form_data_key ] = $class_value;
							}
						}
					}
				}
			}

			// Dynamic field custom classes (mapped from dynamic_field_classes_custom attribute)
			// Format: dynamic_field_classes_custom[sectionKey][fieldKey].innerContent.desktop.value = custom_class_value
			// Maps to form_data: wfacp_{template_slug}_{field_key}_field_class = custom_class_value
			// Matching Divi 4: $this->add_text( $this->custom_class_tab_id, 'wfacp_' . $template_slug . '_' . $field_key . '_field_class', ... )
			if ( isset( $attrs['dynamic_field_classes_custom'] ) && is_array( $attrs['dynamic_field_classes_custom'] ) ) {
				$template_slug = $template->get_template_slug();
				foreach ( $attrs['dynamic_field_classes_custom'] as $section_key => $section_fields ) {
					if ( is_array( $section_fields ) ) {
						foreach ( $section_fields as $field_key => $field_data ) {
							// Check for innerContent structure (matching Divi 5 text field pattern)
							$custom_class_value = '';
							if ( isset( $field_data['innerContent']['desktop']['value'] ) ) {
								$custom_class_value = $field_data['innerContent']['desktop']['value'];
							} elseif ( isset( $field_data['desktop']['value'] ) ) {
								// Fallback for direct value structure
								$custom_class_value = $field_data['desktop']['value'];
							}

							if ( ! empty( $custom_class_value ) ) {
								$form_data_key               = 'wfacp_' . $template_slug . '_' . $field_key . '_field_class';
								$form_data[ $form_data_key ] = sanitize_text_field( $custom_class_value );
							}
						}
					}
				}
			}

			// Fallback: Read D4-converted individual field width attributes.
			// D4→D5 conversion stores field widths as top-level attrs (e.g. wfacp_divi_1_shipping_address_1_field)
			// but native D5 stores them inside dynamic_field_classes. This reads the D4-converted attrs as fallback.
			$tpl_slug = $template->get_template_slug();
			if ( ! empty( $tpl_slug ) ) {
				$d4_prefix = 'wfacp_' . $tpl_slug . '_';
				$d4_suffix = '_field';
				foreach ( $attrs as $attr_key => $attr_data ) {
					if ( strpos( $attr_key, $d4_prefix ) !== 0 || substr( $attr_key, -6 ) !== $d4_suffix ) {
						continue;
					}
					// Skip _field_class attrs (custom class fields end with _field_class).
					if ( substr( $attr_key, -12 ) === '_field_class' ) {
						continue;
					}
					// Only use if not already set from dynamic_field_classes.
					if ( isset( $form_data[ $attr_key ] ) ) {
						continue;
					}
					$class_value = null;
					if ( isset( $attr_data['desktop']['value'] ) ) {
						$class_value = $attr_data['desktop']['value'];
					} elseif ( is_string( $attr_data ) ) {
						$class_value = $attr_data;
					}
					if ( ! empty( $class_value ) ) {
						$form_data[ $attr_key ] = $class_value;
					}
				}
			}

			// Label position (controls modern-label placeholder behavior and the
			// CSS class on the form wrapper: wfacp-modern-label / wfacp-inside / wfacp-top).
			// D5 module.json doesn't define this attribute, so fall back to postmeta.
			$label_position = $get_value( $attrs, 'wfacp_label_position' );
			if ( empty( $label_position ) ) {
				$wfacp_id = \WFACP_Common::get_id();
				if ( $wfacp_id > 0 ) {
					$label_position = get_post_meta( $wfacp_id, '_wfacp_field_label_position', true );
				}
			}
			if ( ! empty( $label_position ) ) {
				$form_data['wfacp_label_position'] = $label_position;
			}

			// CRITICAL STEP 7: Store form_data in session AFTER all attributes are mapped
			// This ensures the entire form_data array is populated before storing
			if ( class_exists( '\WFACP_Common' ) ) {
				\WFACP_Common::set_session( $widget_id, $form_data );

				// CRITICAL: Also store widget_id in WooCommerce session for AJAX fallback
				// This allows get_ajax_exchange_keys() to find the widget_id during AJAX calls
				if ( function_exists( 'WC' ) && WC()->session ) {
					WC()->session->set( 'wfacp_divi_widget_id', $widget_id );
				}
			}

			// CRITICAL STEP 8: Set form_data on template instance BEFORE calling get_ajax_exchange_keys()
			// This ensures our values are set first, then get_ajax_exchange_keys() will load from session
			// which contains our values, so it will maintain them
			$template->set_form_data( $form_data );

			// CRITICAL: Call get_ajax_exchange_keys() AFTER setting form_data and storing in session
			// This ensures the template loads form_data from session using the correct widget_id
			// The hook wfacp_before_process_checkout_template_loader will also call this, but
			// by storing in session first, it will load our values
			if ( method_exists( $template, 'get_ajax_exchange_keys' ) ) {
				$template->get_ajax_exchange_keys();
			}

			// CRITICAL: Set form_data AGAIN after get_ajax_exchange_keys() to ensure our values persist
			// This is necessary because get_ajax_exchange_keys() might have loaded from session
			// but we want to ensure our current values are set
			$template->set_form_data( $form_data );

			// CRITICAL STEP 9: Ensure exchange_keys persist for the hook
			// The hook wfacp_before_process_checkout_template_loader fires when template is included
			// We need to ensure exchange_keys are set globally so the hook can load from session
			// Store a reference to our widget_id in a way that persists across instances
			if ( class_exists( '\WFACP_Common' ) && property_exists( '\WFACP_Common', 'exchange_keys' ) ) {
				// Double-check exchange_keys are set (they should be from above, but ensure they persist)
				if ( ! isset( \WFACP_Common::$exchange_keys['divi']['wfacp_form'] ) || \WFACP_Common::$exchange_keys['divi']['wfacp_form'] !== $widget_id ) {
					\WFACP_Common::$exchange_keys['divi']['wfacp_form'] = $widget_id;
				}
			}

			// CRITICAL: Also ensure form_data is set on the template instance that will be used during rendering
			// The template file uses $this which refers to the template instance, so we need to ensure
			// that instance has form_data set. However, since wfacp_template() might return different instances,
			// we rely on get_ajax_exchange_keys() loading from session, which we've stored above.

			// CRITICAL STEP 10: Render checkout form HTML
			// Wrap in same container structure as Divi 4
			// Use wfacp_get_form() which returns file path to include (same as Divi 4)
			ob_start();
			?>
			<div class='wfacp_form_divi_container'>
				<div class='wfacp_divi_forms' id='wfacp-e-form'>
					<?php
					// Render the checkout form using same method as Divi 4
					// SECURITY: Validate file path to prevent directory traversal attacks
					if ( method_exists( $template, 'wfacp_get_form' ) ) {
						$form_file = $template->wfacp_get_form();
						// SECURITY: Ensure file path is within allowed directories
						if ( $form_file && is_string( $form_file ) ) {
							// Normalize path and check it's within WordPress/plugin directories
							$form_file     = wp_normalize_path( $form_file );
							$allowed_paths = array(
								wp_normalize_path( WP_CONTENT_DIR ),
								wp_normalize_path( ABSPATH ),
							);
							$is_allowed    = false;
							foreach ( $allowed_paths as $allowed_path ) {
								if ( strpos( $form_file, $allowed_path ) === 0 ) {
									$is_allowed = true;
									break;
								}
							}
							if ( $is_allowed && file_exists( $form_file ) && is_file( $form_file ) ) {
								include $form_file;
							} else {
								echo '<!-- Checkout form file not found or invalid path -->';
							}
						} else {
							echo '<!-- Checkout form file not found -->';
						}
					} else {
						echo '<!-- Template method wfacp_get_form() not available -->';
					}
					?>
				</div>
			</div>
			<?php
			$html = ob_get_clean();

			// CRITICAL STEP 11: Check if we're in REST API context
			// In REST API, we don't need Module::render() - just return the HTML directly
			// Check if we're actually in a REST API request, not just if elements is null
			$is_rest_api = (
				( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
				( isset( $_SERVER['REQUEST_URI'] ) && strpos( wp_unslash( $_SERVER['REQUEST_URI'] ), '/wp-json/' ) !== false ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			);

			// Only skip Module::render() if we're DEFINITELY in REST API
			// On frontend, $elements might be null but we still need Module::render() for styles
			if ( $is_rest_api && $elements === null ) {
				// For REST API, return HTML directly without Module::render()
				// This avoids the need for proper elements object
				return $html;
			}

			// For frontend/VB rendering, use Module::render() for proper styling and order class
			// This ensures the order class is added to the HTML wrapper, matching Mini Cart behavior

			// CRITICAL: Only append $content if it's not empty and doesn't contain duplicate form HTML
			// The $content parameter in WordPress block rendering contains inner block content
			// For CheckoutForm module, $content should be empty, but if it contains HTML,
			// it might duplicate the form/next button HTML that's already in $html
			$children_content = $html;
			if ( ! empty( $content ) && trim( $content ) !== '' ) {
				// Check if content contains form/next button HTML to avoid duplication
				$has_duplicate_html = (
					strpos( $content, 'wfacp-two-step-erap' ) !== false ||
					strpos( $content, 'wfacp-next-btn-wrap' ) !== false ||
					strpos( $content, 'wfacp-e-form' ) !== false ||
					strpos( $content, 'wfacp_divi_forms' ) !== false
				);

				// Only append if it doesn't contain duplicate form HTML
				if ( ! $has_duplicate_html ) {
					$children_content = $html . $content;
				}
			}

			// CRITICAL: Always call Module::render() to generate order class wrapper
			// The order class wrapper is ESSENTIAL for styles to apply correctly
			// The selector {{selector}} in module.json gets replaced with the order class
			$rendered_output = Module::render(
				array(
					// FE only.
					'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
					'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,

					// VB equivalent.
					'attrs'               => $attrs,
					'elements'            => $elements,
					'id'                  => $module_id,
					'name'                => $block->block_type->name ?? 'wfacp/checkout-form',
					'moduleCategory'      => $block->block_type->category ?? 'module',
					'classnamesFunction'  => array( CheckoutForm::class, 'module_classnames' ),
					'stylesComponent'     => array( CheckoutForm::class, 'module_styles' ),
					'scriptDataComponent' => array( CheckoutForm::class, 'module_script_data' ),
					'parentAttrs'         => array(),
					'parentId'            => '',
					'parentName'          => '',
					'children'            => array(
						$children_content,
					),
				)
			);

			// Ensure order class wrapper exists for styles to apply
			// Order class format: wfacp_checkout_form_{orderIndex} (no VB prefixes in HTML)
			// HTML: <div class="wfacp_checkout_form_0"> (clean, no VB prefixes)
			// CSS: .et-db #et-boc .et-l .wfacp_checkout_form_0 (with VB prefixes)
			$order_index = $block->parsed_block['orderIndex'] ?? 0;
			$order_class = 'wfacp_checkout_form_' . $order_index;

			// Check if order class exists in output (without VB prefixes)
			$has_order_class_wrapper = preg_match( '/class=["\']([^"\']*\s+)?' . preg_quote( $order_class, '/' ) . '(\s+[^"\']*)?["\']/', $rendered_output );

			// Ensure clean wrapper exists (Module::render() might add it with VB prefixes for VB)
			if ( ! $has_order_class_wrapper ) {
				$rendered_output = '<div class="' . esc_attr( $order_class ) . '">' . $rendered_output . '</div>';
			} else {
				// Verify existing wrapper is clean (no VB prefixes)
				if ( preg_match( '/class=["\']([^"\']*wfacp_checkout_form_[^"\']*)["\']/', $rendered_output, $matches ) ) {
					$found_order_class = $matches[1];
					// If it has VB prefixes or doesn't match exactly, add clean wrapper
					if ( strpos( $found_order_class, 'et-db' ) !== false || strpos( $found_order_class, $order_class ) === false ) {
						$rendered_output = '<div class="' . esc_attr( $order_class ) . '">' . $rendered_output . '</div>';
					}
				}
			}

			return $rendered_output;

		} catch ( \Exception $e ) {
			// Return error message in HTML for debugging in Visual Builder
			return '<div style="padding: 20px; color: #d63638; border: 1px solid #d63638; background: #fcf0f1;">
				<strong>CheckoutForm Render Error:</strong> ' . esc_html( $e->getMessage() ) . '
				<br/><small>Check PHP error logs for details.</small>
			</div>';
		}
	}
}
