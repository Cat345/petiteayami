<?php
/**
 * Everything the MCP feature stores, removed when a host plugin is deleted.
 *
 * Included from each host's uninstall.php. Deleting one plugin must not take
 * the data another one is still using, so the options and the signing secret
 * are only removed once no other bundled copy of this module remains.
 *
 * @package FunnelKit MCP module
 */

/*
 * This directory is the single source; Funnel Builder syncs it with
 * bin/sync-mcp-module.sh. Removes the leftover fkmcp_ajax_unresolvable option.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'fkmcp_uninstall' ) ) {
	/**
	 * @param string $plugin_dir Directory of the plugin being deleted, so its own copy is not counted.
	 *
	 * @return void
	 */
	function fkmcp_uninstall( $plugin_dir = '' ) {
		global $wpdb;

		foreach ( array_keys( fkmcp_uninstall_sessions() ) as $user_id ) {
			fkmcp_uninstall_sessions_for( (int) $user_id );
		}

		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( 'fkmcp_allow_writes', 'fkmcp_app_password', 'fkmcp_last_test' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall, no cache to prime.

		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_fkmcp\_%' OR option_name LIKE '\_transient\_timeout\_fkmcp\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall, no cache to prime.

		if ( fkmcp_uninstall_other_copy_remains( $plugin_dir ) ) {
			return;
		}

		// FunnelKit AI connector data, only once no copy of the module remains, and
		// never while the standalone connector plugin (same data names) still uses it.
		if ( ! defined( 'FKAI_VERSION' ) ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'fkai_grants' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall; table name from $wpdb->prefix.
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_fkai\_%' OR option_name LIKE '\_transient\_timeout\_fkai\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall, no cache to prime.

			foreach ( array( 'fkai_enabled', 'fkai_db_version', 'fkai_site_id', 'fkai_site_home' ) as $option ) {
				delete_option( $option );
			}

			wp_clear_scheduled_hook( 'fkai_cleanup' );
		}

		foreach ( array( 'fkmcp_enabled', 'fkmcp_oauth_enabled', 'fkmcp_oauth_clients', 'fkmcp_oauth_dcr_clients', 'fkmcp_oauth_jwt_secret', 'fkmcp_adapter_release', 'fkmcp_require_review', 'fkmcp_ajax_unresolvable' ) as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Users holding an Application Password this server minted.
	 *
	 * @return array<int, int>
	 */
	function fkmcp_uninstall_sessions() {
		global $wpdb;

		$meta_key = defined( 'WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS' ) ? WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS : '_application_passwords';
		$clients  = array_merge( array_keys( (array) get_option( 'fkmcp_oauth_clients', array() ) ), array_keys( (array) get_option( 'fkmcp_oauth_dcr_clients', array() ) ) );

		if ( empty( $clients ) ) {
			return array();
		}

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall, table name only.

		return array_flip( array_map( 'intval', (array) $ids ) );
	}

	/**
	 * Deletes one user's Application Passwords that belong to an OAuth client.
	 *
	 * @param int $user_id User to clean up.
	 *
	 * @return void
	 */
	function fkmcp_uninstall_sessions_for( $user_id ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return;
		}

		$clients = array_merge( (array) get_option( 'fkmcp_oauth_clients', array() ), (array) get_option( 'fkmcp_oauth_dcr_clients', array() ) );

		foreach ( WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			$app_id = isset( $item['app_id'] ) ? (string) $item['app_id'] : '';

			if ( '' !== $app_id && isset( $clients[ $app_id ] ) ) {
				WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );
			}
		}
	}

	/**
	 * Whether another active plugin still bundles this module.
	 *
	 * @param string $plugin_dir Directory of the plugin being deleted.
	 *
	 * @return bool
	 */
	function fkmcp_uninstall_other_copy_remains( $plugin_dir ) {
		$plugin_dir = untrailingslashit( (string) $plugin_dir );
		$plugins    = (array) get_option( 'active_plugins', array() );

		// A network-activated copy is listed only in the network option.
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		foreach ( $plugins as $plugin ) {
			$dir = untrailingslashit( WP_PLUGIN_DIR . '/' . dirname( (string) $plugin ) );

			if ( '' !== $plugin_dir && $dir === $plugin_dir ) {
				continue;
			}

			if ( is_readable( $dir . '/includes/mcp/module/class-fkmcp-module.php' ) ) {
				return true;
			}
		}

		return false;
	}
}
