<?php
/**
 * Order tax calculation hook handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\StripeCalculationTracker;
use Stripe\StripeTaxForWooCommerce\WordPress\Hook_Handlers;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Order_Controller;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeOrderItemTax;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\InvalidRequestException;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Cart_Input;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Order_Input;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTaxTaxRateMemRepo;
use Stripe\StripeTaxForWooCommerce\StripeTax_Options;

use Throwable;

use WC_Order_Factory;
use WC_Order_Item_Fee;


/**
 * Class for handling hooks used in order tax calculations
 */
abstract class Admin_Order_Tax_Calculation extends Hook_Handlers {
	const ACTIONS = array(
		'new_order_item',
		'order_applied_coupon',
		// 'order_recalculate_coupons_coupon_object',
		'order_before_calculate_taxes',
		'new_order',
		'before_save_order_items',
		'saved_order_items',
		'show_tax_configuration_notice',
	);
	const FILTERS = array();

	const ACTIVATION_OPTIONS = array();

	/**
	 * Temporarly stores tax rate labels.
	 *
	 * @var array Tax rate labels.
	 */
	private static $tax_rate_labels = array();
	/**
	 * Temporarily stores IDs of orders with recalculated coupons.
	 *
	 * @var int[] Order IDs.
	 */
	private static $recalculated_coupon_orders = array();

	/**
	 * Stores ids of order items that needs price inclusive tax meta reset.
	 *
	 * @var array Order item ids.
	 */
	private static $price_tax_inclusive_meta_value_changes = array();

	/**
	 * New created order ids
	 *
	 * @var array New created order ids.
	 */
	private static $new_order_id = array();

	/**
	 * Checks whether admin order tax calculation handlers should be enabled.
	 *
	 * @return bool True when the base handlers are enabled and the current request is in admin.
	 */
	public static function is_enabled() {
		return parent::is_enabled() && is_admin();
	}

