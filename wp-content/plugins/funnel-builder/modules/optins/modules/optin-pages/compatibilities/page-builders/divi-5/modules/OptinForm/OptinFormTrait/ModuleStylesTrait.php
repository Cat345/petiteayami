<?php
/**
 * OptinForm::module_styles().
 *
 * @package WFOP\Modules\OptinForm
 * @since 1.0.0
 */

namespace WFOP\Modules\OptinForm\OptinFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

trait ModuleStylesTrait {

	/**
	 * Normalize submit button text attr so Divi TextStyle gets 'orientation' (text-align).
	 * VB may store 'align' or 'orientation'; Divi Text::style_declaration uses 'orientation'.
	 *
	 * @param array $advanced_text Attribute advanced.text (with 'text' key and breakpoint structure).
	 * @return array Normalized attr for TextStyle::style().
	 */
	private static function normalize_submit_button_text_attr( array $advanced_text ): array {
		$text = $advanced_text['text'] ?? array(
			'desktop' => array(
				'value' => array(
					'orientation' => 'left',
					'align'       => 'left',
				),
			),
		);
		$out  = array();
		foreach ( $text as $breakpoint => $state_data ) {
			if ( ! is_array( $state_data ) ) {
				$out[ $breakpoint ] = $state_data;
				continue;
			}
			$out[ $breakpoint ] = array();
			foreach ( $state_data as $state => $value_data ) {
				if ( ! is_array( $value_data ) || ! isset( $value_data['value'] ) ) {
					$out[ $breakpoint ][ $state ] = $value_data;
					continue;
				}
				$v                            = $value_data['value'];
				$ori                          = $v['orientation'] ?? $v['align'] ?? 'left';
				$out[ $breakpoint ][ $state ] = array(
					'value' => array(
						'orientation' => $ori,
						'align'       => $v['align'] ?? $ori,
					),
				);
			}
		}
		return array( 'text' => $out );
	}

	/**
	 * Optin Form module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/optin-form/styles.tsx.
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
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfop/optin-form' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values so elements->style() reads merged defaults
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

					// Form Label Typography - Label
					// Selector: {{selector}} .bwfac_form_sec > label, {{selector}} .bwfac_form_sec.bwfac_form_field_radio label
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_label_typography',
						)
					),

					// Form Input Typography - Input Field
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input, {{selector}} .bwfac_form_sec .wffn-optin-input::placeholder
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_field_typography',
						)
					),

					// Button Heading Typography - Heading
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_text_typo',
						)
					),

					// Button Sub Heading Typography - Sub Heading
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_subheading_text_typo',
						)
					),

					// Form - Asterisk
					// Selector: {{selector}} .bwfac_form_sec > label > span, {{selector}} .bwfac_form_sec.bwfac_form_field_radio label > span
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_mark_required_color',
						)
					),

					// Form - Background
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_field_background_color',
						)
					),

					// Form Input Border - Border
					// Selector: {{selector}} .bwfac_form_sec .wffn-optin-input
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_field_border',
						)
					),

					// Form - Columns Gap
					// Selector: {{selector}} .wffn-custom-optin-from .bwfac_form_sec
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_column_gap_padding',
						)
					),

					// Form - Rows Gap
					// Selector: {{selector}} .wffn-custom-optin-from .bwfac_form_sec
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_row_gap_margin',
						)
					),

					// Submit Button - Button Alignment (text-align) - explicit output so it applies
					// Selector: %%order_class%% .wffn-custom-optin-from #bwf-custom-button-wrap
					TextStyle::style(
						array(
							'selector'   => $order_class . ' .wffn-custom-optin-from #bwf-custom-button-wrap',
							'attr'       => self::normalize_submit_button_text_attr( $attrs['wfop_optin_form_submit_button_text']['advanced']['text'] ?? array() ),
							'orderClass' => $order_class,
						)
					),

					// Submit Button - Button Width
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_width',
						)
					),

					// Submit Button - Button Background Color (Normal)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_bg_color',
						)
					),

					// Submit Button - Button Color (Hover)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_heading, {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_subheading
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_hover_color',
						)
					),

					// Submit Button - Button Background Color (Hover)
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_hover_bg_color',
						)
					),

					// Submit Button - Button Border
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'bwf_button_border',
						)
					),

					// Submit Button - Button Padding
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'wfop_optin_form_button_text_padding',
						)
					),

					// Submit Button - Button Box Shadow
					// Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit
					$elements->style(
						array(
							'attrName' => 'button_text_alignment_box_shadow',
						)
					),

				),
			)
		);
	}
}
