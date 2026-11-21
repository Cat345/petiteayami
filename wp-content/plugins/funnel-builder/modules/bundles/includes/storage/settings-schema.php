<?php
/**
 * The settings form, described on the server.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Storage;

use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Skins\Options_Display;
use WFFN\Bundles\Includes\Storage\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Describes every field the settings screen carries.
 *
 * The same contract the bundle form uses: a field names a `type` and the
 * admin app maps that to whatever draws it, so a panel an add-on owns is
 * described here without this file knowing what one looks like.
 *
 * An add-on describes its own settings through `wffn_bundles_settings_schema`.
 */
class Settings_Schema {

	/** Field types that draw something but hold no value of their own. */
	const VALUELESS_TYPES = array( 'html', 'note', 'pro' );

	/** Label column width for the horizontal rows, matching the add-on panel. */
	const LABEL_WIDTH = '296px';

	/**
	 * The whole form: tabs, each holding sections, each holding fields.
	 *
	 * @return array
	 */
	public static function get() {
		$schema = array(
			'tabs' => array(
				'general' => array(
					'label'    => __( 'General', 'funnel-builder' ),
					'sections' => self::general_sections(),
				),
			),
		);

		/**
		 * Filters the settings form description.
		 *
		 * @since 1.0.0
		 *
		 * @param array $schema Tabs, each holding sections, each holding fields.
		 */
		$schema = (array) apply_filters( 'wffn_bundles_settings_schema', $schema );

		foreach ( $schema['tabs'] as $tab_key => $tab ) {
			if ( empty( $tab['sections'] ) || ! is_array( $tab['sections'] ) ) {
				continue;
			}
			uasort(
				$schema['tabs'][ $tab_key ]['sections'],
				static function ( $a, $b ) {
					return (int) ( $a['order'] ?? 999 ) <=> (int) ( $b['order'] ?? 999 );
				}
			);
		}

		return $schema;
	}

	/**
	 * The General tab.
	 *
	 * @return array
	 */
	private static function general_sections() {
		return array(
			'pro'             => array(
				'label'  => __( 'FunnelKit Funnel Builder Pro - Bundles Addon', 'funnel-builder' ),
				'order'  => 10,
				'fields' => array(
					array(
						'key'       => 'pro',
						'type'      => 'pro',
						'label'     => __( 'FunnelKit Funnel Builder Pro - Bundles Addon', 'funnel-builder' ),
						'hideLabel' => true,
					),
				),
			),
			'general'         => array(
				'label'      => __( 'General', 'funnel-builder' ),
				'order'      => 20,
				'layout'     => 'horizontal',
				'labelWidth' => self::LABEL_WIDTH,
				'fields'     => array(
					array(
						'key'         => Install::SETTINGS_STATE,
						'type'        => 'radio',
						'label'       => __( 'Enable Bundles', 'funnel-builder' ),
						'orientation' => 'horizontal',
						'options'     => array(
							array( 'value' => Install::STATE_ENABLED, 'label' => __( 'On', 'funnel-builder' ) ),
							array( 'value' => Install::STATE_DISABLED, 'label' => __( 'Off', 'funnel-builder' ) ),
						),
					),
					array(
						'key'     => Install::SETTINGS_PLACEMENT,
						'type'    => 'select',
						'label'   => __( 'Bundle Position', 'funnel-builder' ),
						'default' => Product_Display::DEFAULT_PLACEMENT,
						'options' => array(
							array( 'value' => 'before_cart', 'label' => __( 'Above the add to cart button', 'funnel-builder' ) ),
							array( 'value' => 'after_cart', 'label' => __( 'Below the add to cart button', 'funnel-builder' ) ),
							array( 'value' => 'after_excerpt', 'label' => __( 'Below the short description', 'funnel-builder' ) ),
							array( 'value' => 'after_summary', 'label' => __( 'At the end of the product summary', 'funnel-builder' ) ),
						),
					),
				),
			),
			'product-options' => array(
				'label'      => __( 'Product options', 'funnel-builder' ),
				'order'      => 30,
				'layout'     => 'horizontal',
				'labelWidth' => self::LABEL_WIDTH,
				'fields'     => array(
					array(
						'key'         => 'variationOptionsDisplay',
						'type'        => 'radio',
						'label'       => __( 'Variation Selection Behaviour', 'funnel-builder' ),
						'orientation' => 'horizontal',
						'default'     => Options_Display::DEFAULT_MODE,
						'options'     => array(
							array(
								'value'       => Options_Display::MODE_INLINE,
								'label'       => __( 'Inline', 'funnel-builder' ),
								'tooltip'     => __( 'Select variations using inline dropdown', 'funnel-builder' ),
							),
							array(
								'value'       => Options_Display::MODE_MODAL,
								'label'       => __( 'Modal Popup', 'funnel-builder' ),
								'tooltip'     => __( 'Select variations using modal popup', 'funnel-builder' ),
							),
							array(
								'value'       => Options_Display::MODE_AUTO,
								'label'       => __( 'Adaptive', 'funnel-builder' ),
								'tooltip'     => __( 'Select variations using inline dropdown for up to 2 attributes, and modal popup for 3 or more', 'funnel-builder' ),
							),
						),
					),
				),
			),
		);
	}
}
