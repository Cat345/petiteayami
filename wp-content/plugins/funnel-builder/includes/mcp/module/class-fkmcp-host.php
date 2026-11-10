<?php

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter: everything the shared module needs to know about the plugin
 * it runs inside, in one object per plugin. The module itself never names a
 * host plugin: no references to any plugin's classes, no slug maps.
 *
 * Each host plugin ships one subclass and hands it to fkmcp_register_module(). On a site with several FunnelKit
 * plugins every host is registered; where one answer is needed (capabilities,
 * logging) the highest-priority host answers, so behaviour does not depend on
 * which plugin's module copy won boot arbitration.
 */
abstract class FKMCP_Host {

	/** Plugin directory slug, which is also its text domain. */
	abstract public function slug();

	/** Display name shown to admins. */
	abstract public function name();

	/** Higher wins when hosts disagree (capabilities, log destination). */
	public function priority() {
		return 10;
	}

	/** Whether the current user may read through the MCP endpoint. */
	public function can_read() {
		return current_user_can( 'manage_options' );
	}

	/** Whether the current user may call write tools (hidden from tools/list otherwise). */
	public function can_write() {
		return false;
	}

	/**
	 * Transport permission callback for the MCP server: who may connect.
	 *
	 * @param WP_REST_Request|null $request
	 *
	 * @return bool|WP_Error
	 */
	public function transport_gate( $request = null ) {
		return is_user_logged_in() && $this->can_read();
	}

	/**
	 * Wraps every ability callback before registration - the host's place for
	 * auditing, rate limiting and scrubbing what an agent gets back.
	 *
	 * @param string   $ability_name funnelkit/<slug>
	 * @param callable $callback
	 *
	 * @return callable
	 */
	public function wrap_ability( $ability_name, $callback ) {
		return $callback;
	}

	/**
	 * Append one line to the host's log store.
	 *
	 * @param string $line
	 */
	public function log( $line ) {
	}

	/** Whether this host's Pro companion is active. */
	public function pro_active() {
		return false;
	}

	/** Whether the Pro companion is validly activated (only consulted when pro_active()). */
	public function pro_ok() {
		return true;
	}

	/**
	 * What the FunnelKit AI gateway records a call under, sent as
	 * `_meta.fkai_store` on the connector's initialize and tools/list results
	 * so failure rates split by plugin release: plugin (directory slug),
	 * version, pro_version ('' without the Pro companion) and license (lite,
	 * pro, or the Pro companion's own not-valid states). Empty = send nothing.
	 *
	 * @return array
	 */
	public function store_meta() {
		return array();
	}

	/**
	 * Called once when the module boots: register ability packs, settings
	 * entries, anything hook-based the host contributes.
	 */
	public function register() {
	}
}

/** The hosts registered for this request. */
final class FKMCP_Hosts {
	/** @var FKMCP_Host[] slug => host */
	private static $hosts = array();

	/** @var string Slug of the plugin whose module copy booted. */
	private static $booted = '';

	/**
	 * Registers a host adapter with the module.
	 *
	 * @param FKMCP_Host $host Host adapter.
	 *
	 * @return void
	 */
	public static function add( FKMCP_Host $host ) {
		self::$hosts[ $host->slug() ] = $host;
	}

	/**
	 * Records which plugin's copy of the module booted.
	 *
	 * @param string $slug Plugin directory slug.
	 *
	 * @return void
	 */
	public static function set_booted( $slug ) {
		self::$booted = (string) $slug;
	}

	/** @return FKMCP_Host[] */
	public static function all() {
		return self::$hosts;
	}

	/** @return FKMCP_Host|null */
	public static function get( $slug ) {
		return isset( self::$hosts[ $slug ] ) ? self::$hosts[ $slug ] : null;
	}

	/**
	 * The host that answers single-valued questions (capabilities, logging).
	 *
	 * @return FKMCP_Host|null
	 */
	public static function primary() {
		$best = null;

		foreach ( self::$hosts as $host ) {
			if ( null === $best || $host->priority() > $best->priority() ) {
				$best = $host;
			}
		}

		if ( null === $best ) {
			_doing_it_wrong( __METHOD__, 'No FKMCP_Host registered - fkmcp_register_module() requires a host adapter.', esc_html( FKMCP_VERSION ) );
		}

		return $best;
	}

	/** Slug of the plugin whose module copy booted (the one whose UI usually calls). */
	public static function booted_slug() {
		if ( '' === self::$booted && ! empty( self::$hosts ) ) {
			self::$booted = self::primary()->slug();
		}

		return self::$booted;
	}

	/** Display name for a slug, falling back to a humanised slug. */
	public static function name( $slug = '' ) {
		$slug = '' !== $slug ? $slug : self::booted_slug();
		$host = self::get( $slug );
		$name = $host ? $host->name() : ucwords( str_replace( '-', ' ', (string) $slug ) );

		return apply_filters( 'fkmcp_host_name', $name, $slug );
	}
}
