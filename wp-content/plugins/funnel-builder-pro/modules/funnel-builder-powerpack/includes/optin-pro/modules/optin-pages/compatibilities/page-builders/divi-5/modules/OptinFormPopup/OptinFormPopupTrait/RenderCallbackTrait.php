<?php
/**
 * OptinFormPopup::render_callback()
 *
 * @package WFOPP\Modules\OptinFormPopup
 * @since 1.0.0
 */

namespace WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use WFOPP\Modules\OptinFormPopup\OptinFormPopup;

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
	 * Extract icon from attributes - handle Divi 5 icon-picker format.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attrs Module attributes.
	 * @param string $key   Attribute key.
	 * @param string $default Default value.
	 * @return string Icon code or empty string.
	 */
	private static function extract_icon( array $attrs, string $key, string $default = '' ): string {
		// Handle Divi 5 icon-picker format: btn_icon.desktop.value can be string or object {unicode, type, weight}
		if ( isset( $attrs[ $key ]['desktop']['value'] ) ) {
			$icon_value = $attrs[ $key ]['desktop']['value'];

			// If it's already a string, return it
			if ( is_string( $icon_value ) ) {
				return $icon_value;
			}

			// If it's an array/object, try to extract the icon string
			if ( is_array( $icon_value ) ) {
				// Check for icon-picker structure: {unicode, type, weight}
				if ( isset( $icon_value['unicode'] ) && is_string( $icon_value['unicode'] ) ) {
					return $icon_value['unicode'];
				}
				// Check for other common icon picker structures
				if ( isset( $icon_value['value'] ) && is_string( $icon_value['value'] ) ) {
					return $icon_value['value'];
				}
				if ( isset( $icon_value['icon'] ) && is_string( $icon_value['icon'] ) ) {
					return $icon_value['icon'];
				}
				// If it's a simple array with one string value, return it
				if ( count( $icon_value ) === 1 && is_string( reset( $icon_value ) ) ) {
					return reset( $icon_value );
				}
			}
		}

		// Fallback: check if icon is a direct string (Divi 4 format)
		if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
			return $attrs[ $key ];
		}

		return $default;
	}

	/**
	 * Extract icon position from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attrs Module attributes.
	 * @param string $key   Attribute key.
	 * @param string $default Default value.
	 * @return string Icon position ('left' or 'right').
	 */
	private static function extract_icon_position( array $attrs, string $key, string $default = 'left' ): string {
		// Handle Divi 5 structure: btn_icon_position.desktop.value
		if ( isset( $attrs[ $key ]['desktop']['value'] ) ) {
			$value = $attrs[ $key ]['desktop']['value'];
			if ( is_string( $value ) && ( $value === 'left' || $value === 'right' ) ) {
				return $value;
			}
		}

		// Fallback: check if icon_position is a direct string (Divi 4 format)
		if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
			return $attrs[ $key ];
		}

		return $default;
	}

	/**
	 * Render callback for Optin Form Popup module.
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
			$settings = array();

			// Flatten all attributes to match Divi 4's $this->props structure
			foreach ( $block_attributes as $key => $value ) {
				// Skip module attribute (Divi 5 specific, not used in form rendering)
				if ( $key === 'module' ) {
					continue;
				}

				// Extract value from nested Divi 5 structure or use flat value
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

			// Match Divi 4: $settings['button_border_size'] = 0;
			$settings['button_border_size'] = 0;

			// Normalize specific fields to match Divi 4 behavior
			$settings['btn_text']               = self::extract_text_content( $block_attributes, 'btn_text', __( 'Signup Now', 'funnel-builder-powerpack' ) );
			$settings['btn_subheading_text']    = self::extract_text_content( $block_attributes, 'btn_subheading_text', '' );
			$settings['popup_heading']          = self::extract_text_content( $block_attributes, 'popup_heading', __( "You're just one step away!", 'funnel-builder-powerpack' ) );
			$settings['popup_sub_heading']      = self::extract_text_content( $block_attributes, 'popup_sub_heading', __( "Enter your details below and we'll get you signed up", 'funnel-builder-powerpack' ) );
			$settings['button_text']            = self::extract_text_content( $block_attributes, 'button_text', __( 'Send Me My Free Guide', 'funnel-builder-powerpack' ) );
			$settings['subtitle']               = self::extract_text_content( $block_attributes, 'subtitle', '' );
			$settings['button_submitting_text'] = self::extract_text_content( $block_attributes, 'button_submitting_text', __( 'Submitting...', 'funnel-builder-powerpack' ) );
			$settings['popup_footer_text']      = self::extract_text_content( $block_attributes, 'popup_footer_text', __( 'Your Information is 100% Secure', 'funnel-builder-powerpack' ) );
			$settings['popup_bar_text']         = self::extract_text_content( $block_attributes, 'popup_bar_text', __( 'Almost Complete...', 'funnel-builder-powerpack' ) );

			// Extract icon - handle Divi 5 structure: btn_icon_field.desktop.value can be string or object {unicode, type, weight}
			// Support both btn_icon_field (Divi 5) and btn_icon (Divi 4) for backward compatibility
			$settings['btn_icon'] = self::extract_icon( $block_attributes, 'btn_icon_field', '' );
			if ( empty( $settings['btn_icon'] ) ) {
				$settings['btn_icon'] = self::extract_icon( $block_attributes, 'btn_icon', '' );
			}

			// Extract icon position - handle Divi 5 structure: btn_icon_field_position.desktop.value
			// Support both btn_icon_field_position (Divi 5) and btn_icon_position (Divi 4) for backward compatibility
			$settings['btn_icon_position'] = self::extract_icon_position( $block_attributes, 'btn_icon_field_position', 'left' );
			if ( empty( $settings['btn_icon_position'] ) ) {
				$settings['btn_icon_position'] = self::extract_icon_position( $block_attributes, 'btn_icon_position', 'left' );
			}

			// Normalize show_labels - match Divi 4: 'off' === $settings['show_labels'] ? false : true
			if ( isset( $settings['show_labels'] ) ) {
				$show_labels_raw = $settings['show_labels'];
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

			// Normalize popup settings
			$settings['popup_open_animation']        = $settings['popup_open_animation'] ?? 'fade';
			$settings['enable_popup_bar_on_builder'] = $settings['enable_popup_bar_on_builder'] ?? 'off';

			// Normalize progress bar settings
			// D4 uses 'on' to enable; D5 converts to numeric string like '75' (the percentage)
			// Treat any non-empty, non-'off', non-'disabled' value as enabled
			$raw_bar_pp = isset( $settings['popup_bar_pp'] ) ? $settings['popup_bar_pp'] : '';
			// Save numeric bar percentage for CSS before normalizing
			$bar_fill_pct                            = is_numeric( $raw_bar_pp ) ? intval( $raw_bar_pp ) : ( is_numeric( $settings['popup_bar_width'] ?? '' ) ? intval( $settings['popup_bar_width'] ) : 75 );
			$settings['popup_bar_pp']                = ( ! empty( $raw_bar_pp ) && $raw_bar_pp !== 'off' && $raw_bar_pp !== 'disabled' ) ? 'enable' : 'disabled';
			$settings['popup_bar_text_wrap_classes'] = ( isset( $settings['popup_bar_text_position'] ) && $settings['popup_bar_text_position'] !== 'off' && ! empty( $settings['popup_bar_text_position'] ) ) ? 'on' : 'off';
			$settings['popup_bar_wrap_classes']      = ( isset( $settings['popup_bar_text_position'] ) && $settings['popup_bar_text_position'] !== 'off' && ! empty( $settings['popup_bar_text_position'] ) ) ? 'on' : 'off';
			$settings['popup_bar_animation']         = ( isset( $settings['popup_bar_animation'] ) && $settings['popup_bar_animation'] !== 'off' && ! empty( $settings['popup_bar_animation'] ) ) ? 'yes' : 'no';
			$settings['popup_bar_width']             = isset( $settings['popup_bar_width'] ) && isset( $settings['popup_bar_width']['size'] ) ? $settings['popup_bar_width']['size'] : '75';
			$settings['field_size']                  = isset( $settings['input_size'] ) ? $settings['input_size'] : '12px';

			// Get optin page ID - match Divi 4: WFOPP_Core()->optin_pages->get_optin_id()
			$optinPageId = 0;
			if ( function_exists( 'WFOPP_Core' ) ) {
				$optinPageId = WFOPP_Core()->optin_pages->get_optin_id();
			}

			// Get form fields and settings - match Divi 4 exactly
			$optin_fields   = array();
			$optin_settings = array();
			if ( $optinPageId > 0 && function_exists( 'WFOPP_Core' ) ) {
				$optin_fields   = WFOPP_Core()->optin_pages->form_builder->get_optin_layout( $optinPageId );
				$optin_settings = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optinPageId );

				// Apply width settings from attributes to form fields - MATCH DIVI 4 EXACTLY
				foreach ( $optin_fields as $step_slug => $optinFields ) {
					foreach ( $optinFields as $key => $optin_field ) {
						$input_name = $optin_field['InputName'] ?? '';
						if ( ! empty( $input_name ) ) {
							$width_value                                 = $settings[ $input_name ] ?? '';
							$optin_fields[ $step_slug ][ $key ]['width'] = $width_value;
						}
					}
				}
			}

			// Build wrapper class - MATCH DIVI 4 EXACTLY
			$wrapper_class  = 'elementor-form-fields-wrapper';
			$show_labels    = $settings['show_labels']; // Already normalized above
			$wrapper_class .= $show_labels ? '' : ' wfop_hide_label';

			// Build button args for CTA button
			$button_args = array(
				'title'              => $settings['btn_text'],
				'subtitle'           => $settings['btn_subheading_text'],
				'icon_class'         => $settings['btn_icon'] ?? '',
				'type'               => 'anchor',
				'link'               => '#',
				'icon_position'      => $settings['btn_icon_position'] ?? 'left',
				'divi_icon_position' => $settings['btn_icon_position'] ?? 'left',
				'wrapper_class'      => 'wfop_popup_form',
				'icon_html'          => '',
				'show_icon'          => ( isset( $settings['btn_icon'] ) ) && ! empty( $settings['btn_icon'] ),
			);

			// Process icon HTML if icon is set
			if ( ! empty( $settings['btn_icon'] ) && function_exists( 'et_pb_process_font_icon' ) ) {
				$icon_html                = html_entity_decode( et_pb_process_font_icon( $settings['btn_icon'] ) );
				$btn_icon_position        = $settings['btn_icon_position'] ?? 'left';
				$button_args['icon_html'] = "<span class='wfocu-button-icon et-pb-icon $btn_icon_position'>" . $icon_html . '</span>';
			}

			// Determine popup show class
			$popup_open = ( isset( $settings['popup_open'] ) && $settings['popup_open'] === 'yes' ) ? 'show_popup_form' : '';
			$show_class = '';
			if ( ( isset( $settings['enable_popup_bar_on_builder'] ) && $settings['enable_popup_bar_on_builder'] === 'on' ) && wp_doing_ajax() ) {
				$show_class = 'show_popup_form';
			}

			// Start output buffering for popup content
			ob_start();

			// Output popup HTML
			$popup_outputted = false;
			if ( function_exists( 'WFOPP_Core' ) ) {
				$custom_form = WFOPP_Core()->form_controllers->get_integration_object( 'form' );

				// Check if class exists
				$form_controller_class = 'WFFN_Optin_Form_Controller_Custom_Form';
				$is_instance           = ( $custom_form instanceof $form_controller_class );

				if ( $is_instance ) {
					// Merge with default settings BEFORE calling _output_form
					$default_settings = array();
					if ( method_exists( WFOPP_Core()->optin_pages->form_builder, 'form_customization_settings_default' ) ) {
						$default_settings = WFOPP_Core()->optin_pages->form_builder->form_customization_settings_default();
					}
					$settings = wp_parse_args( $settings, $default_settings );

					// Output popup wrapper
					echo '<div class="wfop_popup_wrapper wfop_pb_widget_wrap">';

					// Output CTA button - match frontend structure exactly
					echo '<div class="bwf-custom-button">';

					// Generate button HTML to match frontend structure exactly
					$btn_text            = $settings['btn_text'] ?? '';
					$btn_subheading      = $settings['btn_subheading_text'] ?? '';
					$icon_position_class = ! empty( $settings['btn_icon_position'] ) ? $settings['btn_icon_position'] : 'left';

					// Process icon if it exists
					$icon_html = '';
					if ( ! empty( $settings['btn_icon'] ) && function_exists( 'et_pb_process_font_icon' ) ) {
						$icon_html = html_entity_decode( et_pb_process_font_icon( $settings['btn_icon'] ) );
					}
					?>
					<div class="wfop_popup_form" id="bwf-custom-button-wrap">
						<a href="#">
							<span class="bwf-text-wrapper">
								<?php if ( 'left' === $icon_position_class && ! empty( $icon_html ) ) { ?>
									<span class="wfocu-button-icon et-pb-icon <?php echo esc_attr( $icon_position_class ); ?>"><?php echo esc_html( $icon_html ); ?></span>
								<?php } ?>
								<?php if ( ! empty( $btn_text ) ) { ?>
									<span class="bwf_heading"><?php echo esc_html( $btn_text ); ?></span>
								<?php } ?>
								<?php if ( 'right' === $icon_position_class && ! empty( $icon_html ) ) { ?>
									<span class="wfocu-button-icon et-pb-icon <?php echo esc_attr( $icon_position_class ); ?>"><?php echo esc_html( $icon_html ); ?></span>
								<?php } ?>
							</span>
							<?php if ( ! empty( $btn_subheading ) ) { ?>
								<span class="bwf_subheading"><?php echo esc_html( $btn_subheading ); ?></span>
							<?php } ?>
						</a>
					</div>
					<?php
					echo '</div>';

					// Output popup overlay
					$popup_animation = esc_attr( $settings['popup_open_animation'] );
					echo '<div class="bwf_pp_overlay ' . esc_attr( $show_class ) . ' bwf_pp_effect_' . esc_attr( $popup_animation ) . ' ' . esc_attr( $popup_open ) . '">';
					echo '<div class="bwf_pp_wrap" style="background: #ffffff!important;">';
					echo '<a class="bwf_pp_close" href="javascript:void(0);">&times;</a>';
					echo '<div class="bwf_pp_cont">';

					// Output form - MATCH DIVI 4: 'popover' type instead of 'inline'
					if ( $optinPageId > 0 && ! empty( $optin_fields ) ) {
						$custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'popover', $settings );
						$popup_outputted = true;
					}

					echo '</div>'; // .bwf_pp_cont
					echo '</div>'; // .bwf_pp_wrap
					echo '</div>'; // .bwf_pp_overlay
					echo '</div>'; // .wfop_popup_wrapper

					// Output reload scripts
					echo '<script>';
					echo 'jQuery(document).trigger(\'wffn_reload_popups\');';
					echo 'jQuery(document).trigger(\'wffn_reload_phone_field\');';
					echo '</script>';
				}
			}

			// Fallback: Show message if popup couldn't be rendered
			if ( ! $popup_outputted ) {
				echo '<div class="wfop-popup-form-error" style="padding: 20px; border: 1px solid #ddd; background: #f9f9f9; color: #666;">';
				echo '<p><strong>' . esc_html__( 'Optin Form Popup', 'funnel-builder-powerpack' ) . '</strong></p>';
				echo '<p>' . esc_html__( 'Popup configuration is required. Please configure your optin form fields.', 'funnel-builder-powerpack' ) . '</p>';
				echo '</div>';
			}

			// Output input size style - MATCH DIVI 4 EXACTLY
			$input_size = absint( $settings['input_size'] );
			echo '<style>';
			if ( wp_doing_ajax() ) {
				echo '.wfop_popup_form a { pointer-events: none !important; }';
			}
			echo '.et-db #et-boc .et-l .et_pb_module .wffn-custom-optin-from .wffn-optin-input { padding: ' . $input_size . 'px 15px; }';
			echo 'body.et-db #et-boc .et-l #et_wfop_optin_form_popup .bwf-custom-button .et-pb-icon { font-family: ETmodules !important; text-decoration: none !important; }';
			// D5 base CSS: override Divi theme reset (#et-boc .et-l input { border: 0 }) with !important
			echo '.bwfac_form_sec .wffn-optin-input { border: 2px solid #d8d8d8 !important; }';
			echo '.bwfac_form_sec.submit_button { text-align: center; }';
			echo '#wffn_custom_optin_submit { display: inline-block; width: 100%; cursor: pointer; text-align: center; border-radius: 0 !important; border-width: 2px; border-style: solid; }';
			echo '.bwf_pp_close { position: absolute; top: 0; right: 0; display: flex !important; align-items: center; justify-content: center; width: 25px; height: 25px; border-radius: 50%; text-decoration: none !important; border: none; z-index: 99; cursor: pointer; font-weight: 700; font-family: serif; line-height: 1; text-align: center; padding: 0 !important; margin: -12px -14px 0 0 !important; }';
			echo '.bwf_pp_overlay { background: rgba(0,0,0,0.75); }';
			echo '.bwf_pp_wrap { position: relative; margin: auto; background: #fff; }';
			echo '.bwf_pp_cont { font-size: 16px; }';
			echo '.bwf_pp_bar_wrap { display: flex; overflow: hidden; margin-bottom: 20px; }';
			echo '.bwf_pp_bar_wrap .bwf_pp_bar { display: flex; flex-direction: column; justify-content: center; color: #fff; text-align: center; white-space: nowrap; font-size: 16px; font-weight: 600; transition: width 500ms ease-in-out; max-width: 100%; }';
			// Progress bar fill width from saved percentage
			if ( $bar_fill_pct > 0 ) {
				echo '.bwf_pp_bar_wrap .bwf_pp_bar { width: ' . intval( $bar_fill_pct ) . '%; }';
			}
			echo '.bwf_pp_opt_head { text-align: center; margin: 0 0 15px; }';
			echo '.bwf_pp_opt_sub_head { text-align: center; margin: 0 0 15px; }';
			echo '.bwf_pp_footer { text-align: center; margin-top: 10px; }';
			echo '.bwfac_form_sec label { display: block; text-align: left; }';
			echo '.wffn-optin-input { width: 100%; outline: none; text-align: left; }';
			echo '.wfop_hide_label label { display: none; }';
			echo '#wffn_custom_optin_submit .bwf_heading { line-height: 1.5; }';
			echo '.bwf-custom-button a span:not(.bwf_subheading) { line-height: 1.5; }';
			echo '.wffn-custom-optin-from *, .wffn-custom-optin-from *::before, .wffn-custom-optin-from *::after { box-sizing: border-box; }';
			echo '</style>';

			$popup_content = ob_get_clean();

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
						'orderIndex'               => $block->parsed_block['orderIndex'] ?? 0,
						'storeInstance'            => $block->parsed_block['storeInstance'] ?? null,

						// VB equivalent.
						'attrs'                    => $block_attributes,
						'elements'                 => $elements,
						'id'                       => $block->parsed_block['id'] ?? '',
						'name'                     => $block->block_type->name ?? 'wfopp/optin-form-popup',
						'moduleCategory'           => $block->block_type->category ?? 'module',
						'classnamesFunction'       => array( OptinFormPopup::class, 'module_classnames' ),
						'stylesComponent'          => array( OptinFormPopup::class, 'module_styles' ),
						'scriptDataComponent'      => array( OptinFormPopup::class, 'module_script_data' ),
						'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
						'parentAttrs'              => $parent_attrs,
						'parentId'                 => $parent_id,
						'parentName'               => $parent_name,
						'children'                 => $popup_content,
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $popup_content;
			}
		} catch ( \Exception $e ) {
			// Return error message so something is displayed
			return '<div class="wfop-popup-form-error" style="padding: 20px; border: 1px solid #f00; background: #ffe6e6; color: #c00;"><p><strong>Error rendering Optin Form Popup</strong></p><p>' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}
}
