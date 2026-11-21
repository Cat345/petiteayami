<?php
/**
 * Role vocabulary for the order lines a bundle writes.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Names the roles every bundle order line is written and read with.
 *
 * Roles this edition never writes are kept so a site without the add-on can still
 * group orders taken while those lines existed.
 */
class Order_Items {

	/**
	 * Line roles.
	 *
	 * Core money roles are anchor/container/member; `child` rows are 0-priced
	 * Fixed Bundle children, so a core-role revenue SUM never double-counts.
	 * `shipping_gift` rows are synthetic: `order_item_id = 0`, `product_id = 0`.
	 */
	const ROLE_ANCHOR        = 'anchor';
	const ROLE_CONTAINER     = 'container';
	const ROLE_CHILD         = 'child';
	const ROLE_MEMBER        = 'member';
	const ROLE_UPSELL        = 'upsell';
	const ROLE_GIFT          = 'gift';
	const ROLE_SHIPPING_GIFT = 'shipping_gift';

	/**
	 * Clamp a bundle name to the `_wffn_bundles_bundle_name` meta's column width.
	 *
	 * Truncates rather than rejecting so an over-long name cannot fail a write.
	 *
	 * @param string $name Bundle name as configured.
	 * @return string
	 */
	public static function truncate_name( $name ) {
		return mb_substr( (string) $name, 0, 255 );
	}
}
