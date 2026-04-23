<?php
/**
 * Module: Reject Button class.
 *
 * @package WFOCU\Modules\RejectButton
 * @since 1.0.0
 */

namespace WFOCU\Modules\RejectButton;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOCU\Modules\BaseModule;
use WFOCU\Modules\RejectButton\RejectButtonTrait\RenderCallbackTrait;
use WFOCU\Modules\RejectButton\RejectButtonTrait\ModuleClassnamesTrait;
use WFOCU\Modules\RejectButton\RejectButtonTrait\ModuleStylesTrait;
use WFOCU\Modules\RejectButton\RejectButtonTrait\ModuleScriptDataTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
// IMPORTANT: Load CustomCssTrait before ModuleStylesTrait since ModuleStylesTrait depends on it
$trait_dir = __DIR__ . '/RejectButtonTrait/';
$traits    = array(
	'CustomCssTrait.php',        // Must be loaded first (dependency for ModuleStylesTrait)
	'RenderCallbackTrait.php',
	'ModuleClassnamesTrait.php',
	'ModuleStylesTrait.php',     // Depends on CustomCssTrait
	'ModuleScriptDataTrait.php',
);

foreach ( $traits as $trait_file ) {
	$trait_path = $trait_dir . $trait_file;
	if ( file_exists( $trait_path ) ) {
		require_once $trait_path;
	}
}

/**
 * RejectButton module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Reject Button module.
 *
 * @since 1.0.0
 */
class RejectButton extends BaseModule {
	use RenderCallbackTrait;
	use ModuleClassnamesTrait;
	use ModuleStylesTrait;
	use ModuleScriptDataTrait;

	/**
	 * Get module name.
	 *
	 * @since 1.0.0
	 * @return string Module name.
	 */
	protected function get_module_name(): string {
		return 'RejectButton';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFOCU\Modules\RejectButton';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'RejectButton';
	}

	/**
	 * Constructor - loads traits.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_traits();
	}
}
