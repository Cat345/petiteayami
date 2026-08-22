<?php
/**
 * PowerPack license loader.
 *
 * The license system was relocated out of the free plugin (WP.org review:
 * no update-checker code may ship in the .org zip). PowerPack is the only
 * place the license classes load from, together with the license module —
 * the live glue bridging the WooFunnels core hooks (daily check, update
 * transients, plugins-screen notice, pro_status) to them. The free build
 * ships neither.
 *
 * The three classes are ~1,100 lines that only matter when a licence is
 * actually checked, activated or displayed, and none of them do anything at
 * file scope. They are registered for autoloading rather than required, so a
 * request that never touches licensing never reads them. The module itself is
 * small and has to load, because it is what registers the hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		$map = array(
			'woofunnels_licenses'           => 'class-woofunnels-licenses.php',
			'woofunnels_license_controller' => 'class-woofunnels-license-controller.php',
			'woofunnels_license_check'      => 'class-woofunnels-license-check.php',
		);

		$key = strtolower( $class );
		if ( ! isset( $map[ $key ] ) ) {
			return;
		}

		$file = __DIR__ . '/' . $map[ $key ];
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
require_once __DIR__ . '/license-module.php';
