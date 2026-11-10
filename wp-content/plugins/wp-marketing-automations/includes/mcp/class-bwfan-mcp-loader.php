<?php

defined( 'ABSPATH' ) || exit;

/** Loads this plugin's MCP runtime pieces (the shared module holds only the server). */
final class BWFAN_MCP_Loader {
	private static $loaded = false;

	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;

		require_once __DIR__ . '/class-bwfan-mcp-schema.php';
		require_once __DIR__ . '/class-bwfan-mcp-format.php';
		require_once __DIR__ . '/class-bwfan-mcp-links.php';
		require_once __DIR__ . '/class-bwfan-mcp-permissions.php';
		require_once __DIR__ . '/class-bwfan-mcp-rate-limit.php';
		require_once __DIR__ . '/class-bwfan-mcp-log.php';
		require_once __DIR__ . '/class-bwfan-mcp-dispatch.php';
	}

	public static function load_admin() {
		self::load();

		require_once __DIR__ . '/admin/class-bwfan-mcp-settings-api.php';
	}
}
