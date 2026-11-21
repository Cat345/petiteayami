<?php
/** Abilities — the AI abilities that place bundles in Funnel Builder's checkouts and upsell offers. */
namespace WFFN\Bundles\Includes\Compat\Funnel_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;

/**
 * Entry class: adds Ability_Pack to the FunnelKit MCP module through the
 * module's own `fkmcp_ability_packs`, beside Aero Checkout or One-Click Upsells.
 * The pack itself loads only when the module asks for packs, so removing this
 * folder takes the abilities with it and nothing else.
 */
class Abilities implements Integration {

	/**
	 * Always on: Funnel Builder loads an Aero Checkout before this module.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return true;
	}

	/**
	 * Hook the module.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'fkmcp_ability_packs', array( $this, 'register_pack' ) );
	}

	/**
	 * Add the pack once the module has loaded its base class.
	 *
	 * @param array $packs Packs so far.
	 * @return array
	 */
	public function register_pack( $packs ) {
		$packs = is_array( $packs ) ? $packs : array();
		if ( class_exists( 'FKMCP_Pack' ) ) {
			$packs[] = new Ability_Pack();
		}
		return $packs;
	}
}
