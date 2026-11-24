<?php
/** Bundles For WooCommerce — core plugin bootstrap. */
namespace WFFN\Bundles;

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin bootstrap: autoloading, REST registration and runtime init.
 */
class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var string Plugin root namespace. */
	public $default_namespace = __NAMESPACE__;

	/** @var string Absolute plugin directory path (trailing slash). */
	public $dir;

	/** @var string Plugin directory URL (trailing slash). */
	public $url;

	/**
	 * The shared plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire the autoloader and the plugin's boot hooks.
	 */
	private function __construct() {
		$this->dir = plugin_dir_path( WFFN_BUNDLES_FILE );
		$this->url = plugin_dir_url( WFFN_BUNDLES_FILE );

		spl_autoload_register( array( $this, 'autoload' ) );

		// Without WooCommerce nothing else boots, admin included; stored data waits untouched.
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		// Early, so an add-on's presets of the same id replace these.
		add_filter( 'wffn_bundles_quick_start_templates', array( Includes\Storage\Quick_Starts::class, 'templates' ), 5 );
		// Runs on every load, not just admin: repairs installs updated without the activation hook firing.
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 5 );
		// After WooCommerce's own init-time taxonomy registration, so the hidden
		// container product's catalog-visibility term actually exists to assign.
		add_action( 'init', array( Includes\Storage\Install::class, 'maybe_ensure_container_product' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		// Fired last so an add-on attaching here can rely on this plugin's runtime being up.
		add_action( 'plugins_loaded', array( $this, 'loaded' ), 25 );
		// The tracker reads its collectors on its own schedule, so the collector loads only then.
		add_filter( 'woofunnels_register_usage_collectors', array( __CLASS__, 'register_usage_collector' ) );

		$this->load_mcp_module();
	}

	/**
	 * Whether WooCommerce is active, read from the active plugin list.
	 *
	 * This plugin boots while plugins are still loading, before WooCommerce's
	 * class exists, so the class alone cannot answer it here.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$basename = 'woocommerce/woocommerce.php';
		$sitewide = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();

		return in_array( $basename, (array) get_option( 'active_plugins', array() ), true ) || isset( $sitewide[ $basename ] );
	}

	/**
	 * Add this plugin's abilities to Funnel Builder's FunnelKit MCP module.
	 *
	 * @return void
	 */
	private function load_mcp_module() {
		if ( ! self::is_mcp_request() || self::is_switched_off() ) {
			return;
		}

		add_filter( 'fkmcp_ability_packs', array( __CLASS__, 'register_packs' ) );
		add_filter( 'fkmcp_pack_meta', array( __CLASS__, 'one_listing' ) );
	}

	/**
	 * Contribute this plugin's pack; an add-on adds its own through the same filter.
	 *
	 * @param array $packs Packs collected so far.
	 * @return array
	 */
	public static function register_packs( $packs ) {
		$packs = is_array( $packs ) ? $packs : array();
		if ( class_exists( 'FKMCP_Pack' ) ) {
			$packs[] = new Includes\Mcp\Pack();
		}
		return $packs;
	}

	/**
	 * Add this plugin's collector to the host's usage tracker; an add-on extends its data through `wffn_bundles_usage_data`.
	 *
	 * @param array $collectors Collectors keyed by plugin id.
	 * @return array
	 */
	public static function register_usage_collector( $collectors ) {
		$collectors = is_array( $collectors ) ? $collectors : array();
		// Named, not loaded: the class extends the tracker's own base, so it loads only once that exists.
		if ( class_exists( 'WooFunnels_Usage_Collector_Abstract' ) ) {
			$collectors['bundles'] = array(
				'class'  => Includes\Usage\Collector::class,
				'module' => 'lite',
			);
		}
		return $collectors;
	}

	/**
	 * List every Bundles pack, an add-on's included, as the one Bundles entry on a settings screen.
	 *
	 * @param array $meta A pack's plugin_meta(), as the screen lists it.
	 * @return array
	 */
	public static function one_listing( $meta ) {
		if ( is_array( $meta ) && 0 === strpos( (string) ( $meta['id'] ?? '' ), Includes\Mcp\Pack::ID . '-' ) ) {
			$meta['id']   = Includes\Mcp\Pack::ID;
			$meta['name'] = __( 'Bundles', 'funnel-builder' );
		}

		return $meta;
	}

	/**
	 * Whether this request could reach an ability: admin, AJAX, WP-CLI or REST.
	 *
	 * The plain storefront skips the module entirely. REST is read from the URI
	 * because REST_REQUEST is not defined this early.
	 *
	 * @return bool
	 */
	private static function is_mcp_request() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}

		if ( ! empty( self::query_arg( 'rest_route' ) ) ) {
			return true;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri ) {
			return false;
		}

		$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		foreach ( array( '/' . trim( $prefix, '/' ) . '/', '/.well-known/oauth-', '/.well-known/openid-configuration' ) as $needle ) {
			if ( false !== strpos( $uri, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the site has saved a bundle, so the storefront runtime boots.
	 *
	 * A dormant site loads only what opening the Bundles screen needs. Read at
	 * plugin load, so a `wffn_bundles_in_use` filter belongs in an mu-plugin.
	 *
	 * @return bool
	 */
	public static function is_in_use() {
		return Includes\Storage\Install::is_in_use();
	}

	/**
	 * Whether the merchant has switched Bundles on, so the storefront may run.
	 *
	 * Not chosen yet and switched off both answer false.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Includes\Storage\Install::STATE_ENABLED === Includes\Storage\Install::get_state();
	}

	/**
	 * Whether the merchant has switched Bundles off, so nothing but the way back on runs.
	 *
	 * Not chosen yet answers false.
	 *
	 * @return bool
	 */
	public static function is_switched_off() {
		return Includes\Storage\Install::STATE_DISABLED === Includes\Storage\Install::get_state();
	}

	/**
	 * Whether this request serves shoppers: a storefront page, WooCommerce's front-end AJAX or the Store API.
	 *
	 * Admin, AJAX through admin-ajax.php, cron, WP-CLI and every other REST call are not.
	 *
	 * @return bool
	 */
	public static function is_storefront_request() {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		$route = self::rest_route();
		return null === $route || 0 === strpos( $route, '/wc/store' );
	}

	/**
	 * The REST route this request is for, or null when it is not a REST request.
	 *
	 * Read from the URI because REST_REQUEST is not defined this early.
	 *
	 * @return string|null
	 */
	private static function rest_route() {
		if ( ! empty( self::query_arg( 'rest_route' ) ) ) {
			return '/' . ltrim( sanitize_text_field( self::query_arg( 'rest_route' ) ), '/' );
		}
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$prefix = '/' . trim( function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json', '/' );
		$at     = '' === $uri ? false : strpos( $uri, $prefix . '/' );
		if ( false === $at && rtrim( $uri, '/' ) !== $prefix ) {
			return null;
		}
		return false === $at ? '/' : '/' . ltrim( substr( $uri, $at + strlen( $prefix ) ), '/' );
	}

	/**
	 * Run the installer where its tables can be needed.
	 *
	 * A site that has not saved a bundle installs nothing here; switching
	 * Bundles on or the first save makes the tables (Install::set_state(),
	 * Bundle_Repository::create()).
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( self::is_in_use() ) {
			Includes\Storage\Install::maybe_upgrade();
		}
	}

	/**
	 * Autoload plugin classes, mapping namespace to file path.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, $this->default_namespace . '\\' ) ) {
			return;
		}

		$relative = preg_replace( '/^' . preg_quote( $this->default_namespace . '\\', '/' ) . '/', '', $class );

		// The MCP classes extend Funnel Builder's, so its MCP runtime has to be in
		// place before one of them is defined. Everything that uses them runs well
		// after the host has booted, but the autoloader can be entered from
		// anywhere and a missing parent class is a fatal, not a notice.
		if ( 0 === strpos( $relative, 'Includes\\Mcp\\' ) && class_exists( 'WFFN_MCP_Loader' ) ) {
			\WFFN_MCP_Loader::load();
		}
		$relative = preg_replace(
			array( '/([a-z0-9])([A-Z])/', '/_/', '/\\\\/' ),
			array( '$1-$2', '-', DIRECTORY_SEPARATOR ),
			$relative
		);
		$file = $this->dir . strtolower( $relative ) . '.php';

		if ( is_readable( $file ) ) {
			include_once $file;
		}
	}

	/**
	 * Register every admin REST controller's routes, one class per area.
	 *
	 * @return void
	 */
	public function register_rest() {
		$route = self::rest_route();
		// A call to another namespace (the Store API on every cart update) never reaches these routes.
		if ( null !== $route && '/' !== $route && 0 !== strpos( $route, '/' . WFFN_BUNDLES_ADMIN_NAMESPACE . '/' ) && 0 !== strpos( $route, '/batch/' ) ) {
			return;
		}
		// Admin responses report on the store, so their money is base money.
		Includes\Rest\Base::pin_currency_to_base();
		// Switched off or not chosen yet: the routes stay readable, but only the way back on writes.
		if ( ! self::is_enabled() ) {
			add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'refuse_writes_while_off' ), 10, 3 );
		}

		/**
		 * Fires before the controller registry is read.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wffn_bundles_rest_api_init' );

		/**
		 * Filters the admin REST controllers to register.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $controllers Fully-qualified controller class names.
		 */
		$controllers = (array) apply_filters(
			'wffn_bundles_rest_api_routes',
			array(
				Includes\Rest\Controller::class,
				Includes\Rest\Settings_Controller::class,
				Includes\Rest\User_Preference_Controller::class,
				Includes\Rest\Portability_Controller::class,
			)
		);

		foreach ( $controllers as $controller ) {
			if ( is_string( $controller ) && class_exists( $controller ) ) {
				( new $controller() )->register_routes();
			}
		}
	}

	/**
	 * Refuse a write to the Bundles admin REST namespace while Bundles is not switched on.
	 *
	 * Every controller, an add-on's included, registers under that namespace, so
	 * this one check covers them all. Still allowed: a settings save that makes the
	 * on / off choice (the way back on), the licence screen, and per-user screen
	 * preferences, none of which change a bundle or what the store sells. Before
	 * the merchant has chosen, saving a bundle (create, or the editor's save) is
	 * that choice: the save switches Bundles on (Install::mark_in_use()).
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Result so far.
	 * @param array                                               $handler  Route handler.
	 * @param \WP_REST_Request                                    $request  The request.
	 * @return mixed
	 */
	public static function refuse_writes_while_off( $response, $handler, $request ) {
		if ( is_wp_error( $response ) || ! $request instanceof \WP_REST_Request || 'GET' === $request->get_method() || 'HEAD' === $request->get_method() ) {
			return $response;
		}
		$prefix = '/' . WFFN_BUNDLES_ADMIN_NAMESPACE . '/' . WFFN_BUNDLES_API_VERSION;
		$route  = (string) $request->get_route();
		// A caller who may not manage Bundles is left to the route's own permission check.
		if ( 0 !== strpos( $route, $prefix . '/' ) || self::is_enabled() || ! Includes\Admin\Navigation::can_manage() ) {
			return $response;
		}
		$path   = substr( $route, strlen( $prefix ) );
		$method = $request->get_method();
		// The licence screen's own routes, by exact match, and per-user screen preferences.
		if ( in_array( $path, array( '/license', '/license/activate', '/license/deactivate' ), true ) || 1 === preg_match( '#^/user-preferences/[0-9]+$#', $path ) ) {
			return $response;
		}
		if ( '/settings' === $path ) {
			$body  = $request->get_json_params();
			$body  = is_array( $body ) ? $body : $request->get_body_params();
			$body  = is_array( $body ) ? $body : array();
			$state = $body[ Includes\Storage\Install::SETTINGS_STATE ] ?? null;
			// Switching on, or only switching off: nothing else is written while off.
			if ( Includes\Storage\Install::STATE_ENABLED === $state || ( Includes\Storage\Install::STATE_DISABLED === $state && 1 === count( $body ) ) ) {
				return $response;
			}
		}
		// Not chosen yet: a bundle save is the merchant's choice to switch Bundles on.
		if ( ! self::is_switched_off() && ( ( '/bundles' === $path && 'POST' === $method ) || ( 1 === preg_match( '#^/bundles/[0-9]+$#', $path ) && 'PUT' === $method ) ) ) {
			return $response;
		}
		return new \WP_Error(
			'wffn_bundles_switched_off',
			self::is_switched_off()
				? __( 'Bundles is switched off. Switch it on to make changes.', 'funnel-builder' )
				: __( 'Switch Bundles on to make changes.', 'funnel-builder' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Fire the add-on seam once this plugin's own runtime is booted.
	 *
	 * @return void
	 */
	public function loaded() {
		/**
		 * Fires after Bundles For WooCommerce has finished booting.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wffn_bundles_loaded' );
	}

	/**
	 * A query-string value, unslashed and unsanitized, or null when absent.
	 *
	 * Only for reads that route or detect a screen; nothing is saved from them, so no
	 * nonce applies. The caller sanitizes the value.
	 *
	 * @param string $key Parameter name.
	 * @return mixed
	 */
	private static function query_arg( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- routing and screen detection only; nothing is saved and the caller sanitizes the value.
		return isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : null;
	}

	/**
	 * A request value (GET or POST), unslashed and unsanitized, or null when absent.
	 *
	 * Only for routing; each handler checks its own nonce. The caller sanitizes the value.
	 *
	 * @param string $key Parameter name.
	 * @return mixed
	 */
	private static function request_arg( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- routing only; each handler checks its own nonce, and the caller sanitizes the value.
		return isset( $_REQUEST[ $key ] ) ? wp_unslash( $_REQUEST[ $key ] ) : null;
	}

	/**
	 * Whether this is one of the product edit screen's Bundles tab AJAX requests.
	 *
	 * @return bool
	 */
	private static function is_product_tab_ajax() {
		$action = self::request_arg( 'action' );
		$action = null === $action ? '' : sanitize_key( $action );
		return wp_doing_ajax() && 0 === strpos( $action, 'wffn_bundles_product_tab_' );
	}

	/**
	 * Switched off, boot only the ways back on: Funnel Builder's screens, where the
	 * Bundles page offers the bundle types again, the Settings tab and the product tab.
	 *
	 * @return void
	 */
	private static function boot_switch() {
		// The switch is reached only from wp-admin and the admin's REST calls; shoppers, cron and WP-CLI get nothing.
		if ( ! is_admin() && ( null === self::rest_route() || self::is_storefront_request() ) ) {
			return;
		}
		if ( self::is_host_request() ) {
			Includes\Admin\Host::boot();
		} else {
			( new Includes\Admin\Host\Settings() )->init();
		}
		if ( self::is_product_tab_ajax() ) {
			Includes\Admin\Product_Tab::boot();
			return;
		}
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}
		add_action( 'load-post.php', array( Includes\Admin\Product_Tab::class, 'boot' ) );
		add_action( 'load-post-new.php', array( Includes\Admin\Product_Tab::class, 'boot' ) );
	}

	/**
	 * Before the choice, boot only the ways to find Bundles and choose: Funnel Builder's
	 * screens and Settings tab, and the product tab, whose first bundle switches Bundles on.
	 *
	 * @return void
	 */
	private static function boot_setup() {
		if ( self::is_product_tab_ajax() ) {
			Includes\Admin\Product_Tab::boot();
			return;
		}
		if ( self::is_host_request() ) {
			Includes\Admin\Host::boot();
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_action( 'load-post.php', array( Includes\Admin\Product_Tab::class, 'boot' ) );
			add_action( 'load-post-new.php', array( Includes\Admin\Product_Tab::class, 'boot' ) );
		}
	}

	/**
	 * Whether this is a screen of Funnel Builder's admin app, or this plugin's own page.
	 *
	 * @return bool
	 */
	public static function is_host_page() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}
		$page = self::query_arg( 'page' );
		return in_array( null === $page ? '' : sanitize_key( $page ), array( 'bwf', 'wffn-bundles' ), true );
	}

	/**
	 * Whether the Funnel Builder hosting serves this request: its screens, its own REST
	 * calls (the Settings tab reads and saves through them) and this plugin's REST namespace.
	 *
	 * @return bool
	 */
	private static function is_host_request() {
		$route = self::rest_route();
		if ( null === $route ) {
			return self::is_host_page();
		}
		return 0 === strpos( $route, '/' . WFFN_BUNDLES_ADMIN_NAMESPACE . '/' ) || 0 === strpos( $route, '/funnelkit-app/' );
	}

	/**
	 * Whether this request can set Bundles up before the choice: Funnel Builder's screens,
	 * a post edit screen, the product tab's AJAX, this plugin's REST namespace and the AI abilities.
	 *
	 * An add-on reads it to boot only where its bundle types can be made.
	 *
	 * @return bool
	 */
	public static function is_setup_request() {
		global $pagenow;

		if ( self::is_product_tab_ajax() ) {
			return true;
		}
		$route = self::rest_route();
		if ( null !== $route ) {
			foreach ( array( '/' . WFFN_BUNDLES_ADMIN_NAMESPACE . '/', '/funnelkit/mcp', '/funnelkit-app/mcp' ) as $prefix ) {
				if ( 0 === strpos( $route, $prefix ) ) {
					return true;
				}
			}
			return false;
		}
		return self::is_host_page() || ( is_admin() && ! wp_doing_ajax() && in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) );
	}

	/**
	 * Boot the plugin runtime; storefront rendering is skipped on admin screens.
	 *
	 * @return void
	 */
	public function init() {
		// Listed active but not loaded, e.g. its files are gone: every subsystem below fatals without it.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( self::is_switched_off() ) {
			self::boot_switch();
			return;
		}
		// Not chosen yet: nothing is saved, so only the ways to find Bundles and choose boot.
		if ( ! self::is_enabled() ) {
			self::boot_setup();
			return;
		}
		// A product's index is forgotten when Bundles is switched back on, so it is kept only while on.
		if ( self::is_in_use() ) {
			Includes\Storage\Product_Placement::boot();
		}
		// The storefront runs only once holding a bundle.
		if ( ! self::is_in_use() && self::is_storefront_request() ) {
			return;
		}
		add_filter( 'woocommerce_is_purchasable', array( Includes\Frontend\Cart::class, 'container_purchasable' ), 10, 2 );
		// Before anything prices, reads or records money.
		Includes\Compat\Currency\Layer::boot();
		// Hooks only: a discount engine installs its runtime instance on `init`,
		// so resolving an adapter here would memoise it absent for the request.
		Includes\Compat\Discount\Layer::boot();
		// On init, after add-ons boot, so the routes they add are registered too.
		add_action( 'init', array( new Includes\Ajax\Controller(), 'register' ), 20 );
		// Writes the `_wffn_bundles_*` line meta the order display reads back.
		Includes\Storage\Order_Attribution::get_instance();
		// Runs everywhere: order emails are dispatched from admin, cron and webhooks too.
		Includes\Frontend\Order_Display::get_instance();
		// Funnel Builder reads its hosting hooks only in wp-admin and from its app's REST calls.
		if ( is_admin() ) {
			Includes\Admin\Host::boot();
		} else {
			add_action( 'rest_api_init', array( Includes\Admin\Host::class, 'boot' ) );
		}
		// Outside the branch below: a builder registers its widgets on the admin
		// editor request, where is_admin() is true and wp_doing_ajax() is false.
		Includes\Compat\Manager::boot();
		if ( is_admin() ) {
			Includes\Admin\Product_Tab::boot();
			Includes\Admin\Order_Refund_Share::boot();
		}
		if ( ! is_admin() || wp_doing_ajax() ) {
			Includes\Frontend\Product_Display::get_instance();
			Includes\Frontend\Cart::get_instance();
			Includes\Frontend\Store_API::get_instance();
		}
	}
}
