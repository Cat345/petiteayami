<?php
/**
 * Add extra functionality to WooCommerce Order Item Tax class.
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

// Exit if script started not from WordPress.
defined( 'ABSPATH' ) || exit;

/**
 * Extends WooCommerce Order Item Tax class with support of Stripe Taxes.
 *
 * @phpstan-consistent-constructor
 */
class StripeOrderItemTax extends \WC_Order_Item_Tax {

	/**
	 * Creates a tax item given a rate id
	 *
	 * @param int $rate_id Rate id.
	 */
	public static function from_rate_id( $rate_id ) {
		$item = new self();

		$item->set_rate_id( $rate_id );

		return $item;
	}

	/**
	 * Sets tax rate properties
	 *
	 * @param array $tax_rate Rate properties.
	 */
	protected function set_rate_properties( $tax_rate ) {
		if ( ! $tax_rate ) {
			return;
		}

		$rate = array(
			'rate_id'      => $tax_rate['id'],
			'label'        => $tax_rate['name'],
			// @phpstan-ignore-next-line
			'rate_code'    => $tax_rate->get_code(),
			'rate_percent' => $tax_rate['rate'],
		);

		$props   = array( 'rate_id', 'label', 'rate_code', 'rate_percent' );
		$changed = false;

		foreach ( $props as $prop_name ) {
			if ( $rate[ $prop_name ] === $this->get_prop( $prop_name ) ) {
				continue;
			}

			$changed = true;
			break;
		}

		if ( ! $changed ) {
			return;
		}

		$current_object_read = $this->get_object_read();

		if ( ! $current_object_read ) {
			$this->set_object_read( true );
		}

		$this->set_prop( 'rate_id', $rate['rate_id'] );
		$this->set_prop( 'label', $rate['label'] );
		$this->set_prop( 'rate_code', $rate['rate_code'] );
		$this->set_prop( 'rate_percent', $rate['rate_percent'] );

		$result = $this->save();

		$this->set_object_read( $current_object_read );
	}

	/**
	 * Sets tax rate properties given its rate id
	 *
	 * @param int $rate_id Rate id.
	 */
	public function set_rate( $rate_id ) {
		$rate = StripeTaxTaxRateMemRepo::read( $rate_id );

		if ( ! $rate ) {
			return;
		}

		$this->set_rate_properties( $rate );
	}

	/**
	 * Sets tax rate properties given its rate id
	 *
	 * @param int $rate_id Rate id.
	 */
	public function set_rate_id( $rate_id ) {
		$rate = StripeTaxTaxRateMemRepo::read( $rate_id );

		if ( $rate ) {
			$this->set_rate_properties( $rate );
		} else {
			parent::set_rate_id( $rate_id );
		}
	}

	/**
	 * Sets tax rate properties given its code
	 *
	 * @param string $rate_code Rate id.
	 */
	public function set_rate_code( $rate_code ) {
		$rate = StripeTaxTaxRateMemRepo::read_by_code( $rate_code );

		if ( $rate ) {
			$this->set_rate_properties( $rate );
		} else {
			parent::set_rate_code( $rate_code );
		}
	}

	/**
	 * Avoid explicit rate label setting
	 *
	 * @param int $rate_id Rate id.
	 */
	public function set_rate_label( $rate_id ) {
	}

	/**
	 * Returns tax rate id.
	 *
	 * @param string $context The context.
	 */
	public function get_rate_id( $context = 'view' ) {
		$rate_code = $this->data['rate_code'];

		if ( strpos( $rate_code, 'stripe_tax_for_woocommerce' ) !== false ) {
			return $rate_code;
		}

		return parent::get_rate_id( $context );
	}
}
