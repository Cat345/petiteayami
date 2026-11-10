<?php

defined( 'ABSPATH' ) || exit;

/** FunnelKit Automations' host adapter for the shared FunnelKit MCP module. */
class FKMCP_Host_Automations extends FKMCP_Host {

	/** REST namespace of the MCP settings endpoints this plugin serves to its admin app. */
	const REST_NAMESPACE = 'autonami-app/mcp';

	public function slug() {
		return 'wp-marketing-automations';
	}

	public function name() {
		return 'FunnelKit Automations';
	}

	/** Highest priority among FunnelKit hosts: the CRM owns capabilities and the log store. */
	public function priority() {
		return 20;
	}

	public function can_read() {
		$this->load();

		return BWFAN_MCP_Permissions::can_read();
	}

	public function can_write() {
		$this->load();

		return BWFAN_MCP_Permissions::can_write();
	}

	public function transport_gate( $request = null ) {
		$this->load();

		return BWFAN_MCP_Permissions::transport_gate( $request );
	}

	public function wrap_ability( $ability_name, $callback ) {
		$this->load();

		return BWFAN_MCP_Log::wrap( $ability_name, $callback );
	}

	public function log( $line ) {
		$this->load();

		BWFAN_MCP_Log::event( $line );
	}

	public function pro_active() {
		return defined( 'BWFAN_PRO_VERSION' );
	}

	/** The Pro license as the license screen sees it: activated, not deactivated by hand, not expired. */
	public function pro_ok() {
		$license = BWFAN_Common::get_pro_license( false );

		return is_array( $license ) && empty( $license['error_msg'] ) && empty( $license['manually_deactivated'] ) && empty( $license['expired'] );
	}

	/** The license level the Pro tools answer by: lite (no Pro), pro (valid), pro_expired, else pro_inactive. */
	public function license_level() {
		if ( ! $this->pro_active() ) {
			return 'lite';
		}

		if ( $this->pro_ok() ) {
			return 'pro';
		}

		$license = BWFAN_Common::get_pro_license( false );

		return is_array( $license ) && ! empty( $license['expired'] ) && empty( $license['manually_deactivated'] ) ? 'pro_expired' : 'pro_inactive';
	}

	public function store_meta() {
		return array(
			'plugin'      => 'wp-marketing-automations',
			'version'     => defined( 'BWFAN_VERSION' ) ? (string) BWFAN_VERSION : '',
			'pro_version' => $this->pro_active() ? (string) BWFAN_PRO_VERSION : '',
			'license'     => $this->license_level(),
		);
	}

	public function register() {
		// Packs, dispatch map, Pro pitch and the settings tab are wired by
		// BWFAN_Common (register_mcp_* methods) so they load with the plugin.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/** The settings-screen endpoints, under this plugin's own namespace. */
	public function register_rest_routes() {
		if ( ! self::is_namespace_request( self::REST_NAMESPACE ) ) {
			return;
		}

		require_once __DIR__ . '/class-bwfan-mcp-loader.php';
		BWFAN_MCP_Loader::load_admin();
		BWFAN_MCP_Settings_API::register_routes( self::REST_NAMESPACE );
	}

	private function load() {
		require_once __DIR__ . '/class-bwfan-mcp-loader.php';
		BWFAN_MCP_Loader::load();
	}

	/** Whether the current REST request targets a namespace (REST_REQUEST is not defined this early). */
	private static function is_namespace_request( $namespace ) {
		$namespace = trim( (string) $namespace, '/' );

		if ( '' === $namespace ) {
			return false;
		}

		$route = isset( $_GET['rest_route'] ) ? trim( (string) wp_unslash( $_GET['rest_route'] ), '/' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path comparison only.

		if ( '' !== $route ) {
			return $route === $namespace || 0 === strpos( $route, $namespace . '/' );
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path comparison only.

		if ( '' === $uri ) {
			return false;
		}

		$path   = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$prefix = function_exists( 'rest_get_url_prefix' ) ? trim( rest_get_url_prefix(), '/' ) : 'wp-json';
		$home   = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		// A site with no rewrite support serves REST from /index.php/wp-json/... - the form
		// rest_url() builds once $wp_rewrite->using_index_permalinks(), and so the one this
		// screen's own apiFetch calls. Drop the site path and that segment, so one
		// comparison covers both permalink shapes.
		$path = '' !== $home && 0 === strpos( $path, $home . '/' ) ? substr( $path, strlen( $home ) + 1 ) : $path;
		$path = 0 === strpos( $path, 'index.php/' ) ? substr( $path, 10 ) : $path;

		$base = $prefix . '/' . $namespace;

		return $path === $base || 0 === strpos( $path, $base . '/' );
	}
}
