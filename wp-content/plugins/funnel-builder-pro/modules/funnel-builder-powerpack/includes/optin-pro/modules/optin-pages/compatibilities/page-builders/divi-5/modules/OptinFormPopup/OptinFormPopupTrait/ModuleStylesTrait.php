<?php
/**
 * OptinFormPopup::module_styles().
 *
 * @package WFOPP\Modules\OptinFormPopup
 * @since 1.0.0
 */

namespace WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

trait ModuleStylesTrait {

	/**
	 * Optin Form Popup module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/optin-form-popup/styles.tsx.
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
		$default_attrs = array();
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfopp/optin-form-popup' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values
		$attrs = $merged_attrs;

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
							'attrName' => 'module',
						)
					),

					// Popup - Width
					// Selector: {{selector}} .bwf_pp_wrap
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_bar_width',
						)
					),

					// Popup - Padding
					// Selector: {{selector}} .bwf_pp_wrap .bwf_pp_cont
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_padding',
						)
					),

					// Progress Bar - Width
					// Selector: {{selector}} .bwf_pp_bar_wrap .bwf_pp_bar
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_progress_bar_width',
						)
					),

					// Progress Bar - Height
					// Selector: {{selector}} .bwf_pp_bar_wrap
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_progress_bar_height',
						)
					),

					// Progress Bar - Padding
					// Selector: {{selector}} .bwf_pp_bar_wrap
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_progress_bar_padding',
						)
					),

					// Progress Bar - Typography
					// Selector: {{selector}} .bwf_pp_overlay .pp-bar-text
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_progress_bar_typography',
						)
					),

					// Progress Bar - Text Color
					// Selector: {{selector}} .bwf_pp_overlay .pp-bar-text
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_progress_text_color',
						)
					),

					// Progress Bar - Background Color
					// Selector: {{selector}} .bwf_pp_overlay .bwf_pp_bar_wrap
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_progress_background_color',
						)
					),

					// Progress Bar - Progress Color
					// Selector: {{selector}} .bwf_pp_bar_wrap .bwf_pp_bar
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_progress_color',
						)
					),

					// Heading - Typography
					// Selector: {{selector}} .bwf_pp_cont .bwf_pp_opt_head
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_heading',
						)
					),

					// Heading - Color
					// Selector: {{selector}} .bwf_pp_cont .bwf_pp_opt_head
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_heading_color',
						)
					),

					// Sub-Heading - Typography
					// Selector: {{selector}} .bwf_pp_cont .bwf_pp_opt_sub_head
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_subheading_typography',
						)
					),

					// Sub-Heading - Color
					// Selector: {{selector}} .bwf_pp_cont .bwf_pp_opt_sub_head
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_subheading_color',
						)
					),

					// Form Label - Typography
					// Selector: {{selector}} .bwfac_form_sec > label, {{selector}} .bwfac_form_sec .wfop_input_cont > label
					$elements->style(
						array(
							'attrName' => 'label_typography',
						)
					),

					// Form Label - Color
					// Selector: {{selector}} .bwfac_form_sec > label, {{selector}} .bwfac_form_sec .wfop_input_cont > label
					$elements->style(
						array(
							'attrName' => 'label_color',
						)
					),

					// Form Label - Asterisk Color
					// Selector: {{selector}} .bwfac_form_sec > label > span, {{selector}} .bwfac_form_sec .wfop_input_cont > label > span
					$elements->style(
						array(
							'attrName' => 'mark_required_color',
						)
					),

					// Form Input - Typography
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input
					$elements->style(
						array(
							'attrName' => 'field_typography',
						)
					),

					// Form Input - Text Color
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input, {{selector}} .bwfac_form_sec .wffn-optin-input::placeholder
					$elements->style(
						array(
							'attrName' => 'field_text_color',
						)
					),

					// Form Input - Background Color
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input
					$elements->style(
						array(
							'attrName' => 'field_background_color',
						)
					),

					// Form Input - Border
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input
					$elements->style(
						array(
							'attrName' => 'field_border',
						)
					),

					// Form - Columns Gap Padding
					// Selector: {{selector}} .bwfac_form_sec
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_pop_column_gap_padding',
						)
					),

					// Form - Rows Gap Margin
					// Selector: {{selector}} .bwfac_form_sec
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_pop_row_gap_margin',
						)
					),

					// Text After Button - Typography
					// Selector: {{selector}} .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_text_after_submit_typography',
						)
					),

					// Text After Button - Color
					// Selector: {{selector}} .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_text_after_submit_color',
						)
					),

					// Submit Button - Width
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_button_width',
						)
					),

					// Submit Button - Text Color (Normal)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading, {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading
					$elements->style(
						array(
							'attrName' => 'button_color',
						)
					),

					// Submit Button - Background Color (Normal)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'button_bg_color',
						)
					),

					// Submit Button - Text Color (Hover)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_heading, {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_subheading
					$elements->style(
						array(
							'attrName' => 'button_hover_color',
						)
					),

					// Submit Button - Background Color (Hover)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover
					$elements->style(
						array(
							'attrName' => 'button_hover_bg_color',
						)
					),

					// Submit Button - Heading Typography
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading
					$elements->style(
						array(
							'attrName' => 'button_text_typo',
						)
					),

					// Submit Button - Sub Heading Typography
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading
					$elements->style(
						array(
							'attrName' => 'button_subheading_text_typo',
						)
					),

					// Submit Button - Padding
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'button_text_padding',
						)
					),

					// Submit Button - Margin
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'button_text_margin',
						)
					),

					// Submit Button - Border
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'bwf_button_border',
						)
					),

					// Submit Button - Box Shadow
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'button_text_alignment_box_shadow',
						)
					),

					// CTA Button - Width
					// Selector: {{selector}} .bwf-custom-button .wfop_popup_form
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_popup_button_width',
						)
					),

					// CTA Button - Background Color (Normal)
					// Selector: {{selector}} .bwf-custom-button a
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_bg_color',
						)
					),

					// CTA Button - Text Color (Normal)
					// Selector: {{selector}} .bwf-custom-button a span
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_color',
						)
					),

					// CTA Button - Background Color (Hover)
					// Selector: {{selector}} .bwf-custom-button a:hover
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_hover_bg_color',
						)
					),

					// CTA Button - Text Color (Hover)
					// Selector: {{selector}} .bwf-custom-button a:hover span
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_hover_color',
						)
					),

					// CTA Button - Title Typography
					// Selector: {{selector}} .bwf-custom-button a span:not(.bwf_subheading)
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_text_typo',
						)
					),

					// CTA Button - SubTitle Typography
					// Selector: {{selector}} .bwf-custom-button .bwf_subheading
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_subheading_text_typo',
						)
					),

					// Call To Action - Button Text Spacing (padding + margin)
					// Selector: {{selector}} .bwf-custom-button a
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_text_spacing',
						)
					),

					// CTA Button - Border
					// Selector: {{selector}} .bwf-custom-button a
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_text_alignment_border',
						)
					),

					// CTA Button - Box Shadow
					// Selector: {{selector}} .bwf-custom-button a
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_btn_text_alignment_box_shadow',
						)
					),

					// Close Button - Position Margin
					// Selector: {{selector}} .bwf_pp_close
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_close_icon_position_margin',
						)
					),

					// Close Button - Font Size
					// Selector: {{selector}} .bwf_pp_close
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_icon_size_font_size',
						)
					),

					// Close Button - Padding
					// Selector: {{selector}} .bwf_pp_close
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_close_btn_inner_gap_padding',
						)
					),

					// Close Button - Border Radius
					// Selector: {{selector}} .bwf_pp_close
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_popup_close_btn_border',
						)
					),

					// Close Button - Background Color (Normal)
					// Selector: {{selector}} .bwf_pp_close
					$elements->style(
						array(
							'attrName' => 'close_button_background_color',
						)
					),

					// Close Button - Color (Normal)
					// Selector: {{selector}} .bwf_pp_close
					$elements->style(
						array(
							'attrName' => 'close_button_color',
						)
					),

					// Close Button - Background Color (Hover)
					// Selector: {{selector}} .bwf_pp_close:hover
					$elements->style(
						array(
							'attrName' => 'close_button_hover_background_color',
						)
					),

					// Close Button - Color (Hover)
					// Selector: {{selector}} .bwf_pp_close:hover
					$elements->style(
						array(
							'attrName' => 'close_button_hover_color',
						)
					),

				),
			)
		);
	}
}
