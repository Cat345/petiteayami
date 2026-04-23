<?php
/**
 * Module: Customer Details class.
 *
 * @package WFTY\Modules\CustomerDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\CustomerDetails;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFTY\Modules\BaseModule;
use WFTY\Modules\CustomerDetails\CustomerDetailsTrait\RenderCallbackTrait;
use WFTY\Modules\CustomerDetails\CustomerDetailsTrait\ModuleClassnamesTrait;
use WFTY\Modules\CustomerDetails\CustomerDetailsTrait\ModuleStylesTrait;
use WFTY\Modules\CustomerDetails\CustomerDetailsTrait\ModuleScriptDataTrait;

require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';
$trait_dir = __DIR__ . '/CustomerDetailsTrait/';
foreach ( array( 'RenderCallbackTrait.php', 'ModuleClassnamesTrait.php', 'ModuleStylesTrait.php', 'ModuleScriptDataTrait.php' ) as $trait_file ) {
	$trait_path = $trait_dir . $trait_file;
	if ( file_exists( $trait_path ) ) {
		require_once $trait_path;
	}
}

class CustomerDetails extends BaseModule {
	use RenderCallbackTrait;
	use ModuleClassnamesTrait;
	use ModuleStylesTrait;
	use ModuleScriptDataTrait;

	protected function get_module_name(): string {
		return 'CustomerDetails';
	}

	protected function get_module_namespace(): string {
		return 'WFTY\Modules\CustomerDetails';
	}

	protected function get_module_dir(): string {
		return 'CustomerDetails';
	}

	public function __construct() {
		$this->load_traits();
	}
}
