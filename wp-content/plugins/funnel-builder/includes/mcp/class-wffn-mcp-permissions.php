<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder's MCP permission gate: the shared one (fk-mcp-core) answering
 * from this plugin's own capability layer.
 */
class WFFN_MCP_Permissions extends FKMCP_Permissions {
	/** This plugin's MCP subclasses, for FKMCP_Host_Classes. */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}

	/**
	 * The plugin's own capability layer (WFFN_Role_Capability: menu / funnel
	 * / analytics x read / write), the same check its REST controllers use.
	 *
	 * @param string $cap    menu, funnel or analytics.
	 * @param string $access read or write.
	 *
	 * @return bool
	 */
	public static function can_access( $cap, $access ) {
		if ( class_exists( 'WFFN_Role_Capability' ) ) {
			return (bool) WFFN_Role_Capability::get_instance()->user_access( $cap, $access );
		}

		return current_user_can( 'manage_options' );
	}
}
