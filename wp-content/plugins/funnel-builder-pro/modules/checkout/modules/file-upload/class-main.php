<?php
/**
 * Main Module Class
 *
 * @package FunnelKit\Checkout\Modules\File_Upload
 */

namespace FunnelKit\Checkout\Modules\File_Upload;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class - Entry point for the File Upload module.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class Main {

	/**
	 * Single instance of the class.
	 *
	 * @var Main
	 */
	private static $instance = null;

	/**
	 * Get single instance of the class.
	 *
	 * @return Main
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor - Initialize the module.
	 */
	private function __construct() {
		// Register autoloader.
		spl_autoload_register( array( $this, 'autoload' ) );

		$this->init_hooks();
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name The class name to load.
	 */
	public function autoload( $class_name ) {
		// Check if class belongs to our namespace.
		if ( false === strpos( $class_name, 'FunnelKit\\Checkout\\Modules\\File_Upload\\' ) ) {
			return;
		}

		// Remove base namespace.
		$class_name = str_replace( 'FunnelKit\\Checkout\\Modules\\File_Upload\\', '', $class_name );

		// Convert namespace separators to directory separators.
		$class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );

		// Split into path parts and class name.
		$parts      = explode( DIRECTORY_SEPARATOR, $class_name );
		$class_name = array_pop( $parts );

		// Convert class name to file name (CamelCase to kebab-case).
		$class_name = $this->slugify_classname( $class_name );
		$filename   = 'class-' . $class_name . '.php';

		// Build file path. FKFU_PLUGIN_DIR points to modules/file-upload/.
		if ( empty( $parts ) ) {
			// No subdirectory - check root first (Main class), then includes/.
			$file_path = FKFU_PLUGIN_DIR . $filename;
			if ( ! file_exists( $file_path ) ) {
				// Try includes/ directory for other classes without subdirectory.
				$file_path = FKFU_PLUGIN_DIR . 'includes/' . $filename;
			}
		} else {
			// Has subdirectory.
			$subdir    = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) );
			$file_path = FKFU_PLUGIN_DIR . 'includes/' . $subdir . '/' . $filename;
		}

		// Load the file if it exists.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}

	/**
	 * Convert class name to slug (CamelCase to kebab-case).
	 *
	 * @param string $class_name The class name.
	 * @return string The slugified class name.
	 */
	private function slugify_classname( $class_name ) {
		// Convert underscores to hyphens.
		$class_name = str_replace( '_', '-', $class_name );

		// Insert hyphen before capital letters and convert to lowercase.
		$class_name = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name );
		$class_name = strtolower( $class_name );

		return $class_name;
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Initialize core file upload functionality (field registration, rendering, AJAX, order processing).
		File_Upload::get_instance();

		// Initialize admin components.
		if ( is_admin() ) {
			Admin::get_instance();
		}
	}
}
