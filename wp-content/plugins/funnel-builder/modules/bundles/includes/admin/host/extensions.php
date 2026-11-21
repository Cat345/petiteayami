<?php
/** Extensions — the Bundles screens rendered inside Funnel Builder's admin app. */
namespace WFFN\Bundles\Includes\Admin\Host;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Admin\Navigation;
use WFFN\Bundles\Includes\Helpers\Request_Context;

/**
 * Renders Bundles inside Funnel Builder: a Bundles page of its own, and a
 * Bundles tab in its Analytics.
 *
 * Funnel Builder fires `wffn_admin_enqueue_extensions` on its own page only.
 * Both are registered on window.wffnExtensions by an inline script in the
 * head (print_registrations()), and the module loaded there defines the
 * elements Funnel Builder renders; each screen loads when first opened. It comes from whichever build serves the admin app, so an add-on
 * with its own build (`wffn_bundles_admin_app_source`) is used here too.
 *
 * While Funnel Builder can host them, everything opens there: the FunnelKit
 * menu item, the header item and the Analytics entry (Header), every link the
 * plugin builds (Navigation::admin_url(), through host_url()), and a visit to
 * this plugin's own page, which is redirected.
 */
class Extensions {

	const HANDLE = 'wffn-bundles-fb-extensions';

	const ENTRY = 'admin/app/src/embed/fb-extensions.tsx';

