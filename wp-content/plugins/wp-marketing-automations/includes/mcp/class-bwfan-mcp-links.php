<?php

defined( 'ABSPATH' ) || exit;

/** Admin-app URL templates for this plugin's packs (see FKMCP_Pack::link_templates()). */
class BWFAN_MCP_Links {
	/**
	 * Admin-app URL with `{placeholders}` left intact for the agent.
	 *
	 * @param string $page WP admin page slug (bwf, autonami, ...).
	 * @param string $path App route, e.g. "/funnels/{funnel_id}/steps".
	 */
	public static function app( $page, $path ) {
		return admin_url( 'admin.php' ) . '?page=' . rawurlencode( $page ) . '&path=' . $path;
	}
}
