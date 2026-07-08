<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFOCU_Rule_Order_Sublium' ) ) {
	/**
	 * FunnelKit Payment Plan Rule for WooFunnels Upstroke One-Click Upsell
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Rule_Order_Sublium extends WFOCU_Rule_Base {
		/**
		 * Environments this rule supports
		 *
		 * @var array
		 */
		public $supports = array( 'cart', 'order' );

		/**
		 * Class constructor
		 */
		public function __construct() {
			parent::__construct( 'order_sublium' );
		}

		/**
		 * Define possible operators for this rule
		 *
		 * @return array Operator options
		 */
		public function get_possible_rule_operators() {
			return array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),
			);
		}

		/**
		 * Define input type for the condition
		 *
		 * @return string Input type
		 */
		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		/**
		 * Get products that have FunnelKit payment plans
		 *
		 * @param int    $limit Number of products to return (-1 for all)
		 * @param string $post_status Product status to include (default: 'publish')
		 *
		 * @return array Array of product options with plan details
		 */
		function get_posts_with_sublium_data( $limit = - 1, $post_status = 'publish' ) {
			global $wpdb;
			$output   = array();
			$meta_key = '_sublium_wcs_plan_data';

			try {
				// Prepare and execute SQL query
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
					$post_status
				);

				// Add limit if specified
				if ( $limit > 0 ) {
					$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
				}

				// Get the results
				$products = $wpdb->get_results( $sql );

				if ( empty( $products ) ) {
					return array();
				}

				// Process each product's plans
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

							$plan_id = $plan_data['id'];
							$plan    = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $wc_product );

							if ( ! $plan ) {
								continue;
							}

							$option_key   = $product->ID . '_' . $plan_id;
							$option_value = sprintf( '%s (#%d) - (%s)', $product->title, $product->ID, $plan->get_title( $wc_product ) );

							$output[ $option_key ] = $option_value;
						}
					} catch ( \Exception | \Error $e ) {
						if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							error_log( sprintf( 'FunnelKit Pay Rule: Error processing product #%d: %s', $product->ID, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						}
					}
				}
			} catch ( \Exception | \Error $e ) {
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( 'FunnelKit Pay Rule: Database error: %s', $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}

			return $output;
		}

		/**
		 * Return possible rule values for the admin UI
		 *
		 * @return array Rule values
		 */
		public function get_possible_rule_values() {
			static $cached_values = null;

			if ( is_null( $cached_values ) ) {
				$cached_values = $this->get_posts_with_sublium_data();
			}

			return $cached_values;
		}

		/**
		 * Check if the rule matches the current environment
		 *
		 * @param array  $rule_data Rule configuration
		 * @param string $env Environment (cart or order)
		 *
		 * @return bool Whether rule matches
		 */
		public function is_match( $rule_data, $env = 'cart' ) {
			if ( empty( $rule_data['condition'] ) || ! is_array( $rule_data['condition'] ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			$type      = $rule_data['operator'];
			$all_terms = $this->get_applicable_terms( $env );
			if ( empty( $all_terms ) ) {
				return $this->return_is_match( false, $rule_data );
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

		/**
		 * Get applicable terms from cart or order based on environment
		 *
		 * @param string $env Environment (cart or order)
		 *
		 * @return array Applicable term IDs
		 */
		private function get_applicable_terms( $env = 'cart' ) {
			$all_terms = array();

			if ( $env === 'cart' ) {
				$cart_contents = (array) WC()->cart->cart_contents;

				if ( ! empty( $cart_contents ) ) {
					foreach ( $cart_contents as $cart_item ) {
						if ( empty( $cart_item['sublium_wcs_plan'] ) ) {
							continue;
						}

						$product_id   = $this->get_product_id_from_cart_item( $cart_item );
						$variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
						$plan_id      = $cart_item['sublium_wcs_plan'];

						if ( $product_id ) {
							$all_terms[] = $product_id . '_' . $plan_id;
						}

						if ( $variation_id ) {
							$all_terms[] = $variation_id . '_' . $plan_id;
						}
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				if ( $order && $order->get_items() ) {
					foreach ( $order->get_items() as $order_item ) {
						if ( ! $order_item->get_meta( 'sublium_wcs_plan' ) ) {
							continue;
						}

						$product = WFOCU_WC_Compatibility::get_product_from_item( $order, $order_item );
						if ( ! $product ) {
							continue;
						}

						$product_id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
						$variation_id = version_compare( WC()->version, '3.0', '>=' ) ? $order_item->get_variation_id() : ( is_array( $order_item['variation_id'] ) ? $order_item['variation_id'][0] : 0 ); // phpcs:ignore WordPress.WP.Posts.Posts.get_variation_id
						$plan_id      = $order_item->get_meta( 'sublium_wcs_plan' );

						if ( $product_id ) {
							$all_terms[] = $product_id . '_' . $plan_id;
						}

						if ( $variation_id ) {
							$all_terms[] = $variation_id . '_' . $plan_id;
						}
					}
				}
			}

			return array_unique( $all_terms );
		}

		/**
		 * Extract product ID from cart item
		 *
		 * @param array $cart_item Cart item data
		 *
		 * @return int Product ID
		 */
		private function get_product_id_from_cart_item( $cart_item ) {
			$product_id = ! empty( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;

			if ( $product_id === 0 && ! empty( $cart_item['data'] ) ) {
				if ( $cart_item['data'] instanceof WC_Product_Variation ) {
					$product_id = $cart_item['data']->get_parent_id();
				} elseif ( $cart_item['data'] instanceof WC_Product ) {
					$product_id = $cart_item['data']->get_id();
				}
			}

			return $product_id;
		}
	}
}
