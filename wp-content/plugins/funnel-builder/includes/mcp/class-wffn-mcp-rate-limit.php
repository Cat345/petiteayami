<?php

defined( 'ABSPATH' ) || exit;

/** Funnel Builder's name for the shared rate limiter (fk-mcp-core). */
class WFFN_MCP_Rate_Limit extends FKMCP_Rate_Limit {
	/** This plugin's MCP subclasses, for FKMCP_Host_Classes. */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}
}
