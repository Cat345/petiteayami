<?php
if ( ! class_exists( 'WFOCU_Rule_Order_Total' ) ) {
	class WFOCU_Rule_Order_Total extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_total' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-upstroke-one-click-upsell' ),
				'!=' => __( 'is not equal to', 'woofunnels-upstroke-one-click-upsell' ),
				'>'  => __( 'is greater than', 'woofunnels-upstroke-one-click-upsell' ),
				'<'  => __( 'is less than', 'woofunnels-upstroke-one-click-upsell' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-upstroke-one-click-upsell' ),
				'=<' => __( 'is less or equal to', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Text';
		}

		public function is_match( $rule_data, $env = 'cart' ) {

			$result = false;
			if ( $env === 'cart' ) {
				$price = WC()->cart->get_total( 'edit' );
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$price = $order->get_total();
			}

			if ( isset( $rule_data['condition'] ) ) {
				$value = (float) $rule_data['condition'];
				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $price == $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						break;
					case '!=':
						$result = $price != $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
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
					case '=<':
						$result = $price <= $value;
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {
			return sprintf( __( 'Order Total %s  <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), wc_price( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Item_Count' ) ) {

	class WFOCU_Rule_Order_Item_Count extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_item_count' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-upstroke-one-click-upsell' ),
				'!=' => __( 'is not equal to', 'woofunnels-upstroke-one-click-upsell' ),
				'>'  => __( 'is greater than', 'woofunnels-upstroke-one-click-upsell' ),
				'<'  => __( 'is less than', 'woofunnels-upstroke-one-click-upsell' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-upstroke-one-click-upsell' ),
				'=<' => __( 'is less or equal to', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Text';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			if ( $env === 'cart' ) {
				$quantities = WC()->cart->get_cart_item_quantities();
				$count      = array_sum( $quantities );
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$count = $order->get_item_count();
			}
			if ( isset( $rule_data['condition'] ) ) {
				$value = (float) $rule_data['condition'];
				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $count == $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						break;
					case '!=':
						$result = $count != $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
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
					case '=<':
						$result = $count <= $value;
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order Items count %s  <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $rule['condition'] );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Item' ) ) {
	class WFOCU_Rule_Order_Item extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_item' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'>' => __( 'contains at least', 'woofunnels-upstroke-one-click-upsell' ),
				'<' => __( 'contains less than', 'woofunnels-upstroke-one-click-upsell' ),

				'==' => __( 'contains exactly', 'woofunnels-upstroke-one-click-upsell' ),
				'!=' => __( "does not contains at least", 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Cart_Product_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {

			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}

			$products       = $rule_data['condition']['products'];
			$quantity       = $rule_data['condition']['qty'];
			$type           = $rule_data['operator'];
			$found_quantity = 0;
			if ( $env === 'cart' ) {

				$cart_contents = (array) WC()->cart->cart_contents;
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					foreach ( $cart_contents as $cart_item ) {
						$productID   = $cart_item['product_id'];
						$variationID = $cart_item['variation_id'];
						if ( absint( $productID ) === 0 ) {
							if ( $cart_item['data'] instanceof WC_Product_Variation ) {
								$productID = $cart_item['data']->get_parent_id();
							} elseif ( $cart_item['data'] instanceof WC_Product ) {
								$productID = $cart_item['data']->get_id();
							}
						}

						if ( absint( $productID ) === absint( $products ) || ( ( $productID ) && absint( $variationID ) === absint( $products ) ) ) {
							$found_quantity += $cart_item['quantity'];
						}
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );
				if ( $order->get_items() && is_array( $order->get_items() ) && count( $order->get_items() ) ) {
					foreach ( $order->get_items() as $cart_item ) {

						$product   = WFOCU_WC_Compatibility::get_product_from_item( $order, $cart_item );
						$productID = $product->get_id();

						$productID = ( $product->get_parent_id() ) ? $product->get_parent_id() : $productID;

						if ( version_compare( WC()->version, '3.0', '>=' ) ) {
							$variationID = $cart_item->get_variation_id();
						} else {
							$variationID = ( is_array( $cart_item['variation_id'] ) && count( $cart_item['variation_id'] ) > 0 ) ? $cart_item['variation_id'][0] : 0;
						}

						if ( absint( $productID ) === absint( $products ) || ( ( $productID ) && absint( $variationID ) === absint( $products ) ) ) {

							$found_quantity += $cart_item['qty'];
						}
					}
				}
			}
			if ( $found_quantity === 0 ) {
				if ( '!=' === $type ) {
					return $this->return_is_match( true, $rule_data );
				}

				return $this->return_is_match( false, $rule_data );
			}
			switch ( $type ) {
				case '<':
					$result = $quantity > $found_quantity;
					break;
				case '>':
					$result = $quantity <= $found_quantity;
					break;
				case '==':
					$result = absint( $quantity ) === absint( $found_quantity );
					break;
				case '!=' :
					$result = ! ( $quantity <= $found_quantity );
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order items %s <strong>%s qty</strong> of <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $rule['condition']['qty'], wc_get_product( $rule['condition']['products'] )->get_title() );
		}

		public function get_operators_string( $operator ) {
			switch ( $operator ) {
				case '!=':
					return __( 'doesn\'t contain atleast', 'woofunnels-order-bump' );
					break;
				case '==':
					return __( 'contains exactly', 'woofunnels-order-bump' );
					break;

				case '>':
					return __( 'contains at least', 'woofunnels-upstroke-one-click-upsell' );
					break;
				case '<':
					return __( 'contains less than', 'woofunnels-upstroke-one-click-upsell' );

			}
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Category' ) ) {
	class WFOCU_Rule_Order_Category extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_category' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$terms = get_terms( 'product_cat', array( 'hide_empty' => false ) );
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

		public function is_match( $rule_data, $env = 'cart' ) {
			$result    = false;
			$type      = $rule_data['operator'];
			$all_terms = array();

			if ( $env === 'cart' ) {

				$cart_contents = WC()->cart->get_cart_contents();
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					foreach ( $cart_contents as $cart_item ) {
						$productID = $cart_item['product_id'];
						$terms     = wp_get_object_terms( $productID, 'product_cat', array( 'fields' => 'ids' ) );

						$all_terms = array_merge( $all_terms, $terms );
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );
				if ( $order->get_items() && is_array( $order->get_items() ) && count( $order->get_items() ) ) {
					foreach ( $order->get_items() as $cart_item ) {
						$product = WFOCU_WC_Compatibility::get_product_from_item( $order, $cart_item );

						$productID = $product->get_id();
						$productID = ( $product->get_parent_id() ) ? $product->get_parent_id() : $productID;

						$terms = wp_get_object_terms( $productID, 'product_cat', array( 'fields' => 'ids' ) );

						$all_terms = array_merge( $all_terms, $terms );

					}
				}
			}
			$all_terms = array_filter( $all_terms );
			if ( empty( $all_terms ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'all':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_terms ) ) === count( $rule_data['condition'] );
						}
						break;
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_terms ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = ( count( array_intersect( $rule_data['condition'], $all_terms ) ) === 0 );
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order items %s Products with catergory(s) <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_category_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Term' ) ) {

	class WFOCU_Rule_Order_Term extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_term' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$terms = get_terms( 'product_tag', array( 'hide_empty' => false ) );
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

		public function is_match( $rule_data, $env = 'cart' ) {
			$result    = false;
			$type      = $rule_data['operator'];
			$all_terms = array();

			if ( $env === 'cart' ) {

				$cart_contents = WC()->cart->get_cart_contents();
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					foreach ( $cart_contents as $cart_item ) {
						$productID = $cart_item['product_id'];
						$terms     = wp_get_object_terms( $productID, 'product_tag', array( 'fields' => 'ids' ) );

						$all_terms = array_merge( $all_terms, $terms );
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );
				if ( $order->get_items() && is_array( $order->get_items() ) && count( $order->get_items() ) ) {
					foreach ( $order->get_items() as $cart_item ) {
						$product = WFOCU_WC_Compatibility::get_product_from_item( $order, $cart_item );

						$productID = $product->get_id();
						$productID = ( $product->get_parent_id() ) ? $product->get_parent_id() : $productID;

						$terms = wp_get_object_terms( $productID, 'product_tag', array( 'fields' => 'ids' ) );

						$all_terms = array_merge( $all_terms, $terms );

					}
				}
			}
			$all_terms = array_filter( $all_terms );

			if ( empty( $all_terms ) && $type !== 'none' ) {
				return $this->return_is_match( false, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'all':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_terms ) ) === count( $rule_data['condition'] );
						}
						break;
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_terms ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = ( count( array_intersect( $rule_data['condition'], $all_terms ) ) === 0 );
						}
						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order items %s Products with term(s) <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_terms_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Item_Type' ) ) {
	class WFOCU_Rule_Order_Item_Type extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_item_type' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = [];
			$terms  = get_terms( 'product_type', array( 'hide_empty' => false ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( 'grouped' === $term->name ) {
						continue;
					}
					$result[ $term->term_id ] = $term->name; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result    = false;
			$type      = $rule_data['operator'];
			$all_types = array();
			if ( $env === 'cart' ) {

				$cart_contents = WC()->cart->get_cart_contents();
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					foreach ( $cart_contents as $cart_item ) {
						$productID = $cart_item['product_id'];
						if ( absint( $productID ) === 0 ) {
							if ( $cart_item['data'] instanceof WC_Product_Variation ) {
								$productID = $cart_item['data']->get_parent_id();
							} elseif ( $cart_item['data'] instanceof WC_Product ) {
								$productID = $cart_item['data']->get_id();
							}
						}

						$product_types = wp_get_post_terms( $productID, 'product_type', array( 'fields' => 'ids' ) );

						$all_types = array_merge( $all_types, $product_types );
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				if ( $order->get_items() && count( $order->get_items() ) ) {
					foreach ( $order->get_items() as $cart_item ) {
						$product = WFOCU_WC_Compatibility::get_product_from_item( $order, $cart_item );

						$productID = $product->get_id();
						$productID = ( $product->get_parent_id() ) ? $product->get_parent_id() : $productID;

						$product_types = wp_get_post_terms( $productID, 'product_type', array( 'fields' => 'ids' ) );

						$all_types = array_merge( $all_types, $product_types );

					}
				}
			}

			$all_types = array_filter( $all_types );
			if ( empty( $all_types ) && $type !== 'none' ) {
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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order items %s Products with type(s) <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_product_type( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Coupons' ) ) {
	class WFOCU_Rule_Order_Coupons extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_coupons' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matched none of', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result  = array();
			$coupons = get_posts( array( //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
				'post_type'      => 'shop_coupon',
				'posts_per_page' => 5,

			) );

			foreach ( $coupons as $coupon ) {
				$result[ sanitize_title( $coupon->post_title ) ] = $coupon->post_title;
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Coupon_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result       = false;
			$type         = $rule_data['operator'];
			$used_coupons = array();

			if ( $env === 'cart' ) {
				$cart_contents = WC()->cart->get_coupons();
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					$used_coupons = array_keys( $cart_contents );
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$used_coupons = BWF_WC_Compatibility::get_used_coupons( $order );
			}
			if ( empty( $used_coupons ) ) {
				if ( $type === 'all' || $type === 'any' ) {
					$res = false;
				} else {
					$res = true;
				}

				return $this->return_is_match( $res, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'all':
						if ( is_array( $rule_data['condition'] ) && is_array( $used_coupons ) ) {
							$result = count( array_intersect( array_map( 'strtolower', $rule_data['condition'] ), array_map( 'strtolower', $used_coupons ) ) ) === count( $rule_data['condition'] );
						}
						break;
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $used_coupons ) ) {
							$result = count( array_intersect( array_map( 'strtolower', $rule_data['condition'] ), array_map( 'strtolower', $used_coupons ) ) ) >= 1;
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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order %s coupons(s) <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_coupons_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Coupon_Exist' ) ) {
	class WFOCU_Rule_Order_Coupon_Exist extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_coupon_exist' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'exist'     => __( 'exist', 'woofunnels-upstroke-one-click-upsell' ),
				'not_exist' => __( 'not exist', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array( 'parent_order' => __( 'In parent order', 'woofunnels-upstroke-one-click-upsell' ) );

			return $result;
		}

		public function get_condition_input_type() {
			return 'Coupon_Exist';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$type         = $rule_data['operator'];
			$used_coupons = array();

			if ( $env === 'cart' ) {
				$cart_contents = WC()->cart->get_coupons();
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					$used_coupons = array_keys( $cart_contents );
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$used_coupons = BWF_WC_Compatibility::get_used_coupons( $order );
			}
			$res = true;
			if ( empty( $used_coupons ) ) {
				if ( $type === 'exist' ) {
					$res = false;
				}

				return $this->return_is_match( $res, $rule_data );
			}

			if ( $type === 'not_exist' ) {
				$res = false;
			}

			return $this->return_is_match( $res, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order %s any coupon. ', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Coupon_Text_Match' ) ) {
	class WFOCU_Rule_Order_Coupon_Text_Match extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_coupon_text_match' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'contains'       => __( 'any contains', 'woofunnels-upstroke-one-click-upsell' ),
				'starts_with'    => __( 'any starts with', 'woofunnels-upstroke-one-click-upsell' ),
				'ends_with'      => __( 'any ends with', 'woofunnels-upstroke-one-click-upsell' ),
				'doesnt_contain' => __( 'doesn\'t contain', 'woofunnels-upstroke-one-click-upsell' ),
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

		public function is_match( $rule_data, $env = 'cart' ) {

			$type         = $rule_data['operator'];
			$used_coupons = array();

			if ( $env === 'cart' ) {
				$cart_contents = WC()->cart->get_coupons();
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					$used_coupons = array_keys( $cart_contents );
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$used_coupons = BWF_WC_Compatibility::get_used_coupons( $order );
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

					case 'doesnt_contain':
						$matched = ( stristr( $coupon, $rule_data['condition'] ) === false );
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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order %s coupon that matches with %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $rule['condition'] );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Custom_Meta' ) ) {
	class WFOCU_Rule_Order_Custom_Meta extends WFOCU_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'order_custom_meta' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'is'     => __( 'is', 'woofunnels-upstroke-one-click-upsell' ),
				'is_not' => __( 'is not', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			return $result;
		}

		public function get_condition_input_type() {
			return 'Custom_Meta';
		}

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$type       = $rule_data['operator'];
			$order_meta = false;
			$order_id   = WFOCU_Core()->rules->get_environment_var( 'order' );
			if ( isset( $rule_data['condition'] ) && is_array( $rule_data['condition'] ) && $rule_data['condition']['meta_key'] !== '' ) {
				$meta_value = WFOCU_Common::get_order_meta( wc_get_order( $order_id ), $rule_data['condition']['meta_key'] );
				$order_meta = ( $rule_data['condition']['meta_value'] === $meta_value ) ? true : false;
			}

			switch ( $type ) {
				case 'is':
					$result = $order_meta;
					break;
				case 'is_not':
					$result = ( $order_meta === true ) ? false : true;
					break;
				default:
					$result = false;
					break;
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order %s meta %s with value %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $rule['condition']['meta_key'], $rule['condition']['meta_value'] );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Payment_Gateway' ) ) {
	class WFOCU_Rule_Order_Payment_Gateway extends WFOCU_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'order_payment_gateway' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'is'     => __( 'is', 'woofunnels-upstroke-one-click-upsell' ),
				'is_not' => __( 'is not', 'woofunnels-upstroke-one-click-upsell' ),

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

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$result = false;
			$type   = $rule_data['operator'];

			$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
			$order    = wc_get_order( $order_id );

			$payment = WFOCU_WC_Compatibility::get_payment_gateway_from_order( $order );

			if ( empty( $payment ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'is':
						$result = in_array( $payment, $rule_data['condition'], true );
						break;
					case 'is_not':
						$result = ! in_array( $payment, $rule_data['condition'], true );
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order payment method %s of %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_gateways_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Shipping_Country' ) ) {

	class WFOCU_Rule_Order_Shipping_Country extends WFOCU_Rule_Base {

		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_shipping_country' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

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

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			$type   = $rule_data['operator'];

			if ( $env === 'cart' ) {
				$shipping_country = ( WC()->customer->get_shipping_country( 'edit' ) );

			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$shipping_country = WFOCU_WC_Compatibility::get_shipping_country_from_order( $order );
			}

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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order shipping country %s %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_countries_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Shipping_Method' ) ) {

	class WFOCU_Rule_Order_Shipping_Method extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_shipping_method' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			foreach ( WC()->shipping()->get_shipping_methods() as $method_id => $method ) {
				// get_method_title() added in WC 2.6
				$result[ $method_id ] = is_callable( array( $method, 'get_method_title' ) ) ? $method->get_method_title() : $method->get_title();
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			$type   = $rule_data['operator'];

			$methods = [];
			if ( $env === 'cart' ) {
				$chosen = WC()->session->get( 'chosen_shipping_methods' );
				foreach ( $chosen as $method ) {
					// extract method slug only, discard instance id
					if ( $split = strpos( $method, ':' ) ) { //phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
						$methods[] = substr( $method, 0, $split );
					} else {
						$methods[] = $method;
					}
				}

			} else {

				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$methods = array();

				foreach ( $order->get_shipping_methods() as $method ) {
					// extract method slug only, discard instance id
					if ( $split = strpos( $method['method_id'], ':' ) ) { //phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
						$methods[] = substr( $method['method_id'], 0, $split );
					} else {
						$methods[] = $method['method_id'];
					}
				}
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {

					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $methods ) ) {
							$result = count( array_intersect( $rule_data['condition'], $methods ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $methods ) ) {
							$result = count( array_intersect( $rule_data['condition'], $methods ) ) === 0;
						}
						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order shipping method %s of %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_shipping_method_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Billing_Country' ) ) {

	class WFOCU_Rule_Order_Billing_Country extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_billing_country' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

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

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			$type   = $rule_data['operator'];

			if ( $env === 'cart' ) {
				$billing_country = ( WC()->customer->get_billing_country( 'edit' ) );

			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$billing_country = WFOCU_WC_Compatibility::get_billing_country_from_order( $order );
			}

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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order Billing country %s %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_countries_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Item_Text_Match' ) ) {
	class WFOCU_Rule_Order_Item_Text_Match extends WFOCU_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'order_item_text_match' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'contains'       => __( 'any contains', 'woofunnels-upstroke-one-click-upsell' ),
				'starts_with'    => __( 'any starts with', 'woofunnels-upstroke-one-click-upsell' ),
				'ends_with'      => __( 'any ends with', 'woofunnels-upstroke-one-click-upsell' ),
				'doesnt_contain' => __( 'doesn\'t contain', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = '';

			return $result;
		}

		public function get_condition_input_type() {
			return 'Item_Text_Match';
		}

		public function is_match( $rule_data, $env = 'cart' ) {

			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}
			$type       = $rule_data['operator'];
			$found_text = array();
			if ( $env === 'cart' ) {

				$cart_contents = (array) WC()->cart->cart_contents;
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					foreach ( $cart_contents as $cart_item ) {
						$productID   = $cart_item['product_id'];
						$variationID = $cart_item['variation_id'];
						if ( absint( $productID ) === 0 ) {
							if ( absint( $productID ) === 0 ) {
								if ( $cart_item['data'] instanceof WC_Product_Variation ) {
									$productID = $cart_item['data']->get_parent_id();
								} elseif ( $cart_item['data'] instanceof WC_Product ) {
									$productID = $cart_item['data']->get_id();
								}
							}
						}

						if ( 0 < absint( $productID ) ) {
							$product = wc_get_product( $productID );
							if ( $product instanceof WC_product ) {
								$found_text[] = $product->get_title();
							}
						}
						if ( 0 < absint( $variationID ) ) {
							$variation_product = new WC_Product_Variation( $variationID );
							if ( $variation_product instanceof WC_Product_Variation ) {
								if ( is_array( $variation_product->get_attributes() ) && count( $variation_product->get_attributes() ) > 0 ) {
									foreach ( $variation_product->get_attributes() as $attribute ) {
										$found_text[] = $attribute;
									}
								}

							}
						}

					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );
				if ( $order->get_items() && is_array( $order->get_items() ) && count( $order->get_items() ) > 0 ) {
					foreach ( $order->get_items() as $cart_item ) {

						$product   = WFOCU_WC_Compatibility::get_product_from_item( $order, $cart_item );
						$productID = $product->get_id();

						$productID = ( $product->get_parent_id() ) ? $product->get_parent_id() : $productID;

						if ( version_compare( WC()->version, '3.0', '>=' ) ) {
							$variationID = $cart_item->get_variation_id();
						} else {
							$variationID = ( is_array( $cart_item['variation_id'] ) && count( $cart_item['variation_id'] ) > 0 ) ? $cart_item['variation_id'][0] : 0;
						}

						if ( 0 < absint( $productID ) ) {
							$product = wc_get_product( $productID );
							if ( $product instanceof WC_product ) {
								$found_text[] = $product->get_title();
							}
						}
						if ( 0 < absint( $variationID ) ) {
							$variation_product = new WC_Product_Variation( $variationID );
							if ( $variation_product instanceof WC_Product_Variation ) {
								if ( is_array( $variation_product->get_attributes() ) && count( $variation_product->get_attributes() ) > 0 ) {
									foreach ( $variation_product->get_attributes() as $attribute ) {
										$found_text[] = $attribute;
									}
								}

							}
						}
					}
				}

			}
			$result = false;
			if ( 0 === count( $found_text ) ) {

				if ( $type === "doesnt_contain" ) {
					$result = true;
				}

				return $this->return_is_match( $result, $rule_data );
			}

			$matched = false;
			foreach ( $found_text as $text ) {
				switch ( $type ) {
					case 'contains':
						$matched = ( stristr( $text, $rule_data['condition'] ) !== false );
						break;

					case 'starts_with':
						$matched = strtolower( substr( $text, 0, strlen( $rule_data['condition'] ) ) ) === strtolower( $rule_data['condition'] );
						break;

					case 'ends_with':
						$matched = strtolower( substr( $text, - strlen( $rule_data['condition'] ) ) ) === strtolower( $rule_data['condition'] );
						break;

					case 'doesnt_contain':
						$matched = ( stristr( $text, $rule_data['condition'] ) === false );
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

		public function get_nice_string( $rule ) {
			return sprintf( __( 'Order %s item that matches with %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $rule['condition'] );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Order_Billing_State' ) ) {

	/** WOOCOMMERCE SUBSCRIPTION PLUGIN RULE ENDS */
	class WFOCU_Rule_Order_Billing_State extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_billing_state' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			return null;
		}

		public function get_condition_input_type() {
			return 'Order_State_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			$type   = $rule_data['operator'];

			if ( $env === 'cart' ) {
				$billing_country = ( WC()->customer->get_billing_country( 'edit' ) );

			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );

				$billing_country = WFOCU_WC_Compatibility::get_billing_country_from_order( $order );
			}

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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Order Billing state %s of %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_countries_title( $rule['condition'] ) );
		}


	}

}