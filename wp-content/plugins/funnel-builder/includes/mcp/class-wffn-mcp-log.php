<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder's MCP audit log: the shared one (fk-mcp-core) writing to this
 * plugin's own log store.
 */
class WFFN_MCP_Log extends FKMCP_Log {
	/** This plugin's MCP subclasses, for FKMCP_Host_Classes. */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}

	/**
	 * Appends to the Funnel Builder log store (WFFN_Logger, forced on).
	 *
	 * @param string $line
	 */
	protected static function write( $line ) {
		try {
			if ( class_exists( 'WFFN_Logger' ) ) {
				WFFN_Logger::get_instance()->log( $line, self::LOG_FILE, true );
			} else {
				error_log( 'FunnelKit MCP: ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		} catch ( \Throwable $e ) {
			// Best-effort: logging must never break the tool call that
			// triggered it, but silently losing the line entirely leaves no
			// trace to debug from. error_log() does not depend on WFFN_Logger.
			error_log( 'FunnelKit MCP log write failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
