<?php
/** Assets — register + enqueue the modular skin behavior scripts. */
namespace WFFN\Bundles\Includes\Skins;

use WFFN\Bundles\Includes\Storage\Bundle_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registration and enqueueing of the skin stylesheets and behavior modules.
 */
class Assets {

	/** Boot handle; pulls in core plus every skin module. */
	const HANDLE = 'wffn-bundles-skins-js';

	/**
	 * Enqueue the skin behavior bundle: core, per-skin modules, then boot, wired
	 * with dependencies so WordPress prints them in order.
	 *
	 * @param bool       $in_footer Print in the footer (storefront) or head (editor preview).
	 * @param array|null $only      Module handles to load, or null for every module.
	 * @return void
	 */
	public static function enqueue_behavior( $in_footer = true, $only = null ) {
		self::register_handles( $in_footer );

		$modules = self::modules();
		if ( null !== $only ) {
			$modules = array_intersect_key( $modules, array_flip( (array) $only ) );
		}

		// Merge, never assign: two bundles on one page each add their own modules.
		$deps = array_merge( array( 'wffn-bundles-skins-core' ), array_keys( $modules ) );
		$reg  = wp_scripts()->registered[ self::HANDLE ] ?? null;
		if ( $reg ) {
			$reg->deps = array_values( array_unique( array_merge( (array) $reg->deps, $deps ) ) );
		}

		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Every behavior script's URL in load order, for a page that loads them itself.
	 *
	 * @return string[]
	 */
	public static function behavior_urls() {
		self::register_handles( false );

		$urls = array();
		foreach ( array_merge( array( 'wffn-bundles-skins-core' ), array_keys( self::modules() ), array( self::HANDLE ) ) as $handle ) {
			$script = wp_scripts()->registered[ $handle ] ?? null;
			if ( $script ) {
				$urls[] = add_query_arg( 'ver', $script->ver, $script->src );
			}
		}
		return $urls;
	}

	/**
	 * Handle → script path for every behavior module.
	 *
	 * @return string[]
	 */
	private static function modules() {
		$base = 'assets/skins/behavior/';

		/**
		 * Filters the behavior modules available to register.
		 *
		 * An absolute path is an add-on's own file; a relative one resolves against
		 * this plugin. Which of these a page loads is `wffn_bundles_behavior_modules`.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $modules Handle → script path.
		 */
		return (array) apply_filters(
			'wffn_bundles_behavior_module_files',
			array(
				'wffn-bundles-skins-fixed-bundle'               => $base . 'fixed-bundle.js',
				'wffn-bundles-skins-frequently-bought-together' => $base . 'frequently-bought-together.js',
				'wffn-bundles-skins-variant-modal'              => $base . 'variant-modal.js',
				'wffn-bundles-skins-collapse'                   => $base . 'collapse.js',
			)
		);
	}

	/**
	 * Register every handle without enqueueing any of them.
	 *
	 * HANDLE gets core-only deps here; enqueue_behavior() merges the bundle's
	 * subset in, so a page never enqueues every module by dependency.
	 *
	 * @param bool $in_footer Print in the footer (storefront) or head.
	 * @return void
	 */
	public static function register_handles( $in_footer = true ) {
		$base = 'assets/skins/behavior/';

		self::register( 'wffn-bundles-skins-core', $base . 'core.js', array(), $in_footer );
		foreach ( self::modules() as $handle => $file ) {
			self::register( $handle, $file, array( 'wffn-bundles-skins-core' ), $in_footer );
		}
		self::register( self::HANDLE, $base . 'index.js', array( 'wffn-bundles-skins-core' ), $in_footer );

		// Registered here too, so a widget can depend on it before any bundle renders.
		if ( ! wp_style_is( 'wffn-bundles-skin-vars', 'registered' ) ) {
			wp_register_style( 'wffn-bundles-skin-vars', false, array(), WFFN_BUNDLES_VERSION );
		}
	}

	/**
	 * Enqueue every skin stylesheet the registry declares, across all types and
	 * layouts — the editor-preview counterpart of `enqueue_behavior( true, null )`.
	 *
	 * @param string[]|int[] $exclude_types Registry type ids to skip, e.g. a type a
	 *                                      caller's integration can never render.
	 * @return void
	 */
	public static function enqueue_skin_styles( array $exclude_types = array() ) {
		$css = array();
		foreach ( Registry::types() as $type_id => $type ) {
			if ( in_array( $type_id, $exclude_types, true ) ) {
				continue;
			}
			foreach ( (array) ( $type['layouts'] ?? array() ) as $layout_id => $cfg ) {
				// Same filter the render path applies, so editor and storefront agree.
				$paths = apply_filters( 'wffn_bundles_skin_styles', (array) ( $cfg['css'] ?? array() ), $type_id, $layout_id );
				foreach ( (array) $paths as $rel ) {
					$css[] = $rel;
				}
			}
		}
		self::enqueue_style_paths( array_unique( $css ) );
	}

	/**
	 * Enqueue plugin-root-relative stylesheet paths, skipping anything that escapes
	 * the plugin's asset tree (`../`, an absolute URL) or doesn't exist.
	 *
	 * @param string[] $css Plugin-root-relative stylesheet paths.
	 * @return void
	 */
	public static function enqueue_style_paths( array $css ) {
		foreach ( self::register_style_paths( $css ) as $handle ) {
			wp_enqueue_style( $handle );
		}
	}

	/**
	 * Stylesheets as `<link>` markup, printed by the style API, for a render that
	 * lands in a document whose style queue has already printed (or never will).
	 *
	 * The same path checks and handles as enqueue_style_paths(); a sheet the page
	 * already printed is not printed twice.
	 *
	 * @param string[] $css Plugin-root-relative stylesheet paths.
	 * @return string
	 */
	public static function print_style_paths( array $css ) {
		return self::captured_print( self::register_style_paths( $css ) );
	}

	/**
	 * CSS carried as an inline style through the style API: a source-less handle
	 * with the rules attached, printed where the markup is.
	 *
	 * For rules that must apply before first paint or that only exist after the
	 * queue has printed, where an enqueue would land too late to matter.
	 *
	 * @param string $handle Unique style handle for these rules.
	 * @param string $css    Rules, without any markup around them.
	 * @param bool   $repeat Print on every call, under a numbered handle, for markup that
	 *                       must carry its own copy (a fragment cached or injected alone).
	 * @return string The printed inline style element, or '' when there is nothing to print.
	 */
	public static function inline_style_markup( $handle, $css, $repeat = false ) {
		static $copies = 0;
		$css = (string) $css;
		if ( '' === $css ) {
			return '';
		}
		if ( $repeat ) {
			++$copies;
			$handle .= '-' . $copies;
		}
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style( $handle, false, array(), WFFN_BUNDLES_VERSION );
		}
		wp_add_inline_style( $handle, $css );
		return self::captured_print( array( $handle ) );
	}

