<?php
/**
 * Who may use the Bundles abilities.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Mcp;

defined( 'ABSPATH' ) || exit;

use WFFN\Bundles\Includes\Admin\Navigation;

/**
 * Funnel Builder's MCP permission gate, answering from Bundles' own capability
 * layer, plus the object-level checks the Bundles abilities need.
 *
 * It is the only auth layer between an ability call and the data: the abilities
 * call the storage layer directly, never the REST controllers and their
 * permission callbacks, so the same capability the admin screens use is checked
 * here.
 */
class Permissions extends \WFFN_MCP_Permissions {

	/**
	 * Bundles has no MCP classes of its own beyond these, so a shared class
	 * looking for a sibling finds the host's.
	 *
	 * @return string
	 */
	protected static function host_prefix() {
		return 'WFFN_MCP_';
	}

	/**
	 * Bundles' own capability layer, the rule every admin screen and REST route
	 * checks. The inherited can_read() and can_write() are built on this, so
	 * they keep answering exactly as they did: read for anyone who may manage
	 * bundles, write for anyone who may also write, minus the per-user opt-out.
	 *
	 * @param string $cap    Capability group; Bundles does not split by area.
	 * @param string $access read or write.
	 * @return bool
	 */
	public static function can_access( $cap, $access ) {
		unset( $cap );

		// Funnel Builder's write level: a read-only role reads, never writes.
		return 'write' === $access ? Navigation::can_write() : Navigation::can_manage();
	}

	/**
	 * Read access plus the right to edit this one product.
	 *
	 * Asked without input, as the connector does to decide whether to list
	 * the tool, it answers for the tool; execute() asks again with the id.
	 *
	 * @param mixed $input Ability input carrying `product_id`.
	 * @return bool
	 */
	public static function can_read_product( $input = array() ) {
		$product_id = is_array( $input ) ? absint( $input['product_id'] ?? 0 ) : 0;

		if ( ! static::can_read() ) {
			return false;
		}

		// A missing product has nothing to protect; execute() answers not found.
		return $product_id < 1 || ! get_post( $product_id ) || current_user_can( 'edit_product', $product_id );
	}

	/**
	 * Read access plus WooCommerce's own object-level check on this one order.
	 *
	 * Without input it answers for the tool, as can_read_product() does.
	 *
	 * @param mixed $input Ability input carrying `order_id`.
	 * @return bool
	 */
	public static function can_read_order( $input = array() ) {
		$order_id = is_array( $input ) ? absint( $input['order_id'] ?? 0 ) : 0;

		if ( ! static::can_read() ) {
			return false;
		}
		if ( $order_id < 1 ) {
			return true;
		}

		return function_exists( 'wc_rest_check_post_permissions' )
			? (bool) wc_rest_check_post_permissions( 'shop_order', 'read', $order_id )
			: current_user_can( 'edit_shop_orders' );
	}
}
