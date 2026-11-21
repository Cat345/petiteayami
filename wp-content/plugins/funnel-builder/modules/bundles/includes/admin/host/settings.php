<?php
/** Settings — the Bundles tab in Funnel Builder's settings screen. */
namespace WFFN\Bundles\Includes\Admin\Host;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Rest\Settings_Controller;
use WFFN\Bundles\Includes\Storage\Install;
use WFFN\Bundles\Includes\Storage\Settings_Schema;

/**
 * Describes this plugin's settings as a tab of Funnel Builder's settings screen,
 * and points the admin app's own Settings link there.
 */
class Settings {

	/** The tab's slug, in the screen's URL and its save action. */
	const TAB = 'wffn-bundles';

	/** Schema field types and the settings-screen types that draw them. */
	const FIELD_TYPES = array(
		'select' => 'select',
		'radio'  => 'radios',
	);

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woofunnels_global_settings', array( $this, 'add_tab' ) );
		add_filter( 'woofunnels_global_settings_fields', array( $this, 'add_fields' ) );
		add_action( 'bwf_global_save_settings_' . self::TAB, array( $this, 'save' ) );
		add_filter( 'wffn_bundles_admin_navigation', array( $this, 'link_settings' ) );
		add_filter( 'wffn_bundles_admin_app_data', array( $this, 'add_app_data' ) );
	}

	/**
	 * Name the tab that switches Bundles on or off, so the app can follow a save of it.
	 *
	 * @param array $data Admin app data.
	 * @return array
	 */
	public function add_app_data( $data ) {
		$data['settings_tab'] = self::TAB;

		return $data;
	}

	/**
	 * Add the Bundles tab.
	 *
	 * @param array $tabs Settings tabs.
	 * @return array
	 */
	public function add_tab( $tabs ) {
		$tabs   = is_array( $tabs ) ? $tabs : array();
		$tabs[] = array(
			'title'    => __( 'Bundles', 'funnel-builder' ),
			'slug'     => self::TAB,
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Describe the tab's sections and their current values.
	 *
	 * @param array $fields Every tab's sections, keyed by tab slug.
	 * @return array
	 */
	public function add_fields( $fields ) {
		$fields   = is_array( $fields ) ? $fields : array();
		$values   = Settings_Controller::read();
		$sections = array();

		foreach ( self::schema_sections() as $key => $section ) {
			$section_fields = array_map( array( __CLASS__, 'field' ), $section['fields'] );
			$sections[ $key ] = array(
				'title'    => $section['label'] ?? '',
				'heading'  => $section['label'] ?? '',
				'slug'     => $key,
				'priority' => (int) ( $section['order'] ?? 999 ),
				'fields'   => $section_fields,
				'values'   => array_intersect_key( $values, array_flip( wp_list_pluck( $section_fields, 'key' ) ) ),
			);
		}

		/**
		 * Filters the Bundles tab's sections in Funnel Builder's settings screen.
		 *
		 * @since 1.0.0
		 *
		 * @param array $sections Sections keyed by slug: `title`, `heading`, `slug`, `priority`, `fields`, `values`.
		 */
		$sections = (array) apply_filters( 'wffn_bundles_funnel_builder_settings_sections', $sections );

		uasort(
			$sections,
			static function ( $a, $b ) {
				return (int) ( $a['priority'] ?? 999 ) <=> (int) ( $b['priority'] ?? 999 );
			}
		);

		// One headingless group draws every row as a single form under one Save, as the General tab does.
		$form = array(
			'title'  => __( 'Bundles', 'funnel-builder' ),
			'slug'   => self::TAB,
			'fields' => array(),
			'values' => array(),
		);
		foreach ( $sections as $section ) {
			$form['fields'] = array_merge( $form['fields'], (array) ( $section['fields'] ?? array() ) );
			$form['values'] = array_merge( $form['values'], (array) ( $section['values'] ?? array() ) );
		}
		$form['fields'] = self::with_switch( $form['fields'] );
		if ( array_key_exists( Install::SETTINGS_STATE, $form['values'] ) ) {
			$form['values'][ Install::SETTINGS_STATE ] = Install::STATE_ENABLED === $form['values'][ Install::SETTINGS_STATE ];
		}

		$fields[ self::TAB ] = array(
			array(
				'heading' => '',
				'tabs'    => array( self::TAB => $form ),
			),
		);

		return $fields;
	}

	/**
	 * Save the tab. The screen posts every field of the tab at once.
	 *
	 * @param array $settings Submitted values, keyed by field key.
	 * @return void
	 */
	public function save( $settings ) {
		if ( ! is_array( $settings ) || ! ( new Settings_Controller() )->can_write() ) {
			return;
		}

		$owned = array();
		foreach ( self::schema_sections() as $section ) {
			$owned = array_merge( $owned, wp_list_pluck( $section['fields'], 'key' ) );
		}

		$patch = array_intersect_key( wp_unslash( $settings ), array_flip( $owned ) );
		if ( array_key_exists( Install::SETTINGS_STATE, $patch ) ) {
			$on = in_array( $patch[ Install::SETTINGS_STATE ], array( true, 1, '1', 'true' ), true );
			$patch[ Install::SETTINGS_STATE ] = $on ? Install::STATE_ENABLED : Install::STATE_DISABLED;
			// An unticked box before the merchant has chosen leaves the choice unmade.
			if ( ! $on && null === Install::get_state() ) {
				unset( $patch[ Install::SETTINGS_STATE ] );
			}
		}

		// Refused patches are dropped: the screen reports every save as a success.
		Settings_Controller::save( $patch );
	}

	/**
	 * Draw the on/off choice as a ticked box with its own row label, and show every other row only while it is ticked.
	 *
	 * @param array[] $fields The tab's fields, in the settings screen's shape.
	 * @return array[]
	 */
	private static function with_switch( array $fields ) {
		if ( ! in_array( Install::SETTINGS_STATE, wp_list_pluck( $fields, 'key' ), true ) ) {
			return $fields;
		}

		$drawn = array();
		foreach ( $fields as $field ) {
			if ( Install::SETTINGS_STATE !== ( $field['key'] ?? '' ) ) {
				$field['toggler'] = array(
					'key'   => Install::SETTINGS_STATE,
					'value' => true,
				);
				$drawn[] = $field;
				continue;
			}
			$drawn[] = array(
				'key'          => 'wffn_bundles_switch_label',
				'type'         => 'label',
				'label'        => __( 'Product Bundles', 'funnel-builder' ),
				'styleClasses' => array( 'wfacp_setting_track_and_events_start', 'bwf_wrap_custom_html_tracking_general' ),
			);
			$drawn[] = array(
				'key'          => Install::SETTINGS_STATE,
				'type'         => 'checkbox',
				'label'        => __( 'Enable Product Bundles', 'funnel-builder' ),
				'hint'         => __( 'Show bundle offers on your product pages, and keep bundled items together in the cart, at checkout and in order emails.', 'funnel-builder' ),
				'styleClasses' => array( 'wfacp_checkbox_wrap', 'wfacp_setting_track_and_events_end' ),
			);
		}

		return $drawn;
	}

	/**
	 * Send the admin app's Settings link to this tab.
	 *
	 * @param array $navigation The admin app's menus.
	 * @return array
	 */
	public function link_settings( $navigation ) {
		foreach ( (array) ( $navigation['secondary'] ?? array() ) as $i => $item ) {
			if ( 'settings' === ( $item['id'] ?? '' ) ) {
				$navigation['secondary'][ $i ]['url'] = admin_url( 'admin.php?page=bwf&path=/settings/' . self::TAB );
			}
		}

		return $navigation;
	}

	/**
	 * The schema's sections that hold a field the settings screen can draw.
	 *
	 * @return array
	 */
	private static function schema_sections() {
		$schema   = Settings_Schema::get();
		$sections = array();

		foreach ( (array) ( $schema['tabs']['general']['sections'] ?? array() ) as $key => $section ) {
			$section['fields'] = array_values(
				array_filter(
					(array) ( $section['fields'] ?? array() ),
					static function ( $field ) {
						return isset( self::FIELD_TYPES[ $field['type'] ?? '' ] ) && ! empty( $field['key'] );
					}
				)
			);
			if ( $section['fields'] ) {
				$sections[ $key ] = $section;
			}
		}

		return $sections;
	}

	/**
	 * One schema field, in the settings screen's shape.
	 *
	 * @param array $field Schema field.
	 * @return array
	 */
	private static function field( $field ) {
		$values = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			$value = array(
				'name'  => $option['label'] ?? '',
				'value' => $option['value'] ?? '',
			);
			// The settings screen shows it beside the option's label.
			if ( ! empty( $option['tooltip'] ) ) {
				$value['tooltip'] = $option['tooltip'];
			}
			$values[] = $value;
		}

		return array(
			'key'    => $field['key'],
			'type'   => self::FIELD_TYPES[ $field['type'] ],
			'label'  => $field['label'] ?? '',
			'values' => $values,
		);
	}
}
