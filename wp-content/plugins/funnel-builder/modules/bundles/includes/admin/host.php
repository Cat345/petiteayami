<?php
/**
 * The admin side of Funnel Builder hosting this module: its menu, header, settings tab and screens.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Funnel Builder hosting classes in `Host\`.
 */
class Host {

	/**
	 * Register every hosting class's hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		( new Host\Menu() )->init();
		( new Host\Settings() )->init();
		( new Host\Header() )->init();
		( new Host\Extensions() )->init();
	}
}
