<?php
/**
 * OptinForm::render_callback()
 *
 * @package WFOP\Modules\OptinForm
 * @since 1.0.0
 */

namespace WFOP\Modules\OptinForm\OptinFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOP\Modules\OptinForm\OptinForm;

// Ensure form controller class is available
if ( ! class_exists( 'WFFN_Optin_Form_Controller_Custom_Form' ) ) {
	// Try to load the form controller class
	$form_controller_path = plugin_dir_path( __FILE__ ) . '../../../../../../form-controllers/class-wffn-optin-form-controller-custom-form.php';
	if ( file_exists( $form_controller_path ) ) {
		require_once $form_controller_path;
	}
}

trait RenderCallbackTrait {

	/**
	 * Extract text content from attributes with fallback to default.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attrs Module attributes.
	 * @param string $key   Attribute key.
	 * @param string $default Default value.
	 * @return string Text content.
	 */
	private static function extract_text_content( array $attrs, string $key, string $default = '' ): string {
		$text_content = '';

		// Try to get text from various attribute structures
		if ( isset( $attrs[ $key ]['innerContent']['desktop']['value'] ) ) {
			$text_content = is_string( $attrs[ $key ]['innerContent']['desktop']['value'] )
				? $attrs[ $key ]['innerContent']['desktop']['value']
				: ( $attrs[ $key ]['innerContent']['desktop']['value']['text'] ?? '' );
		} elseif ( isset( $attrs[ $key ]['innerContent'] ) ) {
			$text_content = is_string( $attrs[ $key ]['innerContent'] )
				? $attrs[ $key ]['innerContent']
				: '';
		} elseif ( isset( $attrs[ $key ] ) ) {
			$text_content = is_string( $attrs[ $key ] ) ? $attrs[ $key ] : '';
		}

		// Fallback to default if empty
		if ( empty( $text_content ) ) {
			$text_content = $default;
		}

		return $text_content;
	}

	/**
	 * Extract boolean value from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attrs Module attributes.
	 * @param string $key   Attribute key.
	 * @param bool   $default Default value.
	 * @return bool Boolean value.
	 */
	private static function extract_boolean( array $attrs, string $key, bool $default = true ): bool {
		if ( isset( $attrs[ $key ]['innerContent']['desktop']['value'] ) ) {
			$value = $attrs[ $key ]['innerContent']['desktop']['value'];
			return is_bool( $value ) ? $value : ( $value === 'on' || $value === true );
		} elseif ( isset( $attrs[ $key ] ) ) {
			$value = $attrs[ $key ];
			return is_bool( $value ) ? $value : ( $value === 'on' || $value === true );
		}

		return $default;
	}

