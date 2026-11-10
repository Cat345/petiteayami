<?php

defined( 'ABSPATH' ) || exit;

/** Funnel Builder's host adapter for the shared FunnelKit MCP module. */
class FKMCP_Host_FB extends FKMCP_Host {

	/** REST namespace of the MCP settings endpoints this plugin serves to its admin app. */
	const REST_NAMESPACE = 'funnelkit-app/mcp';

	public function slug() {
		return 'funnel-builder';
	}

	public function name() {
		return 'FunnelKit Funnel Builder';
	}

	public function priority() {
		return 10;
	}

	public function can_read() {
		$this->load();

		return WFFN_MCP_Permissions::can_read();
	}

	public function can_write() {
		$this->load();

		return WFFN_MCP_Permissions::can_write();
	}

	public function transport_gate( $request = null ) {
		$this->load();

		return WFFN_MCP_Permissions::transport_gate( $request );
	}

	public function wrap_ability( $ability_name, $callback ) {
		$this->load();

		return WFFN_MCP_Log::wrap( $ability_name, $callback );
	}

	public function log( $line ) {
		$this->load();

		WFFN_MCP_Log::event( $line );
	}

	public function pro_active() {
		return defined( 'WFFN_PRO_VERSION' );
	}

	/** Funnel Builder Pro's license as powerpack's paywall reads it; an older powerpack without it counts as valid. */
	public function pro_ok() {
		return ! class_exists( 'WFFN_Pro_MCP' ) || 'ok' === WFFN_Pro_MCP::resolve();
	}

	/**
	 * The license level the Pro tools answer by: lite (no Pro), pro (Pro with a
	 * valid license, or a powerpack too old to say), else powerpack's own state
	 * - pro_expired, pro_inactive, pro_required - as WFFN_Pro_MCP::resolve() names it.
	 */
	public function license_level() {
		if ( ! $this->pro_active() ) {
			return 'lite';
		}

		if ( ! class_exists( 'WFFN_Pro_MCP' ) ) {
			return 'pro';
		}

		$state = WFFN_Pro_MCP::resolve();

		if ( 'ok' === $state ) {
			return 'pro';
		}

		return is_string( $state ) && 1 === preg_match( '/^[a-z_]{1,40}$/', $state ) ? $state : 'pro_required';
	}

	public function store_meta() {
		return array(
			'plugin'      => 'funnel-builder',
			'version'     => defined( 'WFFN_VERSION' ) ? (string) WFFN_VERSION : '',
			'pro_version' => $this->pro_active() ? (string) WFFN_PRO_VERSION : '',
			'license'     => $this->license_level(),
		);
	}

