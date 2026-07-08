<?php
/**
 * Module: Optin Form Popup class.
 *
 * @package WFOPP\Modules\OptinFormPopup
 * @since 1.0.0
 */

namespace WFOPP\Modules\OptinFormPopup;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOPP\Modules\BaseModule;
use WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait\RenderCallbackTrait;
use WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait\ModuleClassnamesTrait;
use WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait\ModuleStylesTrait;
use WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait\ModuleScriptDataTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
$trait_dir = __DIR__ . '/OptinFormPopupTrait/';
$traits    = array(
	'RenderCallbackTrait.php',
	'ModuleClassnamesTrait.php',
	'ModuleStylesTrait.php',
	'ModuleScriptDataTrait.php',
);

foreach ( $traits as $trait_file ) {
	$trait_path = $trait_dir . $trait_file;
	if ( file_exists( $trait_path ) ) {
		require_once $trait_path;
	}
}

/**
 * OptinFormPopup module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Optin Form Popup module.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class OptinFormPopup extends BaseModule {
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
		return 'OptinFormPopup';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFOPP\Modules\OptinFormPopup';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'OptinFormPopup';
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