	/**
	 * Render callback for Optin Form module.
	 *
	 * @since 1.0.0
	 *
	 * @param array     $block_attributes          Block attributes.
	 * @param string    $content                   Block content.
	 * @param \WP_Block $block                    Block object.
	 * @param object    $elements                 Elements object for styling.
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_callback( array $block_attributes, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {
			// Extract settings from attributes - MATCH DIVI 4 LOGIC EXACTLY
			// Divi 4: $settings = $this->props; (all props copied, then button_border_size set to 0)
			// We need to flatten all Divi 5 attributes into a flat $settings array like Divi 4's $this->props

			$settings = array();

			// Flatten all attributes to match Divi 4's $this->props structure
			// This ensures width fields and all other dynamic fields are available
			foreach ( $block_attributes as $key => $value ) {
				// Skip module attribute (Divi 5 specific, not used in form rendering)
				if ( $key === 'module' ) {
					continue;
				}

				// Extract value from nested Divi 5 structure or use flat value
				// Width fields are stored as simple string attributes: { "wfop_optin_first_name": { "desktop": { "value": "wffn-sm-50" } } }
				if ( is_array( $value ) ) {
					if ( isset( $value['desktop']['value'] ) ) {
						$settings[ $key ] = $value['desktop']['value'];
					} elseif ( isset( $value['innerContent']['desktop']['value'] ) ) {
						$settings[ $key ] = $value['innerContent']['desktop']['value'];
					} elseif ( isset( $value['value'] ) ) {
						$settings[ $key ] = $value['value'];
					} else {
						// Keep as-is if can't extract (might be needed for some fields)
						$settings[ $key ] = $value;
					}
				} else {
					$settings[ $key ] = $value;
				}
			}

			// Match Divi 4: $settings['button_border_size'] = 0; (set after copying props)
			$settings['button_border_size'] = 0;

			// Normalize specific fields to match Divi 4 behavior
			// Extract text content for fields that might have nested structure
			if ( ! isset( $settings['button_text'] ) || empty( $settings['button_text'] ) ) {
				$settings['button_text'] = self::extract_text_content( $block_attributes, 'button_text', __( 'Send Me My Free Guide', 'funnel-builder' ) );
			}
			if ( ! isset( $settings['subtitle'] ) ) {
				$settings['subtitle'] = self::extract_text_content( $block_attributes, 'subtitle', '' );
			}
			if ( ! isset( $settings['button_submitting_text'] ) || empty( $settings['button_submitting_text'] ) ) {
				$settings['button_submitting_text'] = self::extract_text_content( $block_attributes, 'button_submitting_text', __( 'Submitting...', 'funnel-builder' ) );
			}

			// Normalize show_labels - match Divi 4: 'off' === $settings['show_labels'] ? false : true
			// Divi 5 uses "on"/"off" strings for toggle fields
			if ( isset( $settings['show_labels'] ) ) {
				$show_labels_raw = $settings['show_labels'];
				// Handle both Divi 5 format ("on"/"off") and legacy format (boolean/string)
				if ( is_string( $show_labels_raw ) ) {
					$settings['show_labels'] = ( $show_labels_raw === 'off' ) ? false : true;
				} else {
					$settings['show_labels'] = ( $show_labels_raw === false ) ? false : true;
				}
			} else {
				$settings['show_labels'] = true; // Default
			}

			// Normalize input_size - match Divi 4: isset( $settings['input_size'] ) ? $settings['input_size'] : '12px'
			if ( ! isset( $settings['input_size'] ) || empty( $settings['input_size'] ) ) {
				$settings['input_size'] = '12px';
			}

			// Get optin page ID - try multiple sources for VB / REST / frontend contexts.
			$optinPageId = 0;
			if ( function_exists( 'WFOPP_Core' ) && WFOPP_Core()->optin_pages ) {
				$optinPageId = WFOPP_Core()->optin_pages->get_optin_id();

				// Fallback: global $post (set by VB page context).
				if ( $optinPageId <= 0 ) {
					global $post;
					$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();
					if ( $post instanceof \WP_Post && $post->post_type === $post_type ) {
						$optinPageId = $post->ID;
					}
				}

				// Fallback: get_the_ID().
				if ( $optinPageId <= 0 ) {
					$current_id = get_the_ID();
					$post_type  = WFOPP_Core()->optin_pages->get_post_type_slug();
					if ( $current_id > 0 && get_post_type( $current_id ) === $post_type ) {
						$optinPageId = $current_id;
					}
				}

				// Fallback: REQUEST params (AJAX / REST from VB).
				if ( $optinPageId <= 0 ) {
					$post_type    = WFOPP_Core()->optin_pages->get_post_type_slug();
					$request_keys = array( 'post_id', 'et_post_id', 'postId' );
					foreach ( $request_keys as $rk ) {
						if ( ! empty( $_REQUEST[ $rk ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							$rid = absint( $_REQUEST[ $rk ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							if ( $rid > 0 && get_post_type( $rid ) === $post_type ) {
								$optinPageId = $rid;
								break;
							}
						}
					}
				}

				// Fallback: HTTP_REFERER (VB REST calls).
				if ( $optinPageId <= 0 && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
					$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();
					$referer   = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
					if ( preg_match( '#[?&]post=(\d+)#', $referer, $m ) ) {
						$rid = absint( $m[1] );
						if ( $rid > 0 && get_post_type( $rid ) === $post_type ) {
							$optinPageId = $rid;
						}
					}
					if ( $optinPageId <= 0 ) {
						$parsed = wp_parse_url( $referer );
						$path   = isset( $parsed['path'] ) ? $parsed['path'] : '';
						if ( $path ) {
							foreach ( explode( '/', trim( $path, '/' ) ) as $seg ) {
								if ( ! empty( $seg ) ) {
									$found = get_page_by_path( $seg, OBJECT, $post_type );
									if ( $found && isset( $found->ID ) ) {
										$optinPageId = absint( $found->ID );
										break;
									}
								}
							}
						}
					}
				}
			}

			// Get form fields and settings - match Divi 4 exactly
			$optin_fields   = array();
			$optin_settings = array();
			if ( $optinPageId > 0 && function_exists( 'WFOPP_Core' ) ) {
				// Match Divi 4: get_optin_layout() and get_optin_form_integration_option()
				$optin_fields   = WFOPP_Core()->optin_pages->form_builder->get_optin_layout( $optinPageId );
				$optin_settings = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optinPageId );

				// Apply width settings from attributes to form fields - MATCH DIVI 4 EXACTLY
				// Divi 4: foreach ( $optin_fields as $step_slug => $optinFields ) {
				// foreach ( $optinFields as $key => $optin_field ) {
				// $optin_fields[ $step_slug ][ $key ]['width'] = $settings[ $optin_field['InputName'] ];
				// }
				// }
				// Note: Divi 4 directly accesses $settings[ $optin_field['InputName'] ] without checking
				// Since we've already flattened all attributes into $settings above, we can use it exactly like Divi 4
				foreach ( $optin_fields as $step_slug => $optinFields ) {
					foreach ( $optinFields as $key => $optin_field ) {
						$input_name = $optin_field['InputName'] ?? '';
						if ( ! empty( $input_name ) ) {
							// Match Divi 4 exactly: $optin_fields[ $step_slug ][ $key ]['width'] = $settings[ $optin_field['InputName'] ];
							// If width not in settings, PHP will set it to null/empty, which form controller handles with defaults
							$width_value                                 = $settings[ $input_name ] ?? '';
							$optin_fields[ $step_slug ][ $key ]['width'] = $width_value;
						}
					}
				}
			}

			// Build wrapper class - MATCH DIVI 4 EXACTLY
			// Divi 4: $wrapper_class = 'divi-form-fields-wrapper';
			// Divi 4: $show_labels = ( isset( $settings['show_labels'] ) && 'off' === $settings['show_labels'] ) ? false : true;
			// Divi 4: $wrapper_class .= $show_labels ? '' : ' wfop_hide_label';
			$wrapper_class  = 'divi-form-fields-wrapper';
			$show_labels    = $settings['show_labels']; // Already normalized above
			$wrapper_class .= $show_labels ? '' : ' wfop_hide_label';

			// Start output buffering for form content
			ob_start();

			// Output form
			$form_outputted = false;
			if ( function_exists( 'WFOPP_Core' ) ) {
				$custom_form = WFOPP_Core()->form_controllers->get_integration_object( 'form' );

				// Check if class exists (might be in global namespace)
				$form_controller_class = 'WFFN_Optin_Form_Controller_Custom_Form';
				$is_instance           = ( $custom_form instanceof $form_controller_class );

				if ( $is_instance ) {
					// Match Divi 4: Merge with default settings BEFORE calling _output_form
					// Divi 4: $settings = wp_parse_args( $settings, WFOPP_Core()->optin_pages->form_builder->form_customization_settings_default() );
					$default_settings = array();
					if ( method_exists( WFOPP_Core()->optin_pages->form_builder, 'form_customization_settings_default' ) ) {
						$default_settings = WFOPP_Core()->optin_pages->form_builder->form_customization_settings_default();
					}
					$settings = wp_parse_args( $settings, $default_settings );

					// Match Divi 4: Call _output_form with exact same parameters
					// Divi 4: $custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'inline', $settings );
					if ( $optinPageId > 0 && ! empty( $optin_fields ) ) {
						$custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'inline', $settings );
						$form_outputted = true;
					}
				}
			}

			// Fallback: Show message if form couldn't be rendered
			if ( ! $form_outputted ) {
				echo '<div class="wfop-optin-form-error" style="padding: 20px; border: 1px solid #ddd; background: #f9f9f9; color: #666;">';
				echo '<p><strong>' . esc_html__( 'Optin Form', 'funnel-builder' ) . '</strong></p>';
				echo '<p>' . esc_html__( 'Form configuration is required. Please configure your optin form fields.', 'funnel-builder' ) . '</p>';
				echo '</div>';
			}

			// Output reload script if needed - MATCH DIVI 4 EXACTLY
			// Divi 4: if ( did_action( 'wp_ajax_et_wfop_optin_form' ) ) { ... }
			if ( did_action( 'wp_ajax_et_wfop_optin_form' ) ) {
				echo '<script>';
				echo 'jQuery(document).trigger(\'wffn_reload_phone_field\');';
				echo '</script>';
			}

			// Inline styles for properties that D5's Style::add / elements->style() may not
			// output correctly (unitless border-width from D4 conversion, missing background default).
			$inline_css  = '.wffn-custom-optin-from .wffn-optin-input { padding: ' . esc_attr( $settings['input_size'] ) . ' 15px !important; }';
			$inline_css .= ' .wffn-custom-optin-from *, .wffn-custom-optin-from *::before, .wffn-custom-optin-from *::after { box-sizing: border-box; }';

			// Field border: fix unitless width (D4→D5 conversion stores "1" instead of "1px").
			$field_border_width = $block_attributes['wfop_optin_form_field_border']['decoration']['border']['desktop']['value']['styles']['all']['width'] ?? '';
			$field_border_color = $block_attributes['wfop_optin_form_field_border']['decoration']['border']['desktop']['value']['styles']['all']['color'] ?? '';
			if ( '' !== $field_border_width ) {
				$width_val   = is_numeric( $field_border_width ) ? $field_border_width . 'px' : $field_border_width;
				$inline_css .= ' .wffn-custom-optin-from .wffn-optin-input { border-width: ' . esc_attr( $width_val ) . ' !important; border-style: solid !important;';
				if ( '' !== $field_border_color ) {
					$inline_css .= ' border-color: ' . esc_attr( $field_border_color ) . ' !important;';
				}
				$inline_css .= ' }';
			}

			// Field background: apply D4 default (#ffffff) when not saved in block attrs.
			$field_bg = $block_attributes['wfop_optin_form_field_background_color']['decoration']['background']['desktop']['value']['color'] ?? '';
			if ( '' === $field_bg ) {
				$field_bg = '#ffffff';
			}
			$inline_css .= ' .wffn-custom-optin-from .wffn-optin-input { background-color: ' . esc_attr( $field_bg ) . '; }';

			echo '<style>' . $inline_css . '</style>';

			$form_content = ob_get_clean();

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

			// Use Module::render() following Divi 5 pattern (like upsell modules)
			// This ensures styles, classnames, and script data are properly applied
			try {
				return Module::render(
					array(
						// FE only.
						'orderIndex'               => $block->parsed_block['orderIndex'] ?? 0,
						'storeInstance'            => $block->parsed_block['storeInstance'] ?? null,

						// VB equivalent.
						'attrs'                    => $block_attributes,
						'elements'                 => $elements,
						'id'                       => $block->parsed_block['id'] ?? '',
						'name'                     => $block->block_type->name ?? 'wfop/optin-form',
						'moduleCategory'           => $block->block_type->category ?? 'module',
						'classnamesFunction'       => array( OptinForm::class, 'module_classnames' ),
						'stylesComponent'          => array( OptinForm::class, 'module_styles' ),
						'scriptDataComponent'      => array( OptinForm::class, 'module_script_data' ),
						'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
						'parentAttrs'              => $parent_attrs,
						'parentId'                 => $parent_id,
						'parentName'               => $parent_name,
						'children'                 => $form_content,
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $form_content;
			}
		} catch ( \Exception $e ) {
			// Return error message so something is displayed
			return '<div class="wfop-optin-form-error" style="padding: 20px; border: 1px solid #f00; background: #ffe6e6; color: #c00;"><p><strong>Error rendering Optin Form</strong></p><p>' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}
}
