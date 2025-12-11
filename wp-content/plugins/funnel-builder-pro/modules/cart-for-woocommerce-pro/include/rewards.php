<?php

namespace FKCart\Pro;

use FKCart\Compatibilities\Compatibility;
use FKCart\Includes\Data as Data;
use FKCart\Includes\Front;

if ( ! class_exists( '\FKCart\Pro\Rewards' ) ) {
	#[\AllowDynamicProperties]
	class Rewards {
		private static $instance = null;
		private $meta_data = [];
		public $active_free_shipping = false;

		private function __construct() {
			$data = Data::get_db_settings();
			if ( ! isset( $data['enable_cart'] ) || 0 === intval( $data['enable_cart'] ) ) {
				return false;
			}

			add_action( 'wp', [ $this, 'maybe_remove_free_gifts' ] );
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'update_free_gift' ], 98 );
			add_action( 'woocommerce_before_calculate_totals', [ $this, 'update_free_gift' ], 98 );
			add_action( 'woocommerce_calculate_totals', [ $this, 'update_reward' ], 99 );
			add_action( 'fkcart_variable_product_before_update', [ $this, 'remove_action_update_reward' ], 99 );
			add_action( 'fkcart_variable_product_after_update', [ $this, 'update_reward' ], 99 );

			add_filter( 'woocommerce_cart_item_remove_link', [ $this, 'do_not_allow_delete_free_gift' ], 10, 2 );
			add_filter( 'wfacp_enable_delete_item', [ $this, 'aero_disabled_delete_icon' ], 10, 2 );
			add_filter( 'wfacp_mini_cart_enable_delete_item', [ $this, 'aero_disabled_delete_icon' ], 10, 2 );

			add_filter( 'pre_option_woocommerce_shipping_cost_requires_address', [ $this, 'disable_hide_shipping_method_until_address' ] );
			add_action( 'woocommerce_removed_coupon', [ $this, 'stored_removed_coupon' ] );
			add_action( 'woocommerce_cart_emptied', [ $this, 'unset_removed_coupon' ] );
			add_action( 'woocommerce_before_calculate_totals', function () {
				add_filter( 'woocommerce_product_get_price', array( $this, 'handle_reward_free_product' ), 10000, 2 );
				add_filter( 'woocommerce_product_variation_get_price', array( $this, 'handle_reward_free_product' ), 10000, 2 );
				add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'handle_reward_free_product' ), 10000, 2 );
			}, 90 );
			add_action( 'wp', [ $this, 'update_choosen_shipping_method' ], 22 );
			add_filter( 'fkcart_woocommerce_geolocate_ip', [ $this, 'pass_customer_geo_data' ] );
			add_action( 'fkcart_geolocation', [ $this, 'set_geolocation_data_to_customer' ] );


			/**
			 * empty checkout field when geo location off and store location
			 */
			add_action( 'wp', [ $this, 'may_be_checkout_field_update' ] );
			/**
			 * May Be update default value of checkout fields
			 */
			add_filter( 'wfacp_default_values', [ $this, 'do_no_set_default_value' ], 10, 2 );
		}

		/**
		 * @return Rewards
		 */
		public static function getInstance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Update free gift product price to 0
		 *
		 * @param $cart
		 *
		 * @return mixed
		 */
		public function update_free_gift( $cart ) {
			$contents = $cart->get_cart_contents();
			foreach ( $contents as $cart_item_key => $cart_item ) {
				$cart->cart_contents[ $cart_item_key ] = $this->set_price_to_zero_for_free_gift( $cart_item );
			}

			return $cart;
		}

		/**
		 * Modify free gift cart item data
		 * Set price 0 and don't allow qty increment
		 *
		 * @param $cart_items
		 *
		 * @return mixed
		 */
		protected function set_price_to_zero_for_free_gift( $cart_items ) {
			if ( ! isset( $cart_items['_fkcart_free_gift'] ) ) {
				return $cart_items;
			}

			if ( ! $cart_items['data'] instanceof \WC_Product ) {
				return $cart_items;
			}

			$cart_items['data']->set_sold_individually( true ); // do not allow quantity increment for free gift
			$cart_items['data']->set_price( 0 );
			$cart_items['data']->update_meta_data( '_fkcart_free_gift', 'yes' );
			$cart_items['quantity'] = 1;

			return $cart_items;
		}

		/**
		 * @param $price
		 * @param $product \WC_Product
		 *
		 * @return mixed
		 */
		public function handle_reward_free_product( $price, $product ) {

			if ( $product instanceof \WC_Product && ! empty( $product->get_meta( '_fkcart_free_gift' ) ) && 'yes' === $product->get_meta( '_fkcart_free_gift' ) ) {
				return 0;
			}

			return $price;
		}

		/**
		 * Don't allow deleting of free gifts products
		 *
		 * @param $link
		 * @param $cart_item_key
		 *
		 * @return mixed|string
		 */
		public function do_not_allow_delete_free_gift( $link, $cart_item_key ) {
			$cart_item = WC()->cart->get_cart_item( $cart_item_key );
			if ( isset( $cart_item['_fkcart_free_gift'] ) ) {
				return '';
			}

			return $link;
		}

		/**
		 * Make cart empty when all free gift available inside the cart
		 *
		 * @return void
		 */
		public function maybe_remove_free_gifts() {
			if ( is_null( WC()->cart ) ) {
				return;
			}


			$cart_count = WC()->cart->get_cart_contents_count();
			$free_items = array_filter( WC()->cart->get_cart_contents(), function ( $cart_item ) {
				return isset( $cart_item['_fkcart_free_gift'] );
			} );

			$data = Data::get_db_settings();


			$enabled_rewards = false;
			if ( isset( $data['reward'] ) && count( $data['reward'] ) > 0 ) {
				foreach ( $data['reward'] as $reward_key => $reward_value ) {
					if ( isset( $reward_value['type'] ) && $reward_value['type'] == 'freegift' ) {
						$enabled_rewards = true;
					}

				}

			}
			if ( false === $enabled_rewards && is_array( $free_items ) && count( $free_items ) > 0 ) {
				foreach ( $free_items as $cart_item_key => $cart_item_v ) {

					if ( empty( $cart_item_key ) ) {
						continue;
					}

					WC()->cart->remove_cart_item( $cart_item_key );

				}
			}

			if ( count( $free_items ) > 0 && count( $free_items ) === $cart_count ) {
				WC()->cart->empty_cart();
			}
		}

		public function remove_action_update_reward() {
			remove_action( 'woocommerce_calculate_totals', [ $this, 'update_reward' ], 99 );
		}

		/**
		 * Update rewards in the cart based on current state
		 *
		 * @return void
		 */
		public function update_reward() {
			$rewards = self::get_rewards();

			// Early return if no rewards or cart not available
			if ( empty( $rewards ) || is_null( WC()->cart ) ) {
				return;
			}

			// Prevent redirect during AJAX requests
			if ( wp_doing_ajax() ) {
				add_filter( 'wp_redirect', '__return_false', PHP_INT_MAX );
			}

			// Save previous notices and prevent recursion
			$previous_notices = wc_get_notices();
			remove_action( 'woocommerce_calculate_totals', [ $this, 'update_reward' ], 99 );

			// Extract reward data
			$coupon_remove   = $rewards['coupons']['remove'] ?? [];
			$coupon_add      = $rewards['coupons']['add'] ?? [];
			$free_shipping   = $rewards['free_shipping'] ?? false;
			$removed_coupons = WC()->session->get( '_fkcart_removed_coupons', [] );

			// Process coupons
			$this->process_coupons( $coupon_remove, $coupon_add, $removed_coupons );

			// Apply gift product filter
			$rewards['gifts'] = apply_filters( 'fkcart_gift_products', $rewards['gifts'], $rewards );

			$gift_add    = $rewards['gifts']['add'] ?? [];
			$gift_remove = $rewards['gifts']['remove'] ?? [];

			// Process gift products
			$this->process_gift_products( $gift_add, $gift_remove );

			// Handle free shipping
			$this->handle_free_shipping( $free_shipping );

			// Restore notices
			wc_clear_notices();
			WC()->session->set( 'wc_notices', $previous_notices );
		}

		/**
		 * Process coupon additions and removals
		 *
		 * @param array $coupon_remove Coupons to remove
		 * @param array $coupon_add Coupons to add
		 * @param array $removed_coupons Previously removed coupons
		 *
		 * @return void
		 */
		private function process_coupons( $coupon_remove, $coupon_add, $removed_coupons ) {
			// Remove coupons if needed
			if ( ! empty( $coupon_remove ) ) {
				foreach ( $coupon_remove as $rm_coupon_code ) {
					if ( WC()->cart->has_discount( $rm_coupon_code ) ) {
						WC()->cart->remove_coupon( $rm_coupon_code );
					}
				}
			}

			// Add coupons if needed
			if ( ! empty( $coupon_add ) ) {
				foreach ( $coupon_add as $add_coupon_code ) {
					if ( WC()->cart->has_discount( $add_coupon_code ) || isset( $removed_coupons[ strtolower( $add_coupon_code ) ] ) ) {
						continue;
					}
					WC()->cart->add_discount( $add_coupon_code );
					$this->update_discount_views( $add_coupon_code );
				}
			}
		}

		/**
		 * Process gift product additions and removals
		 *
		 * @param array $gift_add Gift products to add
		 * @param array $gift_remove Gift products to remove
		 *
		 * @return array Temporary storage for variation data
		 */

		private function process_gift_products( $gift_add, $gift_remove ) {
			$contents                = WC()->cart->get_cart_contents();
			$temp_gift_variation_add = []; // For storing Free Gift Variation data

			// If no gifts to add, remove all free gifts from cart

			$this->remove_all_free_gifts( $contents, $gift_add );


			// Prepare comprehensive removal list
			$gift_remove = array_unique( array_merge( $gift_remove, $gift_add ) );
			sort( $gift_remove );

			// Remove specified gift products and store variation data
			if ( ! empty( $gift_remove ) ) {
				$temp_gift_variation_add = $this->remove_gift_products( $contents, $gift_remove, $gift_add );
			}

			// Add all gift products to the cart
			if ( ! empty( $gift_add ) ) {
				$this->update_free_gift_views( $gift_add );
				$this->add_gift_products( $gift_add, $temp_gift_variation_add );
			}

			return $temp_gift_variation_add;
		}

		/**
		 * Remove all free gifts from cart
		 *
		 * @param array $contents Cart contents
		 *
		 * @return void
		 */
		private function remove_all_free_gifts( $contents, $gift_add ) {
			foreach ( $contents as $cart_item_key => $cart_item ) {
				if ( ! isset( $cart_item['_fkcart_free_gift'] ) ) {
					continue;
				}
				if ( in_array( $cart_item['product_id'], $gift_add ) || in_array( $cart_item['variation_id'], $gift_add ) ) {
					continue;
				}
				$status = WC()->cart->remove_cart_item( $cart_item_key );
				if ( false !== $status ) {
					unset( WC()->cart->removed_cart_contents[ $cart_item_key ] );
				}
			}

		}

		/**
		 * Remove specified gift products
		 *
		 * @param array $contents Cart contents
		 * @param array $gift_remove Products to remove
		 * @param array $gift_add Products to add
		 *
		 * @return array Temporary storage for variation data
		 */
		private function remove_gift_products( $contents, $gift_remove, $gift_add ) {
			$temp_gift_variation_add = [];

			foreach ( $contents as $cart_item_key => $cart_item ) {
				if ( ! isset( $cart_item['_fkcart_free_gift'] ) ) {
					continue;
				}

				$product    = $cart_item['data'];
				$product_id = $product->get_id();
				$parent_id  = $product->get_parent_id();

				if ( in_array( $product_id, $gift_remove ) || in_array( $parent_id, $gift_remove ) ) {
					// Store variable gift product data if needed
					if ( in_array( $parent_id, $gift_add ) && fkcart_is_variation_product_type( $product->get_type() ) ) {
						$temp_gift_variation_add[ $parent_id ] = [
							'variation'    => $cart_item['variation'],
							'variation_id' => $cart_item['variation_id']
						];
					}

					$status = WC()->cart->remove_cart_item( $cart_item_key );
					if ( false !== $status ) {
						unset( WC()->cart->removed_cart_contents[ $cart_item_key ] );
					}
				}
			}

			return $temp_gift_variation_add;
		}

		/**
		 * Add gift products to cart
		 *
		 * @param array $gift_add Products to add
		 * @param array $temp_gift_variation_add Variation data
		 *
		 * @return void
		 */
		private function add_gift_products( $gift_add, $temp_gift_variation_add ) {
			foreach ( $gift_add as $add ) {
				$product = wc_get_product( $add );
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}

				$variation_attributes = [];
				$cart_item_data       = [ '_fkcart_free_gift' => 1 ];

				if ( fkcart_is_variation_product_type( $product->get_type() ) ) {
					$product_id           = $product->get_parent_id();
					$variation_id         = $product->get_id();
					$variation_attributes = $product->get_attributes();

					// Find blank attributes in any case
					$blank_attribute = array_filter( $variation_attributes, function ( $v ) {
						return is_null( $v ) || empty( $v );
					} );

					// If Any-Any case found then map remaining attributes
					if ( ! empty( $blank_attribute ) ) {
						$parent_product       = wc_get_product( $product_id );
						$variation_attributes = self::map_variation_attributes( $variation_attributes, $parent_product->get_variation_attributes() );
					}

					$cart_item_data['_fkcart_variation_gift'] = true;
				} elseif ( fkcart_is_variable_product_type( $product->get_type() ) ) {
					$product_id = $product->get_id();

					if ( isset( $temp_gift_variation_add[ $product_id ] ) ) {
						$variation_attributes = $temp_gift_variation_add[ $product_id ]['variation'];
						$variation_id         = $temp_gift_variation_add[ $product_id ]['variation_id'];
					} else {
						/**
						 * @var $product \WC_Product_Variable
						 */
						$product_attributes = $product->get_variation_attributes();
						$variations         = $product->get_visible_children();

						if ( empty( $product_attributes ) || empty( $variations ) ) {
							continue;
						}

						$variation_id         = $variations[0];
						$variation            = wc_get_product( $variation_id );
						$variation_attributes = $variation->get_attributes();

						// Handle Any-Any case
						$variation_attributes = self::map_variation_attributes( $variation_attributes, $product_attributes );
					}

					$cart_item_data['_fkcart_variable_gift'] = true;
				} else {
					$product_id   = $product->get_id();
					$variation_id = 0;
				}
				$status = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation_attributes, $cart_item_data );
				if ( false !== $status && class_exists( '\WFOB_Public' ) ) {
					\WFOB_Public::get_instance()->re_run_rules_after_bump_removed();
				}

			}

		}

		/**
		 * Handle free shipping settings
		 *
		 * @param mixed $free_shipping Free shipping method or false
		 *
		 * @return void
		 */
		private function handle_free_shipping( $free_shipping ) {
			if ( ! is_null( WC()->session ) && self::need_to_set_free_shipping_in_checkout_session() ) {
				WC()->session->__unset( '_fkcart_free_shipping_methods' );

				if ( $free_shipping ) {
					// Set a key for free shipping reward
					WC()->session->set( '_fkcart_free_shipping_methods', $free_shipping );

					if ( ! wp_doing_ajax() ) {
						WC()->session->set( 'chosen_shipping_methods', [ $free_shipping ] );
					}
				}
			}
		}

		/**
		 * Modify saved rewards and return
		 *
		 * @return array|void
		 */
		public static function get_rewards( $raw_data = false ) {
			if ( false === Data::is_rewards_enabled() ) {
				return;
			}

			/** Validate */
			if ( Plugin::valid_l() === false ) {
				return;
			}
			$rewards = [];
			$rewards = Data::get_value( 'reward' );
			if ( empty( $rewards ) ) {
				return;
			}


			$wc_coupons_enable  = wc_coupons_enabled();
			$wc_shipping_enable = wc_shipping_enabled();
			$shipping_data      = false;
			if ( $wc_shipping_enable ) {
				$reward_types  = array_column( $rewards, 'type' );
				$shipping_data = in_array( 'freeshipping', $reward_types ) ? apply_filters( 'fkcart_free_shipping', self::get_shipping_min_amount() ) : false;
			}

			$rewards_new = $rewards;


			foreach ( $rewards as $r => $reward ) {
				if ( 'freeshipping' === $reward['type'] ) {

					if ( false == $raw_data && ( ! $wc_shipping_enable || empty( $shipping_data ) || ! isset( $shipping_data['method_id'] ) ) ) {
						unset( $rewards_new[ $r ] );
						continue;
					}
					if ( false === $shipping_data ) {
						unset( $rewards_new[ $r ] );
						continue;
					}

					$reward['amount']          = \Automattic\WooCommerce\Utilities\NumberUtil::round( $shipping_data['min_amount'], wc_get_price_decimals() );
					$reward['shipping_method'] = $shipping_data['method_id'];
				}
				if ( 'discount' === $reward['type'] && false === $wc_coupons_enable ) {
					unset( $rewards_new[ $r ] );
					continue;
				}

				/** Restricted the amount conversion in case of free shipping as it is already getting converted */
				if ( isset( $reward['amount'] ) && 'freeshipping' !== $reward['type'] ) {
					$reward['amount'] = Compatibility::get_fixed_currency_price( $reward['amount'] );
				}

				/** Dev filter to disallow reward */
				if ( true === apply_filters( 'fkcart_reward_rules_checking', false, $reward ) ) {
					unset( $rewards_new[ $r ] );
					continue;
				}

				$rewards_new[ $r ] = $reward;
			}

			if ( empty( $rewards_new ) ) {
				return;
			}
			$rewards = $rewards_new;
			unset( $rewards_new );

			/** Sort array based on price */
			usort( $rewards, function ( $item1, $item2 ) {
				return intval( isset( $item1['amount'] ) ? $item1['amount'] : 0 ) <=> intval( isset( $item2['amount'] ) ? $item2['amount'] : 0 );
			} );

			/** Get max amount milestone of progress bar */
			$max_amount = array_column( $rewards, 'amount' );
			if ( count( $max_amount ) > 0 ) {
				$max_amount = max( $max_amount );
			}
			$max_amount = ( is_array( $max_amount ) || empty( $max_amount ) || intval( $max_amount ) < 1 ) ? 1 : $max_amount;

			$subtotal      = self::get_cart_total();
			$title         = '';
			$free_shipping = false;

			$coupons    = [ 'add' => [], 'remove' => [] ];
			$free_gifts = [ 'add' => [], 'remove' => [] ];

			$icon_width  = ( 3 === count( $rewards ) ) ? [ 30, 60, 100 ] : ( ( 2 === count( $rewards ) ) ? [ 45, 100 ] : [ 100 ] );
			$icon_amount = [];

			$i = 0;
			foreach ( $rewards as $key => $reward ) {
				$reward_amount  = ! isset( $reward['amount'] ) || empty( $reward['amount'] ) ? 0 : floatval( $reward['amount'] );
				$amount_checked = $reward_amount >= 0;

				$icon_amount[] = $reward_amount;

				$rewards[ $key ]['achieved']       = $amount_checked && ( round( $reward_amount, 5 ) <= $subtotal );
				$rewards[ $key ]['pending_amount'] = $amount_checked ? ( ( round( $reward_amount, 5 ) < $subtotal ) ? 0 : round( $reward_amount, 5 ) - $subtotal ) : 0;
				$rewards[ $key ]['progress_width'] = $amount_checked ? $icon_width[ $i ] : 0;
				$i ++;

				if ( empty( $title ) && false === $rewards[ $key ]['achieved'] ) {
					$title = isset( $reward['title'] ) ? $reward['title'] : '';
					$title = str_replace( '{{remaining_amount}}', wc_price( $rewards[ $key ]['pending_amount'] ), $title );
					$title = preg_replace( '/~([^~]+)~/', '<div class="fkcart-reward-milestone">$1</div>', $title );
				}

				if ( 'discount' === strval( $reward['type'] ) && isset( $reward['coupon'] ) ) {
					if ( true === $rewards[ $key ]['achieved'] ) {
						$coupons['add'][] = $reward['coupon'];
					} else {
						$coupons['remove'][] = $reward['coupon'];
					}
					continue;
				}
				if ( 'freegift' === strval( $reward['type'] ) && isset( $reward['freeProduct'] ) ) {
					if ( ! is_array( $reward['freeProduct'] ) || 0 === count( $reward['freeProduct'] ) ) {
						continue;
					}
					$free_products = array_column( $reward['freeProduct'], 'key' );
					if ( true === $rewards[ $key ]['achieved'] ) {
						$free_gifts['add'] = array_merge( $free_gifts['add'], $free_products );
					} else {
						$free_gifts['remove'] = array_merge( $free_gifts['remove'], $free_products );
					}
					continue;
				}
				if ( false === $free_shipping && 'freeshipping' === strval( $reward['type'] ) && true === $rewards[ $key ]['achieved'] ) {
					$free_shipping = isset( $rewards[ $key ]['shipping_method'] ) ? $rewards[ $key ]['shipping_method'] : false;
				}
			}

			$progress_width = 0;
			if ( intval( $subtotal ) > 0 ) {
				/** checking 1 reward case */
				if ( 1 === count( $icon_amount ) ) {
					$progress_width = ( ( $subtotal * 100 ) / $max_amount ) > 100 ? 100 : number_format( ( ( $subtotal * 100 ) / $max_amount ), 5 );
				} else {
					foreach ( $icon_amount as $key => $amount ) {
						if ( $subtotal >= $amount ) {
							// reward achieved
							$progress_width = $icon_width[ $key ];
						} else {
							// reward not achieved
							$prev_key = $key - 1;

							$current_gap_amount  = $amount;
							$current_gap_percent = $icon_width[ $key ];
							$old_value_gap       = $subtotal;

							if ( $prev_key >= 0 ) {
								$current_gap_amount  = $amount - ( $icon_amount[ $prev_key ] ?? 0 );
								$current_gap_percent = $icon_width[ $key ] - ( $icon_width[ $prev_key ] ?? 0 );
								$old_value_gap       = $subtotal - ( $icon_amount[ $prev_key ] ?? 0 );
							}

							$milestone_percent = number_format( ( ( $old_value_gap * 100 ) / $current_gap_amount ), 5 );

							$milestone_width = ( $milestone_percent * $current_gap_percent ) / 100;
							$progress_width  += $milestone_width;
							break;
						}
					}
				}
			}
			if ( $progress_width >= 100 ) {
				$title = Data::get_value( 'reward_title' );
			}

			return apply_filters( 'fkcart_rewards_list', [
				'max_amount'    => $max_amount,
				'title'         => $title,
				'coupons'       => $coupons,
				'gifts'         => $free_gifts,
				'free_shipping' => $free_shipping,
				'rewards'       => $rewards,
				'progress_bar'  => $progress_width,
				'subtotal'      => $subtotal
			] );
		}

		/**
		 * Get current shipping method of user with current zone
		 *
		 * @return false|array
		 */
		public static function get_shipping_min_amount() {
			if ( ! class_exists( '\WC_Geolocation' ) ) {
				return false;
			}
			include_once __DIR__ . '/geolocation.php';


			$geolocation = Geolocation::geolocate_ip( Geolocation::get_ip_address(), true );
			if ( empty( $geolocation['country'] ) ) {
				return false;
			}

			$country   = strtoupper( wc_clean( $geolocation['country'] ) );
			$state     = strtoupper( wc_clean( $geolocation['state'] ) );
			$continent = strtoupper( wc_clean( WC()->countries->get_continent_code_for_country( $geolocation['country'] ) ) );
			$postcode  = wc_normalize_postcode( $geolocation['postcode'] );

			$zone_cache_key = \WC_Cache_Helper::get_cache_prefix( 'shipping_zones' ) . 'wc_shipping_zone_' . md5( sprintf( '%s+%s+%s', $country, $state, $postcode ) );
			if ( isset( self::getInstance()->meta_data[ $zone_cache_key ] ) ) {
				return self::getInstance()->meta_data[ $zone_cache_key ];
			}
			$matched_zone_key = wp_cache_get( $zone_cache_key, 'shipping_zones' );

			if ( false === $matched_zone_key ) {
				global $wpdb;

				// Work out criteria for our zone search
				$conditions = array();
				// add condition for country code
				$conditions[] = $wpdb->prepare( "( ( location_type = 'country' AND location_code = %s )", $country );
				// OR condition for country & state Combo
				$conditions[] = $wpdb->prepare( "OR ( location_type = 'state' AND location_code = %s )", $country . ':' . $state );
				// OR condition for Continents
				$conditions[] = $wpdb->prepare( "OR ( location_type = 'continent' AND location_code = %s )", $continent );

				// OR condition for Other location Type
				$conditions[] = "OR ( location_type IS NULL ) )";

				// Postcode range and wildcard matching
				$get_zipcode_locations = $wpdb->get_results( "SELECT zone_id, location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE location_type = 'postcode';" );
				if ( $get_zipcode_locations ) {
					$zone_ids_with_postcode_rules = array_map( 'absint', wp_list_pluck( $get_zipcode_locations, 'zone_id' ) );
					$matches                      = wc_postcode_location_matcher( $postcode, $get_zipcode_locations, 'zone_id', 'location_code', $country );
					$do_not_match                 = array_unique( array_diff( $zone_ids_with_postcode_rules, array_keys( $matches ) ) );
					if ( ! empty( $do_not_match ) ) {
						$conditions[] = "AND zones.zone_id NOT IN (" . implode( ',', $do_not_match ) . ")";
					}
				}
				// Get matching zones
				$matched_zone_key = $wpdb->get_var( "SELECT zones.zone_id FROM {$wpdb->prefix}woocommerce_shipping_zones as zones
				INNER JOIN {$wpdb->prefix}woocommerce_shipping_zone_locations as locations ON zones.zone_id = locations.zone_id inner join  {$wpdb->prefix}woocommerce_shipping_zone_methods as methods on zones.zone_id=methods.zone_id AND methods.is_enabled=1 AND location_type != 'postcode'
				WHERE " . implode( ' ', $conditions ) . "
				ORDER BY zone_order ASC LIMIT 1" );
				wp_cache_set( $zone_cache_key, $matched_zone_key, 'shipping_zones' );
			}

			$shipping_methods                = new  \WC_Shipping_Zone( in_array( $matched_zone_key, [ 0, false, null ] ) ? 0 : $matched_zone_key );
			$shipping_methods                = $shipping_methods->get_shipping_methods( true );
			$free_shipping_supported_methods = fkcart_free_shipping_method();
			foreach ( $shipping_methods as $i => $shipping_method ) {
				if ( ! is_numeric( $i ) || 'yes' !== $shipping_method->enabled ) {
					continue;
				}

				if ( ! in_array( $shipping_method->id, $free_shipping_supported_methods ) || ( property_exists( $shipping_method, 'requires' ) && in_array( $shipping_method->requires, [
							'coupon',
							'both'
						] ) ) ) {
					continue;
				}
				$free_shipping = Compatibility::get_free_shipping( $shipping_method );;
				if ( false !== $free_shipping && $free_shipping['min_amount'] > - 1 ) {// return if min amount > -1 otherwise check other available free shipping methods
					self::getInstance()->meta_data[ $zone_cache_key ] = $free_shipping;

					return self::getInstance()->meta_data[ $zone_cache_key ];
				}
			}
			self::getInstance()->meta_data[ $zone_cache_key ] = false;

			return false;
		}

		/**
		 * Disable Delete icon for Funnelkit cart free Gift item
		 *
		 * @param $status
		 * @param $cart_item
		 *
		 * @return false|mixed
		 */
		public function aero_disabled_delete_icon( $status, $cart_item ) {
			if ( isset( $cart_item['_fkcart_free_gift'] ) ) {
				$status = false;
			}

			return $status;
		}

		/**
		 * Disable Hide Shipping Method until Complete address setting when Free Shipping Reward Available
		 * @return string
		 */
		public function disable_hide_shipping_method_until_address( $status ) {
			/** If unchecked or WC admin page */
			if ( is_admin() && ! wp_doing_ajax() ) {
				return $status;
			}

			if ( 0 === did_action( 'wp_loaded' ) || is_null( WC()->cart ) || WC()->cart->is_empty() || is_null( WC()->session ) ) {
				return $status;
			}

			$free_shipping = WC()->session->get( '_fkcart_free_shipping_methods', '' );

			return ! empty( $free_shipping ) ? 'no' : $status;
		}

		/**
		 * Save removed coupons in WC session if user removed
		 *
		 * @return void
		 */
		public function stored_removed_coupon( $coupon_code ) {
			$count = ( did_action( 'wc_ajax_remove_coupon' ) || did_action( 'wfacp_before_coupon_removed' ) || did_action( 'wc_ajax_fkcart_remove_coupon' ) );
			if ( false === $count || is_null( WC()->session ) ) {
				return;
			}
			$removed_coupons = WC()->session->get( '_fkcart_removed_coupons', [] );

			$removed_coupons[ strtolower( $coupon_code ) ] = true;

			WC()->session->set( '_fkcart_removed_coupons', $removed_coupons );
		}

		/**
		 * Unset removed coupon session key when cart is emptied
		 *
		 * @return void
		 */
		public function unset_removed_coupon() {
			if ( is_null( WC()->session ) ) {
				return;
			}
			WC()->session->__unset( '_fkcart_removed_coupons' );
		}

		/**
		 * Get cart total based on the calculation mode
		 *
		 * @return mixed|void
		 */
		public static function get_cart_total() {
			$calculation_mode = apply_filters( 'fkcart_reward_calculation_based_on', Data::get_value( 'reward_calculation_based' ) );
			$front            = Front::get_instance();

			if ( 'total' === $calculation_mode && method_exists( $front, 'get_total_row' ) ) {
				$total = $front->get_total_row( true );
			} else {
				$total = $front->get_subtotal_row( true );
			}

			return apply_filters( 'fkcart_reward_total', $total, $calculation_mode, $front );
		}

		/**
		 * Map Variation Attributes in case of ANY ,ANY options
		 *
		 * @param $variation_attr
		 * @param $product_attr
		 *
		 * @return array
		 */
		public static function map_variation_attributes( $variation_attr, $product_attr ) {
			$new_product_attr = [];
			foreach ( $product_attr as $k => $item ) {
				$k                      = strtolower( $k );//Lowering the Attribute keys
				$k                      = str_replace( ' ', '-', $k );
				$new_product_attr[ $k ] = $item;
			}
			$output = [];
			foreach ( $variation_attr as $key => $attr ) {
				if ( empty( $attr ) ) {
					$key  = strtolower( $key );
					$key  = str_replace( ' ', '-', $key );
					$attr = $new_product_attr[ $key ][0];
				}
				$output[ 'attribute_' . $key ] = $attr;
			}

			return $output;
		}

		/***
		 * Update Free Shipping Reward at page load.
		 * @return void
		 */
		public function update_choosen_shipping_method() {
			if ( wp_doing_ajax() || is_null( WC()->cart ) || WC()->cart->is_empty() || is_null( WC()->session ) || ! is_checkout() ) {
				return;
			}

			if ( ! self::need_to_set_free_shipping_in_checkout_session() ) {
				return;
			}
			$method         = WC()->session->get( '_fkcart_free_shipping_methods', '' );
			$choosen_method = WC()->session->get( 'chosen_shipping_methods', null );
			if ( empty( $method ) || ! empty( $choosen_method ) ) {
				return;
			}

			WC()->session->set( 'chosen_shipping_methods', [ $method ] );// set reward free shipping method to cart session
		}

		/**
		 * Pass the customer Billing address data to Geolocation Data
		 *
		 * @param $geolocation
		 *
		 * @return mixed
		 */
		public function pass_customer_geo_data( $geolocation ) {
			if ( ! WC()->customer instanceof \WC_Customer ) {
				return $geolocation;
			}

			$billing_country  = WC()->customer->get_billing_country();
			$billing_state    = WC()->customer->get_billing_state();// set geolocate data if
			$billing_city     = WC()->customer->get_billing_city();
			$billing_postcode = WC()->customer->get_billing_postcode();
			// do not set incomplete geolocate data for shipping rewards.
			if ( empty( $billing_country ) || empty( $billing_state ) || empty( $billing_city ) || empty( $billing_postcode ) ) {
				return $geolocation;
			}
			$geolocation['country']  = $billing_country;
			$geolocation['state']    = $billing_state;
			$geolocation['city']     = $billing_city;
			$geolocation['postcode'] = $billing_postcode;

			return $geolocation;
		}

		public function set_geolocation_data_to_customer( $geolocation ) {
			if ( true !== apply_filters( 'fkcart_set_geolocation_data_to_customer', true ) || ! WC()->customer instanceof \WC_Customer || is_user_logged_in() ) {
				return;
			}

			if ( ! empty( $geolocation['country'] ) && empty( WC()->customer->get_billing_country() ) ) {
				WC()->customer->set_billing_country( $geolocation['country'] );
			}
			if ( ! empty( $geolocation['state'] ) && empty( WC()->customer->get_billing_state() ) ) {
				WC()->customer->set_billing_state( $geolocation['state'] );
			}
		}

		/**
		 * Filter
		 * Set a free shipping method in Checkout Session if User rewarded
		 * @return mixed|null
		 */
		public static function need_to_set_free_shipping_in_checkout_session() {
			return apply_filters( 'fkcart_need_to_set_free_shipping_method', true );
		}

		/***
		 * Update available free gift  view in sessions during cart process.
		 *
		 * @param $upsells []
		 *
		 * @return void
		 */
		public function update_discount_views( $coupon_code ) {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return;
			}

			$already_views   = WC()->session->get( '_fkcart_discount_code_views', [] );
			$already_views[] = $coupon_code;
			WC()->session->set( '_fkcart_discount_code_views', array_unique( $already_views ) );
		}


		/***
		 * Update available free gift  view in sessions during cart process.
		 *
		 * @param $upsells []
		 *
		 * @return void
		 */
		public function update_free_gift_views( $gifts ) {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return;
			}

			$already_gift_views = WC()->session->get( '_fkcart_free_gift_views', [] );
			if ( ! empty( $already_gift_views ) ) {
				$gifts = array_merge( $already_gift_views, $gifts );
			}
			WC()->session->set( '_fkcart_free_gift_views', array_unique( $gifts ) );
		}

		/**
		 * return no of upsell view during checkout process.
		 * @return array
		 */
		public function get_free_gift_views() {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return [];
			}

			return WC()->session->get( '_fkcart_free_gift_views', [] );
		}

		/**
		 * return no of upsell view during checkout process.
		 * @return array
		 */
		public function get_discount_views() {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return [];
			}

			return WC()->session->get( '_fkcart_discount_code_views', [] );
		}

		public function get_applied_free_shipping() {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return '';
			}

			return WC()->session->get( '_fkcart_free_shipping_methods', '' );
		}


		/**
		 * Determines whether checkout fields need to be updated.
		 * If free shipping is enabled and no geolocation is set, it applies filters to blank certain checkout fields.
		 */

		public function may_be_checkout_field_update() {


			if ( ! $this->is_free_shipping_enabled_with_no_geolocation() ) {
				return;
			}

			/**
			 * Filters WooCommerce checkout fields to set blank values if required.
			 */
			add_filter( 'woocommerce_checkout_fields', array( $this, 'set_blank_checkout_fields' ), 10, 1 );
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'ensure_blank_checkout_fields' ), 10, 2 );
		}

		public function is_free_shipping_enabled_with_no_geolocation() {


			/**
			 * Free shipping eligibility should only be checked for non-logged-in users.
			 */

			if ( is_user_logged_in() ) {
				return false;
			}

			/**
			 * Retrieve WooCommerce's default customer address setting.
			 */

			$this->default_wc_location = get_option( 'woocommerce_default_customer_address' );


			/**
			 * If default location is set and not 'base', free shipping is not applicable.
			 */
			if ( $this->default_wc_location !== '' && $this->default_wc_location !== 'base' && $this->default_wc_location !== 'geolocation' ) {
				return false;
			}

			$rewards = array(); // Initialize with empty array
			/**
			 * Get available rewards data.
			 */
			if ( class_exists( '\FKCart\Pro\Rewards' ) ) {
				$rewards = \FKCart\Pro\Rewards::get_rewards( true );
			}


			/**
			 * Check if rewards data exists and is a valid array.
			 */
			if ( ! isset( $rewards['rewards'] ) || ! is_array( $rewards['rewards'] ) || count( $rewards['rewards'] ) == 0 ) {
				return false;
			}

			/**
			 * Loop through rewards to check if free shipping is available.
			 */


			foreach ( $rewards['rewards'] as $reward_key => $reward_value ) {

				if ( $reward_value['type'] == 'freeshipping' ) {
					$this->active_free_shipping   = [];
					$this->active_free_shipping[] = $reward_value['type'];
					break;
				}
			}


			/**
			 * If no free shipping rewards are found, return false.
			 */
			if ( $this->active_free_shipping == false ) {
				return false;
			}

			return true;
		}

		/**
		 * Sets specific WooCommerce checkout fields to blank when necessary.
		 *
		 * @param array $fields Checkout fields array.
		 *
		 * @return array Updated checkout fields.
		 */

		public function set_blank_checkout_fields( $fields ) {

			/**
			 * Iterate over checkout fields (billing & shipping) and reset matching ones to blank.
			 */

			foreach ( $fields as $field_key => $field ) {
				if ( ! ( $field_key === 'billing' || $field_key === 'shipping' ) ) {
					continue;
				}

				foreach ( $field as $key => $_field ) {
					if ( ! $this->match_field_key( $key ) ) {
						continue;
					}
					if ( isset( $fields[ $field_key ][ $key ] ) ) {
						$fields[ $field_key ][ $key ]['default'] = '';
					}
				}

			}


			return $fields;
		}

		/**
		 * Ensures specific checkout fields remain blank when retrieved.
		 *
		 * @param mixed $value Field value.
		 * @param string $key Field key.
		 *
		 * @return mixed Updated value.
		 */

		public function ensure_blank_checkout_fields( $value, $key ) {


			/**
			 * If the field key matches specific address-related fields, return blank.
			 */
			if ( $this->match_field_key( $key ) ) {
				return '';
			}


			return $value;


		}

		/**
		 * Checks if a field key corresponds to specific address fields (postcode, city, state, country).
		 *
		 * @param string $key Field key.
		 *
		 * @return bool True if it matches, otherwise false.
		 */

		public function match_field_key( $key ) {
			if ( $this->default_wc_location == '' && ( false !== strpos( $key, '_postcode' ) || false !== strpos( $key, '_city' ) || false !== strpos( $key, '_state' ) || false !== strpos( $key, '_country' ) ) ) {
				return true;
			} else if ( ( ( $this->default_wc_location === 'base' || $this->default_wc_location === 'geolocation' ) && false !== strpos( $key, '_postcode' ) ) || false !== strpos( $key, '_city' ) ) {
				return true;
			}

			return false;
		}

		public function do_no_set_default_value( $field_value, $key ) {

			if ( ! class_exists( '\WFACP_Core' ) ) {
				return $field_value;
			}

			/*
			 * Check rewards free shipping is set and geo location is off in the funnelkit cart
			 */

			if ( false === $this->is_free_shipping_enabled_with_no_geolocation() ) {
				return $field_value;
			}


			if ( $this->match_field_key( $key ) ) {
				$field_value = null;
			}

			return $field_value;
		}

	}
}