	public function register() {
		add_filter( 'fkmcp_ability_packs', array( $this, 'register_packs' ) );
		add_filter( 'wffn_mcp_pro_pitch', array( $this, 'pro_pitch' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * The Funnel Builder Pro abilities listed on this plugin's MCP screen while
	 * Pro is not installed.
	 */
	public function pro_pitch( $pitch ) {
		if ( ! empty( $pitch['id'] ) ) {
			return $pitch;
		}

		$read = 'read';

		return array(
			'id'     => 'funnel-builder-pro',
			'name'   => __( 'Funnel Builder Pro', 'funnel-builder' ),
			'groups' => array(
				array(
					'name'  => __( 'Analytics', 'funnel-builder' ),
					'items' => array(
						array(
							'name'  => __( 'Get Funnel Analytics', 'funnel-builder' ),
							'desc'  => __( 'Per-step views, conversions, conversion rate and revenue for a funnel.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Dashboard Overview', 'funnel-builder' ),
							'desc'  => __( 'Store-wide revenue split by checkout, upsell and bump for a date window.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Revenue Attribution', 'funnel-builder' ),
							'desc'  => __( 'Sales and leads by traffic source and UTM.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Top Funnels', 'funnel-builder' ),
							'desc'  => __( 'Best performing sales and lead funnels for a window.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Funnel Graph', 'funnel-builder' ),
							'desc'  => __( 'Revenue and order time series for charting.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Funnel Performance', 'funnel-builder' ),
							'desc'  => __( 'Orders and revenue for one funnel in a date window.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Step Stats', 'funnel-builder' ),
							'desc'  => __( 'Views, conversions and revenue for one step.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Offer Performance', 'funnel-builder' ),
							'desc'  => __( 'Take rates and revenue for upsell offers and order bumps.', 'funnel-builder' ),
							'scope' => $read,
						),
					),
				),
				array(
					'name'  => __( 'A/B Tests', 'funnel-builder' ),
					'items' => array(
						array(
							'name'  => __( 'List A/B Tests', 'funnel-builder' ),
							'desc'  => __( 'Every test with its type, status and dates.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get A/B Test', 'funnel-builder' ),
							'desc'  => __( 'One test with variants, traffic split and winner.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get A/B Test Results', 'funnel-builder' ),
							'desc'  => __( 'Totals and per-variant performance for a window.', 'funnel-builder' ),
							'scope' => $read,
						),
					),
				),
				array(
					'name'  => __( 'Contacts & Orders', 'funnel-builder' ),
					'items' => array(
						array(
							'name'  => __( 'List Funnel Contacts', 'funnel-builder' ),
							'desc'  => __( 'Contacts captured by funnels with lead/customer type.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Funnel Contact', 'funnel-builder' ),
							'desc'  => __( 'One contact with orders, optins and spend.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'List Funnel Orders', 'funnel-builder' ),
							'desc'  => __( 'Orders attributed to funnels with UTM attribution.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Funnel Order', 'funnel-builder' ),
							'desc'  => __( 'One order with items grouped by checkout, bump and upsell.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'List Funnel Leads', 'funnel-builder' ),
							'desc'  => __( 'Optin submissions captured by funnels.', 'funnel-builder' ),
							'scope' => $read,
						),
					),
				),
				array(
					'name'  => __( 'Offers & Checkout', 'funnel-builder' ),
					'items' => array(
						array(
							'name'  => __( 'Get Upsell Offers', 'funnel-builder' ),
							'desc'  => __( 'Offers configured on an upsell step: products, pricing and flow.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Get Order Bump', 'funnel-builder' ),
							'desc'  => __( 'One order bump: product, pricing and layout.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'List Payment Methods', 'funnel-builder' ),
							'desc'  => __( 'WooCommerce gateways and their enabled state.', 'funnel-builder' ),
							'scope' => $read,
						),
					),
				),
				array(
					'name'  => __( 'Step Design', 'funnel-builder' ),
					'items' => array(
						array(
							'name'  => __( 'Get Step Design', 'funnel-builder' ),
							'desc'  => __( 'How a step page is designed and whether AI can design it with Elementor.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Prepare Step Design', 'funnel-builder' ),
							'desc'  => __( 'Readies a step page for designing with Elementor, keeping its checkout or optin form.', 'funnel-builder' ),
							'scope' => 'write',
						),
						array(
							'name'  => __( 'Read Design', 'funnel-builder' ),
							'desc'  => __( 'Reads a step page\'s layout, widgets and styles, and scores how well it is made.', 'funnel-builder' ),
							'scope' => $read,
						),
						array(
							'name'  => __( 'Edit Design', 'funnel-builder' ),
							'desc'  => __( 'Builds and restyles a step page with Elementor - your brand, copy and layout - and publishes it.', 'funnel-builder' ),
							'scope' => 'write',
						),
					),
				),
			),
		);
	}

	/** Contributes this plugin's ability pack; Pro adds its own from the powerpack module. */
	public function register_packs( $packs ) {
		if ( ! class_exists( 'FKMCP_Pack' ) ) {
			return $packs;
		}

		$this->load();
		require_once __DIR__ . '/class-fkmcp-pack-funnels.php';

		// Whichever host wraps the calls (Automations outranks this plugin on a
		// dual site) asks this filter before scrubbing a result.
		add_filter( 'fkmcp_scrub_result', array( 'FKMCP_Pack_Funnels', 'keep_raw' ), 10, 2 );

		$packs[] = new FKMCP_Pack_Funnels();

		return $packs;
	}

	/** The settings-screen endpoints, under this plugin's own namespace. */
	public function register_rest_routes() {
		if ( ! self::is_namespace_request( self::REST_NAMESPACE ) ) {
			return;
		}

		require_once __DIR__ . '/class-wffn-mcp-loader.php';
		WFFN_MCP_Loader::load_admin();
		WFFN_MCP_Settings_API::register_routes( self::REST_NAMESPACE );
	}

	private function load() {
		require_once __DIR__ . '/class-wffn-mcp-loader.php';
		WFFN_MCP_Loader::load();
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
