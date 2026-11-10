<?php
/**
 * Bearer authentication on the MCP route, and nothing else: a live grant signs
 * the request in as the user who consented. Any other route never sees a user
 * from a token. The 401 always carries the RFC 9728 challenge.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Bearer {
	const TOKEN_RE = '/^[A-Za-z0-9\-._~+\/]+=*$/';

	/** @var bool A Bearer credential was on this request. */
	private static $presented = false;

	/** @var bool The credential did not resolve to a live grant. */
	private static $failed = false;

	/** @var bool The credential resolved and the request was signed in by it. */
	private static $signed_in = false;

	/** @var bool The store could not check the token (DB read error). */
	private static $unavailable = false;

	/** @var bool guard_route already ran for this request. */
	private static $guarded = false;

	/** @var bool resolve() is inside its capability check. */
	private static $checking = false;

	public static function enabled() {
		return 'yes' === get_option( 'fkai_enabled', 'no' );
	}

	public static function disabled_response() {
		return array(
			'status'  => 403,
			'headers' => array( 'Cache-Control' => 'no-store' ),
			'json'    => array(
				'error'             => 'ai_disabled',
				'error_description' => __( 'AI connections are switched off in FunnelKit settings.', 'wp-marketing-automations' ),
			),
		);
	}

	public static function presented() {
		return self::$presented;
	}

	public static function failed() {
		return self::$failed;
	}

	public static function signed_in() {
		return self::$signed_in;
	}

	public static function unavailable() {
		return self::$unavailable;
	}

	public static function reset() {
		self::$presented   = false;
		self::$failed      = false;
		self::$signed_in   = false;
		self::$unavailable = false;
		self::$guarded     = false;
		self::$checking    = false;
	}

	/** determine_current_user (priority 25): only the MCP route, only with a Bearer. */
	public static function authenticate( $user_id ) {
		if ( ! empty( $user_id ) || self::other_entry_point() || ! FKMCP_Connector_Urls::is_mcp_request( $_SERVER, $_GET, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- reads the request path only, to pick the route; nothing from the request is acted on here.
			return $user_id;
		}

		return self::resolve( $user_id, $_SERVER, time() );
	}

	/** Admin, AJAX, cron, XML-RPC or any script other than index.php: never a REST request. */
	private static function other_entry_point() {
		return is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ! FKMCP_Connector_Urls::is_front_controller( $_SERVER );
	}

	/** The decision behind authenticate(), with its inputs explicit. */
	public static function resolve( $user_id, array $server, $now ) {
		// A capability filter that reads the current user re-enters determine_current_user; answer that call without a lookup.
		if ( ! empty( $user_id ) || self::$checking ) {
			return $user_id;
		}

		$header = '';
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
			if ( ! empty( $server[ $key ] ) ) {
				$header = (string) $server[ $key ];
				break;
			}
		}

		if ( 0 !== stripos( $header, 'bearer' ) ) {
			return $user_id;
		}

		$token           = trim( substr( $header, 6 ) );
		self::$presented = ( strlen( $token ) > 0 );

		// Switched off there are no grants to look up, but a presented token is still
		// answered as invalid, so the store's connection check can tell that the
		// Authorization header reaches WordPress before the first client connects.
		if ( 0 === strlen( $token ) || ! self::enabled() ) {
			return $user_id;
		}

		$row = ( 1 === preg_match( self::TOKEN_RE, $token ) ) ? FKMCP_Connector_Grants::by_access( $token, $now ) : null;

		if ( false === $row ) {
			self::$unavailable = true;

			return $user_id;
		}

		if ( ! $row || ! get_userdata( (int) $row->user_id ) || ! self::is_admin_user( (int) $row->user_id ) ) {
			self::$failed = true;

			return $user_id;
		}

		FKMCP_Connector_Grants::touch( (int) $row->id, $now, $row->last_used_at );

		self::$signed_in = true;

		return (int) $row->user_id;
	}

	private static function is_admin_user( $user_id ) {
		self::$checking = true;

		try {
			return user_can( $user_id, 'manage_options' );
		} finally {
			self::$checking = false;
		}
	}

	/** rest_authentication_errors (priority 50): a dead token is a 401 with a reason. */
	public static function auth_errors( $result ) {
		if ( null !== $result ) {
			return $result;
		}

		if ( self::$unavailable ) {
			return new WP_Error( 'fkai_unavailable', __( 'The store could not check the token. Try again shortly.', 'wp-marketing-automations' ), array( 'status' => 503 ) );
		}

		if ( ! self::$failed ) {
			return $result;
		}

		return new WP_Error( 'fkai_invalid_token', __( 'The access token is invalid or has expired.', 'wp-marketing-automations' ), array( 'status' => 401 ) );
	}

	/** rest_pre_dispatch: a bearer sign-in is valid for the MCP route only, whatever route core resolved. */
	public static function guard_route( $result, $server, $request ) {
		if ( null !== $result || self::$guarded ) {
			return $result;
		}
		self::$guarded = true;
		if ( ! self::$signed_in || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}
		if ( FKMCP_Connector_Urls::is_bearer_route( $request->get_route() ) ) {
			return $result;
		}
		wp_set_current_user( 0 );
		return new WP_Error( 'fkai_wrong_route', __( 'Bearer tokens are accepted only on the MCP endpoint.', 'wp-marketing-automations' ), array( 'status' => 401 ) );
	}

	/**
	 * rest_authentication_errors (priority 99): the authorize and consent routes
	 * are opened by a browser with a login cookie and no REST nonce. Answering
	 * "authenticated" here stops core's cookie check (priority 100) from logging
	 * the user out of the request. CSRF on the consent POST is the plugin's nonce.
	 */
	public static function keep_cookie_user( $result ) {
		if ( null !== $result ) {
			return $result;
		}

		return ( FKMCP_Connector_Urls::is_browser_route( $_SERVER, $_GET, $_POST ) && is_user_logged_in() ) ? true : $result; // phpcs:ignore WordPress.Security.NonceVerification, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- reads the request path only, to pick the route; nothing from the request is acted on here.
	}

	/** rest_post_dispatch: every 401 from the MCP route carries the challenge. */
	public static function challenge( $response, $server, $request ) {
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_status' ) || 401 !== (int) $response->get_status() ) {
			return $response;
		}

		if ( ! FKMCP_Connector_Urls::is_mcp_request( $_SERVER, $_GET, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- reads the request path only, to pick the route; nothing from the request is acted on here.
			return $response;
		}

		$response->header( 'WWW-Authenticate', self::challenge_header( self::$presented ) );

		return $response;
	}

	public static function challenge_header( $presented ) {
		$value = 'Bearer realm="FunnelKit AI", resource_metadata="' . FKMCP_Connector_Urls::prm_url() . '"';

		if ( $presented ) {
			$value .= ', error="invalid_token", error_description="The access token is invalid or has expired."';
		}

		return $value;
	}

	/** The MCP route's own 401 when no user was signed in. */
	public static function unauthorized() {
		return array(
			'status'  => 401,
			'headers' => array(
				'WWW-Authenticate' => self::challenge_header( self::$presented ),
				'Cache-Control'    => 'no-store',
			),
			'json'    => array(
				'error'             => self::$presented ? 'invalid_token' : 'unauthorized',
				'error_description' => self::$presented ? __( 'The access token is invalid or has expired.', 'wp-marketing-automations' ) : __( 'A bearer token is required.', 'wp-marketing-automations' ),
			),
		);
	}
}
