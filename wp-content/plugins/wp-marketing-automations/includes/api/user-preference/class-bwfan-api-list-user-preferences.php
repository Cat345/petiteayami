<?php

/**
 * Handles the API endpoint for listing user preferences and custom export selections.
 * Extends BWFAN_API_Base to provide REST API functionality for user preference data.
 *
 * Preferences are built from CRM filter classes (Pro): rules with is_preference / preference true.
 */
class BWFAN_API_List_User_Preferences extends BWFAN_API_Base {
	public static $ins;

	public function __construct() {
		parent::__construct();
		$this->method     = WP_REST_Server::READABLE;
		$this->route      = 'v2/preferences';
		$this->public_api = true;
	}

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function process_api_call() {
		/**
		 * Format:
		 * $selections = [
		 *       [
		 *          'title': '{group-name}',
		 *          'slug': '{group-slug}',
		 *          'rules': [
		 *              '{slug}': [
		 *                  'slug': '{slug}',
		 *                  'name': '{name}'
		 *              ]
		 *          ]
		 *      ]
		 * ]
		 */

		$get_preferences = $this->get_user_preferences();
		$selections      = apply_filters( 'bwfcrm_get_export_custom_selections', [] );
		$selections      = is_array( $selections ) ? $selections : [];

		// Convert old format to new format if needed
		$selections = array_map( function ( $selection ) {
			if ( isset( $selection['selections'] ) ) {
				$selection['title']    = $selection['name'];
				$selection['rules']    = array_values( array_map( function ( $rule, $index ) {
					return array(
						'slug' => $rule['slug'],
						'name' => $rule['name'],
					);
				}, $selection['selections'], array_keys( $selection['selections'] ) ) );
				$selection['priority'] = isset( $selection['priority'] ) ? $selection['priority'] : 999;
				unset( $selection['selections'], $selection['name'] );
			}

			return $selection;
		}, $selections );

		$final_selections = array_merge( $get_preferences, $selections );

		uasort( $final_selections, function ( $a, $b ) {
			$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 999;
			$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 999;

			return $a_priority <=> $b_priority;
		} );

		return $this->success_response( array_values( $final_selections ) );
	}

	/**
	 * REST slug for a CRM filter group key (BWFCRM_Load_Filters::register first argument).
	 *
	 * @param string $group_key e.g. contact_details, contact_custom_fields.
	 *
	 * @return string
	 */
	private function preference_group_slug_from_key( $group_key ) {
		if ( 'contact_custom_fields' === $group_key ) {
			return 'custom-fields';
		}

		return str_replace( '_', '-', $group_key );
	}

	/**
	 * Build preferences from filter classes (Pro). Only rules flagged as preference are included.
	 *
	 * @return array
	 */
	private function get_preferences_from_filters() {
		if ( ! bwfan_is_autonami_pro_active() || ! class_exists( 'BWFCRM_Load_Filters' ) ) {
			return array();
		}

		$filters     = BWFCRM_Load_Filters::get_instance()->get_filters();
		$preferences = array();

		foreach ( $filters as $group_key => $group ) {
			if ( empty( $group['rules'] ) || ! is_array( $group['rules'] ) ) {
				continue;
			}

			$preference_rules = array_filter( $group['rules'], function ( $rule ) {
				return ! empty( $rule['preference'] );
			} );

			if ( empty( $preference_rules ) ) {
				continue;
			}

			$group_title = isset( $group['title'] ) ? $group['title'] : '';
			if ( '' === $group_title ) {
				continue;
			}

			$group_priority  = isset( $group['priority'] ) ? (int) $group['priority'] : 999;
			$preference_slug = $this->preference_group_slug_from_key( $group_key );

			$rules = array_values( array_map( function ( $rule ) {
				return array(
					'slug'     => $rule['slug'],
					'name'     => isset( $rule['name'] ) ? $rule['name'] : $rule['slug'],
					'priority' => isset( $rule['priority'] ) ? (int) $rule['priority'] : 1,
				);
			}, $preference_rules ) );

			$preferences[] = array(
				'slug'     => $preference_slug,
				'title'    => $group_title,
				'rules'    => $rules,
				'priority' => $group_priority,
			);
		}

		return $preferences;
	}

	/**
	 * User preferences for contact columns and export (dynamic filters only).
	 *
	 * @return array
	 */
	public function get_user_preferences() {
		return apply_filters( 'bwfan_set_preferences_v2', $this->get_preferences_from_filters() );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_List_User_Preferences' );
