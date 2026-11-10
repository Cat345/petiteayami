<?php
/**
 * FunnelKit AI connector hooks that must run where the MCP module never boots
 * (wp-cron, the front end, core REST): the weekly grant purge, the end of a removed
 * or re-roled user's connections.
 * FunnelKit Automations registers the same hooks; when it does, these step aside.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_FKAI_Hooks' ) ) {
	final class WFFN_FKAI_Hooks {
		public static function init() {
			add_action( 'fkai_cleanup', array( __CLASS__, 'purge_expired_grants' ) );
			foreach ( array( 'deleted_user', 'remove_user_from_blog', 'set_user_role', 'remove_user_role' ) as $hook ) {
				add_action( $hook, array( __CLASS__, 'revoke_user_grants' ) );
			}
		}

		/** FunnelKit Automations loads after this plugin, so ask at fire time. */
		private static function automations_handles( $method ) {
			return class_exists( 'BWFAN_Common' ) && method_exists( 'BWFAN_Common', $method );
		}

		public static function purge_expired_grants() {
			if ( ! self::automations_handles( 'fkai_purge_expired_grants' ) && self::load_grants() ) {
				FKMCP_Connector_Grants::purge_expired( time() );
			}
		}

		public static function revoke_user_grants( $user_id ) {
			if ( ! self::automations_handles( 'fkai_revoke_user_grants' ) && self::load_grants() ) {
				FKMCP_Connector_Grants::revoke_user( (int) $user_id );
			}
		}

		/** The grants class once the table exists; never while the standalone connector plugin serves the same table. */
		private static function load_grants() {
			if ( defined( 'FKAI_VERSION' ) || (int) get_option( 'fkai_db_version', 0 ) < 1 ) {
				return false;
			}

			// The purge asks the client list which grants are still trusted.
			if ( ! class_exists( 'FKMCP_Connector_Clients' ) ) {
				require_once WFFN_PLUGIN_DIR . '/includes/mcp/module/connector/class-fkmcp-connector-clients.php';
			}

			if ( ! class_exists( 'FKMCP_Connector_Grants' ) ) {
				require_once WFFN_PLUGIN_DIR . '/includes/mcp/module/connector/class-fkmcp-connector-grants.php';
			}

			return true;
		}

		/** The connector reschedules the purge on the next admin load if another FunnelKit plugin still serves it. */
		public static function deactivate() {
			wp_clear_scheduled_hook( 'fkai_cleanup' );
		}
	}
}