	/** @var array|false|null The entry in the build's manifest; false when there is none, null until read. */
	private static $entry = null;

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wffn_admin_enqueue_extensions', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_own_page' ) );
	}

	/**
	 * Can Funnel Builder render the Bundles screens on this request?
	 *
	 * Funnel Builder must fire the enqueue hook and say the admin build it serves
	 * renders extensions, and the build serving this plugin's admin app must
	 * carry the script that registers them.
	 *
	 * @return bool
	 */
	public static function is_hosted() {
		if ( ! is_object( WFFN_Core()->admin ) || ! method_exists( WFFN_Core()->admin, 'enqueue_extensions' ) ) {
			return false;
		}

		if ( ! \WFFN_Features::is_available( 'admin_extensions' ) ) {
			return false;
		}

		return null !== self::get_entry();
	}

	/**
	 * Funnel Builder's admin URL for a path of the Bundles screens.
	 *
	 * @param string $path Path inside Funnel Builder's app, e.g. `/bundles`.
	 * @return string
	 */
	public static function page_url( $path ) {
		return admin_url( 'admin.php?page=' . self::page_slug( $path ) );
	}

	/**
	 * Funnel Builder's submenu slug for a path of its app.
	 *
	 * @param string $path Path inside Funnel Builder's app, e.g. `/bundles`.
	 * @return string
	 */
	public static function page_slug( $path ) {
		return 'bwf&path=' . $path;
	}

	/**
	 * Where a screen of the app lives in Funnel Builder's app: Analytics in its
	 * Analytics, Settings in its Bundles settings tab, and
	 * every other screen on its Bundles page.
	 *
	 * @param string $path Path inside the app, e.g. '/bundles/12'.
	 * @return string Path inside Funnel Builder's app.
	 */
	public static function host_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );

		if ( '/analytics' === $path || 0 === strpos( $path, '/analytics/' ) ) {
			return '/analytics/' . Navigation::KEY;
		}
		if ( '/settings' === $path || 0 === strpos( $path, '/settings/' ) ) {
			return '/settings/' . Settings::TAB;
		}

		return '/' . Navigation::KEY . ( '/' === $path ? '' : $path );
	}

	/**
	 * Point a link to one of the app's screens at Funnel Builder's page, while
	 * it renders them.
	 *
	 * @param string $url  URL on this plugin's own admin page.
	 * @param string $path Path inside the app.
	 * @return string
	 */
	public static function host_url( $url, $path ) {
		return self::is_hosted() ? self::page_url( self::host_path( $path ) ) : $url;
	}

	/**
	 * Send a visit to this plugin's own admin page (a bookmark, an older link)
	 * to the same screen in Funnel Builder, while it renders them.
	 *
	 * @return void
	 */
	public static function redirect_own_page() {
		$page = Request_Context::raw_get( 'page' );
		$page = null === $page ? '' : sanitize_key( $page );
		$path = Request_Context::raw_get( 'path' );
		$path = null === $path ? '/' : sanitize_text_field( $path );

		if ( Navigation::PAGE !== $page || wp_doing_ajax() || ! self::is_hosted() ) {
			return;
		}

		wp_safe_redirect( self::page_url( self::host_path( $path ) ) );
		exit;
	}

	/**
	 * Enqueue the registration script for a user who may manage bundles.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::can_manage() || ! self::is_hosted() ) {
			return;
		}

		$app    = \WFFN_Bundles_Admin_App::get_instance();
		$source = $app->get_app_source();
		$entry  = self::get_entry();
		$assets = \WFFN_Bundles_Admin_App::read_asset_manifest( $source['dir'], self::ENTRY );

		/**
		 * Filters the bootstrap data the Bundles screens get inside Funnel Builder.
		 *
		 * The same data this plugin's own admin page gets; an add-on serving its
		 * own build adds what that build needs.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Localized data, as WFFN_Bundles_Admin_App::get_localized_data() returns it.
		 */
		$data = (array) apply_filters( 'wffn_bundles_fb_extensions_data', $app->get_localized_data() );

		// The editor's previews attach the storefront skin behavior; until switched on, the app loads it itself.
		if ( \WFFN\Bundles\Plugin::is_enabled() ) {
			\WFFN\Bundles\Includes\Skins\Assets::enqueue_behavior( false );
		} else {
			$data['skin_behavior'] = \WFFN\Bundles\Includes\Skins\Assets::behavior_urls();
		}

		/**
		 * Filters the URL the registration script loads from, e.g. a local dev server.
		 *
		 * @since 1.0.0
		 *
		 * @param string $src The built entry's URL.
		 */
		$src = (string) apply_filters( 'wffn_bundles_fb_extensions_src', $source['url'] . $entry['file'] );

		// No version query, as for the app itself: the filename carries a content hash.
		wp_enqueue_script( self::HANDLE, $src, $assets['dependencies'], null, true );
		wp_localize_script( self::HANDLE, 'wffn_bundles_admin', $data );
		wp_set_script_translations( self::HANDLE, 'funnel-builder', WFFN_BUNDLES_DIR . '/languages' );

		add_filter( 'script_loader_tag', array( __CLASS__, 'add_module_type' ), 10, 2 );
		// Ahead of every script in the head, Funnel Builder's app included.
		add_action( 'admin_print_scripts', array( __CLASS__, 'print_registrations' ), 1 );
	}

	/**
	 * Register the Bundles page and Analytics tab before Funnel Builder's app
	 * renders, so a reload of either opens it at once instead of Funnel
	 * Builder's not-found screen while the module script loads (a module runs
	 * after every classic script). The module defines the elements named here;
	 * Funnel Builder renders them straight away and they upgrade when it has.
	 *
	 * @return void
	 */
	public static function print_registrations() {
		$label   = Navigation::label();
		$entries = array(
			array(
				'version'  => 1,
				'location' => 'app/page',
				'key'      => Navigation::KEY,
				'owner'    => Navigation::PAGE,
				'label'    => $label,
				'element'  => 'wffn-bundles-page',
			),
		);
		// Before it is switched on there is nothing to report.
		if ( \WFFN\Bundles\Plugin::is_enabled() ) {
			$entries[] = array(
				'version'  => 1,
				'location' => 'analytics/sidebar',
				'key'      => Navigation::KEY,
				'owner'    => Navigation::PAGE,
				'label'    => $label,
				'element'  => 'wffn-bundles-analytics-report',
			);
		}

		wp_print_inline_script_tag(
			'(window.wffnExtensions = window.wffnExtensions || []).push.apply(window.wffnExtensions, ' . wp_json_encode( $entries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . ');'
		);
	}

	/**
	 * The entry is an ES module.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public static function add_module_type( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return str_replace( ' src=', ' type="module" src=', $tag );
	}

	/**
	 * Same rule the plugin's own screens and REST routes check.
	 *
	 * @return bool
	 */
	private static function can_manage() {
		return Navigation::can_manage();
	}

	/**
	 * The entry in the serving build's manifest, or null when that build has
	 * none: then nothing registers the screens, so nothing links to them.
	 *
	 * @return array|null
	 */
	private static function get_entry() {
		if ( null === self::$entry ) {
			$source      = class_exists( 'WFFN_Bundles_Admin_App' ) ? \WFFN_Bundles_Admin_App::get_instance()->get_app_source() : array( 'dir' => '' );
			$manifest    = $source['dir'] ? \WFFN_Bundles_Admin_App::read_manifest( $source['dir'] ) : null;
			self::$entry = isset( $manifest[ self::ENTRY ]['file'] ) ? $manifest[ self::ENTRY ] : false;
		}

		return self::$entry ? self::$entry : null;
	}
}
