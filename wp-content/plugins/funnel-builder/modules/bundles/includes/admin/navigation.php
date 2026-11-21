<?php
/**
 * The admin app's menus, described on the server.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Every menu the admin app draws: the header's own items and the rail inside
 * Settings.
 *
 * An item names a `path`; it does not name a component. The app maps a path to
 * whatever renders it, so a screen an add-on owns is described here without
 * this plugin knowing what it draws.
 */
class Navigation {

	/** The admin page the app renders on, when nothing hosts it. */
	const PAGE = 'wffn-bundles';

	/** This plugin's key wherever a host lists it: a menu, a header, a sidebar. */
	const KEY = 'bundles';

	/**
	 * What a host calls this plugin in a menu, a header or a sidebar.
	 *
	 * @return string
	 */
	public static function label() {
		return __( 'Bundles', 'funnel-builder' );
	}

	/**
	 * Whether the current user may manage bundles: Funnel Builder's own access
	 * rule, so Bundles opens for exactly the users Funnel Builder opens for.
	 * A site widens it on `wffn_user_access_capabilities`.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return false !== \WFFN_Role_Capability::get_instance()->user_access( 'menu', 'read' );
	}

	/**
	 * Whether the current user may change bundles and their settings: Funnel
	 * Builder's write level of the same rule. A role Funnel Builder lets read
	 * only sees the screens and reads, and every create, update, delete,
	 * import, status or settings write is refused.
	 *
	 * @return bool
	 */
	public static function can_write() {
		return false !== \WFFN_Role_Capability::get_instance()->user_access( 'menu', 'write' );
	}

	/**
	 * The capability the admin pages register with: the role Funnel Builder
	 * lets in, as its own menu does, or one nobody holds.
	 *
	 * @return string
	 */
	public static function capability() {
		$role = \WFFN_Role_Capability::get_instance()->user_access( 'menu', 'read' );

		return $role ? (string) $role : 'do_not_allow';
	}

	/**
	 * The admin URL of one of the app's screens, by its path, e.g. '/bundles/12'.
	 *
	 * Every link to the app goes through here, so a plugin that renders the
	 * screens in its own admin app (Funnel Builder) points them all at it.
	 *
	 * @param string $path Path inside the app; '/' is the list.
	 * @return string
	 */
	public static function admin_url( $path = '/' ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$args = array( 'page' => self::PAGE );
		if ( '/' !== $path ) {
			$args['path'] = $path;
		}

		return Host\Extensions::host_url( add_query_arg( $args, admin_url( 'admin.php' ) ), $path );
	}

	/**
	 * The whole navigation: header items, the header's right-hand slot, and
	 * the groups inside Settings.
	 *
	 * @return array
	 */
	public static function get() {
		$navigation = array(
			'primary'   => array(
				array(
					'id'    => 'bundles',
					'label' => __( 'Bundles', 'funnel-builder' ),
					'path'  => '/',
					// Every editor and list route lights this item.
					'match' => array( '/', '/bundles' ),
					'order' => 10,
				),
				array(
					'id'    => 'analytics',
					'label' => __( 'Analytics', 'funnel-builder' ),
					'path'  => '/analytics',
					'order' => 20,
				),
			),
			'secondary' => array(
				array(
					'id'    => 'settings',
					'label' => __( 'Settings', 'funnel-builder' ),
					'path'  => '/settings',
					'icon'  => 'Settings',
					'order' => 10,
				),
			),
			'settings'  => array(
				array(
					'id'    => 'general',
					'label' => __( 'General', 'funnel-builder' ),
					'path'  => '/settings/general',
					'order' => 10,
				),
			),
		);

		/**
		 * Filters the admin app's menus.
		 *
		 * An add-on appends the items for the screens it owns.
		 *
		 * @since 1.0.0
		 *
		 * @param array $navigation Menus, each a list of items.
		 */
		$navigation = (array) apply_filters( 'wffn_bundles_admin_navigation', $navigation );

		// An add-on appends; `order` is what puts its item where it belongs.
		foreach ( $navigation as $menu => $items ) {
			$items = array_values( (array) $items );
			usort(
				$items,
				static function ( $a, $b ) {
					return (int) ( $a['order'] ?? 999 ) <=> (int) ( $b['order'] ?? 999 );
				}
			);
			$navigation[ $menu ] = $items;
		}

		return $navigation;
	}
}
