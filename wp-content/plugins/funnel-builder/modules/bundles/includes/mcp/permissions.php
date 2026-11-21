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
 * The only auth layer between an ability call and the data: the abilities call
 * the storage layer directly, never the REST controllers and their permission
 * callbacks, so the same capability the admin screens use is checked here.
 */
class Permissions {

	/** Per-user opt-out of write abilities, shared by every FunnelKit host. */
	const META_ALLOW_WRITES = 'fkmcp_allow_writes';

	/**
	 * Whether the current user may read bundles: the rule every admin screen and REST route checks.
	 *
	 * @return bool
	 */
	public static function can_read() {
		return Navigation::can_manage();
	}

	/**
	 * Whether the current user may call write abilities.
	 *
	 * On by default for a user who may manage bundles; the user meta only ever
	 * records an opt-out.
	 *
	 * @return bool
	 */
	public static function can_write() {
		// Funnel Builder's write level: a read-only role reads, never writes.
		if ( ! self::can_read() || ! Navigation::can_write() ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( 'no' === get_user_meta( $user_id, self::META_ALLOW_WRITES, true ) ) {
			return false;
		}

		return (bool) apply_filters( 'fkmcp_allow_writes', true, $user_id );
	}

	/**
	 * Who may connect: logged in, able to read, and under the transport ceiling.
	 *
	 * @param \WP_REST_Request|null $request Incoming MCP request.
	 * @return bool|\WP_Error
	 */
	public static function transport_gate( $request = null ) {
		unset( $request );

		if ( ! is_user_logged_in() || ! self::can_read() ) {
			return false;
		}

		$limited = Rate_Limit::check_transport();

		return is_wp_error( $limited ) ? $limited : true;
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

		if ( ! self::can_read() ) {
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

		if ( ! self::can_read() ) {
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
