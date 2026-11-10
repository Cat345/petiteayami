<?php

defined( 'ABSPATH' ) || exit;

/**
 * REST handlers behind this plugin's FunnelKit AI settings screen. Registered
 * under the plugin's own namespace by its MCP host class; the connector
 * (includes/mcp/module/connector) owns the grants, this class only reads and
 * revokes them.
 */
class BWFAN_MCP_Settings_API {
	const OPT_LAST_TEST = 'fkmcp_last_test';

	/** Seconds any one connection probe may take. */
	const PROBE_TIMEOUT = 5;

	/** Seconds the gateway waits for the home page, the endpoint and the sign-in documents. */
	const GATEWAY_WAIT = 5;

	/** Top-level names the gateway never connects to: they never belong to a public store. */
	const RESERVED_TLDS = array( 'localhost', 'local', 'internal', 'arpa', 'invalid', 'onion', 'test', 'example', 'home', 'lan' );

	/** Colour a pack without one of its own is listed in. */
	const DEFAULT_PACK_COLOUR = '#82828E';

	/** Batch key for the robots.txt probe diagnose_block() adds to its own request set. */
	const DIAG_ROBOTS_KEY = 'diag:robots';

	/**
	 * Where "Contact FunnelKit support" in a diagnosis points - the same
	 * utm_source/utm_campaign every other outbound funnelkit.com link in
	 * this plugin uses, just with its own utm_medium.
	 *
	 * @return string
	 */
	private static function support_url() {
		return add_query_arg(
			array(
				'utm_source'   => 'WordPress',
				'utm_medium'   => 'MCP+Diagnosis',
				'utm_campaign' => 'FKA+Plugin',
			),
			'https://funnelkit.com/support/'
		);
	}

	/** The admin helper this class reads from, loaded on first use. */
	private static function load_admin() {
		if ( ! class_exists( 'BWFAN_MCP_Admin' ) ) {
			require_once __DIR__ . '/class-bwfan-mcp-admin.php';
		}
	}

