<?php
/**
 * Bundles' name for Funnel Builder's MCP rate limiter.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * This was a copy of Funnel Builder's limiter from before it counted reads in a
 * bucket of their own and reported retry_after, though both already shared the
 * `fkmcp_rl_` counter keys, so the counters were never separate.
 */
class Rate_Limit extends \WFFN_MCP_Rate_Limit {
	/**
	 * Bundles has no MCP classes of its own beyond these, so a shared class
	 * looking for a sibling finds the host's.
	 *
	 * @return string
	 */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}
}
