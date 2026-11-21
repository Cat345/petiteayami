<?php

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin menu, enqueues the React SPA and localizes its bootstrap data.
 */
class WFFN_Bundles_Admin_App {

	/** @var string Script handle. A replacement enqueue registers under the same one. */
	public $handle = 'wffn-bundles-admin';

	/** @var self|null */
	private static $instance = null;

	/**
	 * The shared admin app instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire the admin menu, asset and script-tag hooks.
	 */
	private function __construct() {
		// Local development only: bin/ is never part of a release build.
		$dev_file = WFFN_BUNDLES_DIR . '/bin/dev-server.php';
		// WFFN_BUNDLES_VITE_DEV is the name older wp-config.php files still set.
		$dev = ( defined( 'WFFN_BUNDLES_DEV_SERVER' ) && WFFN_BUNDLES_DEV_SERVER ) || ( defined( 'WFFN_BUNDLES_VITE_DEV' ) && WFFN_BUNDLES_VITE_DEV );
		if ( $dev && file_exists( $dev_file ) ) {
			require_once $dev_file;
		}

		// After every FunnelKit parent menu, the last of which registers at 902.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 903 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 2 );
	}

	/**
	 * Register the admin page, under the Marketing menu or without a menu entry.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		$menu_slug  = \WFFN\Bundles\Includes\Admin\Navigation::PAGE;
		$label      = \WFFN\Bundles\Includes\Admin\Navigation::label();
		$capability = \WFFN\Bundles\Includes\Admin\Navigation::capability();

		// Hidden, the page stays registered so its URL keeps working.
		if ( \WFFN\Bundles\Includes\Admin\Host\Menu::is_hidden() ) {
			// Core looks a page's hook up under its current parent, so without one its URL needs a parentless registration.
			add_submenu_page( '', $label, $label, $capability, $menu_slug, array( $this, 'render_admin_page' ) );
			return;
		}

		/**
		 * Filters the top-level admin menu this plugin's page nests under.
		 *
		 * A non-empty slug must name a top-level menu that is already
		 * registered on this request; empty leaves the page out of the menu.
		 *
		 * @since 1.0.0
		 *
		 * @param string $parent_slug Parent menu slug, or empty for none.
		 */
		$parent_slug = (string) apply_filters( 'wffn_bundles_admin_menu_parent', '' );

