<?php

defined( 'ABSPATH' ) || exit;

/**
 * What a host's settings screen needs from the connector. Callers check
 * manage_options and the REST nonce; this class does not. Ownership it does
 * check: a connection belongs to the administrator who approved it, so each
 * one lists and ends their own and nobody else's.
 */
final class FKMCP_Connector_Admin {
	/** FunnelKit AI's MCP URL, the same for every store: an AI app adds it once and FunnelKit AI asks which store at sign-in, or connects another one on request. */
	const CONNECT_URL = FKMCP_Connector_Clients::GATEWAY . '/mcp';

	/**
	 * One administrator's connections, newest first - by default whoever is
	 * asking. Another administrator's connection is not theirs to see.
	 *
	 * @param int $user_id 0 for the current user.
	 *
	 * @return array
	 */
	public static function connections( $user_id = 0 ) {
		$user_id = (int) $user_id > 0 ? (int) $user_id : get_current_user_id();

		if ( $user_id < 1 || (int) get_option( 'fkai_db_version', 0 ) < 1 ) {
			return array();
		}

		$out = array();

		$now = time();

		foreach ( FKMCP_Connector_Grants::for_user( $user_id ) as $row ) {
			// Dead until the weekly purge deletes it: not a connection any more.
			if ( (int) $row->refresh_expires_at <= $now || null === FKMCP_Connector_Clients::get( $row->client_id ) ) {
				continue;
			}

			$user  = get_userdata( (int) $row->user_id );
			$out[] = array(
				'id'            => (int) $row->id,
				'user'          => array(
					'id'    => (int) $row->user_id,
					'name'  => $user ? (string) $user->display_name : '',
					'login' => $user ? (string) $user->user_login : '',
				),
				'client_host'   => FKMCP_Connector_Clients::host( $row->client_id ),
				'account_email' => isset( $row->account_email ) ? $row->account_email : null,
				'scopes'        => isset( $row->scopes ) ? (string) $row->scopes : 'mcp',
				'created_at'    => (int) $row->created_at,
				'last_used_at'  => null === $row->last_used_at ? null : (int) $row->last_used_at,
			);
		}

		return $out;
	}

	/**
	 * Ends one of this administrator's own connections; another's is not theirs
	 * to end. False when no connection of theirs carries that id.
	 *
	 * @param int $id      Connection id from self::connections().
	 * @param int $user_id 0 for the current user.
	 *
	 * @return bool
	 */
	public static function revoke( $id, $user_id = 0 ) {
		$user_id = (int) $user_id > 0 ? (int) $user_id : get_current_user_id();

		return $user_id > 0 && FKMCP_Connector_Grants::revoke_own( (int) $id, $user_id );
	}

	/** The site-wide switch going off, not an administrator ending a connection: every grant goes. */
	public static function revoke_all() {
		FKMCP_Connector_Grants::revoke_all();
	}

	/**
	 * True when the stored value changed. Turning off ends every connection.
	 * Turning on needs the Abilities API (WordPress 6.9+): without it nothing could serve the tools.
	 *
	 * @return bool|WP_Error
	 */
	public static function set_enabled( $on ) {
		if ( $on && ! function_exists( 'wp_register_ability' ) ) {
			return new WP_Error( 'fkmcp_abilities_missing', __( 'AI connections need the WordPress Abilities API. Update WordPress to 6.9 or newer, then turn them on.', 'wp-marketing-automations' ), array( 'status' => 400 ) );
		}

		$changed = update_option( FKMCP_Connector::OPT_ENABLED, $on ? 'yes' : 'no', true );

		if ( ! $on && (int) get_option( 'fkai_db_version', 0 ) >= 1 ) {
			FKMCP_Connector_Grants::revoke_all();
		}

		return (bool) $changed;
	}

	public static function store() {
		return array(
			'site_id'     => null,
			'site_home'   => null,
			'connect_url' => self::CONNECT_URL,
			// What the person gives FunnelKit AI to name this store, at sign-in or in a connect-site prompt.
			'store_url'   => untrailingslashit( home_url() ),
		);
	}
}
