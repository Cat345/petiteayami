<?php
/**
 * A stand-in order the offer line writers can write to without saving anything.
 *
 * Before an accepted offer is charged, its bundle lines are written here by the
 * same code that later writes them to the real order, so the charge can be
 * checked against exactly those lines.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Compat\One_Click_Upsells;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the lines it is given in memory; nothing reaches the database.
 */
class Charge_Probe extends \WC_Order {

	/** Lines added, keyed by a made-up item id. */
	private $probe_lines = array();

	/**
	 * Start empty, priced like the order the offer is charged on.
	 *
	 * @param \WC_Order $order The order the offer is charged on.
	 */
	public function __construct( $order ) {
		parent::__construct( 0 );
		$this->set_currency( $order->get_currency() );
		$this->set_prices_include_tax( $order->get_prices_include_tax() );
	}

	/**
	 * Record a line as the order would add it, without saving it.
	 *
	 * @param \WC_Product|false $product Product the line sells.
	 * @param int               $qty     Quantity.
	 * @param array             $args    Line props, as for WC_Order::add_product().
	 * @return int Made-up item id.
	 */
	public function add_product( $product, $qty = 1, $args = array() ) {
		$defaults = array( 'quantity' => $qty );
		if ( $product instanceof \WC_Product ) {
			$defaults += array(
				'name'         => $product->get_name(),
				'tax_class'    => $product->get_tax_class(),
				'product_id'   => $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $product->get_parent_id() ? $product->get_id() : 0,
			);
		}
		$item = new class() extends \WC_Order_Item_Product {
			/**
			 * Never written: the line only prices the charge.
			 *
			 * @return int
			 */
			public function save() {
				return $this->get_id();
			}
		};
		$item->set_props( wp_parse_args( $args, $defaults ) );
		$id = count( $this->probe_lines ) + 1;
		$item->set_id( $id );
		$this->probe_lines[ $id ] = $item;
		return $id;
	}

	/**
	 * The lines recorded so far.
	 *
	 * @param string|string[] $types Unused; only product lines are recorded.
	 * @return \WC_Order_Item_Product[]
	 */
	public function get_items( $types = 'line_item' ) {
		return $this->probe_lines;
	}

	/**
	 * One recorded line.
	 *
	 * @param int  $item_id      Made-up item id.
	 * @param bool $load_from_db Unused; nothing is ever loaded.
	 * @return \WC_Order_Item_Product|false
	 */
	public function get_item( $item_id, $load_from_db = true ) {
		return $this->probe_lines[ $item_id ] ?? false;
	}

	/**
	 * Never saved.
	 *
	 * @return int
	 */
	public function save() {
		return 0;
	}

	/**
	 * What the recorded lines come to.
	 *
	 * @return float
	 */
	public function lines_total() {
		$total = 0.0;
		foreach ( $this->probe_lines as $item ) {
			$total += (float) $item->get_total();
		}
		return round( $total, wc_get_price_decimals() );
	}
}