	/**
	 * Route table: path => register_rest_route() args (methods, callback,
	 * permission_callback, args). Hosts prefix these with their own namespace.
	 *
	 * @return array
	 */
	public static function routes() {
		$auth = array( __CLASS__, 'permission_check' );

		return array(
			'/settings'                => array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'get_settings' ), 'permission_callback' => $auth ),
			'/prompts'                 => array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'get_prompts' ), 'permission_callback' => $auth ),
			'/toggle'                  => array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'toggle' ),
				'permission_callback' => $auth,
				'args'                => array(
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			),
			'/writes'                  => array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'toggle_writes' ),
				'permission_callback' => $auth,
				'args'                => array(
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			),
			'/test'                    => array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'test_connection' ), 'permission_callback' => $auth ),
			'/connections'             => array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( __CLASS__, 'disconnect_all' ), 'permission_callback' => $auth ),
			'/connections/(?P<id>\d+)' => array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( __CLASS__, 'disconnect' ), 'permission_callback' => $auth ),
		);
	}

	/**
	 * Registers the route table under this plugin's namespace.
	 *
	 * @param string $namespace
	 */
	public static function register_routes( $namespace ) {
		foreach ( self::routes() as $path => $args ) {
			register_rest_route( $namespace, $path, $args );
		}
	}

	public static function permission_check() {
		return current_user_can( 'manage_options' );
	}

	public static function get_settings() {
		return rest_ensure_response(
			array(
				'status'      => self::status(),
				'store'       => FKMCP_Connector_Admin::store(),
				'connections' => self::own_connections(),
				'writes'      => self::writes_state(),
				'abilities'   => self::abilities_payload(),
			)
		);
	}

	/**
	 * The ready-made prompts the FunnelKit AI page lists. They live in
	 * prompts.json beside this class: its categories (the filter, "all"
	 * first) and its prompts, each with a category, a title, what it does,
	 * the prompt to copy and, when its tools need Pro, `pro`.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_prompts() {
		$library = wp_json_file_decode( __DIR__ . '/prompts.json', array( 'associative' => true ) );

		if ( ! is_array( $library ) || empty( $library['categories'] ) || empty( $library['prompts'] ) ) {
			return new WP_Error( 'bwfan_mcp_prompts_unreadable', __( 'The prompt library could not be read.', 'wp-marketing-automations' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $library );
	}

	/**
	 * Site-wide switch for FunnelKit AI connections; turning it off ends every one.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function toggle( WP_REST_Request $request ) {
		$enabled = (bool) $request->get_param( 'enabled' );
		$changed = FKMCP_Connector_Admin::set_enabled( $enabled );

		// Turning on without the Abilities API is refused (400); turning off always works.
		if ( is_wp_error( $changed ) ) {
			return $changed;
		}

		if ( $changed ) {
			BWFAN_MCP_Log::event( sprintf( 'CONNECTOR_SWITCH %s user=%d', $enabled ? 'on' : 'off', get_current_user_id() ) );
		}

		self::$status = null;

		return rest_ensure_response(
			array(
				'status'      => self::status(),
				'store'       => FKMCP_Connector_Admin::store(),
				'connections' => self::own_connections(),
			)
		);
	}

	/**
	 * Ends all of the caller's own connections. Other administrators' are theirs
	 * to end; the site-wide stop is the switch (set_enabled), not this.
	 *
	 * @return WP_REST_Response
	 */
	public static function disconnect_all() {
		foreach ( self::own_connections() as $row ) {
			FKMCP_Connector_Admin::revoke( (int) $row['id'] );
		}

		return rest_ensure_response( array( 'connections' => self::own_connections() ) );
	}

	/**
	 * Ends one of the caller's own connections. Another administrator's is not
	 * theirs to end, and answers exactly as an id that never existed does.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function disconnect( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );

		// false === : an older module's revoke() returns nothing, after ending it.
		if ( ! self::owns( $id ) || false === FKMCP_Connector_Admin::revoke( $id ) ) {
			return new WP_Error( 'bwfan_mcp_no_such_connection', __( 'That connection has already ended, or belongs to another administrator.', 'wp-marketing-automations' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'connections' => self::own_connections() ) );
	}

	/**
	 * The caller's own connections, and only those.
	 *
	 * The connector scopes them already. The filter is applied again here
	 * because the module is shared: on a site where an older copy of it boots,
	 * its connections() is still site-wide, and this screen must not be.
	 *
	 * @return array
	 */
	private static function own_connections() {
		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return array();
		}

		$out = array();

		foreach ( (array) FKMCP_Connector_Admin::connections( $user_id ) as $row ) {
			if ( isset( $row['user']['id'] ) && (int) $row['user']['id'] === $user_id ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Whether a connection id is one of the caller's own. Checked here as well
	 * as in the connector, for the same reason own_connections() filters.
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	private static function owns( $id ) {
		foreach ( self::own_connections() as $row ) {
			if ( (int) $row['id'] === (int) $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Grants/revokes write access for the CURRENT user only (user meta).
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function toggle_writes( WP_REST_Request $request ) {
		update_user_meta( get_current_user_id(), BWFAN_MCP_Permissions::META_ALLOW_WRITES, $request->get_param( 'enabled' ) ? 'yes' : 'no' );

		return rest_ensure_response(
			array(
				'writes' => self::writes_state(),
			)
		);
	}

	private static function writes_state() {
		$user = wp_get_current_user();

		return array(
			'enabled'  => BWFAN_MCP_Permissions::can_write(),
			'option'   => 'no' !== get_user_meta( $user->ID, BWFAN_MCP_Permissions::META_ALLOW_WRITES, true ),
			'username' => $user->user_login,
		);
	}

	/**
	 * Walks this store the way FunnelKit AI's gateway does when someone connects
	 * it: over HTTPS, never following a redirect,
	 * within the gateway's time limit. Every failing row is a step the gateway
	 * would stop on. A 404 or a block gets the full diagnosis screen.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function test_connection() {
		self::load_admin();

		$status = self::status();

		if ( ! $status['abilities_api'] ) {
			return self::test_result( false, __( 'The WordPress Abilities API is missing. WordPress 6.9 or newer is required.', 'wp-marketing-automations' ) );
		}

		$start  = microtime( true );
		$checks = array( self::address_check() );

		// 1. The home page names the REST API, where the gateway looks for it.
		$home = self::timed_probe( home_url( '/' ), 'GET', $status, null, array( 'Accept' => 'text/html' ) );

		if ( self::is_blocked( $home['response'] ) ) {
			$diagnosis = self::diagnose_block( $status, $home['response'] );

			return self::test_result( false, $diagnosis['lede'], self::elapsed( $start ), $checks, $diagnosis );
		}

		$checks[] = self::home_check( $home );

		// 2. The endpoint asks for sign-in and names its metadata.
		$mcp  = self::timed_probe( $status['endpoint'], 'POST', $status, self::rpc( 'initialize' ) );
		$code = is_wp_error( $mcp['response'] ) ? 0 : (int) wp_remote_retrieve_response_code( $mcp['response'] );

		if ( 404 === $code ) {
			return self::test_result( false, __( 'The FunnelKit AI endpoint returned 404. Re-save Settings → Permalinks, then check again.', 'wp-marketing-automations' ), self::elapsed( $start ), $checks, self::diagnose_rest( $status ) );
		}

		if ( self::is_blocked( $mcp['response'] ) ) {
			$diagnosis = self::diagnose_block( $status, $mcp['response'] );

			return self::test_result( false, $diagnosis['lede'], self::elapsed( $start ), $checks, $diagnosis );
		}

		$checks[] = self::endpoint_check( $mcp );
		$checks[] = self::metadata_check( $status );
		$checks[] = self::token_check( $status );
		$checks[] = self::authorization_check( $status );

		foreach ( $checks as $check ) {
			if ( ! $check['ok'] ) {
				return self::test_result( false, $check['message'], self::elapsed( $start ), $checks );
			}
		}

		return self::test_result( true, __( 'This site is ready for FunnelKit AI.', 'wp-marketing-automations' ), self::elapsed( $start ), $checks );
	}

	/**
	 * The gateway connects only to an https address on the standard port with a
	 * public domain name: no IP address, no port, no local or test name.
	 *
	 * @return array
	 */
	private static function address_check() {
		$label  = __( 'Site address', 'wp-marketing-automations' );
		$parts  = wp_parse_url( home_url( '/' ) );
		$host   = strtolower( isset( $parts['host'] ) ? (string) $parts['host'] : '' );
		$labels = explode( '.', $host );
		$tld    = (string) end( $labels );

		if ( ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return self::check( 'address', $label, false, __( 'FunnelKit AI needs HTTPS. Once your site supports it, set Site Address (URL) in Settings → General to https://.', 'wp-marketing-automations' ) );
		}

		if ( ! empty( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return self::check(
				'address',
				$label,
				false,
				sprintf(
				/* translators: %d: port number in the site address. */
					__( 'FunnelKit AI connects on the standard HTTPS port only, and this site runs on port %d.', 'wp-marketing-automations' ),
					(int) $parts['port']
				)
			);
		}

		if ( count( $labels ) < 2 || false !== filter_var( $host, FILTER_VALIDATE_IP ) || in_array( $tld, self::RESERVED_TLDS, true ) ) {
			return self::check(
				'address',
				$label,
				false,
				sprintf(
				/* translators: %s: site hostname. */
					__( 'FunnelKit AI connects from the internet, so it can’t reach %s. Connect a live site with a public domain.', 'wp-marketing-automations' ),
					$host
				)
			);
		}

		return self::check(
			'address',
			$label,
			true,
			sprintf(
			/* translators: %s: site hostname. */
				__( '%s is a public HTTPS address.', 'wp-marketing-automations' ),
				$host
			)
		);
	}

	/**
	 * The home page must load directly (no redirect, password or maintenance
	 * page) and name the REST API on its own host in its Link header.
	 *
	 * @param array $probe Result of self::timed_probe().
	 *
	 * @return array
	 */
	private static function home_check( $probe ) {
		$label    = __( 'Home page', 'wp-marketing-automations' );
		$response = $probe['response'];

		if ( is_wp_error( $response ) ) {
			return self::check(
				'home',
				$label,
				false,
				sprintf(
				/* translators: %s: HTTP error message. */
					__( 'Could not load your home page: %s', 'wp-marketing-automations' ),
					$response->get_error_message()
				)
			);
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$redirect = self::redirect_message( $response, __( 'Your site address', 'wp-marketing-automations' ) );

		if ( '' !== $redirect ) {
			return self::check( 'home', $label, false, $redirect );
		}

		if ( 401 === $code ) {
			return self::check( 'home', $label, false, __( 'Your site asks for a password before it loads, which FunnelKit AI can’t get past. Remove the password protection from this site.', 'wp-marketing-automations' ) );
		}

		if ( 503 === $code ) {
			return self::check( 'home', $label, false, __( 'Your site answers “temporarily unavailable” (503), usually maintenance mode. Turn it off, then check again.', 'wp-marketing-automations' ) );
		}

		if ( 200 !== $code ) {
			return self::check(
				'home',
				$label,
				false,
				sprintf(
				/* translators: %d: HTTP status code. */
					__( 'Your home page answered HTTP %d instead of loading normally.', 'wp-marketing-automations' ),
					$code
				)
			);
		}

		$root = self::rest_link( $response );

		if ( '' === $root ) {
			return self::check( 'home', $label, false, __( 'Your home page doesn’t share its REST API address (the Link header). A security or speed plugin may remove it; turn that option off.', 'wp-marketing-automations' ) );
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( strtolower( (string) wp_parse_url( $root, PHP_URL_HOST ) ) !== strtolower( $host ) ) {
			return self::check(
				'home',
				$label,
				false,
				sprintf(
				/* translators: 1: REST API address, 2: site hostname. */
					__( 'Your REST API address is %1$s, on another domain. FunnelKit AI needs it on %2$s.', 'wp-marketing-automations' ),
					$root,
					$host
				)
			);
		}

		if ( $probe['seconds'] > self::GATEWAY_WAIT ) {
			return self::check( 'home', $label, false, self::slow_message( 'home', $probe['seconds'] ) );
		}

		return self::check( 'home', $label, true, __( 'Loads and shares its REST API address.', 'wp-marketing-automations' ) );
	}

	/**
	 * The MCP endpoint answers 401 with a challenge naming the sign-in metadata
	 * on this host - the one answer the gateway goes on from.
	 *
	 * @param array $probe Result of self::timed_probe().
	 *
	 * @return array
	 */
	private static function endpoint_check( $probe ) {
		$label    = __( 'FunnelKit AI endpoint', 'wp-marketing-automations' );
		$response = $probe['response'];

		if ( is_wp_error( $response ) ) {
			return self::check(
				'mcp',
				$label,
				false,
				sprintf(
				/* translators: %s: HTTP error message. */
					__( 'Could not reach the FunnelKit AI endpoint: %s', 'wp-marketing-automations' ),
					$response->get_error_message()
				)
			);
		}

		$code      = (int) wp_remote_retrieve_response_code( $response );
		$redirect  = self::redirect_message( $response, __( 'The FunnelKit AI endpoint', 'wp-marketing-automations' ) );
		$challenge = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

		if ( '' !== $redirect ) {
			return self::check( 'mcp', $label, false, $redirect );
		}

		if ( 403 === $code && false !== strpos( (string) wp_remote_retrieve_body( $response ), 'ai_disabled' ) ) {
			return self::check( 'mcp', $label, false, __( 'Another plugin runs an older FunnelKit AI connector that refuses new connections. Update Funnel Builder and FunnelKit Automations, then check again.', 'wp-marketing-automations' ) );
		}

		if ( 401 !== $code ) {
			return self::check(
				'mcp',
				$label,
				false,
				sprintf(
				/* translators: %d: HTTP status code. */
					__( 'The FunnelKit AI endpoint answered HTTP %d instead of asking for sign-in.', 'wp-marketing-automations' ),
					$code
				)
			);
		}

		if ( false === strpos( $challenge, 'resource_metadata=' ) ) {
			return self::check( 'mcp', $label, false, __( 'The endpoint asks for sign-in, but its WWW-Authenticate header doesn’t arrive. A proxy or cache in front of the site may strip it from /wp-json/funnelkit/.', 'wp-marketing-automations' ) );
		}

		if ( $probe['seconds'] > self::GATEWAY_WAIT ) {
			return self::check( 'mcp', $label, false, self::slow_message( 'mcp', $probe['seconds'] ) );
		}

		return self::check( 'mcp', $label, true, __( 'Asks for sign-in and names its metadata.', 'wp-marketing-automations' ) );
	}

	/**
	 * Both sign-in documents answer JSON and agree with each other and with
	 * this store, with the features the gateway requires.
	 *
	 * @param array $status Result of self::status().
	 *
	 * @return array
	 */
	private static function metadata_check( $status ) {
		$label    = __( 'Sign-in metadata', 'wp-marketing-automations' );
		$unread   = __( 'The sign-in metadata at /wp-json/funnelkit/.well-known/ doesn’t answer JSON. A security plugin or a rule on /.well-known/ may be in the way.', 'wp-marketing-automations' );
		$outdated = __( 'This site’s FunnelKit AI connector is out of date. Update FunnelKit Automations, then check again.', 'wp-marketing-automations' );

		$prm = self::timed_probe( FKMCP_Connector_Urls::prm_url(), 'GET', $status );
		$doc = self::json_document( $prm['response'] );

		if ( null === $doc ) {
			return self::check( 'metadata', $label, false, self::unread_message( $prm['response'], $unread ) );
		}

		$servers = isset( $doc['authorization_servers'] ) && is_array( $doc['authorization_servers'] ) ? array_values( $doc['authorization_servers'] ) : array();
		$issuer  = 1 === count( $servers ) ? (string) $servers[0] : '';

		if ( ! isset( $doc['resource'] ) || FKMCP_Connector_Urls::resource() !== $doc['resource'] || '' === $issuer ) {
			return self::check( 'metadata', $label, false, __( 'The sign-in metadata names a different address than this site. If the site moved, re-save Settings → Permalinks and check again.', 'wp-marketing-automations' ) );
		}

		$as     = self::timed_probe( FKMCP_Connector_Urls::as_url(), 'GET', $status );
		$server = self::json_document( $as['response'] );

		if ( null === $server ) {
			return self::check( 'metadata', $label, false, self::unread_message( $as['response'], $unread ) );
		}

		$lists = function ( $field, $value ) use ( $server ) {
			return isset( $server[ $field ] ) && is_array( $server[ $field ] ) && in_array( $value, $server[ $field ], true );
		};

		$complete = isset( $server['issuer'] ) && $issuer === $server['issuer']
			&& ! empty( $server['authorization_endpoint'] ) && ! empty( $server['token_endpoint'] ) && ! empty( $server['revocation_endpoint'] )
			&& $lists( 'code_challenge_methods_supported', 'S256' ) && $lists( 'response_types_supported', 'code' )
			&& $lists( 'grant_types_supported', 'authorization_code' ) && $lists( 'grant_types_supported', 'refresh_token' )
			&& ! empty( $server['client_id_metadata_document_supported'] ) && ! empty( $server['authorization_response_iss_parameter_supported'] );

		if ( ! $complete ) {
			return self::check( 'metadata', $label, false, $outdated );
		}

		$seconds = max( $prm['seconds'], $as['seconds'] );

		if ( $seconds > self::GATEWAY_WAIT ) {
			return self::check( 'metadata', $label, false, self::slow_message( 'metadata', $seconds ) );
		}

		return self::check( 'metadata', $label, true, __( 'Both sign-in documents answer and match this site.', 'wp-marketing-automations' ) );
	}

	/**
	 * Why a sign-in document could not be read: a redirect, else the general reason.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $fallback Message when it was not a redirect.
	 *
	 * @return string
	 */
	private static function unread_message( $response, $fallback ) {
		$redirect = self::redirect_message( $response, __( 'The sign-in metadata', 'wp-marketing-automations' ) );

		return '' !== $redirect ? $redirect : $fallback;
	}

	/**
	 * The token address, where the gateway trades a sign-in for access, answers
	 * a bare request with the connector's own JSON error - not a block page.
	 *
	 * @param array $status Result of self::status().
	 *
	 * @return array
	 */
	private static function token_check( $status ) {
		$label    = __( 'Sign-in token address', 'wp-marketing-automations' );
		$probe    = self::timed_probe( FKMCP_Connector_Urls::endpoint( 'token' ), 'POST', $status, 'grant_type=fkai_check', array( 'Content-Type' => 'application/x-www-form-urlencoded' ) );
		$response = $probe['response'];

		if ( is_wp_error( $response ) ) {
			return self::check(
				'token',
				$label,
				false,
				sprintf(
				/* translators: %s: HTTP error message. */
					__( 'Could not reach the sign-in token address: %s', 'wp-marketing-automations' ),
					$response->get_error_message()
				)
			);
		}

		$redirect = self::redirect_message( $response, __( 'The sign-in token address', 'wp-marketing-automations' ) );

		if ( '' !== $redirect ) {
			return self::check( 'token', $label, false, $redirect );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 400 || $code > 403 || ! is_array( $decoded ) || empty( $decoded['error'] ) ) {
			$blocker = self::blocker( $response );

			return self::check(
				'token',
				$label,
				false,
				'' !== $blocker
					? sprintf(
					/* translators: %s: what blocked the request, e.g. Wordfence. */
						__( '%s blocks the sign-in token address, /wp-json/funnelkit/oauth/token. Allow requests to it.', 'wp-marketing-automations' ),
						$blocker
					)
					: sprintf(
					/* translators: %d: HTTP status code. */
						__( 'The sign-in token address answered HTTP %d instead of the connector’s own reply. A security plugin or firewall may block POST requests to /wp-json/funnelkit/oauth/token.', 'wp-marketing-automations' ),
						$code
					)
			);
		}

		return self::check( 'token', $label, true, __( 'Answers sign-in requests.', 'wp-marketing-automations' ) );
	}

	/**
	 * Some servers drop the Authorization header before PHP sees it, which
	 * passes discovery and sign-in and then fails every call. A made-up token
	 * must come back as an invalid token, not as no token at all.
	 *
	 * @param array $status Result of self::status().
	 *
	 * @return array
	 */
	private static function authorization_check( $status ) {
		$label    = __( 'Authorization header', 'wp-marketing-automations' );
		$probe    = self::timed_probe( $status['endpoint'], 'POST', $status, self::rpc( 'ping' ), array( 'Authorization' => 'Bearer fkai-connection-check' ) );
		$response = $probe['response'];

		if ( is_wp_error( $response ) ) {
			return self::check(
				'authorization',
				$label,
				false,
				sprintf(
				/* translators: %s: HTTP error message. */
					__( 'Could not reach the FunnelKit AI endpoint: %s', 'wp-marketing-automations' ),
					$response->get_error_message()
				)
			);
		}

		$challenge = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

		if ( 401 === (int) wp_remote_retrieve_response_code( $response ) && false !== strpos( $challenge, 'invalid_token' ) ) {
			return self::check( 'authorization', $label, true, __( 'Reaches WordPress.', 'wp-marketing-automations' ) );
		}

		return self::check( 'authorization', $label, false, __( 'Your server removes the Authorization header before WordPress sees it, so FunnelKit AI would sign in and then fail on every call. On Apache, add “CGIPassAuth On” to .htaccess, or ask your host to pass the Authorization header to PHP.', 'wp-marketing-automations' ) );
	}

	/**
	 * One request as the gateway makes it, with how long it took.
	 *
	 * @param string      $url     URL to call.
	 * @param string      $method  HTTP method.
	 * @param array       $status  Result of self::status().
	 * @param string|null $body    Request body, if any.
	 * @param array       $headers Extra request headers.
	 *
	 * @return array{response: array|WP_Error, seconds: float}
	 */
	private static function timed_probe( $url, $method, $status, $body = null, $headers = array() ) {
		$start    = microtime( true );
		$response = self::probe( $url, $method, self::agent(), $status, $body, $headers, self::GATEWAY_WAIT * 2 );

		return array(
			'response' => $response,
			'seconds'  => microtime( true ) - $start,
		);
	}

	/**
	 * A refusal from something in front of the store rather than from it: 403 or
	 * 429, or a page a known firewall or CDN signs.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return bool
	 */
	private static function is_blocked( $response ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 403 === $code && false !== strpos( (string) wp_remote_retrieve_body( $response ), 'ai_disabled' ) ) {
			return false;
		}

		return in_array( $code, array( 403, 429 ), true ) || ( $code >= 400 && 0 === stripos( self::blocker( $response ), 'cloudflare' ) );
	}

	/**
	 * Wording for a redirect the gateway would refuse to follow, '' when the
	 * response is not one.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $subject  What redirected, e.g. "Your site address".
	 *
	 * @return string
	 */
	private static function redirect_message( $response, $subject ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 300 || $code >= 400 ) {
			return '';
		}

		$location = (string) wp_remote_retrieve_header( $response, 'location' );

		if ( '' === $location ) {
			return sprintf(
			/* translators: %s: what redirected, e.g. "Your site address". */
				__( '%s redirects, and FunnelKit AI doesn’t follow redirects. Make sure Site Address (URL) in Settings → General is the address your site really loads on.', 'wp-marketing-automations' ),
				$subject
			);
		}

		return sprintf(
		/* translators: 1: what redirected, e.g. "Your site address", 2: redirect target URL. */
			__( '%1$s redirects to %2$s, and FunnelKit AI doesn’t follow redirects. Set Site Address (URL) in Settings → General to the address your site really loads on.', 'wp-marketing-automations' ),
			$subject,
			esc_url( $location )
		);
	}

	/**
	 * @param string $what    What was slow: home, mcp or metadata.
	 * @param float  $seconds How long it took.
	 *
	 * @return string
	 */
	private static function slow_message( $what, $seconds ) {
		switch ( $what ) {
			case 'mcp':
				/* translators: 1: seconds it took, 2: seconds FunnelKit AI waits. */
				$text = __( 'The FunnelKit AI endpoint took %1$s seconds to answer, and FunnelKit AI waits %2$d. Speed up the site or its hosting, then check again.', 'wp-marketing-automations' );
				break;
			case 'metadata':
				/* translators: 1: seconds it took, 2: seconds FunnelKit AI waits. */
				$text = __( 'The sign-in metadata took %1$s seconds to answer, and FunnelKit AI waits %2$d. Speed up the site or its hosting, then check again.', 'wp-marketing-automations' );
				break;
			default:
				/* translators: 1: seconds it took, 2: seconds FunnelKit AI waits. */
				$text = __( 'Your home page took %1$s seconds to answer, and FunnelKit AI waits %2$d. Speed up the site or its hosting, then check again.', 'wp-marketing-automations' );
		}

		return sprintf( $text, number_format_i18n( $seconds, 1 ), self::GATEWAY_WAIT );
	}

	/**
	 * The REST API address from the home page's Link header, '' when it has none.
	 *
	 * @param array $response HTTP response.
	 *
	 * @return string
	 */
	private static function rest_link( $response ) {
		$header = wp_remote_retrieve_header( $response, 'link' );
		$header = is_array( $header ) ? implode( ',', $header ) : (string) $header;

		foreach ( explode( ',', $header ) as $part ) {
			if ( false !== stripos( $part, 'https://api.w.org/' ) && preg_match( '/<([^>]+)>/', $part, $match ) ) {
				return $match[1];
			}
		}

		return '';
	}

	/**
	 * A 200 JSON object, or null.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return array|null
	 */
	private static function json_document( $response ) {
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * @param string $method JSON-RPC method.
	 *
	 * @return string
	 */
	private static function rpc( $method ) {
		return (string) wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => $method,
				'params'  => new stdClass(),
			)
		);
	}

	/**
	 * @param float $start microtime( true ) when the test began.
	 *
	 * @return int Milliseconds.
	 */
	private static function elapsed( $start ) {
		return (int) round( ( microtime( true ) - $start ) * 1000 );
	}

	/**
	 * The full diagnosis behind a 404: nothing is blocking the site, WordPress
	 * simply has not routed the REST endpoint yet - almost always a rewrite
	 * rules problem a Permalinks re-save fixes in one click.
	 *
	 * @param array $status Result of self::status().
	 *
	 * @return array
	 */
	private static function diagnose_rest( $status ) {
		$wp_json         = self::probe( home_url( '/wp-json/' ), 'GET', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ), $status );
		$wp_json_missing = is_wp_error( $wp_json ) || 404 === (int) wp_remote_retrieve_response_code( $wp_json );
		$structure       = (string) get_option( 'permalink_structure' );

		$checks = array(
			array(
				'label'  => __( 'FunnelKit AI endpoint', 'wp-marketing-automations' ),
				'result' => __( '404 · No route', 'wp-marketing-automations' ),
				'ok'     => false,
			),
			array(
				'label'  => __( 'REST API root, /wp-json/', 'wp-marketing-automations' ),
				'result' => $wp_json_missing
					? __( '404 · No route', 'wp-marketing-automations' )
					: __( 'Routed normally', 'wp-marketing-automations' ),
				'ok'     => ! $wp_json_missing,
				'note'   => $wp_json_missing ? __( 'The whole REST API is unrouted, not just the FunnelKit AI endpoint.', 'wp-marketing-automations' ) : '',
			),
			array(
				'label'  => __( 'Permalink structure', 'wp-marketing-automations' ),
				'result' => '' === $structure ? __( 'Plain', 'wp-marketing-automations' ) : $structure,
				'ok'     => '' !== $structure,
				'note'   => '' === $structure ? __( 'Plain permalinks leave REST routes unregistered on most servers.', 'wp-marketing-automations' ) : '',
			),
		);

		$title = __( 'The FunnelKit AI endpoint returns 404', 'wp-marketing-automations' );

		return array(
			'scenario' => 'rest',
			'title'    => $title,
			'lede'     => __( "Your site is up, but WordPress is not serving the REST route FunnelKit registered. Nothing is blocking the request - the URL simply isn't routed yet, and saving your permalinks fixes it in a few seconds.", 'wp-marketing-automations' ),
			'checks'   => $checks,
			'note'     => __( 'Why a 404? WordPress needs its rewrite rules flushed after a plugin registers new REST routes - visiting any /wp-json/ URL before that returns 404 the same way, permissions aside.', 'wp-marketing-automations' ),
			'fix'      => array(
				'heading' => __( 'Fix the permalinks', 'wp-marketing-automations' ),
				'steps'   => array(
					array(
						'title'  => __( 'Open Settings → Permalinks', 'wp-marketing-automations' ),
						'hint'   => __( 'This is a core WordPress screen - no plugin required.', 'wp-marketing-automations' ),
						'button' => array(
							'label'    => __( 'Open Permalink Settings', 'wp-marketing-automations' ),
							'url'      => admin_url( 'options-permalink.php' ),
							'external' => false,
						),
					),
					array(
						'title'         => __( 'Click Save Changes', 'wp-marketing-automations' ),
						'hint'          => '' === $structure
							? __( 'No structure is set yet - choose Post name, then save.', 'wp-marketing-automations' )
							: __( 'No edit needed - saving re-flushes the rewrite rules.', 'wp-marketing-automations' ),
						'confirm_label' => __( "I've saved it", 'wp-marketing-automations' ),
					),
					array(
						'title'  => __( 'Re-test the connection', 'wp-marketing-automations' ),
						'hint'   => __( 'The route registers immediately - no cache purge or restart needed.', 'wp-marketing-automations' ),
						'button' => array(
							'label'  => __( 'Re-test connection', 'wp-marketing-automations' ),
							'action' => 'retest',
						),
					),
				),
			),
			'sidebar'  => array(
				array(
					'icon'          => 'help-circle',
					'title'         => __( 'Still 404 after saving?', 'wp-marketing-automations' ),
					'body'          => __( 'Deactivate anything named "Disable REST API", check a security plugin\'s REST API access setting, and purge any page cache - a cached 404 keeps answering. Then send us the diagnostic report.', 'wp-marketing-automations' ),
					'report_label'  => __( 'Copy diagnostic report', 'wp-marketing-automations' ),
					'support_label' => __( 'Contact FunnelKit support', 'wp-marketing-automations' ),
					'support_url'   => self::support_url(),
				),
			),
			'report'   => self::build_report( $title, $checks, '' ),
		);
	}

	/**
	 * The full diagnosis behind a blocked request: who is refusing it (a host
	 * or CDN sitting above WordPress, or a plugin inside it), proved by
	 * probing /robots.txt with the same client - a public file every working
	 * WordPress site answers, so a block on it too sits above WordPress.
	 *
	 * @param array               $status            Result of self::status().
	 * @param array|WP_Error|null $baseline_response The unauthenticated request already made to the endpoint.
	 *
	 * @return array
	 */
	private static function diagnose_block( $status, $baseline_response = null ) {
		$need = self::block_probe_specs( $status );

		$need[ self::DIAG_ROBOTS_KEY ] = array(
			'url'    => home_url( '/robots.txt' ),
			'method' => 'GET',
			'agent'  => self::agent(),
			'body'   => null,
		);

		$results = self::probe_batch( $need, $status );

		$rows         = array();
		$blocker_name = null === $baseline_response ? '' : self::blocker( $baseline_response );

		foreach ( array( 'home' => 200, 'mcp' => 401 ) as $key => $expected ) {
			$response = self::batched( $results, $key );
			$rows[]   = self::evidence_row( $need[ $key ]['label'], $response, $expected );

			if ( '' === $blocker_name && ! is_wp_error( $response ) ) {
				$blocker_name = self::blocker( $response );
			}
		}

		$robots_row     = self::robots_row( self::batched( $results, self::DIAG_ROBOTS_KEY ) );
		$robots_blocked = ! $robots_row['ok'];

		$checks   = array_merge( $rows, array( $robots_row ) );
		$scenario = self::classify_block( $blocker_name, $robots_blocked );

		return self::block_diagnosis( $scenario, $blocker_name, $checks );
	}

	/**
	 * Which side of WordPress a block sits on, from the clearest evidence
	 * available: a recognised CDN or plugin signature first, else whether the
	 * same client can still load a public static file.
	 *
	 * @param string $blocker_name   Named blocker from self::blocker(), '' when none.
	 * @param bool   $robots_blocked Whether the robots.txt probe was also blocked.
	 *
	 * @return string 'cdn' | 'plugin' | 'bot'
	 */
	private static function classify_block( $blocker_name, $robots_blocked ) {
		if ( false !== stripos( $blocker_name, 'cloudflare' ) ) {
			return 'cdn';
		}

		// A named plugin is still a plugin even if it also blocks robots.txt - several of
		// these block by user agent rather than by path, so the "does robots.txt survive"
		// test alone would misread a confirmed plugin as a host-level block.
		if ( in_array( $blocker_name, self::named_plugin_blockers(), true ) ) {
			return 'plugin';
		}

		return $robots_blocked ? 'bot' : 'plugin';
	}

	/**
	 * Security plugins self-signed enough in blocker() to be named with confidence, and
	 * specific enough to carry their own step-by-step fix in block_diagnosis().
	 *
	 * @return string[]
	 */
	private static function named_plugin_blockers() {
		return array( 'Wordfence', 'Sucuri', 'NinjaFirewall', 'WP Cerber', 'Shield Security', 'Solid Security', 'Kadence Security' );
	}

	/**
	 * One evidence row: whether a request got the store's own answer.
	 *
	 * @param string         $label    What was requested.
	 * @param array|WP_Error $response HTTP response.
	 * @param int            $expected The status the store itself answers with.
	 *
	 * @return array
	 */
	private static function evidence_row( $label, $response, $expected ) {
		$ok = ! is_wp_error( $response ) && $expected === (int) wp_remote_retrieve_response_code( $response ) && '' === self::blocker( $response );

		return array(
			'label'  => $label,
			'result' => $ok ? __( 'Reachable · the site answered', 'wp-marketing-automations' ) : self::evidence_result_text( $response ),
			'ok'     => $ok,
		);
	}

	/**
	 * The /robots.txt probe: a public file, so a block on it too proves the
	 * rule sits above WordPress rather than inside a plugin.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return array
	 */
	private static function robots_row( $response ) {
		$blocked = is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) >= 400;
		$label   = __( '/robots.txt', 'wp-marketing-automations' );

		if ( ! $blocked ) {
			return array(
				'label'  => $label,
				'result' => __( 'Loaded normally · not filtered', 'wp-marketing-automations' ),
				'ok'     => true,
			);
		}

		return array(
			'label'  => $label,
			'result' => self::evidence_result_text( $response ),
			'ok'     => false,
		);
	}

	/**
	 * A blocked probe's result, worded for the checks list: "403 · Cloudflare
	 * challenge" - the code first, since that's what a screenshot or a
	 * support ticket will already show.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return string
	 */
	private static function evidence_result_text( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$blocker = self::blocker( $response );

		if ( '' !== $blocker ) {
			return sprintf(
			/* translators: 1: HTTP status code, 2: what blocked the request, e.g. Cloudflare challenge. */
				__( '%1$d · %2$s', 'wp-marketing-automations' ),
				$code,
				$blocker
			);
		}

		return sprintf(
		/* translators: %d: HTTP status code. */
			__( 'HTTP %d', 'wp-marketing-automations' ),
			$code
		);
	}

	/**
	 * The rest of a block diagnosis: title, ownership badge, the change to
	 * make, where to look for it, and the escalation ladder - all specific to
	 * which side of WordPress the block sits on.
	 *
	 * @param string $scenario     'cdn' | 'plugin' | 'bot'.
	 * @param string $blocker_name Named blocker from self::blocker(), '' when none.
	 * @param array  $checks       Evidence rows already gathered.
	 *
	 * @return array
	 */
	private static function block_diagnosis( $scenario, $blocker_name, $checks ) {
		$paths     = array(
			array(
				'path'  => '/',
				'label' => __( 'Home page, where FunnelKit AI finds your REST API', 'wp-marketing-automations' ),
			),
			array(
				'path'  => '/wp-json/funnelkit/',
				'label' => __( 'FunnelKit AI endpoint and sign-in', 'wp-marketing-automations' ),
			),
		);
		$path_list = "/\n/wp-json/funnelkit/";
		$host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		$support_card = array(
			'icon'          => 'help-circle',
			'title'         => __( 'Changed it and still failing?', 'wp-marketing-automations' ),
			'body'          => __( 'Send us the diagnostic report - it has every check and response we saw.', 'wp-marketing-automations' ),
			'report_label'  => __( 'Copy diagnostic report', 'wp-marketing-automations' ),
			'support_label' => __( 'Contact FunnelKit support', 'wp-marketing-automations' ),
			'support_url'   => self::support_url(),
		);

		if ( 'cdn' === $scenario ) {
			$diagnosis = array(
				'title'   => sprintf(
				/* translators: %s: site hostname. */
					__( 'Cloudflare is blocking FunnelKit AI from %s', 'wp-marketing-automations' ),
					$host
				),
				'lede'    => __( "WordPress and FunnelKit are fine. Cloudflare stops FunnelKit AI's server with a browser challenge before it reaches yours. One Cloudflare rule for FunnelKit AI fixes it - about 3 minutes.", 'wp-marketing-automations' ),
				'note'    => __( 'Why Cloudflare? Even <code>/robots.txt</code>, a public file, is blocked - so the rule sits in front of WordPress.', 'wp-marketing-automations' ),
				'fix'     => array(
					'heading' => __( 'Fix it in Cloudflare', 'wp-marketing-automations' ),
					'steps'   => array(
						array(
							'title'  => __( 'Open Cloudflare and select this site', 'wp-marketing-automations' ),
							'hint'   => sprintf(
							/* translators: %s: site hostname. */
								__( 'Sign in and pick <strong>%s</strong> from your domains.', 'wp-marketing-automations' ),
								$host
							),
							'button' => array(
								'label'    => __( 'Open Cloudflare dashboard', 'wp-marketing-automations' ),
								'url'      => 'https://dash.cloudflare.com/', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- plain link to the Cloudflare dashboard shown in help text; nothing is loaded from it
								'external' => true,
							),
						),
						array(
							'title'         => __( 'Let FunnelKit AI through', 'wp-marketing-automations' ),
							'hint'          => __( 'Add one rule so FunnelKit AI can reach these URLs. Bot protection stays on for everyone else.', 'wp-marketing-automations' ),
							'paths'         => $paths,
							'howto'         => array(
								'breadcrumb' => array(
									__( 'Security', 'wp-marketing-automations' ),
									__( 'WAF', 'wp-marketing-automations' ),
									__( 'Custom rules', 'wp-marketing-automations' ),
									__( 'Create rule', 'wp-marketing-automations' ),
								),
								'body'       => __( "Name it <strong>Allow FunnelKit</strong> and choose <strong>Edit expression</strong>.\n\nPaste this expression:", 'wp-marketing-automations' ),
								'snippet'    => 'http.request.uri.path eq "/" or starts_with(http.request.uri.path, "/wp-json/funnelkit/")',
								'after'      => __( "Set action to <strong>Skip</strong> and tick every component: remaining custom rules, rate limiting, managed rules and Super Bot Fight Mode.\n\nDeploy, then drag the rule to the <strong>top</strong> of your custom rules.", 'wp-marketing-automations' ),
							),
							'confirm_label' => __( "I've added the rule", 'wp-marketing-automations' ),
						),
						array(
							'title'  => __( 'Wait 30 seconds, then re-test', 'wp-marketing-automations' ),
							'hint'   => __( 'Cloudflare applies changes in about 30 seconds. No cache purge needed.', 'wp-marketing-automations' ),
							'button' => array(
								'label'  => __( 'Re-test connection', 'wp-marketing-automations' ),
								'action' => 'retest',
							),
						),
					),
				),
				'sidebar' => array(
					array(
						'icon'       => 'people',
						'title'      => __( "Don't manage Cloudflare?", 'wp-marketing-automations' ),
						'body'       => __( 'Send this to your host or developer. It says exactly what to change.', 'wp-marketing-automations' ),
						'message'    => self::cdn_message( $path_list ),
						'copy_label' => __( 'Copy message', 'wp-marketing-automations' ),
					),
					$support_card,
				),
			);
		} elseif ( 'plugin' === $scenario ) {
			$named       = '' !== $blocker_name;
			$plugin_name = $named ? $blocker_name : __( 'your security plugin', 'wp-marketing-automations' );

			$diagnosis = array(
				'title'   => $named
					? sprintf(
					/* translators: %s: security plugin name, e.g. Wordfence. */
						__( '%s is blocking FunnelKit AI', 'wp-marketing-automations' ),
						$blocker_name
					)
					: __( 'A security plugin is blocking FunnelKit AI', 'wp-marketing-automations' ),
				'lede'    => sprintf(
				/* translators: %s: security plugin name, e.g. Wordfence. */
					__( 'FunnelKit AI reaches WordPress, and %s rejects it. Your host and CDN are clear - the same request loads /robots.txt without a problem.', 'wp-marketing-automations' ),
					$plugin_name
				),
				'fix'     => array(
					'heading' => sprintf(
					/* translators: %s: security plugin name, e.g. Wordfence. */
						__( 'Fix it in %s', 'wp-marketing-automations' ),
						$plugin_name
					),
					'steps'   => self::plugin_fix_steps( $blocker_name ),
				),
				'sidebar' => array( $support_card ),
			);
		} else {
			$diagnosis = array(
				'title'   => __( 'Your server is blocking FunnelKit AI', 'wp-marketing-automations' ),
				'lede'    => __( "Your site is fine - FunnelKit AI's requests never reach WordPress. Even a public file, /robots.txt, is blocked for them, so the rule sits above WordPress.", 'wp-marketing-automations' ),
				'note'    => __( "Why is this above WordPress? A plugin can only see a request once WordPress has booted - it can't block /robots.txt, which WordPress serves directly. A host or CDN firewall sits in front of that and can.", 'wp-marketing-automations' ),
				'fix'     => array(
					'heading' => __( 'Fix it on your server', 'wp-marketing-automations' ),
					'steps'   => array(
						array(
							'title'         => __( "Check your host's bot protection", 'wp-marketing-automations' ),
							'hint'          => __( 'Sign in to your hosting control panel and look for a bot-protection, WAF or firewall add-on - most hosts ship one under Security.', 'wp-marketing-automations' ),
							'confirm_label' => __( 'Found it', 'wp-marketing-automations' ),
						),
						array(
							'title'         => __( "Allow FunnelKit's URLs", 'wp-marketing-automations' ),
							'hint'          => __( 'Add one rule so FunnelKit AI can reach these URLs. Leave bot protection on for everyone else.', 'wp-marketing-automations' ),
							'paths'         => $paths,
							'howto'         => array(
								'breadcrumb' => null,
								'body'       => __( 'Every host panel is different, but look for a setting to allow specific paths through the firewall. Allow these:', 'wp-marketing-automations' ),
								'snippet'    => $path_list,
								'after'      => __( 'If you manage the server config directly instead, remove the rule that blocks these paths in nginx or Apache, then reload it.', 'wp-marketing-automations' ),
							),
							'confirm_label' => __( "I've made the change", 'wp-marketing-automations' ),
						),
						array(
							'title'  => __( 'Wait a minute, then re-test', 'wp-marketing-automations' ),
							'hint'   => __( 'Most host firewalls apply changes within a minute. No cache purge needed.', 'wp-marketing-automations' ),
							'button' => array(
								'label'  => __( 'Re-test connection', 'wp-marketing-automations' ),
								'action' => 'retest',
							),
						),
					),
				),
				'sidebar' => array(
					array(
						'icon'       => 'people',
						'title'      => __( 'Send this to your host', 'wp-marketing-automations' ),
						'body'       => __( 'They own the rule, and most support teams clear it in one reply.', 'wp-marketing-automations' ),
						'message'    => self::host_message( $path_list ),
						'copy_label' => __( 'Copy message', 'wp-marketing-automations' ),
					),
					$support_card,
				),
			);
		}

		$diagnosis['scenario'] = $scenario;
		$diagnosis['checks']   = $checks;
		$diagnosis['report']   = self::build_report( $diagnosis['title'], $checks, '' );

		return $diagnosis;
	}

	/**
	 * The checklist for a named plugin's own step-by-step, or the generic
	 * "open your firewall settings" walkthrough when the blocker has no name
	 * or none of the ones below recognise it.
	 *
	 * The deep link and menu path are this plugin's own admin UI as
	 * documented publicly - accurate as of when this was written, but a
	 * plugin update can move a settings page. A stale link opens wp-admin
	 * and fails closed (a 404 inside wp-admin, not a broken redirect), so
	 * getting one wrong costs a click, not a wrong action.
	 *
	 * @param string $blocker_name Named blocker from self::blocker(), '' when none.
	 *
	 * @return array Steps for the 'fix' key.
	 */
	private static function plugin_fix_steps( $blocker_name ) {
		$specs = array(
			'Wordfence'        => array(
				'open_title'  => __( 'Open Wordfence → Firewall → Blocking', 'wp-marketing-automations' ),
				'open_hint'   => __( 'Look for a custom pattern that blocks REST API requests, and delete or narrow it.', 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=WordfenceWAF#top#blocking',
				'open_label'  => __( 'Open Wordfence', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Under All Options → Rate Limiting, check for a rule blocking unknown crawlers or "How Wordfence should handle" a crawler match - turn off whichever one applies, or add an allow rule for this path.', 'wp-marketing-automations' ),
				'allow_after' => __( "Wordfence → Tools → Live Traffic shows the blocked request live if you're not sure which rule fired.", 'wp-marketing-automations' ),
			),
			'Sucuri'           => array(
				'open_title'  => __( 'Open Sucuri → Firewall (WAF)', 'wp-marketing-automations' ),
				'open_hint'   => __( "If Sucuri shows a website firewall/CloudProxy address there, the block is likely happening on sucuri.net's own dashboard, not in this settings page - sign in there instead.", 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=sucuriscan_firewall',
				'open_label'  => __( 'Open Sucuri Firewall settings', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Look for a whitelist setting and allowlist the FunnelKit AI path.', 'wp-marketing-automations' ),
				'allow_after' => '',
			),
			'NinjaFirewall'    => array(
				'open_title'  => __( 'Open NinjaFirewall → Logs', 'wp-marketing-automations' ),
				'open_hint'   => __( 'Find the blocked request in the log - or go straight to Firewall Policies to add an allow rule by hand.', 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=nfsublog',
				'open_label'  => __( 'Open NinjaFirewall Logs', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Under Firewall Policies, allow this path through.', 'wp-marketing-automations' ),
				'allow_after' => '',
			),
			// WP Cerber's free tier has no UA- or path-matching rule at all - that's
			// Security Rules, which the plugin itself marks Pro-only. The only free
			// control is an IP allowlist, which doesn't suit FunnelKit AI's server with
			// no fixed IP - so this points at the one thing that can actually work:
			// turning off whichever rate-limit rule is doing the blocking.
			'WP Cerber'        => array(
				'open_title'  => __( 'Open WP Cerber → Activity', 'wp-marketing-automations' ),
				'open_hint'   => __( "Find the blocked request and see what it names as the reason - Cerber's free tier blocks by IP and rate-limit rules, not by user agent or path.", 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=cerber-security&tab=activity',
				'open_label'  => __( 'Open WP Cerber Activity', 'wp-marketing-automations' ),
				'allow_mode'  => 'rate_limit',
				'allow_title' => __( 'Turn off the rule that matched', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Under Main Settings, find the request-rate or "block IPs that send excessive requests" rule the Activity log named, and turn it off or raise its limit - FunnelKit AI\'s IP isn\'t fixed, so allowlisting one IP won\'t hold.', 'wp-marketing-automations' ),
				'allow_after' => '',
			),
			// Shield scores visitors on named "signals" (fake crawler, unusual user
			// agent, and similar) rather than matching a literal allowlist - so
			// there's no snippet to paste, only a signal to stop penalising.
			'Shield Security'  => array(
				'open_title'  => __( 'Open Shield Security → Investigate', 'wp-marketing-automations' ),
				'open_hint'   => __( 'Find the blocked request and see which signal it tripped - or go to Configure → Bots and IPs to change the rule directly.', 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=icwp-wpsf-activity',
				'open_label'  => __( 'Open Shield Security', 'wp-marketing-automations' ),
				'allow_mode'  => 'rate_limit',
				'allow_title' => __( 'Stop that signal from penalising this request', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Under Configure → Bots and IPs, look for the setting that excludes a signal - a fake crawler match, or an unusual/empty user agent - from being penalised, or add an IP exception if the signal was IP-based.', 'wp-marketing-automations' ),
				'allow_after' => '',
			),
			// The plugin's default lockouts are login-attempt and IP based, not
			// path or user-agent based - a rule that blocks a REST endpoint by
			// pattern only exists if a custom Firewall Rule was created for it.
			// Verified against the real Firewall → Rules builder: every rule is a
			// FIELD/OPERATOR/VALUE match, so its own VALUE is the thing to search
			// for - not a fixed setting name, which is why this can't offer one.
			'Solid Security'   => array(
				'open_title'  => __( 'Open Firewall → Logs', 'wp-marketing-automations' ),
				'open_hint'   => __( 'Find the blocked request in the Firewall log - or go straight to Firewall → Rules to edit the custom rule that matched.', 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=itsec-firewall',
				'open_label'  => __( 'Open Solid Security Firewall', 'wp-marketing-automations' ),
				'allow_mode'  => 'custom_rule',
				'allow_title' => __( 'Find and edit the matching rule', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Under Firewall → Rules, each rule matches a FIELD (URL Path or User Agent, for example) against a VALUE. Look for the rule whose VALUE matches this path, then set its ACTION to Allow, or deactivate it:', 'wp-marketing-automations' ),
				'allow_after' => '',
			),
			// Same plugin, same admin_url slugs - only the on-screen name changes.
			'Kadence Security' => array(
				'open_title'  => __( 'Open Firewall → Logs', 'wp-marketing-automations' ),
				'open_hint'   => __( 'Find the blocked request in the Firewall log - or go straight to Firewall → Rules to edit the custom rule that matched.', 'wp-marketing-automations' ),
				'open_url'    => 'admin.php?page=itsec-firewall',
				'open_label'  => __( 'Open Kadence Security Firewall', 'wp-marketing-automations' ),
				'allow_mode'  => 'custom_rule',
				'allow_title' => __( 'Find and edit the matching rule', 'wp-marketing-automations' ),
				'allow_hint'  => __( 'Under Firewall → Rules, each rule matches a FIELD (URL Path or User Agent, for example) against a VALUE. Look for the rule whose VALUE matches this path, then set its ACTION to Allow, or deactivate it:', 'wp-marketing-automations' ),
				'allow_after' => '',
			),
		);

		if ( isset( $specs[ $blocker_name ] ) ) {
			$spec = $specs[ $blocker_name ];

			$mode = $spec['allow_mode'] ?? '';

			if ( 'rate_limit' === $mode ) {
				$allow_step = array(
					'title'         => $spec['allow_title'],
					'hint'          => $spec['allow_hint'],
					'confirm_label' => __( "I've turned it off", 'wp-marketing-automations' ),
				);
			} elseif ( 'custom_rule' === $mode ) {
				$allow_step = array(
					'title'         => $spec['allow_title'],
					'hint'          => $spec['allow_hint'],
					'paths'         => null,
					'howto'         => array(
						'breadcrumb' => null,
						'body'       => '',
						// The path, labelled as the FIELD a rule matches on.
						'snippets'   => array(
							array(
								'label' => __( 'URL Path', 'wp-marketing-automations' ),
								'value' => '/wp-json/funnelkit/',
							),
						),
						'after'      => __( "If nothing in Rules matches, the block isn't coming from a Firewall Rule - check Firewall → Logs again for what actually fired.", 'wp-marketing-automations' ),
					),
					'confirm_label' => __( "I've made the change", 'wp-marketing-automations' ),
				);
			} else {
				$allow_step = array(
					'title'         => __( "Allow FunnelKit AI's path", 'wp-marketing-automations' ),
					'hint'          => $spec['allow_hint'],
					'paths'         => null,
					'howto'         => array(
						'breadcrumb' => null,
						'body'       => '',
						// The path, labelled as the FIELD a rule matches on.
						'snippets'   => array(
							array(
								'label' => __( 'URL Path', 'wp-marketing-automations' ),
								'value' => '/wp-json/funnelkit/',
							),
						),
						'after'      => $spec['allow_after'],
					),
					'confirm_label' => __( "I've made the change", 'wp-marketing-automations' ),
				);
			}

			return array(
				array(
					'title'  => $spec['open_title'],
					'hint'   => $spec['open_hint'],
					'button' => array(
						'label'    => $spec['open_label'],
						'url'      => admin_url( $spec['open_url'] ),
						'external' => false,
					),
				),
				$allow_step,
				array(
					'title'  => __( 'Re-test the connection', 'wp-marketing-automations' ),
					'hint'   => __( 'The change applies immediately - no cache purge needed.', 'wp-marketing-automations' ),
					'button' => array(
						'label'  => __( 'Re-test connection', 'wp-marketing-automations' ),
						'action' => 'retest',
					),
				),
			);
		}

		return array(
			array(
				'title'         => __( "Open your security plugin's firewall settings", 'wp-marketing-automations' ),
				'hint'          => __( 'Look for a rule that blocks REST API requests, or a general "block unknown bots" setting, and narrow it.', 'wp-marketing-automations' ),
				'confirm_label' => __( 'Found it', 'wp-marketing-automations' ),
			),
			array(
				'title'         => __( "Allow FunnelKit AI's path", 'wp-marketing-automations' ),
				'hint'          => __( 'Most security plugins let you allow a path through the firewall.', 'wp-marketing-automations' ),
				'paths'         => null,
				'howto'         => array(
					'breadcrumb' => null,
					'body'       => '',
					// The path, labelled as the FIELD a rule matches on.
					'snippets'   => array(
						array(
							'label' => __( 'URL Path', 'wp-marketing-automations' ),
							'value' => '/wp-json/funnelkit/',
						),
					),
					'after'      => '',
				),
				'confirm_label' => __( "I've made the change", 'wp-marketing-automations' ),
			),
			array(
				'title'  => __( 'Re-test the connection', 'wp-marketing-automations' ),
				'hint'   => __( 'Most plugins apply the change immediately.', 'wp-marketing-automations' ),
				'button' => array(
					'label'  => __( 'Re-test connection', 'wp-marketing-automations' ),
					'action' => 'retest',
				),
			),
		);
	}

	/**
	 * Message a site owner can send their host as-is: names the evidence and
	 * the change, so support does not have to ask what "blocked" means.
	 *
	 * @param string $paths Newline-separated paths.
	 *
	 * @return string
	 */
	private static function host_message( $paths ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return sprintf(
		/* translators: 1: newline-separated paths, 2: site hostname. */
			__(
				"Hi — FunnelKit AI, the service that connects my site to AI apps, is being blocked at the server or CDN level.\n\nRequests to my site get a block page on every URL, including /robots.txt. Because /robots.txt never reaches WordPress, the rule is above WordPress, not in a plugin.\n\nPlease allow requests to these paths:\n%1\$s\n\nSite: %2\$s\nThanks.",
				'wp-marketing-automations'
			),
			$paths,
			$host
		);
	}

	/**
	 * Message a site owner can send whoever manages their Cloudflare zone.
	 *
	 * @param string $paths Newline-separated paths.
	 *
	 * @return string
	 */
	private static function cdn_message( $paths ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return sprintf(
		/* translators: 1: newline-separated paths, 2: site hostname. */
			__(
				"Hi — Cloudflare is serving a challenge to FunnelKit AI, the service that connects my site to AI apps.\n\nRequests to my site receive a 403 with cf-mitigated: challenge. FunnelKit AI connects from its own server and cannot solve a browser challenge.\n\nPlease add a WAF skip rule for these paths:\n%1\$s\n\nZone: %2\$s\nThanks.",
				'wp-marketing-automations'
			),
			$paths,
			$host
		);
	}

	/**
	 * Plain-text export of a diagnosis, for the "Copy diagnostic report"
	 * button - what a site owner pastes into a support ticket.
	 *
	 * @param string $title    Diagnosis title.
	 * @param array  $checks   Evidence rows.
	 * @param string $verdict  Plain-text verdict, if any.
	 *
	 * @return string
	 */
	private static function build_report( $title, $checks, $verdict ) {
		$lines = array(
			__( 'FunnelKit AI — connection diagnostic', 'wp-marketing-automations' ),
			sprintf(
			/* translators: %s: site URL. */
				__( 'Site: %s', 'wp-marketing-automations' ),
				home_url( '/' )
			),
			sprintf(
			/* translators: %s: date and time the test ran. */
				__( 'Run: %s', 'wp-marketing-automations' ),
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			'',
			sprintf(
			/* translators: %s: diagnosis title. */
				__( 'Result: %s', 'wp-marketing-automations' ),
				$title
			),
		);

		foreach ( $checks as $check ) {
			$lines[] = sprintf(
				'  [%1$s] %2$s → %3$s',
				! empty( $check['ok'] ) ? 'ok' : 'FAIL',
				$check['label'],
				$check['result']
			);
		}

		if ( '' !== $verdict ) {
			$lines[] = '';
			$lines[] = sprintf(
			/* translators: %s: plain-text verdict. */
				__( 'Verdict: %s', 'wp-marketing-automations' ),
				$verdict
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * The user agent the checks send: WordPress's own, as any request from the
	 * site would. FunnelKit AI's server, not the AI apps, is what calls the store.
	 *
	 * @return string
	 */
	private static function agent() {
		return 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' );
	}

	/**
	 * The two requests a block shows up on: the home page FunnelKit AI reads the
	 * REST API address from, and the endpoint.
	 *
	 * @param array $status Result of self::status().
	 *
	 * @return array Probe specs.
	 */
	private static function block_probe_specs( $status ) {
		return array(
			'home' => array(
				'url'    => home_url( '/' ),
				'method' => 'GET',
				'agent'  => self::agent(),
				'body'   => null,
				'accept' => 'text/html',
				'label'  => __( 'Home page', 'wp-marketing-automations' ),
			),
			'mcp'  => array(
				'url'    => $status['endpoint'],
				'method' => 'POST',
				'agent'  => self::agent(),
				'body'   => self::rpc( 'initialize' ),
				'label'  => __( 'FunnelKit AI endpoint', 'wp-marketing-automations' ),
			),
		);
	}

	/**
	 * One response out of the batch, or an error when the probe never ran.
	 *
	 * @param array  $results Batched responses.
	 * @param string $key     Probe key.
	 *
	 * @return array|WP_Error
	 */
	private static function batched( $results, $key ) {
		if ( isset( $results[ $key ] ) ) {
			return $results[ $key ];
		}

		return new WP_Error( 'fkmcp_probe_missing', __( 'The check could not be run.', 'wp-marketing-automations' ) );
	}

	/**
	 * Every secondary probe in one window: the loopback requests run in
	 * parallel, so a site that swallows them costs one timeout instead of one
	 * per endpoint. Falls back to sequential calls when the Requests library
	 * cannot run a batch.
	 *
	 * @param array $specs  Probe specs.
	 * @param array $status Result of self::status().
	 *
	 * @return array Key => response or WP_Error.
	 */
	private static function probe_batch( array $specs, $status ) {
		$responses = array();

		if ( empty( $specs ) ) {
			return $responses;
		}

		if ( ! class_exists( '\\WpOrg\\Requests\\Requests' ) || ! class_exists( 'WP_HTTP_Requests_Response' ) ) {
			foreach ( $specs as $key => $spec ) {
				$responses[ $key ] = self::probe( $spec['url'], $spec['method'], $spec['agent'], $status, $spec['body'] );
			}

			return $responses;
		}

		$options  = array(
			'timeout'          => self::PROBE_TIMEOUT,
			'connect_timeout'  => self::PROBE_TIMEOUT,
			'follow_redirects' => false,
			'verify'          => $status['is_local_dev'] ? false : ABSPATH . WPINC . '/certificates/ca-bundle.crt',
		);
		$requests = array();

		foreach ( $specs as $key => $spec ) {
			$headers = array( 'Accept' => isset( $spec['accept'] ) ? $spec['accept'] : 'application/json, text/event-stream' );

			if ( null !== $spec['body'] ) {
				$headers['Content-Type'] = 'application/json';
			}

			$requests[ $key ] = array(
				'url'     => $spec['url'],
				'type'    => 'POST' === $spec['method'] ? \WpOrg\Requests\Requests::POST : \WpOrg\Requests\Requests::GET,
				'headers' => $headers,
				'data'    => null === $spec['body'] ? array() : $spec['body'],
				'options' => array_merge( $options, array( 'useragent' => $spec['agent'] ) ),
			);
		}

		try {
			$results = \WpOrg\Requests\Requests::request_multiple( $requests, $options );
		} catch ( \Throwable $e ) {
			foreach ( $specs as $key => $spec ) {
				$responses[ $key ] = self::probe( $spec['url'], $spec['method'], $spec['agent'], $status, $spec['body'] );
			}

			return $responses;
		}

		foreach ( $specs as $key => $spec ) {
			$result = isset( $results[ $key ] ) ? $results[ $key ] : null;

			if ( $result instanceof \WpOrg\Requests\Response ) {
				$wrapped           = new WP_HTTP_Requests_Response( $result );
				$responses[ $key ] = $wrapped->to_array();

				continue;
			}

			$responses[ $key ] = new WP_Error(
				'http_request_failed',
				$result instanceof \Throwable ? $result->getMessage() : __( 'The request did not complete.', 'wp-marketing-automations' )
			);
		}

		return $responses;
	}

	/**
	 * One loopback request to this site. Redirects come back as they are,
	 * because the gateway never follows one.
	 *
	 * @param string      $url     URL to call.
	 * @param string      $method  HTTP method.
	 * @param string      $agent   User agent.
	 * @param array       $status  Result of self::status().
	 * @param string|null $body    Request body, if any (JSON unless $headers say otherwise).
	 * @param array       $headers Extra request headers.
	 * @param int|null    $timeout Seconds, PROBE_TIMEOUT by default.
	 *
	 * @return array|WP_Error
	 */
	private static function probe( $url, $method, $agent, $status, $body = null, $headers = array(), $timeout = null ) {
		$args = array(
			'method'      => $method,
			'timeout'     => null === $timeout ? self::PROBE_TIMEOUT : $timeout,
			'redirection' => 0,
			'sslverify'   => ! $status['is_local_dev'],
			'user-agent'  => $agent,
			'headers'     => array( 'Accept' => 'application/json, text/event-stream' ),
		);

		if ( null !== $body ) {
			$args['body']                    = $body;
			$args['headers']['Content-Type'] = 'application/json';
		}

		$args['headers'] = array_merge( $args['headers'], $headers );

		return wp_remote_request( $url, $args );
	}

	/**
	 * What sits in front of WordPress, judged from a refused response: '' when
	 * nothing recognisable.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return string
	 */
	private static function blocker( $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$server = strtolower( (string) wp_remote_retrieve_header( $response, 'server' ) );
		$type   = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		$body   = strtolower( substr( (string) wp_remote_retrieve_body( $response ), 0, 20000 ) );

		if ( '' !== (string) wp_remote_retrieve_header( $response, 'cf-mitigated' ) || false !== strpos( $body, 'cf-chl' ) || false !== strpos( $body, 'just a moment' ) ) {
			return 'Cloudflare challenge';
		}

		if ( false !== strpos( $server, 'cloudflare' ) && in_array( $code, array( 403, 429, 503 ), true ) ) {
			return 'Cloudflare';
		}

		if ( false !== strpos( $body, 'wordfence' ) ) {
			return 'Wordfence';
		}

		if ( false !== strpos( $body, 'sucuri' ) || '' !== (string) wp_remote_retrieve_header( $response, 'x-sucuri-id' ) ) {
			return 'Sucuri';
		}

		if ( false !== strpos( $body, 'ninjafirewall' ) ) {
			return 'NinjaFirewall';
		}

		if ( false !== strpos( $body, 'cerber' ) ) {
			return 'WP Cerber';
		}

		if ( false !== strpos( $body, 'shield security' ) || false !== strpos( $body, 'shieldpro' ) ) {
			return 'Shield Security';
		}

		// iThemes Security rebranded to Solid Security in 2024 - a site can still be on
		// either name depending when it last updated, and the lockout page carries
		// whichever one was current when it rendered.
		if ( false !== strpos( $body, 'ithemes security' ) || false !== strpos( $body, 'solid security' ) ) {
			return 'Solid Security';
		}

		// The same plugin, white-labelled: a site running it through a Kadence
		// bundle shows "Kadence Security" everywhere in wp-admin, including its
		// own lockout page, and never says Solid or iThemes at all - confirmed
		// against a real install. Named separately so the title matches what
		// that site owner actually sees in their own menu.
		if ( false !== strpos( $body, 'kadence security' ) ) {
			return 'Kadence Security';
		}

		if ( false !== strpos( $body, 'captcha' ) ) {
			return 'CAPTCHA';
		}

		if ( in_array( $code, array( 403, 429, 503 ), true ) && false !== strpos( $type, 'text/html' ) ) {
			return __( 'an HTML block page', 'wp-marketing-automations' );
		}

		return '';
	}

	/**
	 * One line of the connection test result.
	 *
	 * @param string $key     Stable identifier.
	 * @param string $label   Label shown to the user.
	 * @param bool   $ok      Whether the check passed.
	 * @param string $message Result shown next to the label.
	 *
	 * @return array
	 */
	private static function check( $key, $label, $ok, $message ) {
		return array(
			'key'     => $key,
			'label'   => $label,
			'ok'      => (bool) $ok,
			'message' => $message,
		);
	}

	private static function test_result( $ok, $message, $ms = 0, $checks = array(), $diagnosis = null ) {
		$result = array(
			'ok'      => (bool) $ok,
			'message' => $message,
			'ms'      => (int) $ms,
			'time'    => time(),
			'checks'  => array_values( $checks ),
		);

		if ( null !== $diagnosis ) {
			$result['diagnosis'] = $diagnosis;
		}

		if ( $ok ) {
			update_user_meta( get_current_user_id(), self::OPT_LAST_TEST, $result );
		} else {
			delete_user_meta( get_current_user_id(), self::OPT_LAST_TEST );
		}

		return rest_ensure_response(
			array(
				'test' => $result,
			)
		);
	}

	/**
	 * Memoized for the request: status() runs load_packs() and counts every
	 * pack's definitions, and a settings call reads it more than once.
	 * Handlers that change what status() reports reset the memo themselves.
	 */
	private static $status = null;

	private static function status() {
		if ( null !== self::$status ) {
			return self::$status;
		}

		// Packs register their definitions only when loaded, so the abilities copy
		// and the connection test would read an empty registry without this.
		self::load_packs();

		self::load_admin();

		self::$status = BWFAN_MCP_Admin::status();

		return self::$status;
	}

	private static function abilities_payload() {
		self::load_packs();

		$packs     = class_exists( 'FKMCP_Registry' ) ? self::ordered_packs() : array();
		$group_map = class_exists( 'FKMCP_Registry' ) ? self::group_map() : array();
		$groups    = array();
		$plugins   = array();
		$writes    = array( 'total' => 0 );
		$seen      = array();

		foreach ( $packs as $pack ) {
			if ( ! is_object( $pack ) || ! method_exists( $pack, 'is_available' ) || ! $pack->is_available() ) {
				continue;
			}

			$plugin = self::pack_meta( $pack );

			if ( ! isset( $plugins[ $plugin['id'] ] ) ) {
				$plugins[ $plugin['id'] ] = $plugin;
			}

			foreach ( $pack->definitions() as $slug => $definition ) {
				// Same gate the registry applies: an unavailable ability does not exist on this site.
				if ( isset( $definition['available'] ) && ( ! is_callable( $definition['available'] ) || ! call_user_func( $definition['available'] ) ) ) {
					continue;
				}

				if ( isset( $seen[ $slug ] ) ) {
					continue;
				}
				$seen[ $slug ] = true;

				$readonly = ! empty( $definition['annotations']['readonly'] );

				// Every plugin family ships one orientation ability; they live
				// together in a single shared group pinned to the top.
				$is_context = '-context' === substr( $slug, -8 );
				$group_name = $is_context ? __( 'Context', 'wp-marketing-automations' ) : self::group_for( $slug, $group_map );
				// Groups merge across plugins by name; each item keeps its
				// plugin_id so the UI can filter and badge per plugin.
				$key = 'shared::' . $group_name;

				if ( ! $readonly ) {
					++$writes['total'];
				}

				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'name'          => $group_name,
						'plugin'        => 'FunnelKit',
						'plugin_id'     => 'shared',
						'plugin_colour' => $plugin['colour'],
						'items'         => array(),
					);
				}

				$groups[ $key ]['items'][] = array(
					'slug'      => $slug,
					'name'      => isset( $definition['label'] ) ? $definition['label'] : $slug,
					'desc'      => self::short_description( $definition ),
					'scope'     => $readonly ? 'read' : 'write',
					'plugin_id' => $plugin['id'],
				);
			}
		}

		$pro = self::pro_pitch( array_keys( $plugins ) );

		// Not installed: its abilities still list, flagged, under the groups
		// they would join - so the screen shows the whole surface either way.
		if ( empty( $pro['active'] ) ) {
			foreach ( $pro['groups'] as $pro_group ) {
				$key = 'shared::' . $pro_group['name'];

				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'name'          => $pro_group['name'],
						'plugin'        => 'FunnelKit',
						'plugin_id'     => 'shared',
						'plugin_colour' => self::DEFAULT_PACK_COLOUR,
						'items'         => array(),
					);
				}

				foreach ( $pro_group['items'] as $index => $item ) {
					$groups[ $key ]['items'][] = array(
						'slug'      => 'pro-' . sanitize_title( $item['name'] ) . '-' . $index,
						'name'      => $item['name'],
						'desc'      => $item['desc'],
						'scope'     => $item['scope'],
						'plugin_id' => $pro['id'],
						'pro'       => true,
					);
				}
			}
		}

		if ( isset( $groups['shared::Context'] ) ) {
			$groups = array( 'shared::Context' => $groups['shared::Context'] ) + $groups;
		}

		$groups = array_values( $groups );

		foreach ( $groups as &$group ) {
			$group['total_count'] = count( $group['items'] );

			$pro_items = 0;

			foreach ( $group['items'] as $item ) {
				$pro_items += empty( $item['pro'] ) ? 0 : 1;
			}

			$group['has_pro']  = $pro_items > 0;
			$group['pro_only'] = $pro_items === $group['total_count'];
		}
		unset( $group );

		return array(
			'groups'     => $groups,
			'plugins'    => array_values( $plugins ),
			'writes'     => $writes,

			'has_writes' => $writes['total'] > 0,
			'pro'        => $pro,
		);
	}

	/**
	 * Abilities an upgrade would add, listed alongside the registered ones when
	 * the pack that owns them is not contributing. Only this plugin's own Pro is
	 * pitched (bwfan_mcp_pro_pitch filter), never another family's.
	 *
	 * @param array $plugin_ids ids of the packs contributing right now.
	 *
	 * @return array
	 */
	private static function pro_pitch( $plugin_ids ) {
		$pitch = (array) apply_filters( 'bwfan_mcp_pro_pitch', array(), $plugin_ids );

		$pitch = wp_parse_args(
			$pitch,
			array(
				'id'     => '',
				'name'   => '',
				'groups' => array(),
			)
		);

		// No pitch registered reads as nothing to sell.
		$pitch['active'] = '' === $pitch['id'] || in_array( $pitch['id'], $plugin_ids, true );

		$total = 0;

		foreach ( $pitch['groups'] as $group ) {
			$total += isset( $group['items'] ) ? count( $group['items'] ) : 0;
		}

		$pitch['total'] = $total;

		return $pitch;
	}

	/**
	 * Packs in the order this screen lists them: this plugin's family first
	 * (Lite, then Pro), then the other family - registration order otherwise.
	 */
	private static function ordered_packs() {
		$packs = class_exists( 'FKMCP_Registry' ) ? FKMCP_Registry::packs() : array();
		$first = array( 'automations', 'automations-pro' );
		$rank  = array();

		foreach ( $packs as $index => $pack ) {
			$meta   = self::pack_meta( $pack );
			$own    = array_search( $meta['id'], $first, true );
			$rank[] = array( false !== $own ? $own : 2 + ( empty( $meta['pro'] ) ? 0 : 1 ), $index );
		}

		array_multisort( array_column( $rank, 0 ), SORT_ASC, array_column( $rank, 1 ), SORT_ASC, $packs );

		return $packs;
	}

	private static function pack_meta( $pack ) {
		$meta = method_exists( $pack, 'plugin_meta' ) ? (array) $pack->plugin_meta() : array();

		$meta = wp_parse_args(
			$meta,
			array(
				'id'     => sanitize_key( get_class( $pack ) ),
				'name'   => get_class( $pack ),
				'colour' => self::DEFAULT_PACK_COLOUR,
				'groups' => array(),
			)
		);

		return apply_filters( 'fkmcp_pack_meta', $meta, $pack );
	}

	/**
	 * Slug-needle → group label, merged from every pack's plugin_meta().
	 * `+=` keeps the first definition of a needle, so pack registration
	 * order (Lite before Pro before anything else) sets precedence.
	 */
	private static function group_map() {
		$map = array();

		foreach ( self::ordered_packs() as $pack ) {
			if ( ! is_object( $pack ) ) {
				continue;
			}

			$map += (array) self::pack_meta( $pack )['groups'];
		}

		return $map;
	}

	private static function load_packs() {
		if ( class_exists( 'FKMCP_Registry' ) ) {
			return;
		}

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		BWFAN_MCP_Loader::load();

		if ( class_exists( 'FKMCP_Module', false ) ) {
			FKMCP_Module::load_runtime();
		}
	}

	private static function group_for( $slug, $map ) {
		$best = '';
		$hit  = null;

		// Longest matching needle wins, so 'automations-overview' beats 'automation' whatever the registration order.
		foreach ( $map as $needle => $group ) {
			if ( false !== strpos( $slug, $needle ) && strlen( $needle ) > strlen( $best ) ) {
				$best = $needle;
				$hit  = $group;
			}
		}

		if ( null !== $hit ) {
			return $hit;
		}

		return __( 'Other', 'wp-marketing-automations' );
	}

	private static function short_description( $definition ) {
		$description = isset( $definition['description'] ) ? (string) $definition['description'] : '';

		if ( '' === $description ) {
			return '';
		}

		$parts = preg_split( '/(?<=\.)\s+/', $description, 2 );

		return trim( $parts[0] );
	}
}
