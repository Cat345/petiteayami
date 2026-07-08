<?php
/**
 * Module: Product Title class.
 *
 * @package WFOCU\Modules\ProductTitle
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductTitle;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOCU\Modules\BaseModule;
use WFOCU\Modules\ProductTitle\ProductTitleTrait\RenderCallbackTrait;
use WFOCU\Modules\ProductTitle\ProductTitleTrait\ModuleClassnamesTrait;
use WFOCU\Modules\ProductTitle\ProductTitleTrait\ModuleStylesTrait;
use WFOCU\Modules\ProductTitle\ProductTitleTrait\ModuleScriptDataTrait;
use WFOCU\Modules\ProductTitle\ProductTitleTrait\RestApiTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
// IMPORTANT: Load CustomCssTrait before ModuleStylesTrait since ModuleStylesTrait depends on it
$trait_dir = __DIR__ . '/ProductTitleTrait/';
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
 * ProductTitle module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Product Title module.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class ProductTitle extends BaseModule {
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
		return 'ProductTitle';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFOCU\Modules\ProductTitle';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'ProductTitle';
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
