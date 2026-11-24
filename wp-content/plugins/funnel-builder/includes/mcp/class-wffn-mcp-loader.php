<?php

defined( 'ABSPATH' ) || exit;

/**
 * Loads this plugin's MCP runtime pieces.
 *
 * The classes themselves live in the shared fk-mcp-core submodule, mounted at
 * includes/mcp/module. What stays here is this plugin's subclass of each one,
 * carrying only its own policy - its capability layer, its log store, its
 * branding - under the name the rest of Funnel Builder, Powerpack and Bundles
 * already call.
 */
final class WFFN_MCP_Loader {
	private static $loaded = false;

	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;

		require_once __DIR__ . '/module/fkmcp-runtime.php';
		fkmcp_load_runtime();

		require_once __DIR__ . '/class-wffn-mcp-schema.php';
		require_once __DIR__ . '/class-wffn-mcp-format.php';
		require_once __DIR__ . '/class-wffn-mcp-links.php';
		require_once __DIR__ . '/class-wffn-mcp-permissions.php';
		require_once __DIR__ . '/class-wffn-mcp-rate-limit.php';
		require_once __DIR__ . '/class-wffn-mcp-log.php';
	}

	public static function load_admin() {
		self::load();

		require_once __DIR__ . '/module/fkmcp-runtime.php';
		fkmcp_load_admin();

		require_once __DIR__ . '/admin/class-wffn-mcp-admin.php';
		require_once __DIR__ . '/admin/class-wffn-mcp-settings-api.php';
	}
}
