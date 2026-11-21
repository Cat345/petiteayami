<?php
/**
 * Theme_Compat — per-theme compatibility stylesheets.
 *
 * There is no registry: a file's existence is its registration.
 *   assets/skins/compatibility/themes/{slug}.css      — loaded when the slug matches
 *   assets/skins/compatibility/themes/{slug}-rtl.css  — additionally, when is_rtl()
 *
 * Scope every rule with the `body.wffn-bundles-theme-{slug}` class that
 * Product_Display::body_class() stamps, rather than raising specificity.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the per-theme compatibility stylesheets for the active theme.
 */
class Theme_Compat {

	/** Where the per-theme files live, relative to the plugin root. */
	const DIR = 'assets/skins/compatibility/themes/';

	/**
	 * The active theme's slug for both the filename and the body class: the parent
	 * theme when a child is active, verbatim from get_template().
	 *
	 * Theme directory names are case-sensitive, so name the file exactly as the
	 * directory is named.
	 *
	 * @return string
	 */
	public static function slug() {
		return function_exists( 'get_template' ) ? (string) get_template() : '';
	}

	/**
	 * Plugin-root-relative stylesheet paths for the active theme, [] when it has
	 * no file. RTL is additive: `{slug}-rtl.css` only flips what needs flipping.
	 *
	 * @return string[]
	 */
	public static function styles() {
		$slug = self::slug();
		if ( '' === $slug ) {
			return array();
		}

		$out  = array();
		$base = self::DIR . $slug . '.css';
		if ( self::exists( $base ) ) {
			$out[] = $base;
		}
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			$rtl = self::DIR . $slug . '-rtl.css';
			if ( self::exists( $rtl ) ) {
				$out[] = $rtl;
			}
		}

		/**
		 * Per-theme compatibility stylesheets for the current request.
		 *
		 * Paths are plugin-root relative and re-validated by the caller, so a
		 * filter cannot point the enqueue outside the plugin.
		 *
		 * @param string[] $out  Plugin-root-relative stylesheet paths.
		 * @param string   $slug Active theme slug (parent theme when a child is active).
		 */
		return (array) apply_filters( 'wffn_bundles_theme_styles', $out, $slug );
	}

	/**
	 * Does a plugin-root-relative asset exist?
	 *
	 * @param string $rel Plugin-root-relative path.
	 * @return bool
	 */
	private static function exists( $rel ) {
		return file_exists( WFFN_BUNDLES_DIR . '/' . ltrim( $rel, '/' ) );
	}
}
