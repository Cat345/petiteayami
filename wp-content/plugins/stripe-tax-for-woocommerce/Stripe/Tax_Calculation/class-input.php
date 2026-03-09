<?php
/**
 * Tax calculation operations input base class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\StripeTax_Options;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxExemptions;

/**
 * Tax calculation operations input base class.
 */
class Input extends Data {
	const EXPAND = array(
		'line_items',
		'line_items.data',
		'line_items.data.tax_breakdown',
		'line_items.data.tax_breakdown.jurisdiction',
		'shipping_cost.tax_breakdown',
	);

	/**
	 * Creates a tax calculation input
	 *
	 * @param string $currency The currency used for tax calculation.
	 * @param array  $customer_details Customer address.
	 * @param array  $line_items The items to calculate taxes.
	 * @param float  $shipping_cost Txable Shipping cost.
	 */
	public function __construct( $currency, $customer_details, $line_items, $shipping_cost ) {
		if ( null !== $line_items ) {
			usort( $line_items, array( self::class, 'compare_references' ) );
		}

		parent::__construct(
			array(
				'currency'         => strtolower( $currency ),
				'customer_details' => $customer_details,
				'line_items'       => $line_items,
				'shipping_cost'    => $shipping_cost,
				'expand'           => static::EXPAND,
			)
		);
	}

	/**
	 * Compare two tax input ine references.
	 *
	 * @param array $reference1 Line reference.
	 * @param array $reference2 Line reference to compare.
	 */
	public static function compare_references( $reference1, $reference2 ) {
		return strcmp( $reference1['reference'], $reference2['reference'] );
	}

	/**
	 * Creates and returns a copy of the object.
	 */
	public function get_payload() {
		$clone = $this->getArrayCopy();

		foreach ( $clone['line_items'] as $i => $line_item ) {
			if ( ! $line_item['tax_code'] ) {
				unset( $clone['line_items'][ $i ]['tax_code'] );
			}
		}

		unset( $clone['shipping_cost']['quantity'], $clone['shipping_cost']['metadata'], $clone['shipping_cost']['reference'] );

		if ( ! $clone['shipping_cost']['tax_code'] ) {
			unset( $clone['shipping_cost']['tax_code'] );
		}

		return $clone;
	}

	/**
	 * Returns a customer or order tax location
	 *
	 * @param object $customer_or_order A customer or an order.
	 * @param array  $tax_location_override The location provided by WC.
	 */
	protected static function get_customer_tax_location( $customer_or_order, $tax_location_override = null ) {
		if ( ! StripeTax_Options::get_option( StripeTax_Options::IGNORE_WC_TAX_BASED_ON ) ) {
			$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		} elseif ( $tax_location_override && static::is_address_partially_filled_in( $tax_location_override ) ) { // Raw POST data from admin.
				$tax_based_on = 'shipping';
		} else {
				$tax_based_on = static::detect_tax_based_on_option( $customer_or_order );
		}

		return static::build_customer_tax_location( $customer_or_order, $tax_based_on, $tax_location_override );
	}

	/**
	 * Rreturns a customer taxability override
	 *
	 * @param object $customer The customer.
	 */
	protected static function get_customer_taxability_override( $customer ) {
		$customer_id = $customer->get_id();

		$tax_exemptions = new TaxExemptions();

		$taxability_override = $tax_exemptions->get_tax_exeption( $customer_id );

		return $taxability_override;
	}

	/**
	 * Determines a customer or order tax location
	 *
	 * @param object $customer_or_order A cutomer or an order.
	 */
	protected static function detect_tax_based_on_option( $customer_or_order ) {
		$tax_based_on = '';

		if ( $customer_or_order instanceof \WC_Order ) {
			$shipping_address = $customer_or_order->get_address( 'shipping' );
		} else {
			$shipping_address = $customer_or_order->get_shipping();
		}

		if ( static::is_address_partially_filled_in( $shipping_address, 'country', 'state' ) ) {
			$tax_based_on = 'shipping';
		}

		if ( '' === $tax_based_on ) {
			if ( $customer_or_order instanceof \WC_Order ) {
				$billing_address = $customer_or_order->get_address( 'billing' );
			} else {
				$billing_address = $customer_or_order->get_billing();
			}

			if ( static::is_address_partially_filled_in( $billing_address, 'country', 'state' ) ) {
				$tax_based_on = 'billing';
			}
		}

		if ( '' === $tax_based_on && StripeTax_Options::get_option( StripeTax_Options::USE_BASE_ADDRESS_FALLBACK ) ) {
			$shop_location = wc_get_base_location();
			$shop_address  = array(
				'address_1' => get_option( 'woocommerce_store_address' ),
				'address_2' => get_option( 'woocommerce_store_address_2' ),
				'city'      => get_option( 'woocommerce_store_city' ),
				'postcode'  => get_option( 'woocommerce_store_postcode' ),
				'country'   => $shop_location['country'],
				'state'     => $shop_location['state'],
			);

			if ( static::is_address_partially_filled_in( $shop_address, 'country', 'state' ) ) {
				$tax_based_on = 'base';
			}
		}

		return $tax_based_on;
	}

