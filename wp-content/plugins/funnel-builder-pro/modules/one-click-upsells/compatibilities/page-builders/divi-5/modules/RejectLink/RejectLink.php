<?php
/**
 * Module: Reject Link class.
 *
 * @package WFOCU\Modules\RejectLink
 * @since 1.0.0
 */

namespace WFOCU\Modules\RejectLink;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOCU\Modules\BaseModule;
use WFOCU\Modules\RejectLink\RejectLinkTrait\RenderCallbackTrait;
use WFOCU\Modules\RejectLink\RejectLinkTrait\ModuleClassnamesTrait;
use WFOCU\Modules\RejectLink\RejectLinkTrait\ModuleStylesTrait;
use WFOCU\Modules\RejectLink\RejectLinkTrait\ModuleScriptDataTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
// IMPORTANT: Load CustomCssTrait before ModuleStylesTrait since ModuleStylesTrait depends on it
$trait_dir = __DIR__ . '/RejectLinkTrait/';
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
 * RejectLink module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Reject Link module.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class RejectLink extends BaseModule {
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
		return 'RejectLink';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFOCU\Modules\RejectLink';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'RejectLink';
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
