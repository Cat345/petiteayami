<?php
/**
 * The REST glue: registers the nine routes, turns WP_REST_Request into the
 * plain request array the endpoint classes take, and their response arrays
 * into WP_REST_Response (JSON), a redirect, or a printed HTML page.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Http {

	/** rest_api_init, priority 20. */
	public static function register_routes() {
		$routes = array(
			array( 'mcp', 'POST', 'mcp' ),
			array( 'mcp/abilities', 'GET', 'abilities' ),
			array( 'mcp/abilities/(?P<vendor>[a-z0-9][a-z0-9_-]*)/(?P<ability>[a-z0-9][a-z0-9_-]*)', 'GET', 'ability' ),
			array( 'oauth/authorize', 'GET', 'authorize' ),
			array( 'oauth/consent', 'POST', 'consent' ),
			array( 'oauth/token', 'POST', 'token' ),
			array( 'oauth/revoke', 'POST', 'revoke' ),
			array( '\.well-known/oauth-protected-resource', 'GET', 'prm' ),
			array( '\.well-known/oauth-authorization-server', 'GET', 'as_metadata' ),
		);

		foreach ( $routes as $r ) {
			register_rest_route(
				FKMCP_Connector_Urls::NS,
				'/' . $r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( __CLASS__, $r[2] ),
					'permission_callback' => '__return_true', // auth is the bearer (mcp), the login cookie (authorize/consent) or none (public OAuth routes)
				),
				false
			);
		}
	}

	public static function request( WP_REST_Request $r ) {
		$headers = array();
		foreach ( (array) $r->get_headers() as $key => $value ) {
			$headers[ str_replace( '_', '-', strtolower( (string) $key ) ) ] = is_array( $value ) ? (string) reset( $value ) : (string) $value;
		}

		return array(
			'method'  => $r->get_method(),
			'params'  => (array) $r->get_params(),
			// token, revoke and consent read the body only: a token or nonce in the query string ends up in access logs.
			'form'    => array_merge( (array) $r->get_json_params(), (array) $r->get_body_params() ),
			'headers' => $headers,
			'body'    => (string) $r->get_body(),
			'user_id' => get_current_user_id(),
			'url'     => self::current_url(),
			'now'     => time(),
		);
	}

	private static function current_url() {
		$home = wp_parse_url( home_url() );
		$host = $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used as a login redirect target on this host only.

		return $home['scheme'] . '://' . $host . $uri;
	}

	/** JSON becomes a WP_REST_Response; a redirect or a page is sent here and ends the request. */
	public static function send( array $resp ) {
		$status  = isset( $resp['status'] ) ? (int) $resp['status'] : 200;
		$headers = isset( $resp['headers'] ) ? (array) $resp['headers'] : array();

		if ( class_exists( 'FKMCP_Connector_Mcp' ) && '' !== FKMCP_Connector_Mcp::version() ) {
			$headers['X-FKMCP-Version'] = FKMCP_Connector_Mcp::version();
		}

		if ( isset( $resp['redirect'] ) ) {
			nocache_headers();
			wp_redirect( $resp['redirect'], $status ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the pinned gateway callback or this site's login.
			exit;
		}

		if ( isset( $resp['html'] ) || ! empty( $resp['empty'] ) ) {
			nocache_headers();
			status_header( $status );
			foreach ( $headers as $name => $value ) {
				header( $name . ': ' . $value );
			}
			if ( isset( $resp['html'] ) ) {
				echo $resp['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes every value.
			}
			exit;
		}

		$response = new WP_REST_Response( isset( $resp['json'] ) ? $resp['json'] : null, $status );
		foreach ( $headers as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}

	public static function mcp( WP_REST_Request $r ) {
		$q = self::request( $r );

		return self::send( FKMCP_Connector_Mcp::handle( $q['body'], $q['headers'], self::mcp_user( $q['user_id'] ) ) );
	}

	public static function abilities( WP_REST_Request $r ) {
		$q = self::request( $r );

		return self::send( FKMCP_Connector_Abilities::index( $q['params'], self::mcp_user( $q['user_id'] ) ) );
	}

	public static function ability( WP_REST_Request $r ) {
		$q = self::request( $r );

		return self::send( FKMCP_Connector_Abilities::show( (string) $r->get_param( 'vendor' ), (string) $r->get_param( 'ability' ), self::mcp_user( $q['user_id'] ) ) );
	}

	/**
	 * The user the MCP endpoint serves: only one the connector's bearer signed in (D2).
	 * A login cookie, an Application Password or any other REST auth counts as nobody,
	 * so the caller gets the 401 challenge that sends it through the gateway.
	 */
	public static function mcp_user( $user_id ) {
		return ( class_exists( 'FKMCP_Connector_Bearer', false ) && FKMCP_Connector_Bearer::signed_in() ) ? (int) $user_id : 0;
	}

	public static function authorize( WP_REST_Request $r ) {
		$q = self::request( $r );

		return self::send( FKMCP_Connector_Authorize::authorize( $q['params'], $q['user_id'], $q['url'], $q['now'] ) );
	}

	public static function consent( WP_REST_Request $r ) {
		$q = self::request( $r );

		return self::send( FKMCP_Connector_Authorize::consent( $q['form'], $q['user_id'], $q['now'] ) );
	}

	public static function token( WP_REST_Request $r ) {
		if ( ! is_ssl() && ! ( defined( 'FKAI_ALLOW_HTTP' ) && FKAI_ALLOW_HTTP ) ) {
			return self::send( array( 'status' => 400, 'json' => array( 'error' => 'invalid_request', 'error_description' => __( 'HTTPS is required.', 'wp-marketing-automations' ) ), 'headers' => array( 'Cache-Control' => 'no-store' ) ) );
		}

		$q = self::request( $r );

		return self::send( FKMCP_Connector_Token::token( $q['form'], $q['now'] ) );
	}

	public static function revoke( WP_REST_Request $r ) {
		$q = self::request( $r );

		return self::send( FKMCP_Connector_Token::revoke( $q['form'] ) );
	}

	public static function prm() {
		return self::send( FKMCP_Connector_Discovery::response( FKMCP_Connector_Discovery::protected_resource() ) );
	}

	public static function as_metadata() {
		return self::send( FKMCP_Connector_Discovery::response( FKMCP_Connector_Discovery::server_metadata() ) );
	}
}
