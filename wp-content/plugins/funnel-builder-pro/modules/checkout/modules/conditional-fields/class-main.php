<?php
/**
 * Main Module Class
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class - Entry point for the Conditional Fields module.
 *
 * @since 2.0.0
 */
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
		if ( false === strpos( $class_name, 'FunnelKit\\Checkout\\Modules\\Conditional_Fields\\' ) ) {
			return;
		}

		// Remove base namespace.
		$class_name = str_replace( 'FunnelKit\\Checkout\\Modules\\Conditional_Fields\\', '', $class_name );

		// Convert namespace separators to directory separators.
		$class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );

		// Split into path parts and class name.
		$parts      = explode( DIRECTORY_SEPARATOR, $class_name );
		$class_name = array_pop( $parts );

		// Convert class name to file name (CamelCase to kebab-case).
		$class_name = $this->slugify_classname( $class_name );
		$filename   = 'class-' . $class_name . '.php';

		// Build file path. FKCF_PLUGIN_DIR points to modules/conditional-fields/.
		if ( empty( $parts ) ) {
			// No subdirectory (Main class).
			$file_path = FKCF_PLUGIN_DIR . $filename;
		} else {
			// Has subdirectory (Admin, Frontend, Models, etc.).
			$subdir    = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) );
			$file_path = FKCF_PLUGIN_DIR . 'includes/' . $subdir . '/' . $filename;
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
		// Textdomain: woofunnels-aero-checkout (loaded by checkout plugin).

		// Single schema: handle REST API save/read via _fkcf_conditional_rules.
		Field_Conditions_Handler::init();

		// Initialize REST API (used by admin).
		Api\Rest_Controller::get_instance();
		Api\Checkout_Field_Rules::get_instance();

		// Initialize admin components.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Initialize frontend components.
		if ( ! is_admin() ) {
			$this->init_frontend();
		}
	}

	/**
	 * Initialize admin components.
	 */
	private function init_admin() {
		// Initialize admin class.
		Admin\Admin::get_instance();

		// Initialize admin pages.
		Admin\Admin_Pages::get_instance();

		// REST API is primary; Ajax_Handler no longer registers hooks.
		Admin\Ajax_Handler::get_instance();
	}

	/**
	 * Initialize frontend components.
	 */
	private function init_frontend() {
		// Initialize frontend class.
		Frontend\Frontend::get_instance();

		// Initialize field visibility handler.
		Frontend\Field_Visibility::get_instance();

		// Initialize section visibility handler (wfacp_hide_section).
		Frontend\Section_Visibility::get_instance();

		// Initialize validation handler.
		Frontend\Validation::get_instance();

		// Initialize fragments handler.
		Frontend\Fragments::get_instance();
	}
}
