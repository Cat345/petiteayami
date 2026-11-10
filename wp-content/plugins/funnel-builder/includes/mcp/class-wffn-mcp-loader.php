<?php

defined( 'ABSPATH' ) || exit;

/** Loads this plugin's MCP runtime pieces (the shared module holds only the server). */
final class WFFN_MCP_Loader {
	private static $loaded = false;

	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;

		require_once __DIR__ . '/class-wffn-mcp-schema.php';
		require_once __DIR__ . '/class-wffn-mcp-format.php';
		require_once __DIR__ . '/class-wffn-mcp-links.php';
		require_once __DIR__ . '/class-wffn-mcp-permissions.php';
		require_once __DIR__ . '/class-wffn-mcp-rate-limit.php';
		require_once __DIR__ . '/class-wffn-mcp-log.php';
	}

	public static function load_admin() {
		self::load();

		require_once __DIR__ . '/admin/class-wffn-mcp-settings-api.php';
	}
}