	/**
	 * Print handles through core and return what it printed.
	 *
	 * @param string[] $handles Registered style handles.
	 * @return string
	 */
	private static function captured_print( array $handles ) {
		if ( ! $handles ) {
			return '';
		}
		ob_start();
		wp_print_styles( $handles );
		return (string) ob_get_clean();
	}

	/**
	 * Register plugin-root-relative stylesheet paths, skipping anything that
	 * escapes the plugin's asset tree or doesn't exist.
	 *
	 * @param string[] $css Plugin-root-relative stylesheet paths.
	 * @return string[] The handles registered, in the order given.
	 */
	private static function register_style_paths( array $css ) {
		$handles = array();
		foreach ( $css as $rel ) {
			$rel = (string) $rel;
			if ( '' === $rel || false !== strpos( $rel, '..' ) || preg_match( '#^([a-z][a-z0-9+.-]*:)?//#i', $rel ) ) {
				continue;
			}
			// An add-on's absolute path still has to be a plugin's; plugin_basename() also resolves a symlinked one.
			if ( path_is_absolute( $rel ) && plugin_basename( $rel ) === trim( wp_normalize_path( $rel ), '/' ) ) {
				continue;
			}
			$path = self::path( $rel );
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$handle = self::style_handle( $rel );
			$url    = self::url( $rel );
			$taken  = wp_styles()->query( $handle, 'registered' );
			// Another plugin can ship the same assets/skins path; keep both sheets.
			if ( $taken && $taken->src !== $url ) {
				$handle .= '-' . substr( md5( $url ), 0, 8 );
			}
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, $url, array(), self::ver( $rel ) );
			}
			$handles[] = $handle;
		}
		return $handles;
	}

	/**
	 * The handle a stylesheet path is registered under, named from `assets/skins/`
	 * down so an absolute and a relative path yield the same shape.
	 *
	 * @param string $rel Stylesheet path.
	 * @return string
	 */
	public static function style_handle( $rel ) {
		$rel  = ltrim( (string) $rel, '/' );
		$at   = strrpos( $rel, 'assets/skins/' );
		$tail = false !== $at ? substr( $rel, $at ) : $rel;
		return 'wffn-bundles-' . sanitize_title( str_replace( array( 'assets/skins/', '.css' ), '', $tail ) );
	}

	/**
	 * Handles for the modules a bundle actually exercises.
	 *
	 * @param string $type Bundle type id.
	 * @param array  $dc   Saved discount config.
	 * @return string[]
	 */
	public static function modules_for( $type, array $dc ) {
		$map  = array(
			Bundle_Store::TYPE_FIXED_BUNDLES              => 'wffn-bundles-skins-fixed-bundle',
			Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER => 'wffn-bundles-skins-frequently-bought-together',
		);
		$only = array();
		if ( isset( $map[ $type ] ) ) {
			$only[] = $map[ $type ];
		}
		// The popup rewrites inline pickers, so `inline` mode never needs it.
		if ( Options_Display::MODE_INLINE !== Options_Display::mode() ) {
			$only[] = 'wffn-bundles-skins-variant-modal';
		}
		// Collapse is inert below its threshold, so it is never gated by type.
		$only[] = 'wffn-bundles-skins-collapse';
		/**
		 * The behavior modules a page loads. Unknown handles are ignored by
		 * enqueue_behavior(), so a stale entry cannot 404.
		 *
		 * @param string[] $only Module handles to load.
		 * @param string   $type Bundle type.
		 * @param array    $dc   Saved discount config.
		 */
		return (array) apply_filters( 'wffn_bundles_behavior_modules', $only, $type, $dc );
	}

	/**
	 * Register one script handle if nothing has registered it already.
	 *
	 * @param string   $handle    Script handle.
	 * @param string   $file      Script path.
	 * @param string[] $deps      Dependency handles.
	 * @param bool     $in_footer Print in the footer.
	 * @return void
	 */
	private static function register( $handle, $file, $deps, $in_footer ) {
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, self::url( $file ), $deps, self::ver( $file ), $in_footer );
		}
	}

	/**
	 * Absolute filesystem path for a registered asset.
	 *
	 * @param string $path Absolute path, or one relative to this plugin's root.
	 * @return string
	 */
	public static function path( $path ) {
		$path = (string) $path;
		return path_is_absolute( $path ) ? $path : WFFN_BUNDLES_DIR . '/' . ltrim( $path, '/' );
	}

	/**
	 * Public URL for a registered asset.
	 *
	 * @param string $path Absolute path, or one relative to this plugin's root.
	 * @return string
	 */
	public static function url( $path ) {
		$path = (string) $path;
		if ( ! path_is_absolute( $path ) ) {
			return WFFN_BUNDLES_URL . ltrim( $path, '/' );
		}
		return plugins_url( basename( $path ), $path );
	}

	/**
	 * Cache-busting version for an asset: its modification time, since the plugin
	 * version is static during development.
	 *
	 * @param string $rel Asset path.
	 * @return string
	 */
	public static function ver( $rel ) {
		$path = self::path( $rel );
		return file_exists( $path ) ? (string) filemtime( $path ) : WFFN_BUNDLES_VERSION;
	}
}
