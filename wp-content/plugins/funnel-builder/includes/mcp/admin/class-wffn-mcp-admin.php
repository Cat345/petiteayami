<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder's status provider for the FunnelKit AI settings tab: the
 * shared one (fk-mcp-core) reporting on this plugin's host adapter.
 */
class WFFN_MCP_Admin extends FKMCP_Admin {
	/** This plugin's MCP subclasses, for FKMCP_Host_Classes. */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}

	/** FKMCP_Host_FB::slug(). */
	protected static function host_slug() {
		return 'funnel-builder';
	}
}