		add_submenu_page(
			$parent_slug,
			$label,
			$label,
			$capability,
			$menu_slug,
			array( $this, 'render_admin_page' ),
			99
		);
	}

	/**
	 * Render the admin page, the React mount point.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		echo '<div id="wffn-bundles-app" class="wffn-bundles-admin-wrap"></div>';
		// Some notices force display with !important, so the app's stylesheet cannot hide them.
		wp_register_script( 'wffn-bundles-admin-notices', false, array(), WFFN_BUNDLES_VERSION, true );
		wp_enqueue_script( 'wffn-bundles-admin-notices' );
		wp_add_inline_script( 'wffn-bundles-admin-notices', "document.querySelectorAll('#wpbody-content > .notice, #wpbody-content > .updated, #wpbody-content > .error, #wpbody-content > .update-nag').forEach(function (n) { n.remove(); });" );
	}

	/**
	 * Enqueue the admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_plugin_admin_page() ) {
			return;
		}

		wp_enqueue_media();

		// In the head so window.wffnBundlesSkins exists before the deferred app module
		// calls wffnBundlesSkins.attachBehavior() on its shadow-root previews.
		\WFFN\Bundles\Includes\Skins\Assets::enqueue_behavior( false );

		/**
		 * Filters whether the admin app's script was already enqueued under its handle.
		 *
		 * A local development setup serves the app from elsewhere here.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $enqueued False until something enqueues it.
		 * @param array  $source   The app source, as get_app_source() returns it.
		 * @param string $handle   The handle to enqueue under.
		 */
		if ( ! apply_filters( 'wffn_bundles_admin_app_enqueue', false, $this->get_app_source(), $this->handle ) ) {
			$this->enqueue_production_assets();
		}

		wp_localize_script(
			$this->handle,
			'wffn_bundles_admin',
			$this->get_localized_data()
		);

		wp_set_script_translations(
			$this->handle,
			'funnel-builder',
			WFFN_BUNDLES_DIR . '/languages'
		);
		$this->set_chunk_translations( 'funnel-builder', WFFN_BUNDLES_DIR . '/languages' );
	}

	/**
	 * Hand the translations of the app's lazily loaded files to `wp.i18n`.
	 *
	 * WordPress loads a script's translations only for the file it enqueues. The
	 * app imports its screens as separate files later, so each file's strings are
	 * set on the shared registry before the app starts, as core does for its own.
	 *
	 * @param string $domain Text domain the build's strings use.
	 * @param string $path   Directory holding that domain's translation files.
	 * @return void
	 */
	public function set_chunk_translations( $domain, $path ) {
		$source   = $this->get_app_source();
		$manifest = self::read_manifest( $source['dir'] );
		$scripts  = array();

		foreach ( $manifest as $key => $chunk ) {
			$file = (string) ( $chunk['file'] ?? '' );
			if ( $key === $source['entry'] || '.js' !== substr( $file, -3 ) ) {
				continue;
			}
			// Registered only so core can resolve the file's translations; never printed.
			$handle = $this->handle . '-i18n-' . sanitize_key( basename( $file, '.js' ) );
			wp_register_script( $handle, $source['url'] . $file, array(), $source['version'], true );
			$json = load_script_textdomain( $handle, $domain, $path );
			wp_deregister_script( $handle );
			if ( ! $json ) {
				continue;
			}
			$scripts[] = sprintf(
				'( function( domain, translations ) { var localeData = translations.locale_data[ domain ] || translations.locale_data.messages; localeData[""].domain = domain; wp.i18n.setLocaleData( localeData, domain ); } )( %1$s, %2$s );',
				wp_json_encode( $domain ),
				$json
			);
		}

		if ( ! empty( $scripts ) ) {
			wp_add_inline_script( $this->handle, implode( "\n", $scripts ), 'before' );
		}
	}

	/**
	 * Where the admin app's built assets live.
	 *
	 * Filtered so an add-on carrying its own build of the app can serve that
	 * instead. This plugin's own build is the default and the fallback; it never
	 * learns which add-on, if any, answered.
	 *
	 * @return array{dir:string,url:string,entry:string,version:string}
	 */
	public function get_app_source() {
		$default = array(
			'dir'        => WFFN_BUNDLES_DIR . '/admin/app/dist/',
			'url'        => WFFN_BUNDLES_URL . 'admin/app/dist/',
			'entry'      => 'admin/app/src/main.tsx',
			'version'    => WFFN_BUNDLES_VERSION,
		);

		/**
		 * Filters the location of the admin app's assets.
		 *
		 * @since 1.0.0
		 *
		 * @param array $default Absolute dir and URL of the build, the entry's
		 *                       manifest key and the asset version.
		 */
		$source = (array) apply_filters( 'wffn_bundles_admin_app_source', $default );

		return array_merge( $default, $source );
	}

	/**
	 * Enqueue the production build assets named by the build manifest.
	 *
	 * @return void
	 */
	public function enqueue_production_assets() {
		$source    = $this->get_app_source();
		$dist_path = $source['dir'];
		$dist_url  = $source['url'];

		$manifest = self::read_manifest( $dist_path );
		if ( ! $manifest ) {
			return;
		}

		$entry_key = $source['entry'];
		if ( ! isset( $manifest[ $entry_key ] ) ) {
			return;
		}

		$entry = $manifest[ $entry_key ];

		$assets = self::read_asset_manifest( $dist_path, $entry_key );

		wp_enqueue_script(
			$this->handle,
			$dist_url . $entry['file'],
			$assets['dependencies'],
			$source['version'],
			true
		);

		if ( ! empty( $entry['css'] ) ) {
			foreach ( $entry['css'] as $index => $css_file ) {
				$css_handle = 0 === $index ? $this->handle : $this->handle . '-' . $index;
				wp_enqueue_style(
					$css_handle,
					$dist_url . $css_file,
					array(),
					$source['version']
				);
			}
		}
	}

	/**
	 * The build's manifest, or an empty array when there is none.
	 *
	 * @param string $dist_path Directory holding the build.
	 * @return array
	 */
	public static function read_manifest( $dist_path ) {
		$manifest_file = $dist_path . 'manifest.json';
		if ( ! file_exists( $manifest_file ) ) {
			return array();
		}

		$manifest = wp_json_file_decode( $manifest_file, array( 'associative' => true ) );

		return is_array( $manifest ) ? $manifest : array();
	}

	/**
	 * The script handles the build left to WordPress, as the build recorded them.
	 *
	 * The file is written next to the bundle when the WordPress packages are
	 * externalised. A build that bundles them writes none, and the entry then
	 * depends on nothing.
	 *
	 * @param string $dist_path Directory holding the build.
	 * @param string $entry_key Manifest key of the entry, e.g. `admin/app/src/main.tsx`.
	 * @return array{dependencies:string[]}
	 */
	public static function read_asset_manifest( $dist_path, $entry_key ) {
		$name = pathinfo( $entry_key, PATHINFO_FILENAME );
		$file = $dist_path . $name . '.asset.php';

		if ( ! file_exists( $file ) ) {
			return array( 'dependencies' => array() );
		}

		$assets = include $file;
		$deps   = isset( $assets['dependencies'] ) ? (array) $assets['dependencies'] : array();

		return array( 'dependencies' => array_map( 'strval', $deps ) );
	}

	/**
	 * Add the type="module" attribute to this plugin's script tags.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function add_module_type( $tag, $handle ) {
		if ( $this->handle === $handle ) {
			$tag = str_replace( ' src=', ' type="module" src=', $tag );
		}

		return $tag;
	}

	/**
	 * The localized bootstrap data for the admin app.
	 *
	 * @return array
	 */
	public function get_localized_data() {
		$current_user = wp_get_current_user();

		$data = array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'rest_url'   => rest_url( WFFN_BUNDLES_ADMIN_NAMESPACE . '/' . WFFN_BUNDLES_API_VERSION ),
			// The site's REST root and this plugin's namespace, separately: the
			// admin's shared apiFetch already roots requests at the site's, so a
			// path has to name the namespace itself rather than rely on ours.
			'rest_root'  => rest_url(),
			'rest_ns'    => '/' . WFFN_BUNDLES_ADMIN_NAMESPACE . '/' . WFFN_BUNDLES_API_VERSION,
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'ajax_nonce' => wp_create_nonce( \WFFN\Bundles\Includes\Ajax\Controller::NONCE ),
			'admin_url'  => admin_url(),
			'site_url'   => site_url(),
			'plugin_url' => WFFN_BUNDLES_URL,
			'version'    => WFFN_BUNDLES_VERSION,
			'date_format' => get_option( 'date_format', 'F j, Y' ),
			'time_format' => get_option( 'time_format', 'g:i a' ),
			'settings'   => array(),
			'skins'    => $this->get_skin_chunks(),
			'theme'    => $this->get_theme_compat(),
			'currency' => $this->get_currency(),
			'config_keys' => $this->get_config_keys(),
			'navigation' => \WFFN\Bundles\Includes\Admin\Navigation::get(),
			// The list's first paint reads this to pick its loading shape instead
			// of guessing "rows" and jumping to the empty state once the real
			// fetch resolves.
			'has_bundles' => $this->has_any_bundles(),
			// Null until the merchant activates or dismisses Bundles.
			'state'       => \WFFN\Bundles\Includes\Storage\Install::get_state(),
			'settings_url' => \WFFN\Bundles\Includes\Admin\Navigation::admin_url( '/settings' ),
			'user'       => array(
				'id'    => $current_user->ID,
				'name'  => $current_user->display_name,
				'email' => $current_user->user_email,
				'can_write' => \WFFN\Bundles\Includes\Admin\Navigation::can_write(),
				'preferences' => array(
					'notifications' => \WFFN\Bundles\Includes\Rest\User_Preference_Controller::dismissed_notices( $current_user->ID ),
				),
			),
			'i18n'       => array(
				'plugin_name' => __( 'Bundles For WooCommerce', 'funnel-builder' ),
			),
			// The preview prints the storefront's own words, already in the store's language.
			'shopper_text' => \WFFN\Bundles\Includes\Skins\Shopper_Text::all(),
		);

		/**
		 * Filters the admin app's localized bootstrap data.
		 *
		 * Compat integrations add their own environment keys here.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Localized data, shape mirrored by AppData in
		 *                    admin/app/src/lib/wordpress.ts.
		 */
		return (array) apply_filters( 'wffn_bundles_admin_app_data', $data );
	}

	/**
	 * Whether at least one bundle row exists, however it was created.
	 *
	 * @return bool
	 */
	private function has_any_bundles() {
		if ( ! \WFFN\Bundles\Includes\Storage\Install::is_in_use() ) {
			return false;
		}
		return \WFFN\Bundles\Includes\Storage\Bundle_Store::has_listed();
	}

	/**
	 * The allowed `discountConfig` keys per bundle type, keyed by type.
	 *
	 * Bundle_Store::config_keys() is the authority; it prunes every write. A
	 * type it keeps no list for is left out, and the editor keeps every key.
	 *
	 * @return array<string,string[]>
	 */
	private function get_config_keys() {
		$store = '\WFFN\Bundles\Includes\Storage\Bundle_Store';
		$out   = array();
		foreach ( $store::accepted_types() as $type ) {
			$keys = $store::config_keys( $type );
			if ( null !== $keys ) {
				$out[ $type ] = $keys;
			}
		}
		return $out;
	}

	/**
	 * Chunk-sets for the JS stamper, keyed by discount type.
	 *
	 * @return array<string,array> [ type => [ { id, label, template, css, defaults } ] ].
	 */
	private function get_skin_chunks() {
		$reg = '\WFFN\Bundles\Includes\Skins\Registry';
		if ( ! class_exists( $reg ) ) {
			return array();
		}
		$out = array();
		foreach ( array_keys( $reg::types() ) as $type ) {
			$out[ $type ] = $reg::layouts_for( $type );
		}
		return $out;
	}

	/**
	 * The active theme's compatibility stylesheet, for the editor preview.
	 *
	 * The preview renders in a shadow root inside wp-admin where the theme's own
	 * stylesheet is absent, so `probeUrl` reads the real custom-property values
	 * off a storefront page. An empty `css` is the signal to skip that probe.
	 *
	 * @return array
	 */
	private function get_theme_compat() {
		$compat = '\WFFN\Bundles\Includes\Skins\Theme_Compat';
		if ( ! class_exists( $compat ) ) {
			return array( 'slug' => '', 'name' => '', 'css' => '', 'probeUrl' => '' );
		}

		$css = '';
		foreach ( $compat::styles() as $rel ) {
			$path = WFFN_BUNDLES_DIR . '/' . ltrim( $rel, '/' );
			if ( file_exists( $path ) ) {
				$css .= \WFFN\Bundles\Includes\Helpers\Files::read( $path ) . "\n";
			}
		}

		$theme = wp_get_theme();
		return array(
			'slug'     => $compat::slug(),
			'name'     => $theme ? (string) $theme->get( 'Name' ) : '',
			'css'      => $css,
			'probeUrl' => '' === $css ? '' : $this->theme_probe_url(),
		);
	}

	/**
	 * A storefront URL whose palette the preview can measure.
	 *
	 * The shop page over home: a theme that scopes palette variables to its
	 * WooCommerce templates still defines them there.
	 *
	 * @return string
	 */
	private function theme_probe_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );
			if ( $shop ) {
				return $shop;
			}
		}
		return home_url( '/' );
	}

	/**
	 * WooCommerce currency config, so the JS formatter matches wc_price().
	 *
	 * @return array
	 */
	private function get_currency() {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return array(
				'code' => 'USD', 'symbol' => '$', 'symbolPosition' => 'left',
				'thousandSeparator' => ',', 'decimalSeparator' => '.', 'precision' => 2,
				'priceFormat' => '%1$s%2$s',
			);
		}
		return array(
			'code'              => get_woocommerce_currency(),
			'symbol'            => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			'symbolPosition'    => get_option( 'woocommerce_currency_pos', 'left' ),
			'thousandSeparator' => wc_get_price_thousand_separator(),
			'decimalSeparator'  => wc_get_price_decimal_separator(),
			'precision'         => (int) wc_get_price_decimals(),
			// Spaced symbol positions carry an `&nbsp;` and the app renders this as
			// text, so the entity would show up literally.
			'priceFormat'       => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
		);
	}

	/**
	 * Is the current screen one of this plugin's admin pages?
	 *
	 * @return bool
	 */
	public function is_plugin_admin_page() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, 'wffn-bundles' ) !== false;
	}
}

WFFN_Bundles_Admin_App::get_instance();
