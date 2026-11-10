<?php
/**
 * The browser half of OAuth: /oauth/authorize validates the gateway's request
 * and shows the consent page; /oauth/consent turns Allow into a single-use
 * authorization code. Only pinned clients get past the first check, and every
 * redirect back carries iss (RFC 9207).
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Authorize {
	const PENDING_TTL  = 600;
	const CODE_TTL     = 600;
	const STATE_MAX    = 512;
	const CHALLENGE_RE = '/^[A-Za-z0-9_-]{43}$/';

	public static function authorize( array $params, $user_id, $request_url, $now ) {
		$client = FKMCP_Connector_Clients::get( self::param( $params, 'client_id' ) );

		if ( ! $client ) {
			return self::page( 400, __( 'Unknown OAuth client.', 'wp-marketing-automations' ) );
		}

		$redirect = self::param( $params, 'redirect_uri' );

		// The redirect URI is validated before it is used for anything (RFC 6749 §10.15).
		if ( ! hash_equals( $client['redirect_uri'], $redirect ) ) {
			return self::page( 400, __( 'redirect_uri does not match the registered value.', 'wp-marketing-automations' ) );
		}

		$state = self::param( $params, 'state' );

		if ( strlen( $state ) > self::STATE_MAX ) {
			return self::deny( $redirect, 'invalid_request', '' );
		}

		if ( 'code' !== self::param( $params, 'response_type' ) ) {
			return self::deny( $redirect, 'unsupported_response_type', $state );
		}

		$challenge = self::param( $params, 'code_challenge' );

		if ( 1 !== preg_match( self::CHALLENGE_RE, $challenge ) || 'S256' !== self::param( $params, 'code_challenge_method' ) ) {
			return self::deny( $redirect, 'invalid_request', $state );
		}

		if ( ! FKMCP_Connector_Urls::resource_matches( self::param( $params, 'resource' ) ) ) {
			return self::deny( $redirect, 'invalid_target', $state );
		}

		if ( (int) $user_id < 1 ) {
			return self::redirect( wp_login_url( $request_url ) );
		}

		// Gateway-authored and display only: also stored on the grant, never used for a decision. fkai_client is accepted and not shown.
		$account = self::param( $params, 'fkai_account' );
		$account = ( strlen( $account ) <= 254 && is_email( $account ) ) ? $account : '';

		$blocked = self::blocked( (int) $user_id );

		// Nothing to approve: no pending record, no form.
		if ( '' !== $blocked ) {
			return self::consent_page( '', (int) $user_id, $request_url, $account, $blocked );
		}

		$handle = bin2hex( random_bytes( 16 ) );
		set_transient(
			self::pending_key( $handle ),
			array(
				'client_id'      => $client['client_id'],
				'redirect_uri'   => $redirect,
				'state'          => $state,
				'code_challenge' => $challenge,
				'account_email'  => $account,
				'created'        => (int) $now,
			),
			self::PENDING_TTL
		);

		return self::consent_page( $handle, (int) $user_id, $request_url, $account );
	}

	/** Why this user cannot approve, or '' when they can. consent() enforces the same on the POST. */
	private static function blocked( $user_id ) {
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			return __( 'Log in as a store administrator to approve this connection', 'wp-marketing-automations' );
		}

		if ( count( FKMCP_Connector_Mcp::tools_for_user() ) < 1 ) {
			return __( "This account can't use FunnelKit AI: it has no FunnelKit tools. Sign in with an account that manages FunnelKit.", 'wp-marketing-automations' );
		}

		return '';
	}

	public static function consent( array $params, $user_id, $now ) {
		if ( (int) $user_id < 1 ) {
			return self::page( 400, __( 'You must be logged in to approve a connection.', 'wp-marketing-automations' ) );
		}

		$handle = self::param( $params, 'handle' );

		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $handle ) ) {
			return self::page( 400, __( 'Missing request handle.', 'wp-marketing-automations' ) );
		}

		if ( ! wp_verify_nonce( self::param( $params, '_fkai_nonce' ), 'fkai_consent_' . $handle ) ) {
			return self::page( 400, __( 'This form has expired. Go back to Claude or ChatGPT and connect again.', 'wp-marketing-automations' ) );
		}

		$key     = self::pending_key( $handle );
		$pending = get_transient( $key );

		// delete_transient() answers true to one caller when two submissions race.
		if ( ! is_array( $pending ) || ! delete_transient( $key ) ) {
			return self::page( 400, __( 'This sign-in request has expired. Go back to Claude or ChatGPT and connect again.', 'wp-marketing-automations' ) );
		}

		$state = (string) $pending['state'];

		if ( 'allow' !== self::param( $params, 'decision' ) ) {
			return self::deny( $pending['redirect_uri'], 'access_denied', $state );
		}

		if ( ! user_can( (int) $user_id, 'manage_options' ) ) {
			return self::deny( $pending['redirect_uri'], 'access_denied', $state );
		}

		if ( count( FKMCP_Connector_Mcp::tools_for_user() ) < 1 ) {
			return self::deny( $pending['redirect_uri'], 'access_denied', $state );
		}

		// Approving an AI client is the opt-in: AI connections switch on with the first one.
		if ( ! FKMCP_Connector_Bearer::enabled() && is_wp_error( FKMCP_Connector_Admin::set_enabled( true ) ) ) {
			return self::deny( $pending['redirect_uri'], 'access_denied', $state, 'ai_disabled' );
		}

		$code = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		set_transient(
			self::code_key( $code ),
			array(
				'user_id'        => (int) $user_id,
				'client_id'      => $pending['client_id'],
				'redirect_uri'   => $pending['redirect_uri'],
				'code_challenge' => $pending['code_challenge'],
				'account_email'  => isset( $pending['account_email'] ) ? (string) $pending['account_email'] : '',
				'created'        => (int) $now,
			),
			self::CODE_TTL
		);

		return self::redirect( self::callback_url( $pending['redirect_uri'], array( 'code' => $code, 'state' => $state ) ) );
	}

	/** Consumes an authorization code; its record, or null when unknown, expired or used. */
	public static function take_code( $code ) {
		if ( ! is_string( $code ) || '' === $code || strlen( $code ) > 128 ) {
			return null;
		}

		$key  = self::code_key( $code );
		$data = get_transient( $key );

		if ( ! is_array( $data ) || ! delete_transient( $key ) ) {
			return null;
		}

		return $data;
	}

	public static function pending_key( $handle ) {
		return 'fkai_pending_' . $handle;
	}

	public static function code_key( $code ) {
		return 'fkai_code_' . hash( 'sha256', (string) $code );
	}

	/** Redirect-back URL: the given params (empty ones dropped) plus iss, RFC 3986 encoded. */
	public static function callback_url( $redirect_uri, array $params ) {
		$params = array_filter(
			$params,
			function ( $v ) {
				return '' !== (string) $v;
			}
		);
		$params['iss'] = FKMCP_Connector_Urls::issuer();

		return $redirect_uri . ( false === strpos( $redirect_uri, '?' ) ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	private static function param( array $params, $key ) {
		return ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) ) ? $params[ $key ] : '';
	}

	private static function deny( $redirect_uri, $error, $state, $description = '' ) {
		$params = array( 'error' => $error );

		if ( '' !== $description ) {
			$params['error_description'] = $description;
		}

		$params['state'] = $state;

		return self::redirect( self::callback_url( $redirect_uri, $params ) );
	}

	private static function redirect( $url ) {
		return array(
			'status'   => 302,
			'headers'  => array( 'Cache-Control' => 'no-store' ),
			'redirect' => $url,
		);
	}

	private static function page( $status, $message ) {
		return array(
			'status'  => $status,
			'headers' => self::html_headers(),
			'html'    => self::render( 'error', array( 'message' => $message ) ),
		);
	}

	private static function consent_page( $handle, $user_id, $request_url, $account_email = '', $blocked = '' ) {
		$user  = get_userdata( $user_id );
		$roles = $user ? array_values( (array) $user->roles ) : array();
		$names = wp_roles()->role_names;
		$role  = '';
		if ( $roles ) {
			$role = isset( $names[ $roles[0] ] ) ? translate_user_role( $names[ $roles[0] ] ) : (string) $roles[0];
		}

		$home = wp_parse_url( home_url() );
		$data = array(
			'store_host'    => ( isset( $home['host'] ) ? $home['host'] : '' ) . ( isset( $home['path'] ) ? untrailingslashit( $home['path'] ) : '' ),
			'user_name'     => self::user_name( $user ),
			'user_email'    => $user ? (string) $user->user_email : '',
			'user_role'     => $role,
			'account_email' => (string) $account_email,
			'blocked'       => (string) $blocked,
			'handle'        => (string) $handle,
			'nonce'         => '' === $handle ? '' : wp_create_nonce( 'fkai_consent_' . $handle ),
			'consent_url'   => FKMCP_Connector_Urls::endpoint( 'consent' ),
			'logout_url'    => wp_logout_url( $request_url ),
		);

		return array(
			'status'  => 200,
			'headers' => self::html_headers(),
			'html'    => self::render( 'consent', $data ),
		);
	}

	/** Who is being granted: the display name, else first and last name, else the login. */
	private static function user_name( $user ) {
		if ( ! $user ) {
			return '';
		}

		$name = trim( (string) $user->display_name );

		if ( '' === $name ) {
			$name = trim( ( isset( $user->first_name ) ? $user->first_name : '' ) . ' ' . ( isset( $user->last_name ) ? $user->last_name : '' ) );
		}

		return '' !== $name ? $name : (string) $user->user_login;
	}

	/**
	 * No form-action directive: Chromium applies it to every redirect that follows the consent POST,
	 * and that chain ends at the AI app's own callback (claude.ai, chatgpt.com, a loopback port),
	 * which the store cannot know. The form's action is server-generated and the page has no script.
	 */
	private static function html_headers() {
		return array(
			'Content-Type'            => 'text/html; charset=utf-8',
			'Cache-Control'           => 'no-store',
			'X-Frame-Options'         => 'DENY',
			'Referrer-Policy'         => 'no-referrer',
			'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'",
		);
	}

	private static function render( $view, array $data ) {
		ob_start();
		include __DIR__ . '/views/' . $view . '.php';

		return (string) ob_get_clean();
	}
}
