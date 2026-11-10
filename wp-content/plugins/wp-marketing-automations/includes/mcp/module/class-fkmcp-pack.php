<?php

defined( 'ABSPATH' ) || exit;

/**
 * Pack contract version. Bump the major when a pack written against the old
 * contract could no longer register cleanly; the registry refuses packs that
 * declare a newer major than the module understands instead of half-loading.
 */
if ( ! defined( 'FKMCP_API_VERSION' ) ) {
	define( 'FKMCP_API_VERSION', 1 );
}

/**
 * Base class for ability packs. A pack is what a plugin contributes to the
 * shared MCP server: a set of ability definitions plus the metadata the
 * settings screen and link templates need. Packs are collected through the
 * fkmcp_ability_packs filter.
 *
 * Contract (API 1):
 *   is_available()   bool   - the plugin/classes this pack reads are loaded
 *   plugin_meta()    array  - id, name, colour, groups (slug needle => label), pro (bool)
 *   definitions()    array  - slug => ability definition (label, description,
 *                             permission_callback, execute_callback, input_schema, output_schema, annotations,
 *                             capability - optional, in the pack's own vocabulary; see can();
 *                             license - optional, see the module README)
 *   link_templates() array  - entity => { ids, urls, note } - merged by FKMCP_Registry::link_templates()
 */
abstract class FKMCP_Pack {
	/** Contract this pack was written against. */
	const API = 1;

	abstract public function is_available();

	abstract public function plugin_meta();

	/**
	 * Built once per request by the pack: definitions() is asked for several
	 * times (registration, the tools list, the consent screen, the settings
	 * screen) and rebuilding the table each time is pure work.
	 *
	 * @var array|null
	 */
	protected $definitions = null;

	abstract public function definitions();

	/**
	 * Link templates this pack contributes to the context tools.
	 *
	 * @return array
	 */
	public function link_templates() {
		return array();
	}

	/**
	 * Capability an ability needs when its definition names none. In the
	 * pack's own vocabulary (whatever can() understands); null = no gate.
	 */
	public function default_capability() {
		return null;
	}

	/**
	 * Whether the current user holds a capability. Abilities register for
	 * every request (the user is not yet authenticated when the Abilities API
	 * initialises); the registry then hides the ones the user cannot use from
	 * tools/list and re-checks on every call through the ability's permission
	 * callback, so a capability layer behind the plugin's own REST API applies
	 * to MCP as well.
	 *
	 * @param mixed $capability Whatever default_capability() / definitions name.
	 *
	 * @return bool
	 */
	public function can( $capability ) {
		return true;
	}

	/** Whether this pack belongs to a Pro plugin (counted separately on the settings screen). */
	public function is_pro() {
		$meta = (array) $this->plugin_meta();

		return ! empty( $meta['pro'] );
	}

	/**
	 * Whether a collected pack (of any class - older plugins still hand in
	 * plain objects) can be used by this module.
	 *
	 * @param mixed $pack
	 *
	 * @return bool
	 */
	public static function is_compatible( $pack ) {
		if ( ! is_object( $pack ) || ! method_exists( $pack, 'is_available' ) || ! method_exists( $pack, 'definitions' ) ) {
			return false;
		}

		$api = defined( get_class( $pack ) . '::API' ) ? (int) $pack::API : 1;

		return $api <= FKMCP_API_VERSION;
	}
}
