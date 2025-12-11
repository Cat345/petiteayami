<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'wfob_Rule_Cart_Total_Full' ) ) {
	class wfob_Rule_Cart_Total_Full extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_total_full' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-order-bump' ),
				'!=' => __( 'is not equal to', 'woofunnels-order-bump' ),
				'>'  => __( 'is greater than', 'woofunnels-order-bump' ),
				'<'  => __( 'is less than', 'woofunnels-order-bump' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-order-bump' ),
				'<=' => __( 'is less or equal to', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Text';
		}

		public function is_match( $rule_data ) {
			$result = false;
			$items  = WC()->cart->get_cart();
			$price  = 0;
			foreach ( $items as $index => $cart ) {
				if ( isset( $cart['_wfob_product'] ) ) {
					$price += $cart['line_subtotal'] + $cart['line_subtotal_tax'];
				}

			}
			$cart_total = WC()->cart->get_total( 'total' );
			$cart_total = floatval( $cart_total );
			if ( $cart_total > 0 && $price > 0 ) {
				$cart_total -= $price;
			}

			if ( isset( $rule_data['condition'] ) ) {
				$value = (float) $rule_data['condition'];
				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $cart_total == $value;
						break;
					case '!=':
						$result = $cart_total != $value;
						break;
					case '>':
						$result = $cart_total > $value;
						break;
					case '<':
						$result = $cart_total < $value;
						break;
					case '>=':
						$result = $cart_total >= $value;
						break;
					case '<=':
						$result = $cart_total <= $value;
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Total' ) ) {
	class wfob_Rule_Cart_Total extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_total' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-order-bump' ),
				'!=' => __( 'is not equal to', 'woofunnels-order-bump' ),
				'>'  => __( 'is greater than', 'woofunnels-order-bump' ),
				'<'  => __( 'is less than', 'woofunnels-order-bump' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-order-bump' ),
				'<=' => __( 'is less or equal to', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Text';
		}

		public function is_match( $rule_data ) {
			global $woocommerce;
			$result = false;
			$items  = WC()->cart->get_cart();
			$price  = 0;
			foreach ( $items as $index => $cart ) {
				if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart, __CLASS__ ) ) {
					continue;
				}
				if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart['_wfob_product'] ), $cart, __CLASS__ ) ) {
					continue;
				}

				if ( ! $woocommerce->cart->prices_include_tax ) {
					$price += $cart['line_subtotal'];
				} else {
					$price += $cart['line_subtotal'] + $cart['line_subtotal_tax'];
				}
			}

			if ( isset( $rule_data['condition'] ) ) {
				$value = (float) $rule_data['condition'];
				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $price == $value;
						break;
					case '!=':
						$result = $price != $value;
						break;
					case '>':
						$result = $price > $value;
						break;
					case '<':
						$result = $price < $value;
						break;
					case '>=':
						$result = $price >= $value;
						break;
					case '<=':
						$result = $price <= $value;
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Product' ) ) {
	class wfob_Rule_Cart_Product extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_product' );
		}

		public function get_possible_rule_values() {

		}

		public function get_possible_rule_operators() {

			$operators = array(
				'<'  => __( 'contains at most', 'woofunnels-order-bump' ),
				'>'  => __( 'contains at least', 'woofunnels-order-bump' ),
				'==' => __( 'contains exactly', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Cart_Product_Select';
		}

		public function is_match( $rule_data ) {
			global $woocommerce;

			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}

			$cart_contents = $woocommerce->cart->get_cart();

			$products = $rule_data['condition']['products'];
			$quantity = $rule_data['condition']['qty'];
			$type     = $rule_data['operator'];
			if ( ! is_array( $products ) || empty( $products ) ) {
				$products = [];
			}
			$found_quantity = 0;

			if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) ) {
				foreach ( $cart_contents as $cart_item_key => $cart_item ) {
					if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart_item, __CLASS__ ) ) {
						continue;
					}
					if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart_item['_wfob_product'] ), $cart_item, __CLASS__ ) ) {
						continue;
					}
					if ( in_array( $cart_item['product_id'], $products ) || ( isset( $cart_item['variation_id'] ) && in_array( $cart_item['variation_id'], $products ) ) ) {
						$found_quantity += $cart_item['quantity'];
					}
				}
			}

			switch ( $type ) {
				case '<':
					$result = $quantity >= $found_quantity;
					break;
				case '>':
					$result = $quantity <= $found_quantity;
					break;
				case '==':
					$result = $quantity == $found_quantity;
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Category' ) ) {
	class wfob_Rule_Cart_Category extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_category' );

		}

		public function get_possible_rule_operators() {

			$operators = array(
				'>'    => __( 'matches any of', 'woofunnels-order-bump' ),
				'<'    => __( 'matches all of', 'woofunnels-order-bump' ),
				'=='   => __( 'contains exactly', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$terms = get_terms( 'product_cat', array(
				'hide_empty' => false,
			) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[ absint( $term->term_id ) ] = $term->name;
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Cart_Category_Select';
		}

		public function match_exactly( $rule_tags, $product_terms ) {
			if ( empty( $rule_tags ) || empty( $product_terms ) ) {
				return false;
			}

			return count( array_intersect( $rule_tags, $product_terms ) ) === count( $rule_tags );
		}

		public function is_match( $rule_data ) {
			global $woocommerce;
			if ( is_null( WC()->cart ) ) {
				return false;
			}
			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}
			$cart_contents   = $woocommerce->cart->get_cart();
			$categories      = $rule_data['condition']['categories'];
			$type            = $rule_data['operator'];
			$all_terms       = [];
			$contain_exactly = null;
			if ( empty( $categories ) ) {
				return false;
			}
			if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) ) {
				foreach ( $cart_contents as $cart_item_key => $cart_item ) {
					if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart_item, __CLASS__ ) ) {
						continue;
					}
					if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart_item['_wfob_product'] ), $cart_item, __CLASS__ ) ) {
						continue;
					}
					$terms = wp_get_object_terms( $cart_item['product_id'], 'product_cat', array(
						'fields' => 'ids',
					) );
					if ( $type === '==' && false !== $contain_exactly ) {
						$contain_exactly = $this->match_exactly( $categories, $terms );

					}
					$all_terms = array_merge( $all_terms, $terms );
				}
			}

			$result = true;
			if ( empty( $all_terms ) || empty( $categories ) ) {
				$result = ( 'none' === $type ) ? true : false;

				return $this->return_is_match( $result, $rule_data );
			}
			$categories = array_map( 'absint', $categories );
			switch ( $type ) {
				case '<':
					// All match
					if ( is_array( $categories ) && is_array( $all_terms ) ) {
						$result = count( array_intersect( $categories, $all_terms ) ) === count( $categories );
					}
					break;
				case '>':
					//Any Matched
					if ( is_array( $categories ) && is_array( $all_terms ) ) {
						$result = count( array_intersect( $categories, $all_terms ) ) >= 1;
					}
					break;
				case '==':
					//contain exactly
					$result = $contain_exactly;
					break;
				case 'none':
					//Do not match
					if ( is_array( $categories ) && is_array( $all_terms ) ) {
						$result = count( array_intersect( $categories, $all_terms ) ) === 0;
					}
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_tags' ) ) {
	class wfob_Rule_Cart_tags extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_tags' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'>'    => __( 'matches any of', 'woofunnels-order-bump' ),
				'<'    => __( 'matches all of', 'woofunnels-order-bump' ),
				'=='   => __( 'contains exactly', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$terms = get_terms( 'product_tag', array(
				'hide_empty' => false,
			) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[ absint( $term->term_id ) ] = $term->name;
				}
			}


			return $result;
		}

		public function get_condition_input_type() {
			return 'Cart_Tag_Select';
		}

		public function match_exactly( $rule_tags, $product_terms ) {
			if ( empty( $rule_tags ) || empty( $product_terms ) ) {
				return false;
			}

			return count( array_intersect( $rule_tags, $product_terms ) ) === count( $rule_tags );
		}

		public function is_match( $rule_data ) {
			global $woocommerce;
			if ( is_null( WC()->cart ) ) {
				return false;
			}

			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}

			$cart_contents = $woocommerce->cart->get_cart();
			$categories    = $rule_data['condition']['categories'];
			$type          = $rule_data['operator'];
			$all_terms     = [];
			if ( empty( $categories ) ) {
				return false;
			}
			$contain_exactly = null;
			if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) ) {
				foreach ( $cart_contents as $cart_item_key => $cart_item ) {
					if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart_item, __CLASS__ ) ) {
						continue;
					}
					if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart_item['_wfob_product'] ), $cart_item, __CLASS__ ) ) {
						continue;
					}
					$terms = wp_get_object_terms( $cart_item['product_id'], 'product_tag', array(
						'fields' => 'ids',
					) );
					if ( $type === '==' && false !== $contain_exactly ) {
						$contain_exactly = $this->match_exactly( $categories, $terms );

					}
					$all_terms = array_merge( $all_terms, $terms );
				}
			}
			$result = true;

			if ( empty( $all_terms ) || empty( $categories ) ) {
				$result = ( 'none' === $type ) ? true : false;

				return $this->return_is_match( $result, $rule_data );
			}
			$categories = array_map( 'absint', $categories );
			switch ( $type ) {
				case '<':
					// All match
					if ( is_array( $categories ) && is_array( $all_terms ) ) {
						$result = count( array_intersect( $categories, $all_terms ) ) === count( $categories );
					}
					break;
				case '>':
					//Any Matched
					if ( is_array( $categories ) && is_array( $all_terms ) ) {
						$result = count( array_intersect( $categories, $all_terms ) ) >= 1;
					}
					break;
				case '==':
					$result = $contain_exactly;
					break;
				case 'none':
					//Do not match
					if ( is_array( $categories ) && is_array( $all_terms ) ) {
						$result = count( array_intersect( $categories, $all_terms ) ) === 0;
					}
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Items_Count' ) ) {
	class wfob_Rule_Cart_Items_Count extends wfob_Rule_Base {
		private $items_count = 0;

		public function __construct() {
			parent::__construct( 'cart_items_count' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-order-bump' ),
				'!=' => __( 'is not equal to', 'woofunnels-order-bump' ),
				'>'  => __( 'is greater than', 'woofunnels-order-bump' ),
				'<'  => __( 'is less than', 'woofunnels-order-bump' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-order-bump' ),
				'<=' => __( 'is less or equal to', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Text';
		}


		public function is_match( $rule_data ) {
			$result        = false;
			$cart_contents = WC()->cart->get_cart_contents();
			$count         = 0;
			foreach ( $cart_contents as $cart_item ) {
				if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart_item, __CLASS__ ) ) {
					continue;
				}
				if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart_item['_wfob_product'] ), $cart_item, __CLASS__ ) ) {
					continue;
				}
				$count += $cart_item['quantity'];
			}
			if ( isset( $rule_data['condition'] ) ) {
				$value = (float) $rule_data['condition'];
				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $count == $value;
						break;
					case '!=':
						$result = $count != $value;
						break;
					case '>':
						$result = $count > $value;
						break;
					case '<':
						$result = $count < $value;
						break;
					case '>=':
						$result = $count >= $value;
						break;
					case '<=':
						$result = $count <= $value;
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Item_Count' ) ) {
	class wfob_Rule_Cart_Item_Count extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_item_count' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-order-bump' ),
				'!=' => __( 'is not equal to', 'woofunnels-order-bump' ),
				'>'  => __( 'is greater than', 'woofunnels-order-bump' ),
				'<'  => __( 'is less than', 'woofunnels-order-bump' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-order-bump' ),
				'<=' => __( 'is less or equal to', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Text';
		}

		public function is_match( $rule_data ) {
			$count        = 0;
			$result       = false;
			$cart_content = WC()->cart->get_cart_contents();
			if ( count( $cart_content ) > 0 ) {
				foreach ( $cart_content as $key => $item ) {
					if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $item, __CLASS__ ) ) {
						continue;
					}
					if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $item['_wfob_product'] ), $item, __CLASS__ ) ) {
						continue;
					}
					$count ++;
				}
			}
			if ( isset( $rule_data['condition'] ) ) {
				$value = (float) $rule_data['condition'];
				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $count == $value;
						break;
					case '!=':
						$result = $count != $value;
						break;
					case '>':
						$result = $count > $value;
						break;
					case '<':
						$result = $count < $value;
						break;
					case '>=':
						$result = $count >= $value;
						break;
					case '<=':
						$result = $count <= $value;
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Item_Type' ) ) {
	class wfob_Rule_Cart_Item_Type extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_item_type' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any' => __( 'matched any of', 'woofunnels-order-bump' ),
				'all' => __( 'matches all of ', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of',  'woofunnels-order-bump'),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$terms = get_terms( 'product_type', array(
				'hide_empty' => false,
			) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[ $term->term_id ] = $term->name;
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {

			$type = $rule_data['operator'];

			$cart      = WC()->cart->get_cart_contents();
			$all_types = array();
			$result    = false;

			/**
			 * @var $cart_item WC_Order_Item
			 */
			if ( $cart && count( $cart ) > 0 ) {
				foreach ( $cart as $item_key => $cart_item ) {
					if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart_item, __CLASS__ ) ) {
						continue;
					}

					if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart_item['_wfob_product'] ), $cart_item, __CLASS__ ) ) {
						continue;
					}


					$product = $cart_item['data'];
					if ( ! $product instanceof WC_Product ) {
						continue;
					}
					$product_id = $product->get_id();
					$product_id = ( WFOB_Common::get_product_parent_id( $product ) ) ? WFOB_Common::get_product_parent_id( $product ) : $product_id;

					$product_types = wp_get_post_terms( $product_id, 'product_type', array(
						'fields' => 'ids',
					) );
					$all_types     = array_merge( $all_types, $product_types );

				}
			}
			$all_types = array_filter( $all_types );
			if ( empty( $all_types ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'all':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_types ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_types ) ) === count( $rule_data['condition'] );
						}
						break;
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_types ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_types ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_types ) ) {
							$result = ( count( array_intersect( $rule_data['condition'], $all_types ) ) === 0 );
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Coupons' ) ) {
	class wfob_Rule_Cart_Coupons extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_coupons' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-order-bump' ),
				'all'  => __( 'matches all of ', 'woofunnels-order-bump' ),
				'none' => __( 'matched none of', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$coupons = get_posts( array(
				'post_type'      => 'shop_coupon',
				'posts_per_page' => defined( 'REST_REQUEST' ) ? 5 : - 1,

			) );

			foreach ( $coupons as $coupon ) {
				$result[ sanitize_title( $coupon->post_title ) ] = $coupon->post_title;
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {

			$type         = $rule_data['operator'];
			$used_coupons = WC()->cart->get_coupons();
			$result       = false;

			if ( empty( $used_coupons ) ) {
				if ( $type === 'all' || $type === 'any' ) {
					$res = false;
				} else {
					$res = true;
				}

				return $this->return_is_match( $res, $rule_data );
			} else {
				$used_coupons = array_keys( $used_coupons );

			}
			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'all':
						if ( is_array( $rule_data['condition'] ) && is_array( $used_coupons ) ) {
							$result = count( array_intersect( $rule_data['condition'], $used_coupons ) ) === count( $rule_data['condition'] );
						}
						break;
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $used_coupons ) ) {
							$result = count( array_intersect( $rule_data['condition'], $used_coupons ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $used_coupons ) ) {
							$result = count( array_intersect( array_map( 'strtolower', $rule_data['condition'] ), array_map( 'strtolower', $used_coupons ) ) ) === 0;
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Payment_Gateway' ) ) {
	class wfob_Rule_Cart_Payment_Gateway extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_payment_gateway' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'is'     => __( 'is', 'woofunnels-order-bump' ),
				'is_not' => __( 'is not', 'woofunnels-order-bump' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
				if ( $gateway->enabled === 'yes' ) {
					$result[ $gateway->id ] = ! empty( $gateway->get_title() ) ? $gateway->get_title() : $gateway->get_method_title();
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {
			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}

			$type      = $rule_data['operator'];
			$condition = $rule_data['condition'];

			if ( ! is_array( $condition ) || empty( $condition ) ) {
				$condition = [];
			}
			$payment = WC()->session->get( 'wfob_payment_method', '' );
			//      WC()->cart->get_gat

			if ( empty( $payment ) ) {
				if ( $type == 'is_not' ) {
					return $this->return_is_match( true, $rule_data );
				}

				return $this->return_is_match( false, $rule_data );
			}

			switch ( $type ) {

				case 'is':
					$result = in_array( $payment, $condition );
					break;
				case 'is_not':
					$result = ! in_array( $payment, $condition );
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Shipping_Country' ) ) {
	class wfob_Rule_Cart_Shipping_Country extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_shipping_country' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of ', 'woofunnels-order-bump' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$result = WC()->countries->get_allowed_countries();

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {
			$result = false;

			$type             = $rule_data['operator'];
			$shipping_country = WC()->session->get( 'wfob_shipping_country', '' );
			if ( empty( $shipping_country ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			$shipping_country = array( $shipping_country );

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {

					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $shipping_country ) ) {
							$result = count( array_intersect( $rule_data['condition'], $shipping_country ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $shipping_country ) ) {
							$result = count( array_intersect( $rule_data['condition'], $shipping_country ) ) === 0;
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Billing_Country' ) ) {
	class wfob_Rule_Cart_Billing_Country extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_billing_country' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of ', 'woofunnels-order-bump' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$result = WC()->countries->get_allowed_countries();

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {
			$result = false;
			$type   = $rule_data['operator'];

			$billing_country = WC()->session->get( 'wfob_billing_country', '' );

			if ( empty( $billing_country ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			$billing_country = array( $billing_country );
			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {

					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $billing_country ) ) {
							$result = count( array_intersect( $rule_data['condition'], $billing_country ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $billing_country ) ) {
							$result = count( array_intersect( $rule_data['condition'], $billing_country ) ) === 0;
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Shipping_Method' ) ) {
	class wfob_Rule_Cart_Shipping_Method extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_shipping_method' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of ', 'woofunnels-order-bump' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			foreach ( WC()->shipping()->get_shipping_methods() as $method_id => $method ) {
				// get_method_title() added in WC 2.6
				$result[ $method_id ] = is_callable( array(
					$method,
					'get_method_title'
				) ) ? $method->get_method_title() : $method->get_title();
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {
			$result = false;
			$type   = $rule_data['operator'];

			$methods = $this->get_ids();
			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {

					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $methods ) ) {
							$result = count( array_intersect( $rule_data['condition'], $methods ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $methods ) ) {
							$result = count( array_intersect( $rule_data['condition'], $methods ) ) == 0;
						}
						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		private function get_ids() {
			$method_ids     = array();
			$chosen_methods = WC()->session->get( 'wfob_shipping_method', array() );
			foreach ( $chosen_methods as $chosen_method ) {
				$chosen_method = explode( ':', $chosen_method );
				$method_ids[]  = current( $chosen_method );
			}

			return $method_ids;

		}

	}
}
if ( ! class_exists( 'wfob_Rule_Cart_Item' ) ) {
	class wfob_Rule_Cart_Item extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'cart_item' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'>'  => __( 'contains at least', 'woofunnels-order-bump' ),
				'<'  => __( 'contains at most', 'woofunnels-order-bump' ),
				'==' => __( 'contains exactly', 'woofunnels-order-bump' ),
				'!=' => __( 'does not contains at least', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Cart_Product_Select';
		}

		public function is_match( $rule_data ) {
			if ( is_null( WC()->cart ) ) {
				return false;
			}
			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}

			$items    = WC()->cart->get_cart_contents();
			$products = $rule_data['condition']['products'];
			if ( ! is_array( $products ) || empty( $products ) ) {
				$products = [];
			}
			$quantity       = $rule_data['condition']['qty'];
			$type           = $rule_data['operator'];
			$found_quantity = 0;

			if ( $items && is_array( $items ) && count( $items ) > 0 ) {
				foreach ( $items as $item_key => $cart_item ) {
					if ( apply_filters( 'wfob_exclude_cart_item_in_rule', false, $cart_item, __CLASS__ ) ) {
						continue;
					}


					if ( apply_filters( 'wfob_dont_allow_bump_item_in_rule', isset( $cart_item['_wfob_product'] ), $cart_item, __CLASS__ ) ) {
						continue;
					}
					$product = $cart_item['data'];
					if ( ! $product instanceof WC_Product ) {
						continue;
					}
					$product_id  = $product->get_id();
					$product_id  = ( WFOB_Common::get_product_parent_id( $product ) ) ? WFOB_Common::get_product_parent_id( $product ) : $product_id;
					$variationID = ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0 ) ? $cart_item['variation_id'] : 0;

					if ( in_array( $product_id, $products ) || ( ( $product_id ) && in_array( $variationID, $products ) ) ) {
						$found_quantity += $cart_item['quantity'];
					}
				}
			}
			if ( $found_quantity === 0 ) {

				if ( '!=' == $type ) {
					return $this->return_is_match( true, $rule_data );
				}

				return $this->return_is_match( false, $rule_data );
			}

			switch ( $type ) {
				case '<':
					$result = ( $quantity >= $found_quantity );
					break;
				case '>':
					$result = ( $quantity <= $found_quantity );
					break;
				case '==':
					$result = ( $quantity == $found_quantity );
					break;
				case '!=':
					$result = ! ( $quantity <= $found_quantity );
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Order_Coupon_Text_Match' ) ) {
	class wfob_Rule_Order_Coupon_Text_Match extends wfob_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {

			parent::__construct( 'order_coupon_text_match' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'contains'       => __( 'any contains', 'woofunnels-order-bump' ),
				'starts_with'    => __( 'any starts with', 'woofunnels-order-bump' ),
				'ends_with'      => __( 'any ends with', 'woofunnels-order-bump' ),
				'doesnt_contain' => __( 'doesn\'t contain', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = '';

			return $result;
		}

		public function get_condition_input_type() {
			return 'Coupon_Text_Match';
		}

		public function is_match( $rule_data ) {

			$type         = $rule_data['operator'];
			$used_coupons = array();


			$cart_contents = WC()->cart->get_coupons();
			if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
				$used_coupons = array_keys( $cart_contents );
			}


			$result = false;
			if ( empty( $used_coupons ) || empty( $rule_data['condition'] ) ) {

				if ( $type === "doesnt_contain" ) {
					$result = true;
				}


				return $this->return_is_match( $result, $rule_data );
			}

			$matched = false;
			foreach ( $used_coupons as $coupon ) {
				switch ( $type ) {
					case 'contains':
						$matched = ( stristr( $coupon, $rule_data['condition'] ) !== false );
						break;
					case 'doesnt_contain':
						$matched = ( stristr( $coupon, $rule_data['condition'] ) === false );
						break;
					case 'starts_with':
						$matched = strtolower( substr( $coupon, 0, strlen( $rule_data['condition'] ) ) ) === strtolower( $rule_data['condition'] );
						break;

					case 'ends_with':
						$matched = strtolower( substr( $coupon, - strlen( $rule_data['condition'] ) ) ) === strtolower( $rule_data['condition'] );
						break;

					default:
						$matched = false;
						break;
				}
				if ( $matched ) {
					return $this->return_is_match( $matched, $rule_data );
				}
			}

			return $this->return_is_match( $matched, $rule_data );
		}

	}
}
