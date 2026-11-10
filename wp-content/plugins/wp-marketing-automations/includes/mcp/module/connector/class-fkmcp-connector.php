<?php

defined( 'ABSPATH' ) || exit;

/**
 * FunnelKit AI connector: the OAuth server the FunnelKit AI gateway signs in
 * through and the MCP endpoint it calls, under /wp-json/funnelkit/.
 *
 * Entry class: the only connector file loaded wherever the module boots. Its
 * hook wrappers look at the route first, so the other classes load only on a
 * connector request.
 */
final class FKMCP_Connector {
	const OPT_ENABLED = 'fkai_enabled';
	const CRON        = 'fkai_cleanup';
	const DB_VERSION  = 1;

	private static $booted     = false;
	private static $stood_down = false;

	public static function dir() {
		return trailingslashit( __DIR__ );
	}

	/** The site-wide "Allow AI connections" switch. */
	public static function enabled() {
		return 'yes' === get_option( self::OPT_ENABLED, 'no' );
	}

	/** True when the standalone connector plugin is active and keeps serving. */
	public static function stood_down() {
		return self::$stood_down;
	}

	public static function autoload( $class ) {
		if ( 0 !== strpos( (string) $class, 'FKMCP_Connector_' ) ) {
			return;
		}

		$file = self::dir() . 'class-fkmcp-connector-' . strtolower( substr( (string) $class, 16 ) ) . '.php';

		if ( preg_match( '/^FKMCP_Connector_[A-Za-z]+$/', (string) $class ) && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/** The exact-name autoloader. Always registered, even without the Abilities API: the settings screen reads the connector data. */
	public static function register_autoloader() {
		static $done = false;

		if ( ! $done ) {
			$done = true;
			spl_autoload_register( array( __CLASS__, 'autoload' ) );
		}
	}

	/**
	 * The connector's hooks; the module calls this only when the Abilities API exists.
	 * The weekly purge and the user-revocation hooks are not here: they must run on
	 * requests where the module never boots (wp-cron, front end, core REST), so the
	 * host registers them from always-loaded code (BWFAN_Common::init).
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		self::register_autoloader();

		// The standalone plugin serves the same routes and data names; two servers on one site would race.
		if ( defined( 'FKAI_VERSION' ) ) {
			self::$stood_down = true;

			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'routes' ), 20 );
		add_filter( 'determine_current_user', array( __CLASS__, 'authenticate' ), 25 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'auth_errors' ), 50 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'keep_cookie_user' ), 99 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'challenge' ), 10, 3 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_route' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
	}

	/** Creates or upgrades the grants table and schedules the weekly purge. Cheap when current. */
	public static function maybe_install() {
		if ( (int) get_option( 'fkai_db_version', 0 ) < self::DB_VERSION ) {
			FKMCP_Connector_Grants::install();
		}

		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON );
		}
	}

	public static function routes() {
		self::maybe_install();
		FKMCP_Connector_Http::register_routes();
	}

	public static function authenticate( $user_id ) {
		if ( ! empty( $user_id ) || ! FKMCP_Connector_Urls::is_mcp_request( $_SERVER, $_GET, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- reads the request path only, to pick the route; nothing from the request is acted on here.
			return $user_id;
		}

		return FKMCP_Connector_Bearer::authenticate( $user_id );
	}

	public static function auth_errors( $result ) {
		return class_exists( 'FKMCP_Connector_Bearer', false ) ? FKMCP_Connector_Bearer::auth_errors( $result ) : $result;
	}

	public static function keep_cookie_user( $result ) {
		if ( null !== $result || ! FKMCP_Connector_Urls::is_browser_route( $_SERVER, $_GET, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- reads the request path only, to pick the route; nothing from the request is acted on here.
			return $result;
		}

		return FKMCP_Connector_Bearer::keep_cookie_user( $result );
	}

	public static function challenge( $response, $server, $request ) {
		return class_exists( 'FKMCP_Connector_Bearer', false ) ? FKMCP_Connector_Bearer::challenge( $response, $server, $request ) : $response;
	}

	public static function guard_route( $result, $server, $request ) {
		return class_exists( 'FKMCP_Connector_Bearer', false ) ? FKMCP_Connector_Bearer::guard_route( $result, $server, $request ) : $result;
	}
}
