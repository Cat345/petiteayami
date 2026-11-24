<?php
/**
 * Loads the shared runtime and admin classes.
 *
 * These are the pieces every host plugin needs around the connector: schema and
 * payload builders, the audit log, the permission and rate-limit gates, and the
 * FunnelKit AI settings screen's REST controller. The connector itself boots
 * through fkmcp-bootstrap.php and does not go through here.
 *
 * Each class is a base. A host subclasses the ones it has policy for - its
 * capability layer, its log store, its branding - and keeps the subclass under
 * its own historic name, so the call sites in that plugin and in its add-ons
 * never move. The seams are the `static::` methods on each base.
 *
 * Loading is split so a front-end request that only needs the runtime does not
 * pay for the 2,000-line settings controller.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'fkmcp_load_runtime' ) ) {
	/** Schema, formatting, links, permissions, rate limiting and the audit log. */
	function fkmcp_load_runtime() {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}
		$loaded = true;

		$dir = __DIR__ . '/runtime';

		require_once $dir . '/trait-fkmcp-host-classes.php';
		require_once $dir . '/class-fkmcp-schema.php';
		require_once $dir . '/class-fkmcp-format.php';
		require_once $dir . '/class-fkmcp-links.php';
		require_once $dir . '/class-fkmcp-permissions.php';
		require_once $dir . '/class-fkmcp-rate-limit.php';
		require_once $dir . '/class-fkmcp-log.php';
	}

	/** The FunnelKit AI settings screen: its status provider and REST controller. */
	function fkmcp_load_admin() {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}
		$loaded = true;

		fkmcp_load_runtime();

		require_once __DIR__ . '/runtime/admin/class-fkmcp-admin.php';
		require_once __DIR__ . '/runtime/admin/class-fkmcp-settings-api.php';
	}
}
