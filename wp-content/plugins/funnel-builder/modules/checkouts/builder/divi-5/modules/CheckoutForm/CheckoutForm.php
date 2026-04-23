<?php
/**
 * Module: Checkout Form class.
 *
 * @package WFACP\Modules\CheckoutForm
 * @since 1.0.0
 */

namespace WFACP\Modules\CheckoutForm;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFACP\Modules\BaseModule;
use WFACP\Modules\CheckoutForm\CheckoutFormTrait\RenderCallbackTrait;
use WFACP\Modules\CheckoutForm\CheckoutFormTrait\RestApiTrait;
use WFACP\Modules\CheckoutForm\CheckoutFormTrait\ModuleClassnamesTrait;
use WFACP\Modules\CheckoutForm\CheckoutFormTrait\ModuleStylesTrait;
use WFACP\Modules\CheckoutForm\CheckoutFormTrait\ModuleScriptDataTrait;

// Load base module class
require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

// Load trait files - using consistent pattern for all modules
// IMPORTANT: Load CustomCssTrait before ModuleStylesTrait since ModuleStylesTrait depends on it
$trait_dir = __DIR__ . '/CheckoutFormTrait/';
$traits    = array(
	'CustomCssTrait.php',        // Must be loaded first (dependency for ModuleStylesTrait)
	'RenderCallbackTrait.php',
	'RestApiTrait.php',          // REST API for Visual Builder rendering
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
 * CheckoutForm module class.
 *
 * This class extends BaseModule and handles registration and rendering
 * of the Checkout Form module.
 *
 * @since 1.0.0
 */
class CheckoutForm extends BaseModule {
	use RenderCallbackTrait;
	use RestApiTrait;
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
		return 'CheckoutForm';
	}

	/**
	 * Get module namespace.
	 *
	 * @since 1.0.0
	 * @return string Module namespace.
	 */
	protected function get_module_namespace(): string {
		return 'WFACP\Modules\CheckoutForm';
	}

	/**
	 * Get module directory.
	 *
	 * @since 1.0.0
	 * @return string Module directory path.
	 */
	protected function get_module_dir(): string {
		return 'CheckoutForm';
	}

	/**
	 * Constructor - loads traits and registers REST routes.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_traits();
		// Register REST API routes for Visual Builder rendering
		self::register_rest_routes();
	}
}
