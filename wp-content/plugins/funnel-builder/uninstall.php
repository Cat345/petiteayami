<?php
/**
 * Runs when Funnel Builder is deleted from the plugins screen.
 *
 * Only the FunnelKit MCP feature's own data is removed here - funnels, steps
 * and their settings are the site's content and are left alone.
 *
 * @package Funnel Builder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$fkmcp_uninstall_file = __DIR__ . '/includes/mcp/module/fkmcp-uninstall.php';

if ( is_readable( $fkmcp_uninstall_file ) ) {
	require_once $fkmcp_uninstall_file;

	fkmcp_uninstall( __DIR__ );
}
