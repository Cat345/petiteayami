<?php
/** Integration — the contract every third-party compatibility adapter implements. */
namespace WFFN\Bundles\Includes\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A compatibility adapter for one third-party plugin.
 *
 * Manager constructs an adapter before calling is_enable(), so a constructor
 * must not touch the plugin it adapts — that plugin may not be loaded. An adapter
 * whose is_enable() changes needs the same change in Manager::present().
 */
interface Integration {

	/**
	 * Is the third-party plugin this adapter targets actually present?
	 *
	 * @return bool
	 */
	public function is_enable();

	/**
	 * Register this adapter's hooks. Only ever called when is_enable() is true.
	 *
	 * @return void
	 */
	public function init();
}
