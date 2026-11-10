<?php
/**
 * /oauth/token and /oauth/revoke for one public client. A code becomes a grant
 * once PKCE, client and redirect match; a refresh rotates through FKMCP_Connector_Grants,
 * which keeps the 60 s grace. invalid_grant is the only answer that ends a
 * connection on the gateway side, so it is given only when it should.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Token {
	const VERIFIER_RE = '/^[A-Za-z0-9._~-]{43,128}$/';

	public static function token( array $params, $now ) {
		if ( ! FKMCP_Connector_Bearer::enabled() ) {
			return FKMCP_Connector_Bearer::disabled_response();
		}

		$client = FKMCP_Connector_Clients::get( self::param( $params, 'client_id' ) );

		if ( ! $client ) {
			return self::error( 400, 'invalid_client', __( 'Unknown client.', 'wp-marketing-automations' ) );
		}

		$resource = self::param( $params, 'resource' );

		if ( '' !== $resource && ! FKMCP_Connector_Urls::resource_matches( $resource ) ) {
			return self::error( 400, 'invalid_target', __( 'resource does not name this MCP endpoint.', 'wp-marketing-automations' ) );
		}

		switch ( self::param( $params, 'grant_type' ) ) {
			case 'authorization_code':
				return self::code_grant( $params, $client, (int) $now );
			case 'refresh_token':
				return self::refresh_grant( $params, $client, (int) $now );
			default:
				return self::error( 400, 'unsupported_grant_type' );
		}
	}

	private static function code_grant( array $params, array $client, $now ) {
		$code     = self::param( $params, 'code' );
		$verifier = self::param( $params, 'code_verifier' );

		if ( '' === $code ) {
			return self::error( 400, 'invalid_request', __( 'code is required.', 'wp-marketing-automations' ) );
		}

		if ( '' === $verifier ) {
			return self::error( 400, 'invalid_request', __( 'code_verifier is required.', 'wp-marketing-automations' ) );
		}

		$record = FKMCP_Connector_Authorize::take_code( $code );

		if ( ! $record ) {
			return self::error( 400, 'invalid_grant', __( 'Code is invalid, expired or already used.', 'wp-marketing-automations' ) );
		}

		if ( $record['client_id'] !== $client['client_id'] || $record['redirect_uri'] !== self::param( $params, 'redirect_uri' ) ) {
			return self::error( 400, 'invalid_grant', __( 'Code was issued to a different client or redirect URI.', 'wp-marketing-automations' ) );
		}

		if ( ! self::pkce_ok( $verifier, (string) $record['code_challenge'] ) ) {
			return self::error( 400, 'invalid_grant', __( 'PKCE code_verifier does not match.', 'wp-marketing-automations' ) );
		}

		if ( ! get_userdata( (int) $record['user_id'] ) ) {
			return self::error( 400, 'invalid_grant', __( 'The approving user no longer exists.', 'wp-marketing-automations' ) );
		}

		if ( ! user_can( (int) $record['user_id'], 'manage_options' ) ) {
			return self::error( 400, 'invalid_grant', __( 'Only administrators can connect this store to FunnelKit AI.', 'wp-marketing-automations' ) );
		}

		$pair = FKMCP_Connector_Grants::create( (int) $record['user_id'], $client['client_id'], $now, isset( $record['account_email'] ) ? (string) $record['account_email'] : '' );

		return false === $pair ? self::error( 500, 'server_error', __( 'Could not store the connection. Try again.', 'wp-marketing-automations' ) ) : self::ok( $pair );
	}

	private static function refresh_grant( array $params, array $client, $now ) {
		$token = self::param( $params, 'refresh_token' );

		if ( 0 === strlen( $token ) ) {
			return self::error( 400, 'invalid_request', __( 'refresh_token is required.', 'wp-marketing-automations' ) );
		}

		$pair = FKMCP_Connector_Grants::refresh( $token, $now, $client['client_id'] );

		// false is a temporary failure: never invalid_grant, which would end the connection.
		if ( false === $pair ) {
			return self::error( 500, 'server_error', __( 'Could not refresh the connection. Try again.', 'wp-marketing-automations' ) );
		}

		return $pair ? self::ok( $pair ) : self::error( 400, 'invalid_grant', __( 'Refresh token is invalid, expired or revoked.', 'wp-marketing-automations' ) );
	}

	/** RFC 7009: delete the grant behind any of its tokens; always 200. */
	public static function revoke( array $params ) {
		$token = self::param( $params, 'token' );

		if ( 0 === strlen( $token ) ) {
			return self::error( 400, 'invalid_request', __( 'token is required.', 'wp-marketing-automations' ) );
		}

		FKMCP_Connector_Grants::revoke_token( $token );

		return array(
			'status'  => 200,
			'headers' => array( 'Cache-Control' => 'no-store' ),
			'json'    => new stdClass(),
		);
	}

	public static function pkce_ok( $verifier, $challenge ) {
		if ( 1 !== preg_match( self::VERIFIER_RE, (string) $verifier ) ) {
			return false;
		}

		$computed = rtrim( strtr( base64_encode( hash( 'sha256', (string) $verifier, true ) ), '+/', '-_' ), '=' );

		return hash_equals( (string) $challenge, $computed );
	}

	private static function param( array $params, $key ) {
		return ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) ) ? $params[ $key ] : '';
	}

	private static function ok( array $pair ) {
		return array(
			'status'  => 200,
			'headers' => array( 'Cache-Control' => 'no-store' ),
			'json'    => $pair,
		);
	}

	private static function error( $status, $error, $description = '' ) {
		$json = array( 'error' => $error );

		if ( '' !== $description ) {
			$json['error_description'] = $description;
		}

		return array(
			'status'  => $status,
			'headers' => array( 'Cache-Control' => 'no-store' ),
			'json'    => $json,
		);
	}
}
