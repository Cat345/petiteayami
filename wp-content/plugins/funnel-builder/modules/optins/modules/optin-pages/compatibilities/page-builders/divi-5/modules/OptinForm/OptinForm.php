<?php
/**
 * Module: Optin Form class.
 *
 * @package WFOP\Modules\OptinForm
 * @since 1.0.0
 */

namespace WFOP\Modules\OptinForm;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOP\Modules\BaseModule;
use WFOP\Modules\OptinForm\OptinFormTrait\RenderCallbackTrait;
use WFOP\Modules\OptinForm\OptinFormTrait\ModuleClassnamesTrait;
use WFOP\Modules\OptinForm\OptinFormTrait\ModuleStylesTrait;
use WFOP\Modules\OptinForm\OptinFormTrait\ModuleScriptDataTrait;
use WFOP\Modules\OptinForm\OptinFormTrait\RestApiTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
$trait_dir = __DIR__ . '/OptinFormTrait/';
$traits    = array(
	'RenderCallbackTrait.php',
	'ModuleClassnamesTrait.php',
	'ModuleStylesTrait.php',
	'ModuleScriptDataTrait.php',
	'RestApiTrait.php',
);

foreach ( $traits as $trait_file ) {
	$trait_path = $trait_dir . $trait_file;
	if ( file_exists( $trait_path ) ) {
		require_once $trait_path;
	}
}

/**
 * OptinForm module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Optin Form module.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class OptinForm extends BaseModule {
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
		return 'OptinForm';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFOP\Modules\OptinForm';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'OptinForm';
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
