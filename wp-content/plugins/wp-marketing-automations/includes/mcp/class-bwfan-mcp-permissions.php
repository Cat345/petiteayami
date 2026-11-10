<?php

defined( 'ABSPATH' ) || exit;

/**
 * The only auth layer: dispatch bypasses controller permission callbacks, so
 * transport_gate() + per-ability can_read() are what stand between a request
 * and the data.
 */
class BWFAN_MCP_Permissions {

	/** Per-user opt-out of write abilities. */
	const META_ALLOW_WRITES = 'fkmcp_allow_writes';
	const CONNECTED_META = 'fkmcp_connected';

	/**
	 * @param WP_REST_Request|null $request
	 *
	 * @return bool|WP_Error The adapter logs a WP_Error and fails closed, so a
	 *                       refused flood says why in the site error log while
	 *                       the client still just sees a denial.
	 */
	public static function transport_gate( $request = null ) {
		if ( ! is_user_logged_in() || ! self::can_read() ) {
			return false;
		}

		// Before note_first_connection(): a refused request is not a connection.
		$limited = BWFAN_MCP_Rate_Limit::check_transport();

		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		self::note_first_connection( $request );

		return true;
	}

	/**
	 * Logs the first time a credential is used against the MCP endpoint, once
	 * per Application Password.
	 *
	 * Cookie-authenticated requests carry no Application Password: that is an
	 * admin's browser, not a client, and is not a connection worth recording.
	 *
	 * @param WP_REST_Request|null $request
	 *
	 * @return void
	 */
	private static function note_first_connection( $request = null ) {
		if ( ! function_exists( 'rest_get_authenticated_app_password' ) ) {
			return;
		}

		$uuid = rest_get_authenticated_app_password();

		if ( empty( $uuid ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$seen    = get_user_meta( $user_id, self::CONNECTED_META, true );
		$seen    = is_array( $seen ) ? $seen : array();

		if ( isset( $seen[ $uuid ] ) ) {
			return;
		}

		// ponytail: one entry per Application Password ever used, never pruned.
		// A user holds a handful of these; add a cap if that stops being true.
		$seen[ $uuid ] = time();

		update_user_meta( $user_id, self::CONNECTED_META, $seen );

		BWFAN_MCP_Log::event(
			sprintf(
				'CONNECTED%s app_password_uuid=%s ip=%s',
				self::client_label( $request ),
				$uuid,
				isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
			)
		);
	}

	/**
	 * The client's own name, when it introduced itself: a client opens with
	 * initialize, whose params carry clientInfo {name, version}. Anything else
	 * - a bare tools/list, a probe, a batch - has nobody to name, and the
	 * credential still identifies the connection.
	 *
	 * The name is whatever the caller typed, on its way to a log file, so it is
	 * sanitised (newlines out, or it could forge a line of its own), stripped of
	 * the quotes that delimit the field, and capped.
	 *
	 * @param WP_REST_Request|null $request
	 *
	 * @return string Empty, or ' client="..."'.
	 */
	private static function client_label( $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_json_params' ) ) {
			return '';
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) || ! isset( $body['method'] ) || 'initialize' !== $body['method'] ) {
			return '';
		}

		$info = isset( $body['params']['clientInfo'] ) && is_array( $body['params']['clientInfo'] ) ? $body['params']['clientInfo'] : array();
		$name = isset( $info['name'] ) && is_scalar( $info['name'] ) ? trim( (string) $info['name'] ) : '';

		if ( '' === $name ) {
			return '';
		}

		$version = isset( $info['version'] ) && is_scalar( $info['version'] ) ? trim( (string) $info['version'] ) : '';

		$label = sanitize_text_field( $name . ( '' !== $version ? ' ' . $version : '' ) );
		$label = str_replace( '"', '', $label );

		return sprintf( ' client="%s"', mb_substr( $label, 0, 60 ) );
	}

	public static function can_read() {
		$caps = class_exists( 'BWFAN_Common' ) ? (array) BWFAN_Common::access_capabilities() : array( 'manage_options' );

		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Per-user grant: writes apply only to the WordPress user the AI client
	 * authenticates as (their Application Password), not site-wide.
	 *
	 * Writes are on by default for a user who passes the read check; the meta
	 * only ever records an opt-out.
	 */
	public static function can_write() {
		if ( ! self::can_read() ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( 'no' === get_user_meta( $user_id, self::META_ALLOW_WRITES, true ) ) {
			return false;
		}

		return (bool) apply_filters( 'fkmcp_allow_writes', true, $user_id );
	}
}
