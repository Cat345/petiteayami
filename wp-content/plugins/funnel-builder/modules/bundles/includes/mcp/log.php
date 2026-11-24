<?php
/**
 * Bundles' MCP audit log: Funnel Builder's, writing to WooCommerce's logger.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Bundles ships inside Funnel Builder and uses its MCP layer. Only the store
 * differs: these lines go to WooCommerce's logger under their own source,
 * beside the rest of the Bundles log, rather than to the FunnelKit log.
 */
class Log extends \WFFN_MCP_Log {

	/** WooCommerce log source for the Bundles MCP audit trail. */
	const LOG_FILE = 'wffn-bundles-mcp';

	/**
	 * Bundles has no MCP classes of its own beyond these, so a shared class
	 * looking for a sibling finds the host's.
	 *
	 * @return string
	 */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}

	/**
	 * Where an administrator looks an error id up. These lines are in the
	 * WooCommerce logs, not the FunnelKit ones.
	 *
	 * @return string
	 */
	protected static function log_location() {
		return __( 'WooCommerce', 'funnel-builder' );
	}

	/**
	 * Appends to the WooCommerce log store.
	 *
	 * @param string $line Line to record.
	 * @return void
	 */
	protected static function write( $line ) {
		try {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->info( $line, array( 'source' => self::LOG_FILE ) );
			}
		} catch ( \Throwable $e ) {
			// Logging must never break the tool call that triggered it.
			unset( $e );
		}
	}
}
