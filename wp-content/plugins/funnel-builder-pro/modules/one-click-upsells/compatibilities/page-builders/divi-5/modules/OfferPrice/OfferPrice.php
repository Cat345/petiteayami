<?php
/**
 * Module: Offer Price class.
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFOCU\Modules\BaseModule;
use WFOCU\Modules\OfferPrice\OfferPriceTrait\RenderCallbackTrait;
use WFOCU\Modules\OfferPrice\OfferPriceTrait\ModuleClassnamesTrait;
use WFOCU\Modules\OfferPrice\OfferPriceTrait\ModuleStylesTrait;
use WFOCU\Modules\OfferPrice\OfferPriceTrait\ModuleScriptDataTrait;
use WFOCU\Modules\OfferPrice\OfferPriceTrait\RestApiTrait;

require_once plugin_dir_path( __FILE__ ) . '../BaseModule.php';

$trait_dir = __DIR__ . '/OfferPriceTrait/';
$traits    = array(
	'CustomCssTrait.php',
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

#[\AllowDynamicProperties]
class OfferPrice extends BaseModule {
	use RenderCallbackTrait;
	use ModuleClassnamesTrait;
	use ModuleStylesTrait;
	use ModuleScriptDataTrait;
	use RestApiTrait;

	protected function get_module_name(): string {
		return 'OfferPrice';
	}

	protected function get_module_namespace(): string {
		return 'WFOCU\\Modules\\OfferPrice';
	}

	protected function get_module_dir(): string {
		return 'OfferPrice';
	}

	public function __construct() {
		$this->load_traits();
	}
}
