<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Analytics_GA' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Analytics_GA extends WFACP_Analytics {
		private static $self = null;
		protected $slug = 'google_ua';

		protected function __construct() {
			parent::__construct();
			$this->admin_general_settings = BWF_Admin_General_Settings::get_instance();
			add_action( 'wfacp_internal_css', [ $this, 'print_tag_js' ] );

		}

		public static function get_instance() {
			if ( is_null( self::$self ) ) {
				self::$self = new self;
			}

			return self::$self;
		}

		public function get_key() {
			$get_ga_key = apply_filters( 'wfacp_get_ga_key', $this->admin_general_settings->get_option( 'ga_key' ) );

			return empty( $get_ga_key ) ? '' : $get_ga_key;
		}

		public function enable_custom_event() {
			return $this->admin_general_settings->get_option( 'is_ga_custom_events' );
		}


		public function print_tag_js() {
			if ( ! $this->admin_general_settings instanceof BWF_Admin_General_Settings ) {
				$this->prepare_data();
			}
			if ( true !== $this->enable_tracking() ) {
				return;
			}
			$pixel_id = $this->get_key();
			if ( empty( $pixel_id ) ) {
				return;
			}
			self::print_google_tag_manager_js( $pixel_id );
		}

		public function get_prepare_data() {
			$options = $this->get_options();

			if ( ! isset( $options['id'] ) || empty( $options['id'] ) ) {
				return $options;
			}

			if ( wc_string_to_bool( $options['settings']['add_to_cart'] ) ) {
				$add_to_cart_data       = $this->get_items_data( true );
				$this->add_to_cart_data = $add_to_cart_data;
				$options['add_to_cart'] = $add_to_cart_data;
			}

			$data                = $this->get_items_data();
			$this->checkout_data = $data;
			$options['checkout'] = $data;

			return $options;
		}

		public function get_item( $product, $cart_item ) {
			if ( ! $product instanceof WC_Product ) {
				return parent::get_item( $product, $cart_item );
			}
			$data = [
				'price' => 0,
				'items' => []
			];

			$product_data = $this->prepare_product_data( $cart_item );
			if ( ! empty( $product_data ) ) {
				$data['price']   += floatval( $product_data['price'] );
				$data['items'][] = $product_data['item'];
			}

			return [
				'value'        => $data['price'],
				'content_type' => 'product',
				'currency'     => get_woocommerce_currency(),
				'items'        => $data['items'],
				'user_roles'   => WFACP_Common::get_current_user_role(),
			];
		}


		public function remove_item( $product_obj, $cart_item ) {
			return $this->get_item( $product_obj, $cart_item );
		}

		public function get_checkout_data() {
			$options = $this->get_options();
			if ( ! isset( $options['id'] ) || empty( $options['id'] ) ) {
				return $this->checkout_data;
			}
			$data = $this->get_items_data();
			if ( wc_string_to_bool( $options['settings']['checkout'] ) ) {
				$this->checkout_data = $data;
			}


			return $this->checkout_data;
		}

		public function get_items_data( $is_cart = false ) {
			$output = new stdClass();
			if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
				return $output;
			}

			$contents = WC()->cart->get_cart_contents();
			if ( empty( $contents ) ) {
				return $output;
			}
			$data = [
				'price' => 0,
				'items' => []
			];
			foreach ( $contents as $cart_item ) {
				if ( $cart_item['data'] instanceof WC_Product ) {
					if ( true === $is_cart ) {
						$cart_item['is_cart'] = true;
					}
					$product_data = $this->prepare_product_data( $cart_item );
					if ( ! empty( $product_data ) ) {
						$data['price']   += floatval( $product_data['price'] );
						$data['items'][] = $product_data['item'];
					}

				}
			}

			return array(
				[
					'value'        => $data['price'],
					'content_type' => 'product',
					'currency'     => get_woocommerce_currency(),
					'items'        => $data['items'],
					'user_roles'   => WFACP_Common::get_current_user_role(),
				]
			);
		}

		public function prepare_product_data( $cart_item ) {
			$data    = [
				'price' => 0,
				'item'  => []
			];
			$is_cart = false;
			if ( $cart_item['data'] instanceof WC_Product ) {
				$product = $cart_item['data'];
				if ( isset( $cart_item['is_cart'] ) ) {
					unset( $cart_item['is_cart'] );
					$is_cart = true;
				}
				$product_id = $this->get_cart_item_id( $cart_item );
				$content_id = $this->get_product_content_id( $product_id );
				$name       = $product->get_title();
				if ( $cart_item['variation_id'] ) {
					$product = wc_get_product( $cart_item['variation_id'] );
					if ( $product->get_type() === 'variation' && false === ( $this->do_treat_variable_as_simple( 'google_ua' ) ) ) {
						$variation_name = implode( "/", $product->get_variation_attributes() );
						$categories     = $this->get_object_terms( 'product_cat', $product->get_parent_id() );
					} else {
						$variation_name = null;
						$categories     = $this->get_object_terms( 'product_cat', $product_id );
					}
				} else {
					$variation_name = null;
					$categories     = $this->get_object_terms( 'product_cat', $product_id );
				}

				if ( true === $is_cart ) {
					$sub_total = $cart_item['line_subtotal'];
					$sub_total = $this->number_format( $sub_total );
					$price     = $sub_total;
				} else {
					$sub_total = $cart_item['line_subtotal'];
					$sub_total = $this->number_format( $sub_total );
					$price     = $sub_total;
					$quantity  = absint( $cart_item['quantity'] );
					if ( $quantity > 0 ) {
						$price     = $sub_total;
						$sub_total = WFACP_Common::wfacp_round( $sub_total / $quantity );
					}
				}
				$currency = get_woocommerce_currency();

				$item = array(
					'item_id'   => $content_id,
					'item_name' => $name,
					'quantity'  => absint( $cart_item['quantity'] ),
					'price'     => floatval( $sub_total ),
					'currency'  => $currency
				);

				if ( ! is_null( $variation_name ) ) {
					$item['item_variant'] = $variation_name;
				}
				$cat_count = 0;
				if ( is_array( $categories ) && count( $categories ) > 0 ) {
					foreach ( $categories as $cat ) {
						$item_category          = ( 0 === $cat_count ) ? 'item_category' : 'item_category' . $cat_count;
						$item[ $item_category ] = $cat;
						$cat_count ++;
					}
				}
				$data['price'] += absint( $price );
				$data['item']  = $item;
			}

			return $data;

		}

		public function is_global_pageview_enabled() {
			return wc_string_to_bool( $this->admin_general_settings->get_option( 'is_ga_page_view_global' ) );
		}

		public function is_global_add_to_cart_enabled() {
			return wc_string_to_bool( $this->admin_general_settings->get_option( 'is_ga_add_to_cart_global' ) );
		}
	}
}
