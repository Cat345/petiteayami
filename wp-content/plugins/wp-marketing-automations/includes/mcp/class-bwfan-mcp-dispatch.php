<?php

defined( 'ABSPATH' ) || exit;

/**
 * Calls FunnelKit REST controllers directly (they never register routes on an
 * MCP request), so the ability input_schema is the only validation and
 * BWFAN_MCP_Permissions the only auth.
 *
 * The slug → controller map is contributed by each plugin through the
 * fkmcp_dispatch_map filter - entries are {file (absolute), class, handler} -
 * so the module carries no knowledge of any plugin's internals.
 *
 * Controllers are singletons and handle_request() keeps the previous call's
 * pagination limit whenever a call omits one, so every dispatch here restores
 * the controller's own default first: a tool that asks for one row must not
 * decide the page size of the next tool in a JSON-RPC batch.
 */
class BWFAN_MCP_Dispatch {
	private static $instances = array();

	/** Constructor-time pagination limit per controller class. */
	private static $limits = array();

	private static $map = null;

	private static function target( $slug ) {
		if ( null === self::$map ) {
			self::$map = (array) apply_filters( 'fkmcp_dispatch_map', array() );
		}

		return isset( self::$map[ $slug ] ) ? self::$map[ $slug ] : null;
	}

	public static function call( $slug, array $query_params = array(), array $url_params = array() ) {
		return self::request( $slug, $query_params, $url_params, 'GET' );
	}

	/**
	 * Write dispatch: POST with body params, EDITABLE method. Callers must be
	 * behind BWFAN_MCP_Permissions::can_write() - this layer does not re-check.
	 */
	public static function write( $slug, array $body_params = array(), array $url_params = array() ) {
		return self::request( $slug, $body_params, $url_params, 'POST' );
	}

	private static function request( $slug, array $params, array $url_params, $method ) {
		$target = self::target( $slug );

		if ( null === $target ) {
			return new WP_Error( 'fkmcp_unknown_tool', __( 'Unknown tool.', 'wp-marketing-automations' ) );
		}

		$controller = self::controller( $target );

		if ( is_wp_error( $controller ) ) {
			return $controller;
		}

		$request = new WP_REST_Request( $method, '' );

		if ( 'POST' === $method ) {
			$request->set_body_params( $params );
		} else {
			$request->set_query_params( $params );
		}

		$request->set_url_params( $url_params );

		self::restore_pagination( $target['class'], $controller );

		$wp_method = 'POST' === $method ? WP_REST_Server::EDITABLE : WP_REST_Server::READABLE;

		// Discard stray controller output - it would corrupt the MCP JSON-RPC body.
		$ob_level = ob_get_level();
		ob_start();

		try {
			$response = $controller->handle_request( $request, $target['handler'], $wp_method );
		} finally {
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
		}

		if ( is_wp_error( $response ) ) {
			return self::translate_error( $response );
		}

		if ( $response instanceof WP_REST_Response ) {
			$response = $response->get_data();
		}

		return is_array( $response ) ? $response : array( 'result' => $response );
	}

	/**
	 * A controller's "no such record" (HTTP 404) is an answer an agent can act
	 * on, so it keeps its message under fkmcp_not_found. Any other 4xx with a
	 * message is the controller refusing the request ("Contact status is
	 * bounced"), relayed under fkmcp_refused with its status. A 5xx stays a
	 * foreign error for the log wrapper to sanitise.
	 */
	private static function translate_error( WP_Error $error ) {
		$code   = $error->get_error_code();
		$data   = $error->get_error_data( $code );
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : ( is_numeric( $code ) ? (int) $code : 0 );

		$message = wp_strip_all_tags( (string) $error->get_error_message( $code ) );

		// Some controllers report a missing record as 400 or 500; the message still says so.
		if ( 404 === $status || preg_match( '/\b(not found|not exists?|doesn\'t exists?|does not exist|no [a-z ]+ found)\b/i', $message ) ) {
			return new WP_Error( 'fkmcp_not_found', $message, array( 'status' => 404 ) );
		}

		if ( $status >= 400 && $status < 500 && '' !== trim( $message ) ) {
			return new WP_Error( 'fkmcp_refused', mb_substr( $message, 0, 300 ), array( 'status' => $status ) );
		}

		return $error;
	}

	private static function controller( array $target ) {
		if ( isset( self::$instances[ $target['class'] ] ) ) {
			return self::$instances[ $target['class'] ];
		}

		if ( empty( $target['file'] ) || ! is_readable( $target['file'] ) ) {
			return new WP_Error( 'fkmcp_controller_missing', __( 'FunnelKit controller file not found.', 'wp-marketing-automations' ) );
		}

		require_once $target['file'];

		if ( ! is_callable( array( $target['class'], 'get_instance' ) ) ) {
			return new WP_Error( 'fkmcp_controller_missing', __( 'FunnelKit controller class not found.', 'wp-marketing-automations' ) );
		}

		$instance = call_user_func( array( $target['class'], 'get_instance' ) );

		if ( isset( $instance->pagination->limit ) ) {
			self::$limits[ $target['class'] ] = $instance->pagination->limit;
		}

		self::$instances[ $target['class'] ] = $instance;

		return self::$instances[ $target['class'] ];
	}

	/**
	 * Puts the controller's constructor default back, so each call starts where
	 * a real REST request would rather than where the last dispatch left it.
	 *
	 * @param string $class
	 * @param object $controller
	 *
	 * @return void
	 */
	private static function restore_pagination( $class, $controller ) {
		if ( ! isset( self::$limits[ $class ] ) || ! isset( $controller->pagination ) ) {
			return;
		}

		$controller->pagination->limit = self::$limits[ $class ];
	}
}
