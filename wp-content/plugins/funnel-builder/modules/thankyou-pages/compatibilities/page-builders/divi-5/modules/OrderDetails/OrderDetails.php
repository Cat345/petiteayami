<?php
/**
 * Module: Order Details class.
 *
 * @package WFTY\Modules\OrderDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\OrderDetails;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFTY\Modules\BaseModule;
use WFTY\Modules\OrderDetails\OrderDetailsTrait\RenderCallbackTrait;
use WFTY\Modules\OrderDetails\OrderDetailsTrait\ModuleClassnamesTrait;
use WFTY\Modules\OrderDetails\OrderDetailsTrait\ModuleStylesTrait;
use WFTY\Modules\OrderDetails\OrderDetailsTrait\ModuleScriptDataTrait;

require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';
$trait_dir = __DIR__ . '/OrderDetailsTrait/';
foreach ( array( 'RenderCallbackTrait.php', 'ModuleClassnamesTrait.php', 'ModuleStylesTrait.php', 'ModuleScriptDataTrait.php' ) as $trait_file ) {
	$trait_path = $trait_dir . $trait_file;
	if ( file_exists( $trait_path ) ) {
		require_once $trait_path;
	}
}

#[\AllowDynamicProperties]
class OrderDetails extends BaseModule {
	use RenderCallbackTrait;
	use ModuleClassnamesTrait;
	use ModuleStylesTrait;
	use ModuleScriptDataTrait;

	protected function get_module_name(): string {
		return 'OrderDetails';
	}

	protected function get_module_namespace(): string {
		return 'WFTY\Modules\OrderDetails';
	}

	protected function get_module_dir(): string {
		return 'OrderDetails';
	}

	public function __construct() {
		$this->load_traits();
	}
}
