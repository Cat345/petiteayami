<?php
/** Menu — the Bundles page inside FunnelKit Cart's FunnelKit admin menu and header. */
namespace WFFN\Bundles\Includes\Compat\Fkcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Admin\Navigation;
use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Storage\Install;

/**
 * Nests the Bundles page under the FunnelKit menu FunnelKit Cart registers
 * when it runs without Funnel Builder, rather than adding a second one, and
 * adds Bundles to the FunnelKit header Cart draws on its own screens.
 */
class Menu implements Integration {

	/** FunnelKit Cart's top-level menu slug. */
	const PARENT_SLUG = 'fkcart';

	/**
	 * Is FunnelKit Cart loaded? It defines this before Manager::boot runs.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return defined( 'FKCART_VERSION' );
	}

	/**
	 * Register this integration's hooks. Only called when is_enable() is true.
	 *
	 * @return void
	 */
	public function init() {
		// After the Funnel Builder answer, which takes precedence when both exist.
		add_filter( 'wffn_bundles_admin_menu_parent', array( $this, 'parent_slug' ), 20 );
		add_filter( 'fkcart_app_header_menu', array( $this, 'add_header_item' ) );
	}

	/**
	 * Nest under Cart's FunnelKit menu when no other parent was chosen.
	 *
	 * @param string $parent_slug Parent menu slug, or empty for none.
	 * @return string
	 */
	public function parent_slug( $parent_slug ) {
		global $admin_page_hooks;

		if ( '' !== $parent_slug ) {
			return $parent_slug;
		}

		return isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ? self::PARENT_SLUG : $parent_slug;
	}

	/**
	 * Add Bundles to Cart's FunnelKit header, just before Cart, as the Funnel Builder header orders them.
	 *
	 * @param array $menu Header items keyed by slug: `name`, `link`.
	 * @return array
	 */
	public function add_header_item( $menu ) {
		$menu = is_array( $menu ) ? $menu : array();
		if ( Install::STATE_DISABLED === Install::get_state() || isset( $menu[ Navigation::KEY ] ) ) {
			return $menu;
		}
		$item = array(
			Navigation::KEY => array(
				'name' => Navigation::label(),
				'link' => Navigation::admin_url(),
			),
		);
		$at   = array_search( 'cart', array_keys( $menu ), true );

		return false === $at ? $menu + $item : array_slice( $menu, 0, $at, true ) + $item + array_slice( $menu, $at, null, true );
	}
}
