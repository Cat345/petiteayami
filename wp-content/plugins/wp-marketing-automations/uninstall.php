<?php
/**
 * Runs when FunnelKit Automations is deleted from the plugins screen.
 *
 * Only the FunnelKit MCP feature's own data is removed here - contacts,
 * automations and campaigns are the site's data and are left alone.
 *
 * @package FunnelKit Automations
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$fkmcp_uninstall_file = __DIR__ . '/includes/mcp/module/fkmcp-uninstall.php';

if ( is_readable( $fkmcp_uninstall_file ) ) {
	require_once $fkmcp_uninstall_file;

	fkmcp_uninstall( __DIR__ );
}
