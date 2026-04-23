<?php
/**
 * Module: Accept Button class.
 *
 * @package WFOCU\Modules\AcceptButton
 * @since 1.0.0
 */

namespace WFOCU\Modules\AcceptButton;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOCU\Modules\BaseModule;
use WFOCU\Modules\AcceptButton\AcceptButtonTrait\RenderCallbackTrait;
use WFOCU\Modules\AcceptButton\AcceptButtonTrait\ModuleClassnamesTrait;
use WFOCU\Modules\AcceptButton\AcceptButtonTrait\ModuleStylesTrait;
use WFOCU\Modules\AcceptButton\AcceptButtonTrait\ModuleScriptDataTrait;
use WFOCU\Modules\AcceptButton\AcceptButtonTrait\RestApiTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
// IMPORTANT: Load CustomCssTrait before ModuleStylesTrait since ModuleStylesTrait depends on it
$trait_dir = __DIR__ . '/AcceptButtonTrait/';
$traits    = array(
	'CustomCssTrait.php',        // Must be loaded first (dependency for ModuleStylesTrait)
	'RenderCallbackTrait.php',
	'ModuleClassnamesTrait.php',
	'ModuleStylesTrait.php',     // Depends on CustomCssTrait
	'ModuleScriptDataTrait.php',
	'RestApiTrait.php',          // REST API for dynamic product options
);

foreach ( $traits as $trait_file ) {
	$trait_path = $trait_dir . $trait_file;
	if ( file_exists( $trait_path ) ) {
		require_once $trait_path;
	}
}

/**
 * AcceptButton module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Accept Button module.
 *
 * @since 1.0.0
 */
class AcceptButton extends BaseModule {
	use RenderCallbackTrait;
	use ModuleClassnamesTrait;
	use ModuleStylesTrait;
	use ModuleScriptDataTrait;
	use RestApiTrait;

	/**
	 * Get module name.
	 *
	 * @since 1.0.0
	 * @return string Module name.
	 */
	protected function get_module_name(): string {
		return 'AcceptButton';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFOCU\Modules\AcceptButton';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'AcceptButton';
	}

	/**
	 * Constructor - loads traits and registers REST endpoints.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_traits();

		// Note: REST API endpoints are registered at file load time (above)
		// rather than in constructor, because rest_api_init fires before
		// the class is instantiated via divi_module_library_modules_dependency_tree
	}
}
