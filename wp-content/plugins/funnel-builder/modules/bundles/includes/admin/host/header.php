<?php
/** Header — Bundles inside the FunnelKit admin header and Analytics sidebar. */
namespace WFFN\Bundles\Includes\Admin\Host;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Admin\Navigation;
use WFFN\Bundles\Includes\Storage\Install;

/**
 * Adds a Bundles item to the FunnelKit header and a Bundles entry to its
 * Analytics sidebar, and hands the admin app the header so it draws the same one.
 * Both open the screens inside Funnel Builder when it can render them (Extensions).
 */
class Header {

	/** The header's icon names and the admin app's names for them. */
	const ICONS = array(
		'settings'    => 'Settings',
		'help-circle' => 'CircleHelp',
	);

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wffn_header_menu', array( $this, 'add_header_item' ) );
		add_filter( 'wffn_analytics_sidebar_items', array( $this, 'add_sidebar_item' ) );
		add_filter( 'wffn_bundles_admin_app_data', array( $this, 'add_app_data' ) );
	}

	/**
	 * Add Bundles to the FunnelKit header, unless switched off in Settings; Marketing keeps its entry either way.
	 *
	 * @param array $menu Header items keyed by slug: `name`, `link`.
	 * @return array
	 */
	public function add_header_item( $menu ) {
		$menu = is_array( $menu ) ? $menu : array();
		if ( Install::STATE_DISABLED === Install::get_state() ) {
			return $menu;
		}
		$menu[ Navigation::KEY ] = array(
			'name' => Navigation::label(),
			'link' => Navigation::admin_url(),
		);

		return $menu;
	}

	/**
	 * Add Bundles to the Analytics sidebar.
	 *
	 * @param array[] $items Sidebar entries.
	 * @return array[]
	 */
	public function add_sidebar_item( $items ) {
		$items = is_array( $items ) ? $items : array();
		// Before it is switched on there is nothing to report.
		if ( Install::STATE_ENABLED !== Install::get_state() ) {
			return $items;
		}
		$item  = array(
			'key'   => Navigation::KEY,
			'label' => Navigation::label(),
		);

		// Inside Funnel Builder's app when it renders the report itself.
		if ( Extensions::is_hosted() ) {
			$item['path'] = '/analytics/' . Navigation::KEY;
		} else {
			$item['url'] = Navigation::admin_url( '/analytics' );
		}

		$items[] = $item;

		return $items;
	}

	/**
	 * Hand the admin app the FunnelKit header.
	 *
	 * @param array $data Admin app data.
	 * @return array
	 */
	public function add_app_data( $data ) {
		if ( ! class_exists( 'WFFN_Header' ) ) {
			return $data;
		}

		$header = ( new \WFFN_Header() )->get_render_data();
		$items  = array();
		foreach ( (array) ( $header['left_nav'] ?? array() ) as $key => $item ) {
			$items[] = array(
				'key'   => (string) $key,
				'label' => (string) ( $item['name'] ?? '' ),
				'url'   => (string) ( $item['link'] ?? '' ),
			);
		}

		$actions = array();
		foreach ( (array) ( $header['right_nav']['menu_react'] ?? array() ) as $key => $item ) {
			$actions[] = array(
				'key'   => (string) $key,
				'label' => (string) ( $item['name'] ?? '' ),
				'icon'  => self::ICONS[ $item['icon'] ?? '' ] ?? '',
				'url'   => (string) ( $item['link'] ?? '' ),
			);
		}

		$data['funnelkit'] = array(
			'logo'     => (string) ( $header['logo'] ?? '' ),
			'logoLink' => (string) ( $header['logo_link'] ?? '' ),
			'items'    => $items,
			'actions'  => $actions,
			'menuItem' => array(
				'key'   => Navigation::KEY,
				'label' => Navigation::label(),
				'url'   => Navigation::admin_url(),
			),
		);

		return $data;
	}
}
