<?php
defined( 'ABSPATH' ) || exit;
/**
 * Author Woofunnels.
 */

use Sublium_WCS\Compatibilities\Compatibility;
use Sublium_WCS\Includes\Controller\Subscriptions\Subscription;
use Sublium_WCS\Includes\Helpers\Dates;
use Sublium_WCS\Includes\Helpers\Subscription as SubscriptionHelper;

if ( ! class_exists( 'UpStroke_Sublium_Subscriptions' ) ) {
	#[\AllowDynamicProperties]
	class UpStroke_Sublium_Subscriptions {

		public static $instance = null;

		public function __construct() {
			/**
			 * Create New subscriptions when offer is accepted
			 */
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'maybe_create_new_subscriptions' ), 1, 5 );
			add_action( 'wfocu_offer_new_order_created_before_complete', array( $this, 'maybe_create_new_subscriptions_on_new_order' ), 1, 1 );
			add_action( 'wfocu_offer_payment_failed_event', array( $this, 'create_pending_subscription' ), 10, 1 );
			add_filter( 'sublium_wcs_plan_data', array( $this, 'append_price_html' ), 10, 3 );
			add_filter( 'wfocu_add_product_to_order_item_meta', array( $this, 'add_plan_id_to_order_item_meta' ), 10, 3 );
			// Activate upsell subscriptions that remain PENDING after CREATE_ORDER payment flow.
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'maybe_activate_pending_upsell_subscriptions' ), 50, 5 );
			// Activate any bump/checkout subscriptions that Sublium skipped (it only activates the first one per order).
			add_action( 'woocommerce_order_status_changed', array( $this, 'activate_remaining_pending_subscriptions' ), 20, 4 );
		}
		public function append_price_html( $plan_data, $plan, $product ) {

			if ( $plan->get_type() === 1 ) {
				$rg_price = $product->get_regular_price();

				if ( \Sublium_WCS\Includes\Main\Product::is_variable_product_types( $product ) ) {
					/** @var \WC_Product_Variable $product */
					$rg_price = $product->get_variation_regular_price( 'max' );
				}
				$rg_price = \Sublium_WCS\Includes\Main\Product::get_instance()->get_price_based_on_tax( $rg_price, $product );

				$rg_price = apply_filters( 'sublium_wcs_plan_regular_price', $rg_price, $product, $this );
				$price    = $plan->get_recurring_cart_price( $product->get_price(), $product );
				$price    = \Sublium_WCS\Includes\Main\Product::get_instance()->get_price_based_on_tax( $price, $product );
			} elseif ( $plan->get_type() === 2 ) {

				if ( empty( $plan->product_plan_map_data ) ) {
					return $plan_data;
				}
				$rg_price = $plan->product_plan_map_data['regular_price'];
				$price    = $plan->get_recurring_cart_price( $product->get_price(), $product );
				if ( empty( $price ) ) {
					$price = $rg_price;
				}

				$signup_fee = $plan->get_signup_fee( $product );
				if ( $signup_fee > 0 ) {
					$price += $signup_fee;
				}

				$rg_price = \Sublium_WCS\Includes\Main\Product::get_instance()->get_price_based_on_tax( $rg_price, $product );
				$rg_price = apply_filters( 'sublium_wcs_plan_regular_price', $rg_price, $product, $this );
				$price    = \Sublium_WCS\Includes\Main\Product::get_instance()->get_price_based_on_tax( $price, $product );

			} elseif ( $plan->get_type() === 3 ) {
				/**
				 * display the recurring price with tax applied need to check if tax is enabled
				 */
				$rg_price = $product->get_regular_price();
				if ( \Sublium_WCS\Includes\Main\Product::is_variable_product_types( $product ) ) {
					/** @var \WC_Product_Variable $product */
					$rg_price = $product->get_variation_regular_price( 'max' );
				}
				$rg_price = \Sublium_WCS\Includes\Main\Product::get_instance()->get_price_based_on_tax( $rg_price, $product );
				$rg_price = apply_filters( 'sublium_wcs_plan_regular_price', $rg_price, $product, $this );

				$recurring_price = $plan->get_recurring_cart_price( $product->get_price(), $product );
				$tax_result      = \Sublium_WCS\Includes\Helpers\Utility::apply_tax_to_price( $product, $recurring_price, 1 );
				$price           = $tax_result['price'];
			}

			// Apply currency conversion for multi-currency compatibility
			if ( class_exists( '\Sublium_WCS\Compatibilities\Compatibility' ) ) {
				$rg_price = Compatibility::get_fixed_currency_price( $rg_price );
				$price    = Compatibility::get_fixed_currency_price( $price );
			}

			ob_start();
			?>
		<div class="wfocu-price-wrapper">
			<div class="wfocu-product-price wfocu-product-on-sale" bis_skin_checked="1">
			<span class="wfocu-regular-price"><?php echo wp_kses_post( wc_price( $rg_price ) ); ?></span>
			<?php if ( $price < $rg_price ) : ?>
				<span class="wfocu-sale-price"><?php echo wp_kses_post( wc_price( $price ) ); ?></span>
			<?php endif; ?>
			</div>
		</div>
			<?php
			$plan_data['discounted_upsell_price_html'] = ob_get_clean();
			return $plan_data;
		}


		/**
		 * Creates and instance of the class
		 *
		 * @return UpStroke_Sublium_Subscriptions
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function get_plan_id( $data ) {
			if ( is_array( $data ) && isset( $data['args']['variation']['_sublium_data'] ) ) {
				$sublium_data = stripslashes( $data['args']['variation']['_sublium_data'] );
				$sublium_data = json_decode( $sublium_data, true );

				return absint( $sublium_data['plan_id'] );
			}

			return false;
		}

		public function get_plan_sublium_data( $data ) {
			if ( is_array( $data ) && isset( $data['args']['variation']['_sublium_data'] ) ) {
				$sublium_data = stripslashes( $data['args']['variation']['_sublium_data'] );
				$sublium_data = json_decode( $sublium_data, true );

				return $sublium_data['data'];
			}

			return array();
		}


		/**
		 * Create New Subscriptions with the data provided
		 *
		 * @param $get_offer_id
		 * @param $get_package
		 * @param $get_parent_order
		 * @param $new_order
		 * @param $get_transaction_id
		 */
		public function maybe_create_new_subscriptions( $get_offer_id, $get_package, $get_parent_order, $new_order, $get_transaction_id ) {

			try {
				/**
				 *
				 * Creation of a new order
				 */
				if ( $new_order instanceof WC_Order && did_action( 'wfocu_offer_new_order_created_before_complete' ) ) {
					return;
				}

				if ( $new_order instanceof WC_Order ) {
					$subscription_order = $new_order;
				} else {
					$subscription_order = $get_parent_order;
				}

				$this->create_sublium_subscriptions( $get_package, $subscription_order, Subscription::STATUSES['ACTIVE'] );

			} catch ( Error | Exception $error ) {

			}
		}

		public function maybe_create_new_subscriptions_on_new_order( $new_order ) {

			try {
				$subscription_order = $new_order;
				$get_package        = WFOCU_Core()->data->get( '_upsell_package' );
				$this->create_sublium_subscriptions( $get_package, $subscription_order, Subscription::STATUSES['PENDING'] );
			} catch ( Error | Exception $error ) {

			}
		}

		/**
		 * After all upsell processing completes, activate any upsell subscriptions still PENDING.
		 *
		 * CREATE_ORDER mode: activates PENDING subscriptions on the child WC order.
		 * BATCHING mode: activates subscriptions added to the parent order AFTER checkout
		 *   (i.e. IDs present in _sublium_wcs_subscription_id but NOT in _sublium_wcs_parent_order).
		 */
		public function maybe_activate_pending_upsell_subscriptions( $get_offer_id, $get_package, $get_parent_order, $new_order, $get_transaction_id ) {
			try {
				if ( $new_order instanceof WC_Order ) {
					// CREATE_ORDER mode — subscriptions live on the child order.
					$order_to_check       = $new_order;
					$checkout_sub_ids     = array(); // All IDs on child order are upsell subs.
				} elseif ( $get_parent_order instanceof WC_Order ) {
					// BATCHING mode — upsell subs were added to the parent order after checkout.
					$order_to_check   = $get_parent_order;
					// _sublium_wcs_parent_order holds the subs created at checkout time.
					$parent_order_raw = $order_to_check->get_meta( '_sublium_wcs_parent_order' );
					$checkout_sub_ids = ! empty( $parent_order_raw )
						? array_map( 'absint', (array) json_decode( $parent_order_raw, true ) )
						: array();
				} else {
					return;
				}

				// Collect every _sublium_wcs_subscription_id on the order.
				$pending_subs = array();
				foreach ( $order_to_check->get_meta_data() as $meta ) {
					if ( '_sublium_wcs_subscription_id' !== $meta->key ) {
						continue;
					}
					$sub_id = absint( $meta->value );
					if ( ! $sub_id ) {
						continue;
					}
					// In BATCHING mode skip subs that were created at checkout (not upsell subs).
					if ( in_array( $sub_id, $checkout_sub_ids, true ) ) {
						continue;
					}
					$subscription = sublium_get_subscription( $sub_id );
					if ( $subscription && Subscription::STATUSES['PENDING'] === $subscription->get_status() ) {
						$pending_subs[] = array( 'id' => $sub_id );
					}
				}

				if ( empty( $pending_subs ) ) {
					return;
				}

				\Sublium_WCS\Includes\Main\Subscriptions::get_instance()->payment_complete( $pending_subs, $order_to_check );
			} catch ( Error|Exception $error ) {
				// Fail silently — subscription remains PENDING rather than crashing the upsell flow.
			}
		}

		/**
		 * After Sublium activates the first subscription on a checkout order (at priority 10),
		 * activate any remaining subscriptions that were skipped because Sublium reads only the
		 * first _sublium_wcs_subscription_id meta value when multiple subscriptions exist.
		 * This handles bump subscriptions that share the checkout order with the primary plan.
		 */
		public function activate_remaining_pending_subscriptions( $order_id, $old_order_status, $new_order_status, $order ) {
			try {
				if ( ! $order instanceof WC_Order ) {
					$order = wc_get_order( $order_id );
					if ( ! $order instanceof WC_Order ) {
						return;
					}
				}

				// WFOCU child orders (upsells) are handled by maybe_activate_pending_upsell_subscriptions.
				if ( $order->get_parent_id() > 0 ) {
					return;
				}

				// Only trigger when payment transitions to a complete status.
				$complete_statuses  = array( 'processing', 'completed' );
				$valid_old_statuses = array( 'pending', 'on-hold', 'failed' );
				if ( ! in_array( $new_order_status, $complete_statuses, true ) || ! in_array( $old_order_status, $valid_old_statuses, true ) ) {
					return;
				}

				// _sublium_wcs_parent_order holds all subscription IDs created for this order as JSON.
				$parent_order_meta = $order->get_meta( '_sublium_wcs_parent_order' );
				if ( empty( $parent_order_meta ) ) {
					return;
				}
				$all_sub_ids = json_decode( $parent_order_meta, true );
				if ( ! is_array( $all_sub_ids ) || count( $all_sub_ids ) <= 1 ) {
					return; // Only one subscription — Sublium already handles it.
				}

				// Sublium reads the first _sublium_wcs_subscription_id value and activates that one.
				$first_sub_id = absint( $order->get_meta( '_sublium_wcs_subscription_id' ) );

				$pending_subs = array();
				foreach ( $all_sub_ids as $sub_id ) {
					$sub_id = absint( $sub_id );
					if ( ! $sub_id || $sub_id === $first_sub_id ) {
						continue; // Skip the first one that Sublium already activated.
					}
					$subscription = sublium_get_subscription( $sub_id );
					if ( $subscription && Subscription::STATUSES['PENDING'] === $subscription->get_status() ) {
						$pending_subs[] = array( 'id' => $sub_id );
					}
				}

				if ( empty( $pending_subs ) ) {
					return;
				}

				\Sublium_WCS\Includes\Main\Subscriptions::get_instance()->payment_complete( $pending_subs, $order );
			} catch ( Error|Exception $error ) {
				// Fail silently.
			}
		}

		public function create_sublium_subscriptions( $get_package, $subscription_order, $new_status = null ) {
			if ( null === $new_status ) {
				$new_status = Subscription::STATUSES['PENDING'];
			}
			try {
				/**
				 * @var $subscription_order WC_Order;
				 */
				// Check if this is a new order created for upsell (has parent_id > 0)
				$is_new_upsell_order = $subscription_order->get_parent_id() > 0;

				// Prepare offers data - one subscription per offer/product
				$offers_data = array();
				foreach ( $get_package['products'] as $offer_data ) {
					$get_product = $offer_data['data'];
					if ( ! $get_product instanceof WC_Product ) {
						continue;
					}
					$plan_id = $this->get_plan_id( $offer_data );
					if ( false === $plan_id ) {
						continue;
					}
					$offers_data[] = array(
						'offer_data' => $offer_data,
						'plan_id'    => $plan_id,
						'product'    => $get_product,
					);
				}

				if ( empty( $offers_data ) ) {
					return;
				}

				if ( $is_new_upsell_order ) {
					// For new upsell orders: Create a separate subscription for every offer accepted
					$created_subscription_ids = array();

					foreach ( $offers_data as $offer_info ) {
						$offer_data = $offer_info['offer_data'];
						$plan_id    = $offer_info['plan_id'];
						$product    = $offer_info['product'];

						// Prepare plan data for single offer
						$plan_data                      = array( $offer_data );
						$plan_data['_sublium_wcs_plan'] = $plan_id;

						$subscription_items           = $this->prepare_subscription_items( $plan_data, $subscription_order );
						$subscription_data            = $this->prepare_subscription_data( $subscription_order, $plan_data, $subscription_items );
						$subscription_data['plan_id'] = $plan_id;

						$subscription = Subscription::create( $subscription_data );
						if ( $subscription instanceof Subscription && $subscription->get_id() > 0 ) {
							foreach ( $subscription_items['line_items'] as $subscription_item ) {
								// Add plan_id at top level for renewal order creation
								if ( ! isset( $subscription_item['plan_id'] ) && isset( $plan_id ) ) {
									$subscription_item['plan_id'] = $plan_id;
								}
								$subscription->add_item( $subscription_item );
							}
							// Update subscription item IDs after adding all items
							$item_ids = $this->collect_item_ids_from_subscription_items( $subscription_items['line_items'] );
							if ( ! empty( $item_ids ) ) {
								$subscription->update_items( $item_ids );
							}
							$subscription->update_status( $new_status );
							SubscriptionHelper::create_activity(
								$subscription->get_id(),
								array(
									'object_type' => SubscriptionHelper::ACTIVITY_OBJECT_TYPE['SUBSCRIPTION'],
									'action'      => SubscriptionHelper::ACTIVITY_ACTION['CREATED'],
									'new_value'   => 0,
									'old_value'   => 0,
									'user_type'   => SubscriptionHelper::ACTIVITY_USER_TYPES['SYSTEM'],
								),
								sprintf( __( 'Sublium Subscription created using upsell for Order - %d', 'sublium' ), $subscription_order->get_id() )
							);
							$this->maybe_copy_payment_token_to_subscription( $subscription, $subscription_order );
							$subscription->save();
							$modifier = new Sublium_WCS\Includes\Controller\Subscriptions\Subscriptionmodifier( $subscription->get_id() );
							$modifier->recalculate_totals();

							// Track subscription ID to add to order meta
							$created_subscription_ids[] = $subscription->get_id();
						}
					}

					// Link all created subscriptions to the newly created order
					if ( ! empty( $created_subscription_ids ) ) {
						foreach ( $created_subscription_ids as $subscription_id ) {
							$subscription_order->add_meta_data( '_sublium_wcs_subscription_id', $subscription_id );
						}
						$subscription_order->save();
					}
				} else {
					// For parent order (batching mode): Always create new subscription for every offer
					// Never add upsell items to existing subscriptions
					$created_subscription_ids = array();

					foreach ( $offers_data as $offer_info ) {
						$offer_data = $offer_info['offer_data'];
						$plan_id    = $offer_info['plan_id'];
						$product    = $offer_info['product'];

						// Prepare plan data for single offer
						$plan_data                      = array( $offer_data );
						$plan_data['_sublium_wcs_plan'] = $plan_id;

						$subscription_items           = $this->prepare_subscription_items( $plan_data, $subscription_order );
						$subscription_data            = $this->prepare_subscription_data( $subscription_order, $plan_data, $subscription_items );
						$subscription_data['plan_id'] = $plan_id;

						$subscription = Subscription::create( $subscription_data );
						if ( $subscription instanceof Subscription && $subscription->get_id() > 0 ) {
							foreach ( $subscription_items['line_items'] as $subscription_item ) {
								// Add plan_id at top level for renewal order creation
								if ( ! isset( $subscription_item['plan_id'] ) && isset( $plan_id ) ) {
									$subscription_item['plan_id'] = $plan_id;
								}
								$subscription->add_item( $subscription_item );
							}
							// Update subscription item IDs after adding all items
							$item_ids = $this->collect_item_ids_from_subscription_items( $subscription_items['line_items'] );
							if ( ! empty( $item_ids ) ) {
								$subscription->update_items( $item_ids );
							}
							$subscription->update_status( $new_status );
							SubscriptionHelper::create_activity(
								$subscription->get_id(),
								array(
									'object_type' => SubscriptionHelper::ACTIVITY_OBJECT_TYPE['SUBSCRIPTION'],
									'action'      => SubscriptionHelper::ACTIVITY_ACTION['CREATED'],
									'new_value'   => 0,
									'old_value'   => 0,
									'user_type'   => SubscriptionHelper::ACTIVITY_USER_TYPES['SYSTEM'],
								),
								sprintf( __( 'Sublium Subscription created using upsell for Order - %d', 'sublium' ), $subscription_order->get_id() )
							);
							$this->maybe_copy_payment_token_to_subscription( $subscription, $subscription_order );
							$subscription->save();
							$modifier = new Sublium_WCS\Includes\Controller\Subscriptions\Subscriptionmodifier( $subscription->get_id() );
							$modifier->recalculate_totals();

							// Track subscription ID to add to order meta
							$created_subscription_ids[] = $subscription->get_id();
						}
					}

					// Link all created subscriptions to the order
					if ( ! empty( $created_subscription_ids ) ) {
						foreach ( $created_subscription_ids as $subscription_id ) {
							$subscription_order->add_meta_data( '_sublium_wcs_subscription_id', $subscription_id );
						}
						$subscription_order->save();
					}
				}
			} catch ( Error | Exception $error ) {
			}
		}

		/**
		 * Copy the payment gateway token (e.g. _fkwcs_source_id) from the order used to charge
		 * this subscription onto the subscription itself, mirroring what
		 * Sublium_WCS\Includes\Main\Subscriptions::payment_complete() does for checkout-created
		 * subscriptions. Without this, subscriptions created here (upsell offers, and bumps added
		 * during checkout batching) never get their token copied, so has_active_payment_method()
		 * reports no active payment method even though the order was charged successfully.
		 *
		 * @param Subscription $subscription
		 * @param \WC_Order    $order
		 */
		private function maybe_copy_payment_token_to_subscription( $subscription, $order ) {
			try {
				$gateway = \Sublium_WCS\Includes\Main\Gateways::get_instance()->get_gateway( $order->get_payment_method() );
				if ( $gateway instanceof \Sublium_WCS\Includes\Abstracts\Payment_Gateway ) {
					$gateway->maybe_update_meta( $subscription, $order );
				}
			} catch ( Error | Exception $error ) {
			}
		}

		public function find_tax_and_apply() {
		}

		/**
		 * Collect item IDs (product IDs and variation IDs) from subscription items
		 *
		 * @param array $subscription_items Array of subscription items with item_type and item_data
		 * @return array Array of unique product IDs and variation IDs as strings
		 */
		private function collect_item_ids_from_subscription_items( $subscription_items ) {
			$item_ids = array();
			foreach ( $subscription_items as $item ) {
				if ( ! isset( $item['item_type'] ) || ! isset( $item['item_data'] ) ) {
					continue;
				}
				// Only collect IDs for product items (item_type === 1)
				if ( (int) $item['item_type'] === 1 && isset( $item['item_data']['product_id'] ) ) {
					$values = array( (string) $item['item_data']['product_id'] );
					if ( ! empty( $item['item_data']['variation_id'] ) ) {
						$values[] = (string) $item['item_data']['variation_id'];
					}
					$item_ids = array_merge( $item_ids, array_filter( $values ) );
				}
			}
			// Remove duplicates to ensure unique product/variation IDs only
			// This prevents duplicate products from appearing in the admin listing
			return array_values( array_unique( $item_ids ) );
		}

		/**
		 * Prepare subscription items with down payment & item IDs
		 *
		 * @param object    $recurring_cart
		 * @param \WC_Order $order
		 *
		 * @return array
		 */
		public function prepare_subscription_items( $plans_data, $order = null ) {
			$items          = array();
			$item_ids       = array();
			$address        = $order->get_address();
			$total          = 0;
			$needs_shipping = false;
			foreach ( $plans_data as $offer_data ) {
				if ( ! is_array( $offer_data ) ) {
					continue;
				}
				$product = $offer_data['data'];
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}
				if ( $product->needs_shipping() ) {
					$needs_shipping = true;
				}
				/**
				 * We are creating simple item object here just so that we could run the action to get all metadata
				 */
				$item = new \WC_Order_Item_Product();
				$item->set_props(
					array(
						'quantity'  => ( is_array( $offer_data ) && isset( $offer_data['qty'] ) && (int) $offer_data['qty'] > 1 ) ? $offer_data['qty'] : 1,
						'variation' => array(),
					)
				);
				if ( $product ) {
					$item->set_props(
						array(
							'name'         => $product->get_name(),
							'tax_class'    => $product->get_tax_class(),
							'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
							'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
						)
					);
				}
				$address_data = array(
					'country'   => $address['country'],
					'state'     => $address['state'],
					'postcode'  => $address['postcode'],
					'city'      => $address['city'],
					'tax_class' => $product->get_tax_class(),
				);
				// The subscription line item represents the RECURRING charge (what renews),
				// not the upsell's initial charge ($offer_data['price'], which is $0 during a
				// free trial or just the signup fee). Sourcing it from $offer_data['price']
				// stored the trial/initial amount as the recurring price, so trial subscriptions
				// were created with a $0 (or signup-fee-only) recurring amount. Resolve the plan
				// and use its configured recurring price instead.
				$recurring_price = (float) $offer_data['price'];
				$plan_id_for_recurring = isset( $plans_data['_sublium_wcs_plan'] ) ? absint( $plans_data['_sublium_wcs_plan'] ) : absint( $this->get_plan_id( $offer_data ) );
				if ( $plan_id_for_recurring > 0 ) {
					$recurring_plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id_for_recurring, $product );
					if ( ! is_null( $recurring_plan ) ) {
						$recurring_price = (float) $recurring_plan->get_recurring_cart_price( $product->get_price(), $product );
					}
				}

				if ( wc_tax_enabled() ) {

					$sublium_data = $this->get_plan_sublium_data( $offer_data );

					$taxes = array();
					if ( ! empty( $sublium_data ) && isset( $sublium_data['sublium_tax_data'] ) ) {
						foreach ( $sublium_data['sublium_tax_data'] as $_tax ) {
							$taxes[ $_tax['item_data']['rate_id'] ] = $_tax['item_data']['amount'];
							$items[]                                = $_tax;
						}
					}
					// $recurring_price is tax-EXCLUSIVE (the net recurring price). Compute tax to
					// add on top so the item total (net) plus its tax matches the recurring charge,
					// instead of wrongly extracting an already-embedded tax from a net price.
					$tax_rates = WC_Tax::find_rates( $address_data );
					$taxes     = WC_Tax::calc_tax( $recurring_price, $tax_rates, false ); // add tax
					$item->set_subtotal( $recurring_price );
					$item->set_total( $recurring_price );
					$item->set_taxes(
						array(
							'total'    => $taxes,
							'subtotal' => $taxes,
						)
					);
					$total += $recurring_price + array_sum( $taxes );

				} else {
					$item->set_subtotal( $recurring_price );
					$item->set_total( $recurring_price );
					$total += $recurring_price;
				}
				$item->set_backorder_meta();
				$p_item     = $this->create_item_data( $product, $offer_data, $item );
				$item_ids[] = $item->get_product_id();
				$items[]    = $p_item;
			}
			$shippings = $order->get_items( 'shipping' );
			if ( $needs_shipping && count( $shippings ) > 0 ) {
				$first_shipping = current( $shippings );
				$s_item         = new WC_Order_Item_Shipping();
				$s_item->set_props(
					array(
						'method_title' => $first_shipping->get_method_title(),
						'method_id'    => $first_shipping->get_method_id(),
						'total'        => $first_shipping->get_total(),
						'instance_id'  => $first_shipping->get_instance_id(),
					)
				);
				$s_item->calculate_taxes( $address );
				$items[] = array(
					'item_type' => 2,
					'item_data' => array(
						'method_title' => $s_item->get_method_title(),
						'method_id'    => $s_item->get_method_id(),
						'total'        => $s_item->get_total(),
						'instance_id'  => $s_item->get_instance_id(),
						'tax'          => $s_item->get_taxes(),
					),
				);
				$total  += $first_shipping->get_total();

			}

			return array(
				'line_items'  => $items,
				'item_ids'    => $item_ids,
				'downpayment' => 0,
				'total'       => $total,
			);
		}

		/**
		 * Prepare subscription data
		 *
		 * @param \WC_Order $order
		 * @param object    $recurring_cart
		 * @param array     $subscription_items
		 *
		 * @return array
		 */
		public function prepare_subscription_data( $order, $plan_data, $subscription_items ) {
			$search_str = $this->build_search_str( $order );

			/** Added  base total to subscription data */
			$base_total = $subscription_items['total'];

				$base_total = Compatibility::get_fixed_currency_price_reverse( $base_total, $order->get_currency() );

			// get customer id from order
			$user_id = $order->get_customer_id();

			// Check if user not exists than create new
			if ( empty( $user_id ) ) {
				$user_id = \Sublium_WCS\Includes\Main\Checkout::get_instance()->create_user_subscription( $order );
			}

			// throw error if user not created
			if ( ( $user_id instanceof \WP_Error ) ) {
				throw new \Exception( $user_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			// Get product from plan_data or order items
			$product = null;
			if ( ! empty( $plan_data[0]['data'] ) && $plan_data[0]['data'] instanceof \WC_Product ) {
				$product = $plan_data[0]['data'];
			} elseif ( ! empty( $subscription_items['line_items'] ) ) {
				$first_item = reset( $subscription_items['line_items'] );
				if ( ! empty( $first_item['item_data']['product_id'] ) ) {
					$product = wc_get_product( $first_item['item_data']['product_id'] );
				}
			}

			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_data['_sublium_wcs_plan'], $product );

			return array(
				'parent_order_id'       => $order->get_id(),
				'gateway'               => $order->get_payment_method(),
				'user_id'               => $user_id,
				'currency'              => $order->get_currency(),
				'totals'                => $subscription_items['total'],
				'base_totals'           => $base_total,
				'next_payment_date_utc' => Dates::system_to_utc( $plan->get_next_payment_date() ),
				'gateway_mode'          => 1,
				'status'                => Subscription::STATUSES['PENDING'],
				'plan_type'             => $plan->get_type(),
				'next_payment_date'     => $plan->get_next_payment_date(),
				'last_payment_date'     => Dates::get_current_system_date(),
				'items'                 => $subscription_items['item_ids'],
				'search_str'            => $search_str,
				'end_date'              => $plan->get_end_date(),
				'end_date_utc'          => $plan->get_end_date( 'Y-m-d H:i:s', '', true ),
				'meta_data'             => $this->get_meta_data( $order, $plan_data, $subscription_items ),
			);
		}

		/**
		 * Get meta data for subscription
		 *
		 * @param \WC_Order $order
		 * @param object    $recurring_cart
		 *
		 * @return array
		 */
		public function get_meta_data( $order, $plan_data, $subscription_items ) {
			// Get product from plan_data or order items
			$product = null;
			if ( ! empty( $plan_data[0]['data'] ) && $plan_data[0]['data'] instanceof \WC_Product ) {
				$product = $plan_data[0]['data'];
			} elseif ( ! empty( $subscription_items['line_items'] ) ) {
				$first_item = reset( $subscription_items['line_items'] );
				if ( ! empty( $first_item['item_data']['product_id'] ) ) {
					$product = wc_get_product( $first_item['item_data']['product_id'] );
				}
			}

			$plan      = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_data['_sublium_wcs_plan'], $product );
			$data      = $plan->get_data();
			$meta_data = array(
				'billing_details'                 => $order->get_address(),
				'shipping_details'                => $order->get_address( 'shipping' ),
				'gateway_multiple_fields'         => '',
				'plan_data'                       => $plan->get_plan_data(),
				'downpayment'                     => $subscription_items['downpayment'],
				'billing_frequency'               => $plan->get_billing_frequency(),
				'billing_interval'                => $plan->get_billing_interval(),
				'billing_length'                  => $plan->get_billing_length(),
				'free_trial'                      => $plan->get_trial_days_left(),
				'subscription_ends'               => $data['subscription_ends'] ?? 'never',
				'subscription_ends_payment_count' => $data['subscription_ends_payment_count'] ?? '',
			);

			$order_meta = SubscriptionHelper::get_filtered_order_meta_data( $order );

			$meta_data = array_merge( $meta_data, $order_meta );

			// filter meta data for blank values
			$meta_data = array_filter(
				$meta_data,
				function ( $value ) {
					return ! empty( $value );
				}
			);

			return $meta_data;
		}

		/**
		 * Build search string from order and posted data
		 *
		 * @param \WC_Order $order
		 * @param array     $posted_data
		 *
		 * @return string
		 */
		private function build_search_str( $order ) {
			return $order->get_billing_email() . ' ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' ' . $order->get_billing_phone();
		}

		/**
		 * Create item data for subscription
		 *
		 * @param \WC_Product            $product
		 * @param array                  $values
		 * @param \WC_Order_Item_Product $item
		 *
		 * @return array
		 */
		private function create_item_data( $product, $values, $item ) {
			$plan_id = $this->get_plan_id( $values );

			// Add plan summary to item meta if plan exists
			if ( $plan_id > 0 && $product instanceof \WC_Product ) {
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
				if ( $plan instanceof \Sublium_WCS\Includes\Abstracts\Plan ) {
					$summary = $plan->display_summary( $product );
					if ( ! empty( $summary ) ) {
						$item->add_meta_data( 'sublium_summary', wp_kses_post( $summary ) );
					}
				}
			}

			$item_data = array(
				'quantity'     => $item->get_quantity(),
				'variation'    => $item->get_variation_id(),
				'subtotal'     => $item->get_subtotal(),
				'total'        => $item->get_total(),
				'name'         => $product->get_name(),
				'tax_class'    => $product->get_tax_class(),
				'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $item->get_variation_id(),
				'plan'         => $plan_id,
				'tax'          => $item->get_taxes(),
				'meta'         => $item->get_meta_data(),
			);

			return array(
				'item_type' => 1,
				'item_data' => $item_data,
			);
		}


		/**
		 * Hooked over `wfocu_offer_payment_failed_event`
		 * Create a Pending subscription of a pending order when upsell fails.
		 *
		 * @param $args
		 */
		public function create_pending_subscription( $args ) {
			$subscription_order = $args['_failed_order'];
			$get_package        = WFOCU_Core()->data->get( '_upsell_package' );
			$this->create_sublium_subscriptions( $get_package, $subscription_order, Subscription::STATUSES['PENDING'] );
		}

		/**
		 * Add plan_id to order item meta when products are added to order
		 *
		 * @param array $item_meta Existing item meta array
		 * @param int   $item_id   Order item ID
		 * @param array $product   Product data from package
		 *
		 * @return array Modified item meta array
		 */
		public function add_plan_id_to_order_item_meta( $item_meta, $item_id, $product ) {
			$plan_id = $this->get_plan_id( $product );
			if ( false !== $plan_id && $plan_id > 0 ) {
				$item_meta['_sublium_wcs_plan'] = $plan_id;

				// Add plan summary to order item meta
				$product_obj = null;
				if ( isset( $product['data'] ) && $product['data'] instanceof \WC_Product ) {
					$product_obj = $product['data'];
				} elseif ( isset( $product['id'] ) ) {
					$product_obj = wc_get_product( $product['id'] );
				}

				if ( $product_obj instanceof \WC_Product ) {
					$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product_obj );
					if ( $plan instanceof \Sublium_WCS\Includes\Abstracts\Plan ) {
						$summary = $plan->display_summary( $product_obj );
						if ( ! empty( $summary ) ) {
							$item_meta['_sublium_wcs_plan_summary'] = wp_kses_post( $summary );
						}
					}
				}
			}
			return $item_meta;
		}
	}


	UpStroke_Sublium_Subscriptions::get_instance();

}
