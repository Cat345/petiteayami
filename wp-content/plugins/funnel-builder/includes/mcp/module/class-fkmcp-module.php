<?php

defined( 'ABSPATH' ) || exit;

/**
 * Entry point for the shared FunnelKit module (bundled by each FunnelKit
 * plugin, arbitrated by fkmcp-bootstrap.php). It registers the hosts' ability
 * packs and boots the FunnelKit AI connector, which serves them to the
 * FunnelKit AI gateway. Auth, logging, settings screens and pack helpers
 * belong to the host plugins (FKMCP_Host).
 */
class FKMCP_Module {
	private static $booted = false;

	private static $runtime_loaded = false;

	/** @var bool|null Memoised answer of needs_abilities() for this request. */
	private static $needs_abilities = null;

	/** WP-CLI commands that read abilities: scripts. A shell already controls the site, so the switch does not apply. */
	const CLI_COMMANDS = array( 'eval', 'eval-file', 'shell' );

	/** REST routes that read the abilities: the MCP endpoint and the two consent pages that count tools. */
	const ROUTES = array( 'funnelkit/mcp', 'funnelkit/oauth/authorize', 'funnelkit/oauth/consent' );

	/** The consent pages, which run before AI connections are on. */
	const CONSENT_ROUTES = array( 'funnelkit/oauth/authorize', 'funnelkit/oauth/consent' );

	/**
	 * Directory of the module copy that won arbitration, with a trailing slash.
	 *
	 * @return string
	 */
	public static function path() {
		return trailingslashit( __DIR__ );
	}

	/**
	 * Boots the module: hosts, the connector, and the ability hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Hosts contribute their packs and settings entries; the module never
		// touches a host's settings schema itself.
		foreach ( FKMCP_Hosts::all() as $host ) {
			$host->register();
		}

		// The settings screen reads the connector's data on any WordPress version, so the
		// entry and its autoloader load first; only the hooks need the Abilities API.
		if ( ! class_exists( 'FKMCP_Connector', false ) ) {
			require_once self::path() . 'connector/class-fkmcp-connector.php';
		}
		FKMCP_Connector::register_autoloader();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		FKMCP_Connector::init();

		// Cheap to add: needs_abilities() decides at registration time, so a
		// script that turns the switch on before asking still gets them.
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Whether this request may read the funnelkit abilities. Memoised: the
	 * category and the abilities must get the same answer, or the abilities
	 * would register against a missing category.
	 *
	 * @return bool
	 */
	public static function needs_abilities() {
		if ( null === self::$needs_abilities ) {
			$needs = (bool) apply_filters( 'fkmcp_needs_abilities', self::request_needs_abilities() );

			self::$needs_abilities = (bool) apply_filters( 'funnelkit_needs_abilities', $needs );
		}

		return self::$needs_abilities;
	}

	/**
	 * WP-CLI: only the script commands. While AI connections are off, only the
	 * consent pages; once on, every non-REST request (admin, AJAX,
	 * rest_do_request - nothing builds unless asked), and over REST only the
	 * connector's routes and the hosts' settings namespaces.
	 *
	 * @return bool
	 */
	private static function request_needs_abilities() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( ! class_exists( 'WP_CLI', false ) || ! method_exists( 'WP_CLI', 'get_runner' ) ) {
				return true;
			}

			$args = (array) WP_CLI::get_runner()->arguments;

			return isset( $args[0] ) && in_array( $args[0], self::CLI_COMMANDS, true );
		}

		if ( ! class_exists( 'FKMCP_Connector' ) ) {
			return false;
		}

		$enabled = FKMCP_Connector::enabled();

		if ( ! function_exists( 'wp_is_serving_rest_request' ) || ! wp_is_serving_rest_request() ) {
			return $enabled;
		}

		// Canonical route, normalised for pretty, ?rest_route=, index.php/, subdirectory and custom-prefix URLs. Not parsed yet: fail open while on.
		if ( ! isset( $GLOBALS['wp'] ) || ! is_object( $GLOBALS['wp'] ) || ! isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return $enabled;
		}

		// WP_REST_Server matches routes case-insensitively.
		$route = strtolower( trim( (string) $GLOBALS['wp']->query_vars['rest_route'], '/' ) );

		// Off, only the consent pages count tools: approving the first AI client is what turns connections on.
		if ( ! $enabled ) {
			return in_array( $route, self::CONSENT_ROUTES, true );
		}

		$prefixes = self::ROUTES;

		foreach ( isset( $GLOBALS['fkmcp_module_candidates'] ) ? (array) $GLOBALS['fkmcp_module_candidates'] : array() as $candidate ) {
			if ( ! empty( $candidate['host']['namespace'] ) ) {
				$prefixes[] = trim( (string) $candidate['host']['namespace'], '/' );
			}
		}

		foreach ( array_map( 'strtolower', $prefixes ) as $prefix ) {
			if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * wp_abilities_api_categories_init: registers the ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! self::needs_abilities() ) {
			return;
		}

		self::load_runtime();
		FKMCP_Registry::register_category();
	}

	/**
	 * wp_abilities_api_init: registers every pack's abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! self::needs_abilities() ) {
			return;
		}

		self::load_runtime();
		FKMCP_Registry::register_abilities();
	}

	/** Display name of a host plugin; the booted one by default. */
	public static function host_name( $slug = '' ) {
		return FKMCP_Hosts::name( $slug );
	}

	public static function load_runtime() {
		if ( self::$runtime_loaded ) {
			return;
		}
		self::$runtime_loaded = true;

		require_once self::path() . 'class-fkmcp-pack.php';
		require_once self::path() . 'class-fkmcp-registry.php';
	}
}
