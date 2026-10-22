<?php
/**
 * Class SubscriptionsIntegration
 *
 * @package WPDesk\FS\Shipment
 */

namespace WPDesk\FS\Shipment;

use FSVendor\WPDesk\FS\Shipment\Checkout\ShipmentCreator;
use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Integrates Shipments with WooCommerce Subscriptions plugin.
 */
class SubscriptionsIntegration implements Hookable {

	/**
	 * @var ShipmentCreator
	 */
	private ShipmentCreator $shipment_creator;

	/**
	 * SubscriptionsIntegration constructor.
	 *
	 * @param ShipmentCreator $shipment_creator .
	 */
	public function __construct( ShipmentCreator $shipment_creator ) {
		$this->shipment_creator = $shipment_creator;
	}

	/**
	 * .
	 */
	public function hooks() {
		$last_priority = PHP_INT_MAX;
		add_action( 'woocommerce_checkout_subscription_created', [ $this, 'create_shipping_for_subscription' ], $last_priority, 3 );
		add_filter( 'wcs_renewal_order_created', [ $this, 'create_shipping_for_order_from_subscription' ], 10, 2 );
	}

	/**
	 * @param \WC_Order        $subscription .
	 * @param \WC_Order        $order .
	 * @param \WC_Cart         $recurring_cart .
	 */
	public function create_shipping_for_subscription( $subscription, $order, $recurring_cart ) {
		$this->shipment_creator->create_shipping_for_order_and_cart( $subscription, $recurring_cart );
	}

	/**
	 * @param \WC_Order        $order .
	 * @param \WC_Order        $subscription .
	 */
	public function create_shipping_for_order_from_subscription( $order, $subscription ) {
		$subscription_shipments = fs_get_order_shipments( $subscription->get_id() );
		foreach ( $subscription_shipments as $shipment ) {
			$this->create_single_shipment( $shipment, $order );
		}

		return $order;
	}

	/**
	 * @param \WPDesk_Flexible_Shipping_Shipment $shipment .
	 * @param \WC_Order                          $order .
	 */
	private function create_single_shipment( \WPDesk_Flexible_Shipping_Shipment $shipment, \WC_Order $order ) {
		$meta_data      = $shipment->get_meta_data();
		$fs_method      = $shipment->get_meta( '_fs_method' );
		$fs_method      = is_array( $fs_method ) ? $fs_method : [ 'method_integration' => $shipment->get_integration() ];
		$order_shipment = fs_create_shipment( $order, $fs_method );
		$integration    = $order_shipment->get_integration();
		$this->setup_shipment_meta_data( $meta_data, $integration, $shipment, $order_shipment );
		$order_shipment->save();

		/**
		 * New shipment created from subscription.
		 *
		 * @param \WPDesk_Flexible_Shipping_Shipment $order_shipment Created shipment.
		 */
		do_action( 'flexible-shipping/shipment-from-subscription/created/' . $integration, $order_shipment );
	}

	/**
	 * @param array                              $meta_data .
	 * @param string                             $integration .
	 * @param \WPDesk_Flexible_Shipping_Shipment $shipment .
	 * @param \WPDesk_Flexible_Shipping_Shipment $order_shipment .
	 */
	private function setup_shipment_meta_data( array $meta_data, $integration, \WPDesk_Flexible_Shipping_Shipment $shipment, \WPDesk_Flexible_Shipping_Shipment $order_shipment ) {
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$order_shipment_meta_value =
				apply_filters( 'flexible-shipping/shipment-from-subscription/meta-value/' . $integration, $shipment->get_meta( $meta_key ), $meta_key, $order_shipment );
			$order_shipment->set_meta( $meta_key, $order_shipment_meta_value );
		}
	}
}