	/**
	 * "woocommerce_order_applied_coupon" hook handler
	 *
	 * @param mixed  $args Hook arg.
	 * @param object $order The order.
	 */
	public static function order_applied_coupon( $args, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		if ( $order->get_prices_include_tax() ) {
			foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
				Order_Controller::store_totals_tax_inclusive( $item );
			}
			Order_Input::reset_item_taxes_to_totals( $order );
		}
	}

	/**
	 * "woocommerce_order_recalculate_coupons_coupon_object" hook handler
	 *
	 * @param object $coupon_object Coupon details object.
	 * @param string $coupon_code Coupon code.
	 * @param object $coupon_item Coupon order item.
	 * @param object $order The order.
	 */
	public static function order_recalculate_coupons_coupon_object( $coupon_object, $coupon_code, $coupon_item, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		if ( $order->get_prices_include_tax() ) {
			Order_Input::reset_item_taxes_to_totals( $order );

			$order_id = $order->get_id();

			self::$recalculated_coupon_orders[ $order_id ] = $order_id;
		}

		return $coupon_object;
	}

	/**
	 * 'woocommerce_new_order_item' hook.
	 *
	 * @param int    $item_id Order item id.
	 * @param object $item The order id.
	 */
	public static function new_order_item( $item_id, $item ) {
		if ( ! static::is_enabled() ) {
			return;
		}
		$order_id = $item->get_order_id();

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order_status = $order->get_status();

		if ( 'auto-draft' === $order_status ) {
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->save();
		}

		$prices_include_tax = $order->get_prices_include_tax();

		if ( $prices_include_tax ) {
			$item_type = $item->get_type();
			$reference = Order_Input::build_item_reference_by_type( $item );

			$item->add_meta_data( Order_Input::CART_ITEM_REFERENCE_META_NAME, $reference, true );

			$tax_behavior = StripeTax_Options::item_allow_price_tax_inclusive( $item_type )
				? Order_Input::TAX_BEHAVIOR_INCLUSIVE
				: Order_Input::TAX_BEHAVIOR_EXCLUSIVE;

			$item->update_meta_data( '__stripe_tax_behavior', $tax_behavior );

			if ( Order_Input::TAX_BEHAVIOR_INCLUSIVE !== $tax_behavior ) {
				return;
			}

			Order_Controller::store_totals_tax_inclusive( $item );
			$item->save();
		}
	}

	/**
	 * "woocommerce_order_before_calculate_taxes" hook handler
	 *
	 * @param array  $args WC args.
	 * @param object $order The order.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function order_before_calculate_taxes( $args, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		try {
			$tax_location     = null;
			$customer_user_id = null;

			if ( is_admin() ) {
				if ( ( ! $args || isset( $_REQUEST['stripe_tax_for_woocommerce_customer_user'] ) ) && isset( $_REQUEST['security'] ) ) {
					$nonce_ok        = false;
					$nonce           = sanitize_text_field( wp_unslash( $_REQUEST['security'] ) );
					$allowed_actions = array( 'order-item', 'calc-totals' );

					foreach ( $allowed_actions as $allowed_action ) {
						if ( ! wp_verify_nonce( $nonce, $allowed_action ) ) {
							continue;
						}

						$nonce_ok = true;
						break;
					}

					if ( $nonce_ok && isset( $_POST['country'], $_POST['state'], $_POST['postcode'], $_POST['city'] ) ) {
						$shipping_address = map_deep( wp_unslash( $_POST['stripe_tax_for_woocommerce_customer_details_shipping_address'] ?? array() ), 'sanitize_text_field' );
						$billing_address  = map_deep( wp_unslash( $_POST['stripe_tax_for_woocommerce_customer_details_billing_address'] ?? array() ), 'sanitize_text_field' );

						$line1 = trim( (string) ( $shipping_address['line1'] ?? '' ) );
						if ( '' === $line1 ) {
							$line1 = trim( (string) ( $billing_address['line1'] ?? '' ) );
						}

						$line2 = trim( (string) ( $shipping_address['line2'] ?? '' ) );
						if ( '' === $line2 ) {
							$line2 = trim( (string) ( $billing_address['line2'] ?? '' ) );
						}

						$tax_location = array(
							'country'  => sanitize_text_field( wp_unslash( $_POST['country'] ) ),
							'state'    => sanitize_text_field( wp_unslash( $_POST['state'] ) ),
							'postcode' => sanitize_text_field( wp_unslash( $_POST['postcode'] ) ),
							'city'     => sanitize_text_field( wp_unslash( $_POST['city'] ) ),
							'line1'    => $line1,
							'line2'    => $line2,
						);

						if ( isset( $_POST['line1'] ) ) {
							$tax_location['line1'] = sanitize_text_field( wp_unslash( $_POST['line1'] ) );
						}

						if ( isset( $_POST['line2'] ) ) {
							$tax_location['line2'] = sanitize_text_field( wp_unslash( $_POST['line2'] ) );
						}
					}

					if ( isset( $_REQUEST['stripe_tax_for_woocommerce_customer_user'] ) ) {
						$customer_user_id = sanitize_text_field( wp_unslash( $_REQUEST['stripe_tax_for_woocommerce_customer_user'] ) );

						if ( 'guest' === $customer_user_id ) {
							$customer_user_id = null;
						}
					}
				} elseif ( $args ) {
					$tax_location = $args;
				}
			}

			if ( $order->get_prices_include_tax() ) {
				foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
					Order_Controller::store_totals_tax_inclusive( $item );
				}
			}

			Order_Controller::calculate_taxes( $order, $tax_location, $customer_user_id );
		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
			self::set_current_request_failed( true );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
			self::set_current_request_failed( true );
		}
	}

	/**
	 * 'woocommerce_order_before_calculate_totals' hook handler.
	 *
	 * @param bool   $and_taxes Hook arg.
	 * @param object $order The order.
	 */
	public static function order_before_calculate_totals( $and_taxes, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}
	}

	/**
	 * 'woocommerce_order_after_calculate_totals' hook handler.
	 *
	 * @param bool   $and_taxes Hook arg.
	 * @param object $order The order.
	 */
	public static function order_after_calculate_totals( $and_taxes, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		Order_Controller::sync_order_totals( $order );
	}

	/**
	 * "woocommerce_get_order_item_classname" hook handler
	 *
	 * @param string $class_name Default class name.
	 * @param string $item_type Order item type.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function get_order_item_classname( $class_name, $item_type ) {
		if ( ! static::is_enabled() ) {
			return $class_name;
		}

		if ( 'tax' === $item_type ) {
			$class_name = StripeOrderItemTax::class;
		}

		return $class_name;
	}

	/**
	 * "woocommerce_new_order" hook handler
	 *
	 * @param int $order_id New order id.
	 */
	public static function new_order( $order_id ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		if ( ! isset( self::$new_order_id[ $order_id ] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$order->set_prices_include_tax( true );
		$order->save();
	}

	/**
	 * "woocommerce_update_order" hook handler
	 *
	 * @param int    $order_id Order id.
	 * @param object $order The order.
	 */
	public static function update_order( $order_id, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		if ( 'yes' !== get_option( 'woocommerce_prices_include_tax' ) ) {
			return;
		}

		if ( $order->get_prices_include_tax() ) {
			return;
		}

		self::$new_order_id[ $order_id ] = $order_id;
	}

	/**
	 * 'woocommerce_before_save_order_items' hook.
	 *
	 * @param object $order_id Order id.
	 * @param array  $items Order new items.
	 */
	public static function before_save_order_items( $order_id, $items ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		$order        = wc_get_order( $order_id );
		$order_status = $order->get_status();

		if ( 'auto-draft' === $order_status ) {
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->save();
		}
		$tax_items = $order->get_taxes();

		foreach ( $tax_items as $tax_item ) {
			$rate_id    = $tax_item->get_rate_id();
			$rate_label = $tax_item->get_label();

			self::$tax_rate_labels[ $rate_id ] = $rate_label;
		}

		add_filter(
			'woocommerce_rate_label',
			array( static::class, 'woocommerce_rate_label' ),
			10,
			2
		);

		if ( ! $order->get_prices_include_tax() ) {
			return;
		}

		foreach ( $items['order_item_id'] as $item_id ) {
			$order_item = WC_Order_Factory::get_order_item( absint( $item_id ) );

			$stripe_tax_price_inclusive_tax = $order_item->get_meta( '_stripe_tax_checkout_total_tax_inclusive' );

			if ( '' === $stripe_tax_price_inclusive_tax ) {
				continue;
			}

			$item_total = (float) wc_format_decimal( $items['line_total'][ $item_id ] );
			// @phpstan-ignore-next-line
			$order_item_total = (float) $order_item->get_total();
			if ( $order_item instanceof WC_Order_Item_Fee ) {
				if ( abs( $order_item_total - $item_total ) < 0.05 ) {
					continue;
				}
			} elseif ( abs( $order_item_total - $item_total ) < 0.05 ) {
					continue;
			}

			self::$price_tax_inclusive_meta_value_changes[ $item_id ] = $item_id;
		}

		foreach ( $items['shipping_method_id'] as $item_id ) {
			$order_item = WC_Order_Factory::get_order_item( absint( $item_id ) );

			$stripe_tax_price_inclusive_tax = $order_item->get_meta( '_stripe_tax_checkout_total_tax_inclusive' );

			if ( '' === $stripe_tax_price_inclusive_tax ) {
				continue;
			}

			if ( ! method_exists( $order_item, 'get_total' ) ) {
				continue;
			}

			$order_item_total = $order_item->get_total();

			if ( '' === $order_item_total ) {
				continue;
			}

			$item_total = wc_format_decimal( (float) $items['shipping_cost'][ $item_id ] );

			if ( abs( $order_item_total - $item_total ) < 0.05 ) {
				continue;
			}
			self::$price_tax_inclusive_meta_value_changes[ $item_id ] = $item_id;
		}
	}

	/**
	 * 'woocommerce_saved_order_items' hook.
	 *
	 * @param object $order_id Order id.
	 * @param array  $items Order new items.
	 */
	public static function saved_order_items( $order_id, $items ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		foreach ( self::$price_tax_inclusive_meta_value_changes as $item_id => $item_key ) {
			$order_item = WC_Order_Factory::get_order_item( absint( $item_id ) );

			$order_item->update_meta_data( '__stripe_tax_behavior', 'exclusive' );

			$order_item->save();
		}
	}

	/**
	 * 'woocommerce_rate_label' hook.
	 *
	 * @param string $rate_label Default tax rate label.
	 * @param int    $rate_id Rate id.
	 */
	public static function woocommerce_rate_label( $rate_label, $rate_id ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		$rate_label = StripeTaxTaxRateMemRepo::read_rate_label( $rate_id );

		if ( $rate_label ) {
			return $rate_label;
		}

		if ( isset( self::$tax_rate_labels[ $rate_id ] ) ) {
			return self::$tax_rate_labels[ $rate_id ];
		}

		return $rate_label;
	}

	/**
	 * 'woocommerce_show_tax_configuration_notice' hook.
	 * Disables base loation stardard tax rate not set message.
	 */
	public static function show_tax_configuration_notice() {
		return false;
	}
}
