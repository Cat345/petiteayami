<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder's FunnelKit AI settings controller: the shared one
 * (fk-mcp-core) wearing this plugin's branding and listing its packs first.
 */
class WFFN_MCP_Settings_API extends FKMCP_Settings_API {
	/** This plugin's MCP subclasses, for FKMCP_Host_Classes. */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}

	/** This plugin's published hook names and error codes, e.g. wffn_mcp_pro_pitch. */
	protected static function hook_prefix() {
		return 'wffn_mcp_';
	}

	/** This plugin's prompt library, which stays here rather than in the submodule. */
	protected static function prompts_file() {
		return __DIR__ . '/prompts.json';
	}

	/** Campaign tag on this screen's outbound funnelkit.com links. */
	protected static function utm_campaign() {
		return 'FB+Lite+Plugin';
	}

	/** This plugin's name, as advice telling the merchant to update it writes it. */
	protected static function brand_name() {
		return 'Funnel Builder';
	}

	/** This plugin's own packs sort before every other FunnelKit plugin's. */
	protected static function pack_order() {
		return array( 'funnel-builder', 'funnel-builder-pro' );
	}

	/** The status provider this screen reads from. */
	protected static function load_admin() {
		if ( ! class_exists( 'WFFN_MCP_Admin' ) ) {
			require_once __DIR__ . '/class-wffn-mcp-admin.php';
		}
	}
}
