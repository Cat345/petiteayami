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
			'compound'     => ! empty( $tax_rate['compound'] ),
		);

		$props   = array( 'rate_id', 'label', 'rate_code', 'rate_percent', 'compound' );
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
		$this->set_prop( 'compound', $rate['compound'] );

		$this->save();

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
	 * Returns the Stripe virtual rate associated with this order tax item.
	 *
	 * WooCommerce 11+ may call direct setters (set_rate_percent/set_compound)
	 * using DB tax-rate rows; virtual Stripe rates must be resolved from memory.
	 */
	protected function get_stripe_virtual_rate() {
		$rate_id = parent::get_rate_id( 'edit' );

		if ( $rate_id ) {
			$rate = StripeTaxTaxRateMemRepo::read( $rate_id );

			if ( $rate ) {
				return $rate;
			}
		}

		$rate_code = $this->get_prop( 'rate_code', 'edit' );

		if ( ! $rate_code ) {
			return null;
		}

		return StripeTaxTaxRateMemRepo::read_by_code( $rate_code );
	}

	/**
	 * Keeps Stripe virtual rate percent when WooCommerce sets rate percent directly.
	 *
	 * @param mixed $rate_percent Tax rate percent.
	 */
	public function set_rate_percent( $rate_percent ) {
		$stripe_rate = $this->get_stripe_virtual_rate();

		if ( $stripe_rate ) {
			parent::set_rate_percent( $stripe_rate['rate'] );
			return;
		}

		parent::set_rate_percent( $rate_percent );
	}

	/**
	 * Keeps Stripe virtual rate compound flag when WooCommerce sets it directly.
	 *
	 * @param mixed $compound Tax compound flag.
	 */
	public function set_compound( $compound ) {
		$stripe_rate = $this->get_stripe_virtual_rate();

		if ( $stripe_rate ) {
			parent::set_compound( ! empty( $stripe_rate['compound'] ) );
			return;
		}

		parent::set_compound( $compound );
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
