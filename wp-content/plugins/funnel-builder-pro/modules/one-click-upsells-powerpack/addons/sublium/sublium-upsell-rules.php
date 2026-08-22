<?php
/**
 * Sublium rule classes for FunnelKit One-Click Upsells.
 *
 * Moved from sublium-subscriptions-for-woocommerce/compatibilities/funnelkit/upsell-rules.php
 * into the UpStroke PowerPack so rule ownership lives in funnel-builder-pro.
 *
 * Loaded via WooFunnels_UpStroke_PowerPack::load_rule_files().
 * Rule types are registered via WFOCU_Sublium_Compatibility::register_rule_type().
 */
defined( 'ABSPATH' ) || exit;

// Bail early if Sublium is not active — avoids fatal errors from missing classes.
if ( ! class_exists( '\Sublium_WCS\Includes\Main\Plans' ) ) {
	return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WFOCU_Rule_Order_Contain_Sublium_Subscription' ) ) {
	/**
	 * Simple boolean rule: does the order/cart contain any Sublium subscription item?
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Rule_Order_Contain_Sublium_Subscription extends WFOCU_Rule_Base {

		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_contain_sublium_subscription' );
		}

		public function get_possible_rule_operators() {
			return array(
				'yes' => __( 'Yes', 'woofunnels-upstroke-power-pack' ),
				'no'  => __( 'No', 'woofunnels-upstroke-power-pack' ),
			);
		}

		public function get_possible_rule_values() {
		}

		public function get_condition_input_type() {
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$type  = isset( $rule_data['operator'] ) ? $rule_data['operator'] : 'yes';
			$found = $this->has_sublium_item( $env );

			switch ( $type ) {
				case 'yes':
					$result = $found;
					break;
				case 'no':
					$result = ! $found;
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

		private function has_sublium_item( $env = 'cart' ) {
			if ( $env === 'cart' ) {
				if ( is_null( WC()->cart ) ) {
					return false;
				}
				foreach ( (array) WC()->cart->cart_contents as $cart_item ) {
					if ( ! empty( $cart_item['sublium_wcs_plan'] ) ) {
						return true;
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );
				if ( $order ) {
					foreach ( $order->get_items() as $order_item ) {
						if ( $order_item->get_meta( 'sublium_wcs_plan' ) ) {
							return true;
						}
					}
				}
			}

			return false;
		}
	}
}

if ( ! class_exists( 'WFOCU_Rule_Order_Sublium_Plan_Type' ) ) {
	/**
	 * Base class for Sublium plan-type specific upsell rules.
	 * Sublium plan types: 1 = Subscribe & Save, 2 = Recurring, 3 = Installments
	 */
	#[\AllowDynamicProperties]
	abstract class WFOCU_Rule_Order_Sublium_Plan_Type extends WFOCU_Rule_Base {

		public $supports = array( 'cart', 'order' );

		abstract protected function get_plan_type();

		public function get_possible_rule_operators() {
			return array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-power-pack' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-power-pack' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-power-pack' ),
			);
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function get_possible_rule_values() {
			static $cached = array();

			$plan_type = $this->get_plan_type();

			if ( ! isset( $cached[ $plan_type ] ) ) {
				$cached[ $plan_type ] = $this->get_posts_with_subliumplan_data_by_type( $plan_type );
			}

			return $cached[ $plan_type ];
		}

		private function get_posts_with_subliumplan_data_by_type( $plan_type ) {
			global $wpdb;
			$output   = array();
			$meta_key = '_sublium_wcs_plan_data';

			try {
				$sql = $wpdb->prepare(
					"SELECT
						p.ID as ID,
						p.post_title as title,
						pm.meta_value AS sublium_plan_data
					FROM
						{$wpdb->posts} p
					JOIN
						{$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE
						pm.meta_key = %s
						AND p.post_status = %s
						AND p.post_type = 'product'
					ORDER BY
						p.post_title ASC",
					$meta_key,
					'publish'
				);

				$products = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

				if ( empty( $products ) ) {
					return array();
				}

				foreach ( $products as $product ) {
					if ( empty( $product->sublium_plan_data ) || ! is_serialized( $product->sublium_plan_data ) ) {
						continue;
					}

					try {
						$plans_data = maybe_unserialize( $product->sublium_plan_data );

						if ( ! is_array( $plans_data ) ) {
							continue;
						}

						$wc_product = wc_get_product( $product->ID );
						if ( ! $wc_product ) {
							continue;
						}

						foreach ( $plans_data as $plan_data ) {
							if ( empty( $plan_data['id'] ) ) {
								continue;
							}

							$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_data['id'], $wc_product );

							if ( ! $plan || $plan->get_type() !== $plan_type ) {
								continue;
							}

							$option_key            = $product->ID . '_' . $plan_data['id'];
							$option_value          = sprintf( '%s (#%d) - (%s)', $product->title, $product->ID, wp_strip_all_tags( $plan->get_title( $wc_product ) ) );
							$output[ $option_key ] = $option_value;
						}
					} catch ( \Exception | \Error $e ) {
						if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							error_log( sprintf( 'Sublium Rule: Error processing product #%d: %s', $product->ID, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						}
					}
				}
			} catch ( \Exception | \Error $e ) {
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( 'Sublium Rule: Database error: %s', $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}

			return $output;
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			if ( empty( $rule_data['condition'] ) || ! is_array( $rule_data['condition'] ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			$type      = $rule_data['operator'];
			$all_terms = $this->get_applicable_terms( $env );

			if ( empty( $all_terms ) ) {
				$result = ( 'none' === $type ) ? true : false;

				return $this->return_is_match( $result, $rule_data );
			}

			$condition = isset( $rule_data['condition']['categories'] ) ? $rule_data['condition']['categories'] : array();
			$result    = false;

			switch ( $type ) {
				case 'all':
					$result = count( array_intersect( $condition, $all_terms ) ) === count( $condition );
					break;
				case 'any':
					$result = count( array_intersect( $condition, $all_terms ) ) >= 1;
					break;
				case 'none':
					$result = count( array_intersect( $condition, $all_terms ) ) === 0;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

		private function get_applicable_terms( $env = 'cart' ) {
			$all_terms = array();
			$plan_type = $this->get_plan_type();

			if ( $env === 'cart' ) {
				if ( is_null( WC()->cart ) ) {
					return $all_terms;
				}

				foreach ( (array) WC()->cart->cart_contents as $cart_item ) {
					if ( empty( $cart_item['sublium_wcs_plan'] ) ) {
						continue;
					}

					$plan_id    = $cart_item['sublium_wcs_plan'];
					$product_id = ! empty( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;

					if ( $product_id === 0 && ! empty( $cart_item['data'] ) ) {
						if ( $cart_item['data'] instanceof \WC_Product_Variation ) {
							$product_id = $cart_item['data']->get_parent_id();
						} elseif ( $cart_item['data'] instanceof \WC_Product ) {
							$product_id = $cart_item['data']->get_id();
						}
					}

					$wc_product = wc_get_product( $product_id );
					if ( ! $wc_product ) {
						continue;
					}

					$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $wc_product );
					if ( ! $plan || $plan->get_type() !== $plan_type ) {
						continue;
					}

					if ( $product_id ) {
						$all_terms[] = $product_id . '_' . $plan_id;
					}

					$variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
					if ( $variation_id ) {
						$all_terms[] = $variation_id . '_' . $plan_id;
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				if ( $order ) {
					foreach ( $order->get_items() as $order_item ) {
						$plan_id = $order_item->get_meta( 'sublium_wcs_plan' );
						if ( ! $plan_id ) {
							continue;
						}

						$product = WFOCU_WC_Compatibility::get_product_from_item( $order, $order_item );
						if ( ! $product ) {
							continue;
						}

						$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
						$wc_product = wc_get_product( $product_id );
						if ( ! $wc_product ) {
							continue;
						}

						$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $wc_product );
						if ( ! $plan || $plan->get_type() !== $plan_type ) {
							continue;
						}

						if ( $product_id ) {
							$all_terms[] = $product_id . '_' . $plan_id;
						}

						$variation_id = version_compare( WC()->version, '3.0', '>=' ) ? $order_item->get_variation_id() : 0;
						if ( $variation_id ) {
							$all_terms[] = $variation_id . '_' . $plan_id;
						}
					}
				}
			}

			return array_unique( $all_terms );
		}
	}
}

if ( ! class_exists( 'WFOCU_Rule_Order_Sublium_Recurring_Plan' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Rule_Order_Sublium_Recurring_Plan extends WFOCU_Rule_Order_Sublium_Plan_Type {

		public function __construct() {
			parent::__construct( 'order_sublium_recurring_plan' );
		}

		protected function get_plan_type() {
			return 2;
		}
	}
}

if ( ! class_exists( 'WFOCU_Rule_Order_Sublium_Subscribe_And_Save' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Rule_Order_Sublium_Subscribe_And_Save extends WFOCU_Rule_Order_Sublium_Plan_Type {

		public function __construct() {
			parent::__construct( 'order_sublium_subscribe_and_save' );
		}

		protected function get_plan_type() {
			return 1;
		}
	}
}

if ( ! class_exists( 'WFOCU_Rule_Order_Sublium_Installment_Plan' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Rule_Order_Sublium_Installment_Plan extends WFOCU_Rule_Order_Sublium_Plan_Type {

		public function __construct() {
			parent::__construct( 'order_sublium_installment_plan' );
		}

		protected function get_plan_type() {
			return 3;
		}
	}
}