	/**
	 * Checks if a given address has any field specified.
	 *
	 * @param array $address The address.
	 * @param array ...$field_names_2_skip Field names to skip.
	 */
	protected static function is_address_partially_filled_in( $address, ...$field_names_2_skip ) {
		foreach ( $address as $address_field_name => $address_field_value ) {
			if ( in_array( $address_field_name, $field_names_2_skip, true ) ) {
				continue;
			}
			if ( strlen( trim( $address_field_value ) ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns a customer or order tax location based on WooCommerce "woocommerce_tax_based_on" option
	 *
	 * @param object $customer_or_order A customer or an order.
	 * @param string $tax_based_on Tax location type.
	 * @param array  $tax_location Location provided by WC.
	 */
	protected static function build_customer_tax_location( $customer_or_order, $tax_based_on, $tax_location ) {
		if ( ! $tax_location ) {
			$tax_location = array();
		}

		switch ( $tax_based_on ) {
			case 'shipping':
				if ( ! $tax_location ) {
					$tax_location['country']  = $customer_or_order->get_shipping_country();
					$tax_location['state']    = $customer_or_order->get_shipping_state();
					$tax_location['postcode'] = $customer_or_order->get_shipping_postcode();
					$tax_location['city']     = $customer_or_order->get_shipping_city();
				}

				$tax_location['line1'] = $customer_or_order->get_shipping_address_1();
				$tax_location['line2'] = $customer_or_order->get_shipping_address_2();

				$tax_location['source'] = static::ADDRESS_SOURCE_SHIPPING;

				break;

			case 'billing':
				$tax_location['country']  = $customer_or_order->get_billing_country();
				$tax_location['state']    = $customer_or_order->get_billing_state();
				$tax_location['postcode'] = $customer_or_order->get_billing_postcode();
				$tax_location['city']     = $customer_or_order->get_billing_city();
				$tax_location['line1']    = $customer_or_order->get_billing_address_1();
				$tax_location['line2']    = $customer_or_order->get_billing_address_2();

				$tax_location['source'] = static::ADDRESS_SOURCE_BILLING;

				break;

			case 'base':
				$shop_location = wc_get_base_location();

				$tax_location['city']     = get_option( 'woocommerce_store_city' );
				$tax_location['postcode'] = get_option( 'woocommerce_store_postcode' );
				$tax_location['country']  = $shop_location['country'];
				$tax_location['state']    = $shop_location['state'];
				$tax_location['line1']    = get_option( 'woocommerce_store_address' );
				$tax_location['line2']    = get_option( 'woocommerce_store_address_2' );

				$tax_location['source'] = static::ADDRESS_SOURCE_SHIPPING;

				break;
			default:
				$tax_location['line1']  = '';
				$tax_location['line2']  = '';
				$tax_location['source'] = static::ADDRESS_SOURCE_SHIPPING;
		}

		static::validate_tax_location( $tax_location );

		return $tax_location;
	}

	/**
	 * Validates a tax location fields
	 *
	 * @param array $tax_location The tax location.
	 *
	 * @throws Input_Exception Throws an exception if validation fails.
	 */
	protected static function validate_tax_location( $tax_location ) {
		if ( ! isset( $tax_location['country'] ) || ! $tax_location['country'] ) {
			throw new Input_Exception( 'Invalid tax location address' );
		}
	}

	/**
	 * Returns an order item reference based on its type.
	 *
	 * @param object $item The order item.
	 */
	public static function get_order_item_reference( $item ) {
		$type = $item->get_type();
		$args = array( $type );

		switch ( $type ) {
			case 'fee':
				$args[] = $item->get_name();
				break;
			case 'line_item':
				$args[] = $item->get_product_id();
				$args[] = $item->get_variation_id();
				$args[] = $item->get_name();

				break;
			case 'shipping':
				$args[0] = Result::SHIPPING_COST_KEY;
				break;
		}
		// @phpstan-ignore-next-line
		return static::get_item_reference( ...$args );
	}
}
