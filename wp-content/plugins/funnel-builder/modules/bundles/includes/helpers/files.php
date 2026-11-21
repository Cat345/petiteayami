<?php
/** Files helper: reads the plugin's own files and uploaded media through WordPress's filesystem layer. */
namespace WFFN\Bundles\Includes\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local file reads, one direct filesystem instance per request.
 */
class Files {

	/**
	 * Direct filesystem: local paths only, so no credentials are ever asked for.
	 *
	 * @var \WP_Filesystem_Direct|null
	 */
	private static $fs = null;

	/**
	 * The contents of a local file.
	 *
	 * @param string $path Absolute path.
	 * @return string|false The contents, or false when the file cannot be read.
	 */
	public static function read( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return false;
		}
		if ( null === self::$fs ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			self::$fs = new \WP_Filesystem_Direct( null );
		}
		return self::$fs->get_contents( $path );
	}
}
