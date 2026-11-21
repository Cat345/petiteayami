<?php
/** Menu — the Bundles page in WooCommerce's Marketing menu, beside Coupons. */
namespace WFFN\Bundles\Includes\Admin\Host;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Admin\Navigation;

/**
 * Lists Bundles in WooCommerce's Marketing menu, directly after Coupons, and
 * nowhere in the FunnelKit menu. Whether Bundles is switched on or off does not
 * decide the entry: the page it opens is where bundles are made, in every state.
 */
class Menu {

	/** WooCommerce's Marketing menu slug. */
	const PARENT_SLUG = 'woocommerce-marketing';

	/** WooCommerce's Coupons entry in that menu, which Bundles follows. */
	const COUPONS_SLUG = 'edit.php?post_type=shop_coupon';

	/** @var string|null The page slug lend_slug() put aside, until take_slug_back() returns it. */
	private static $page = null;

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wffn_bundles_admin_menu_parent', array( $this, 'parent_slug' ) );
		add_filter( 'parent_file', array( __CLASS__, 'lend_slug' ) );
		add_action( 'adminmenu', array( __CLASS__, 'take_slug_back' ) );
	}

	/**
	 * Keep this plugin's own page out of the menu while Funnel Builder renders
	 * the screens: the Marketing entry then opens them there.
	 *
	 * @return bool Whether to register the page without a menu entry.
	 */
	public static function is_hidden() {
		return Extensions::is_hosted();
	}

	/**
	 * Nest this plugin's own page under Marketing when that menu is registered.
	 *
	 * @param string $parent_slug Parent menu slug, or empty for none.
	 * @return string
	 */
	public function parent_slug( $parent_slug ) {
		global $admin_page_hooks;

		return isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ? self::PARENT_SLUG : $parent_slug;
	}

	/**
	 * Answer the Bundles slot Funnel Builder calls in its WP submenu and in its app header.
	 *
	 * Funnel Builder decides nothing itself: whether an item appears at all, what
	 * it says, what it opens and who may see it are answered here. The header
	 * entry is the one Header already describes. The FunnelKit submenu gets
	 * nothing; the call schedules the Marketing entry instead, after WooCommerce
	 * has ordered that menu (admin_menu 99) and this plugin's own page has
	 * registered (903).
	 *
	 * @param string $context 'menu' for the WP submenu, 'header' for the app header.
	 *
	 * @return array[]|void The header entry keyed by item key; nothing in the submenu.
	 */
	public static function register_menu( $context = 'menu' ) {
		// Without WooCommerce Bundles boots nothing and has no page, but Funnel Builder still calls this slot.
		if ( ! \WFFN\Bundles\Plugin::is_woocommerce_active() ) {
			return 'header' === $context ? array() : null;
		}

		if ( 'header' === $context ) {
			$header = new Header();

			return $header->add_header_item( array() );
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_marketing_item' ), 904 );
	}

	/**
	 * Add Bundles to the Marketing menu, directly after Coupons.
	 *
	 * While Funnel Builder renders the screens the entry opens its Bundles page;
	 * otherwise this plugin's own page is already in the menu (parent_slug()) and
	 * is only moved into place.
	 *
	 * @return void
	 */
	public static function add_marketing_item() {
		global $admin_page_hooks, $submenu;

		if ( empty( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			return;
		}

		$slug = Navigation::PAGE;
		if ( Extensions::is_hosted() ) {
			$slug = Extensions::page_slug( Extensions::host_path( '/' ) );
			add_submenu_page( self::PARENT_SLUG, Navigation::label(), Navigation::label(), Navigation::capability(), $slug, '__return_null' );
		}

		$items = isset( $submenu[ self::PARENT_SLUG ] ) ? array_values( (array) $submenu[ self::PARENT_SLUG ] ) : array();
		$own   = array_search( $slug, array_column( $items, 2 ), true );
		if ( false === $own ) {
			return;
		}

		$entry = array_splice( $items, $own, 1 );
		$after = array_search( self::COUPONS_SLUG, array_column( $items, 2 ), true );
		$at    = false === $after ? count( $items ) : $after + 1;
		array_splice( $items, $at, 0, $entry );

		$submenu[ self::PARENT_SLUG ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Placing this plugin's own entry, as WooCommerce orders this menu.
	}

	/**
	 * Lend WordPress the Marketing entry's slug while it draws the menu, on the screens that entry opens.
	 *
	 * WordPress works the open menu out from `page`, which names the FunnelKit menu's own top-level
	 * slug, so it opens that menu whatever a filter answers. `parent_file` is the last hook before it does.
	 *
	 * @param string $parent_file Parent slug, returned untouched: WordPress works it out again straight after.
	 * @return string
	 */
	public static function lend_slug( $parent_file ) {
		global $plugin_page, $submenu;

		if ( ! isset( $plugin_page ) || ! Extensions::is_hosted() ) {
			return $parent_file;
		}

		$slug  = Extensions::page_slug( Extensions::host_path( '/' ) );
		$path  = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the screen being drawn, to open its menu.
		$here  = $plugin_page . '&path=' . $path;
		$items = isset( $submenu[ self::PARENT_SLUG ] ) ? (array) $submenu[ self::PARENT_SLUG ] : array();

		if ( ! in_array( $slug, array_column( $items, 2 ), true ) ) {
			return $parent_file;
		}
		if ( $here !== $slug && 0 !== strpos( $here, $slug . '/' ) ) {
			return $parent_file;
		}

		self::$page              = $plugin_page;
		$plugin_page             = $slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Taken back on `adminmenu`, once the menu is drawn.
		$GLOBALS['submenu_file'] = $slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The FunnelKit menu points this at its own Dashboard on `admin_head`.

		return $parent_file;
	}

	/**
	 * Take the slug back once the menu is drawn.
	 *
	 * @return void
	 */
	public static function take_slug_back() {
		if ( null !== self::$page ) {
			$GLOBALS['plugin_page'] = self::$page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Returning what lend_slug() put aside.
			self::$page             = null;
		}
	}
}
