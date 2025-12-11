<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFOB_AJAX_Controller' ) ) {
	/**
	 * Class wfob_AJAX_Controller
	 * Handles All the request came from front end or the backend
	 */
	abstract class WFOB_AJAX_Controller {
		private static $bump_action_data = '';
		private static $output_resp = [];
		private static $gateway_change = [];

		public static function init() {
			/**
			 * Backend AJAX actions
			 */
			if ( is_admin() ) {
				self::handle_admin_ajax();
			}
			self::handle_public_ajax();
		}

		public static function handle_admin_ajax() {
			add_action( 'wp_ajax_wfob_global_save_settings', [ __CLASS__, 'save_global_settings' ] );
			add_action( 'wp_ajax_wfob_preview_details', [ __CLASS__, 'preview_details' ] );
			add_action( 'wp_ajax_wfob_create_bump', [ __CLASS__, 'create_bump' ] );
			add_action( 'wp_ajax_wfob_add_product', [ __CLASS__, 'add_product' ] );
			add_action( 'wp_ajax_wfob_remove_product', [ __CLASS__, 'remove_product' ] );
			add_action( 'wp_ajax_wfob_product_search', [ __CLASS__, 'product_search' ] );
			add_action( 'wp_ajax_wfob_save_products', [ __CLASS__, 'save_products' ] );
			add_action( 'wp_ajax_wfob_update_rules', [ __CLASS__, 'update_rules' ] );
			add_action( 'wp_ajax_wfob_save_design', [ __CLASS__, 'save_design' ] );
			add_action( 'wp_ajax_wfob_save_settings', [ __CLASS__, 'save_settings' ] );
			add_action( 'wp_ajax_wfob_update_page_status', [ __CLASS__, 'update_page_status' ] );
			add_action( 'wp_ajax_wfob_make_wpml_duplicate', [ __CLASS__, 'make_wpml_duplicate' ] );
		}

		public static function handle_public_ajax() {
			add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'check_bump_action' ], - 10 );
			add_action( 'bwf_global_save_settings_wfob', [ __CLASS__, 'update_global_settings_fields' ] );

			$endpoints = self::get_available_public_endpoints();
			foreach ( $endpoints as $action => $function ) {
				if ( method_exists( __CLASS__, $function ) ) {
					add_action( 'wc_ajax_' . $action, [ __CLASS__, $function ] );
				}
			}
		}

		public static function get_available_public_endpoints() {
			$endpoints = [
				'wfob_quick_view_ajax' => 'wf_quick_view_ajax',
			];

			return $endpoints;
		}

		public static function get_public_endpoints() {
			$endpoints        = [];
			$public_endpoints = self::get_available_public_endpoints();
			if ( count( $public_endpoints ) > 0 ) {
				foreach ( $public_endpoints as $key => $function ) {
					$endpoints[ $key ] = WC_AJAX::get_endpoint( $key );
				}
			}

			return $endpoints;
		}

		/**
		 * Create BUmp is callback of Create bump ajax
		 */
		public static function create_bump() {
			self::check_nonce( true );
			$resp = array(
				'msg'    => '',
				'status' => false,
			);

			if ( isset( $_POST['wfob_name'] ) && '' !== $_POST['wfob_name'] ) {
				$post                 = array();
				$post['post_title']   = $_POST['wfob_name'];
				$post['post_type']    = WFOB_Common::get_bump_post_type_slug();
				$post['post_status']  = 'publish';
				$post['post_name']    = isset( $_POST['post_name'] ) ? $_POST['post_name'] : $_POST['post_title'];
				$post['post_content'] = '';
				if ( ! empty( $post ) ) {
					if ( isset( $_POST['wfob_id'] ) && $_POST['wfob_id'] > 0 ) {
						$wfob_id = absint( $_POST['wfob_id'] );
						$data    = [
							'ID'         => $wfob_id,
							'post_title' => $post['post_title'],
							'post_name'  => $post['post_name'],
						];
						$status  = wp_update_post( $data );
						if ( ! is_wp_error( $status ) ) {
							update_post_meta( $wfob_id, '_wfob_version', WFOB_VERSION );
							$resp['status']       = true;
							$resp['new_url']      = get_the_permalink( $wfob_id );
							$resp['redirect_url'] = '#';
							$resp['msg']          = __( 'Order bump Successfully Update', 'woofunnels-order-bump' );

						}

						self::send_resp( $resp );
					}
					$menu_order = WFOB_Common::get_highest_menu_order();

					$post['menu_order'] = $menu_order + 1;
					$wfob_id            = wp_insert_post( $post );
					if ( 0 !== $wfob_id && ! is_wp_error( $wfob_id ) ) {
						update_post_meta( $wfob_id, '_wfob_version', WFOB_VERSION );
						$resp['status']       = true;
						$resp['redirect_url'] = add_query_arg( array(
							'page'    => 'wfob',
							'section' => 'products',
							'wfob_id' => $wfob_id,
						), admin_url( 'admin.php' ) );
						$resp['msg']          = __( 'Order bump Successfully Created', 'woofunnels-order-bump' );
					} else {
						$resp['redirect_url'] = '#';
						$resp['msg']          = __( 'Order bump Successfully Updated', 'woofunnels-order-bump' );
					}
				}
			}

			self::send_resp( $resp );
		}

		public static function check_nonce( $admin = false ) {


			$rsp = [
				'status' => 'false',
				'msg'    => 'Invalid Call',
			];


			if ( true === $admin && false !== WFOB_Core()->role->user_access( 'menu', 'read' ) ) {
				if ( ! isset( $_REQUEST['wfob_nonce'] ) || ! wp_verify_nonce( $_REQUEST['wfob_nonce'], 'wfob_admin_secure_key' ) ) {
					wp_send_json( $rsp );
				}

				return;
			}

			if ( ! isset( $_REQUEST['wfob_nonce'] ) || ! wp_verify_nonce( $_REQUEST['wfob_nonce'], 'wfob_secure_key' ) ) {
				wp_send_json( $rsp );
			}
		}

		public static function send_resp( $data = array() ) {
			if ( ! is_array( $data ) ) {
				$data = [];
			}
			$data['nonce'] = wp_create_nonce( 'wfob_secure_key' );

			if ( isset( $_REQUEST['wfob_id'] ) && $_REQUEST['wfob_id'] > 0 ) {
				WFOB_Common::delete_transient( $_REQUEST['wfob_id'] );
			}

			wp_send_json( $data );
		}


		public static function product_search() {
			self::check_nonce( true );
			$term = wc_clean( empty( $term ) ? stripslashes( $_POST['term'] ) : $term );
			if ( empty( $term ) ) {
				wp_die();
			}
			$variations = true;
			$ids        = WFOB_Common::search_products( $term, $variations );
			/**
			 * Products types that are allowed in the offers
			 */
			$allowed_types   = apply_filters( 'wfob_offer_product_types', array(
				'simple',
				'variable',
				'course',
				'variation',
				'subscription',
				'variable-subscription',
				'subscription_variation',
				'bundle',
				'yith_bundle',
				'woosb',
			) );
			$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );
			$product_objects = array_filter( $product_objects, function ( $arr ) use ( $allowed_types ) {
				return $arr && is_a( $arr, 'WC_Product' ) && in_array( $arr->get_type(), $allowed_types );
			} );
			$products        = array();
			/**
			 * @var $product_object WC_Product;
			 */
			foreach ( $product_objects as $product_object ) {
				$products[] = array(
					'id'      => $product_object->get_id(),
					'product' => rawurldecode( WFOB_Common::get_formatted_product_name( $product_object ) ),
				);
			}
			wp_send_json( apply_filters( 'wfob_woocommerce_json_search_found_products', $products ) );
		}

		public static function add_product() {
			self::check_nonce( true );
			$resp = array(
				'msg'      => '',
				'status'   => false,
				'products' => [],
			);
			if ( isset( $_POST['wfob_id'] ) && count( $_POST['products'] ) > 0 ) {
				$wfob_id          = absint( $_POST['wfob_id'] );
				$products         = $_POST['products'];
				$existing_product = WFOB_Common::get_bump_products( $wfob_id );
				foreach ( $products as $pid ) {
					$unique_id = uniqid( 'wfob_' );
					$product   = wc_get_product( $pid );
					if ( $product instanceof WC_Product ) {
						$product_type = $product->get_type();
						$image_id     = $product->get_image_id();
						$default      = WFOB_Common::get_default_product_config();

						$product_image_url = '';
						$images            = wp_get_attachment_image_src( $image_id );
						if ( is_array( $images ) && count( $images ) > 0 ) {
							$product_image_url = wp_get_attachment_image_src( $image_id )[0];
						}
						$default['image'] = apply_filters( 'wfob_product_image', $product_image_url, $product );
						if ( '' == $default['image'] ) {
							$default['image'] = WFOB_PLUGIN_URL . '/admin/assets/img/product_default_icon.jpg';
						}

						$default['type']                 = $product_type;
						$default['id']                   = $product->get_id();
						$default['stock']                = $product->is_in_stock();
						$default['parent_product_id']    = $product->get_parent_id();
						$default['title']                = $product->get_title();
						$default['is_sold_individually'] = $product->is_sold_individually();
						if ( in_array( $product_type, WFOB_Common::get_variable_product_type() ) ) {
							$default['variable'] = 'yes';
							$default['price']    = $product->get_price_html();
						} else {
							if ( in_array( $product_type, WFOB_Common::get_variation_product_type() ) ) {
								$default['title'] = $product->get_name();
							}
							$row_data                 = $product->get_data();
							$sale_price               = $row_data['sale_price'];
							$default['price']         = wc_price( $row_data['price'] );
							$default['regular_price'] = wc_price( $row_data['regular_price'] );
							if ( '' != $sale_price ) {
								$default['sale_price'] = wc_price( $sale_price );
							}
						}
						$resp['products'][ $unique_id ] = $default;
						$default                        = WFOB_Common::remove_product_keys( $default );
						$existing_product[ $unique_id ] = $default;
					}
				}

				WFOB_Common::update_page_product( $wfob_id, $existing_product );
				if ( count( $resp['products'] ) > 0 ) {
					$resp['status'] = true;
				}
			}

			self::send_resp( $resp );

		}

		public static function remove_product() {

			self::check_nonce( true );

			$resp = array(
				'status' => false,
				'msg'    => '',
			);
			if ( isset( $_POST['wfob_id'] ) && $_POST['wfob_id'] > 0 && isset( $_POST['product_key'] ) && $_POST['product_key'] != '' ) {
				$wfob_id          = absint( $_POST['wfob_id'] );
				$product_key      = trim( $_POST['product_key'] );
				$existing_product = WFOB_Common::get_bump_products( $wfob_id );
				if ( isset( $existing_product[ $product_key ] ) ) {
					unset( $existing_product[ $product_key ] );
					WFOB_Common::update_page_product( $wfob_id, $existing_product );
					$resp['status'] = true;
					$resp['msg']    = __( 'Product removed from checkout page', 'woofunnels-order-bump' );
				}
			}
			self::send_resp( $resp );
		}

		public static function save_products() {

			self::check_nonce( true );

			$resp = array(
				'msg'      => __( 'Changes saved', 'woofunnels-order-bump' ),
				'products' => [],
			);
			if ( isset( $_POST['products'] ) && count( $_POST['products'] ) > 0 ) {
				$wfob_id          = $_POST['wfob_id'];
				$product_settings = isset( $_POST['product_settings'] ) ? ( $_POST['product_settings'] ) : [];
				$wfob_settings    = isset( $_POST['wfob_settings'] ) ? ( $_POST['wfob_settings'] ) : [];
				$products         = $_POST['products'];
				foreach ( $products as $key => $val ) {
					if ( isset( $products[ $key ]['variable'] ) ) {
						$pro                = WFOB_Common::wc_get_product( $products[ $key ]['id'] );
						$is_found_variation = WFOB_Common::get_default_variation( $pro );
						if ( count( $is_found_variation ) > 0 ) {
							$products[ $key ]['default_variation']      = $is_found_variation['variation_id'];
							$products[ $key ]['default_variation_attr'] = $is_found_variation['attributes'];
						}
					}
					$products[ $key ] = WFOB_Common::remove_product_keys( $products[ $key ] );
				}
				WFOB_Common::update_page_product( $wfob_id, $products );
				WFOB_Common::update_product_settings( $wfob_id, $product_settings );
				WFOB_Common::update_setting_data( $wfob_id, $wfob_settings );
			}

			$resp['status'] = true;

			self::send_resp( $resp );

		}

		public static function update_rules() {
			self::check_nonce( true );
			$resp = array(
				'msg'    => '',
				'status' => false,
			);


			if ( isset( $_POST['wfob_id'] ) && $_POST['wfob_id'] > 0 && isset( $_POST['wfob_rule'] ) && ! empty( $_POST['wfob_rule'] ) > 0 ) {
				$bump_id = $_POST['wfob_id'];
				$rules   = $_POST['wfob_rule'];
				$post    = get_post( $bump_id );

				if ( ! is_wp_error( $post ) ) {
					WFOB_Common::update_bump_rules( $bump_id, $rules );
					WFOB_Common::update_bump_time( $bump_id );
					$resp = array(
						'msg'    => __( 'Changes Saved' ),
						'status' => true,
					);
				}
			}
			self::send_resp( $resp );
		}

		public static function save_design() {
			self::check_nonce( true );
			$resp = array(
				'msg'    => '',
				'status' => false,
			);

			if ( isset( $_POST['wfob_id'] ) && $_POST['wfob_id'] > 0 && isset( $_POST['settings'] ) && count( $_POST['settings'] ) > 0 ) {

				WFOB_Common::update_design_data( $_POST['wfob_id'], $_POST['settings'] );
				$resp = array(
					'msg'    => __( 'Changes saved' ),
					'status' => true,
				);
			}
			self::send_resp( $resp );
		}

		public static function save_settings() {
			self::check_nonce( true );
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( isset( $_POST['wfob_id'] ) && $_POST['wfob_id'] > 0 && isset( $_POST['settings'] ) && count( $_POST['settings'] ) > 0 ) {

				WFOB_Common::update_setting_data( $_POST['wfob_id'], $_POST['settings'] );
				$resp = array(
					'msg'    => __( 'Changes saved' ),
					'status' => true,
				);
			}
			self::send_resp( $resp );
		}

		/**
		 * Save Order Bump global settings
		 */

		public static function save_global_settings() {
			self::check_nonce( true );
			$options = isset( $_POST['settings'] ) ? $_POST['settings'] : 0; //phpcs:ignore
			$resp    = self::update_global_settings_fields( $options );
			self::send_resp( $resp );
		}

		public static function update_global_settings_fields( $options ) {
			$options = ( is_array( $options ) && count( $options ) > 0 ) ? wp_unslash( $options ) : 0;
			$resp    = [
				'status' => false,
				'msg'    => __( 'Settings Saved Successfully', 'woofunnels-order-bump' ),
			];

			if ( ! is_array( $options ) || count( $options ) === 0 ) {
				return $resp;
			}

			$options['css'] = isset( $options['css'] ) ? stripslashes_deep( $options['css'] ) : '';
			update_option( '_wfob_global_settings', $options, false );
			$resp['status'] = true;

			return $resp;
		}

		public static function update_page_status() {
			self::check_nonce( true );
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( isset( $_POST['id'] ) && $_POST['id'] > 0 && isset( $_POST['post_status'] ) ) {
				$args    = [
					'ID'          => $_POST['id'],
					'post_status' => 'true' == $_POST['post_status'] ? 'publish' : 'draft',
				];
				$post_id = wp_update_post( $args );

				$resp = array(
					'msg'     => __( 'Changes saved', 'woofunnels-order-bump' ),
					'status'  => true,
					'post_id' => $post_id,
				);
			}
			self::send_resp( $resp );
		}


		public static function _check_bump_action_for_global_data( $post_data ) {

			if ( isset( $post_data['wfob_input_hidden_data'] ) ) {
				$bump_action_data = json_decode( $post_data['wfob_input_hidden_data'], true );

				if ( ! empty( $bump_action_data ) ) {
					return;
				}

			}
			if ( ! isset( $post_data['wfob_input_bump_global_data'] ) ) {
				return;
			}

			$bump_global_data = json_decode( $post_data['wfob_input_bump_global_data'], true );
			if ( empty( $bump_global_data ) ) {
				return;
			}

			self::check_bump_items( $bump_global_data );
		}


		public static function check_bump_action( $data ) {
			if ( empty( $data ) ) {
				return;
			}
			parse_str( $data, $post_data );

			//self::_check_bump_action_for_global_data( $post_data );


			if ( empty( $post_data ) || ! isset( $post_data['wfob_input_hidden_data'] ) || empty( $post_data['wfob_input_hidden_data'] ) ) {
				return;
			}

			/* fetching available payment method before modifying bump */
			$available_before_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			$before_cart_total         = WC()->cart->get_total( 'no' );

			$bump_action_data = json_decode( $post_data['wfob_input_hidden_data'], true );

			if ( empty( $bump_action_data ) ) {
				return;
			}
			$bump_global_data = json_decode( isset( $post_data['wfob_input_bump_global_data'] ) ? $post_data['wfob_input_bump_global_data'] : '{}', true );
			$action           = $bump_action_data['action'];
			if ( 'add_order_bump' == $action ) {
				self::$output_resp = self::add_order_bump_order( $bump_action_data );
			} elseif ( 'remove_order_bump' == $action ) {
				self::$output_resp = self::remove_order_bump( $bump_action_data );
			} elseif ( 'update_quantity' == $action ) {
				self::$output_resp = self::update_quantity( $bump_action_data );
			}
			if ( ! empty( $bump_global_data ) ) {
				$response                              = self::check_bump_items( $bump_global_data );
				self::$output_resp['response']         = $response;
				self::$output_resp['bump_global_data'] = $bump_global_data;
			}
			$after_cart_total                      = WC()->cart->get_total( 'no' );
			self::$bump_action_data                = $action;
			self::$output_resp['wfob_product_key'] = $bump_action_data['product_key'];
			self::$output_resp['wfob_id']          = $bump_action_data['wfob_id'];
			self::$output_resp['bump_action_data'] = $bump_action_data;
			self::$output_resp['callback_id']      = isset( $bump_action_data['callback_id'] ) ? $bump_action_data['callback_id'] : '';

			/* fetching available payment method after modifying bump */
			$available_after_gateways = WC()->payment_gateways()->get_available_payment_gateways();

			self::$gateway_change = ( array_keys( $available_after_gateways ) != array_keys( $available_before_gateways ) );
			if ( false == self::$gateway_change ) {
				self::$gateway_change = ( $before_cart_total != $after_cart_total ) && ( 0 == absint( $before_cart_total ) || 0 == absint( $after_cart_total ) );
			}

			self::$gateway_change = apply_filters( 'wfob_need_payment_gateway_refresh', self::$gateway_change, $available_after_gateways, self::$output_resp, $bump_action_data );
			add_filter( 'woocommerce_update_order_review_fragments', [ __CLASS__, 'merge_fragments' ], 999 );
		}

		/**
		 *Try to add or remove order bump items from the cart
		 */
		public static function check_bump_items( $items ) {
			$response = [];
			try {


				$cart_product_map = [];
				foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
					if ( ! empty( $cart_item['_wfob_product_key'] ) ) {
						$cart_product_map[ $cart_item['_wfob_product_key'] ] = $cart_item_key;
					}
				}

				foreach ( $items as $key => $item ) {
					$product_key = $item['product_key'];

					if ( $item['action'] === 'add_order_bump' ) {
						if ( ! isset( $cart_product_map[ $product_key ] ) ) {
							$_response = self::add_order_bump_order( $item );
							if ( isset( $_response['status'] ) && $_response['status'] === true ) {
								$response[ $product_key ] = $_response;
							}
						}

					} elseif ( $item['action'] === 'remove_order_bump' ) {
						if ( isset( $cart_product_map[ $product_key ] ) ) {
							$item['cart_key'] = $cart_product_map[ $product_key ];
							// Collect both item and cart_item_key
							WFOB_Common::remove_bump_from_cart( $item );

						}
					}
				}
			} catch ( Exception|Error $e ) {

			}

			return $response;
		}

		public static function merge_fragments( $fragments ) {
			//unset gateway fragment if gateways is not changed
			if ( false == self::$gateway_change && isset( $fragments['.woocommerce-checkout-payment'] ) ) {
				unset( $fragments['.woocommerce-checkout-payment'] );
			}


			$data                        = [];
			$data['action']              = self::$bump_action_data;
			$data['cart_is_empty']       = WC()->cart->is_empty();
			$data['cart_total']          = WC()->cart->get_total( 'edit' );
			$data['cart_is_virtual']     = WFOB_Common::is_cart_is_virtual();
			$data['analytics_data']      = WFOB_Common::analytics_checkout_data();
			$fragments['wfob_ajax_data'] = array_merge( $data, self::$output_resp );

			return $fragments;
		}

		private static function add_order_bump_order( $post ) {
			$resp = array(
				'msg'    => '',
				'status' => false,

			);
			if ( ! isset( $post['wfob_id'] ) || empty( $post['wfob_id'] ) ) {
				return $resp;
			}
			$wfob_id    = absint( $post['wfob_id'] );
			$posted_qty = absint( $post['qty'] );
			WFOB_Common::set_id( absint( $wfob_id ) );
			$products         = WFOB_Common::get_bump_products( absint( $wfob_id ) );
			$product_design   = WFOB_Common::get_design_data_meta( $wfob_id );
			$session_products = WC()->session->get( 'wfob_added_bump_product', [] );
			$remove_item_key  = ( isset( $post['remove_item_key'] ) ? trim( $post['remove_item_key'] ) : '' );
			$product_key      = $post['product_key'];

			//do not add to bump product if same bump present in cart.

			$contents = WC()->cart->get_cart_contents();
			if ( empty( $remove_item_key ) ) {
				$filter_item = array_filter( $contents, function ( $content ) use ( $product_key ) {
					return ( isset( $content['_wfob_product_key'] ) && ! empty( $product_key ) && $content['_wfob_product_key'] == $product_key );
				} );


				if ( ! empty( $filter_item ) ) {
					$resp['error']  = '';
					$resp['status'] = false;

					return $resp;
				}
			}


			do_action( 'wfob_before_add_to_cart', $post );
			if ( ! isset( $products[ $product_key ] ) || count( $products[ $product_key ] ) == 0 ) {
				return $resp;
			}

			$remove_item_key = ( isset( $post['remove_item_key'] ) ? trim( $post['remove_item_key'] ) : '' );
			if ( '' !== $remove_item_key ) {
				$item = WC()->cart->get_cart_item( $remove_item_key );
				if ( is_array( $item ) && count( $item ) ) {
					$item_key = $item['_wfob_product_key'];
					if ( isset( $session_products[ $wfob_id ] ) && $session_products[ $wfob_id ] [ $item_key ] ) {
						unset( $session_products[ $wfob_id ] [ $item_key ] );
						WC()->session->set( 'wfob_added_bump_product', $session_products );
					}
					WC()->cart->remove_cart_item( $remove_item_key );
				}
			}

			$product_variation_id = ( isset( $post['variation_id'] ) ? absint( $post['variation_id'] ) : 0 );
			$attributes           = ( isset( $post['attributes'] ) && is_array( $post['attributes'] ) ) ? $post['attributes'] : [];

			$product    = $products[ $product_key ];
			$product_id = absint( $product['id'] );
			$quantity   = absint( $product['quantity'] );
			if ( $posted_qty > 0 ) {
				$quantity *= $posted_qty;
			}
			$custom_data  = [];
			$variation_id = 0;
			if ( $product['parent_product_id'] && $product['parent_product_id'] > 0 && $product_variation_id > 0 ) {
				$product_id   = absint( $product['parent_product_id'] );
				$variation_id = absint( $product['id'] );
			}

			if ( $product_variation_id > 0 ) {
				$variation_id  = $product_variation_id;
				$product['id'] = $variation_id;
			}

			$product_obj = WFOB_Common::wc_get_product( $product_id );
			if ( ! $product_obj instanceof WC_Product ) {
				return $resp;
			}

			if ( ! $product_obj->is_purchasable() ) {
				return $resp;
			}

			if ( $variation_id > 0 ) {
				// if variation_id found then we fetch attributes of variation from below function
				$is_found_variation = WFOB_Common::get_first_variation( $product_obj, $variation_id );
				if ( count( $attributes ) == 0 ) {
					$attributes = $is_found_variation['attributes'];
				}
			} else {
				if ( isset( $product['variable'] ) ) {
					$variation_id = absint( $product['default_variation'] );
					$attributes   = $product['default_variation_attr'];
				}
			}
			if ( $variation_id > 0 ) {
				$custom_data['wfob_variable_attributes'] = $attributes;
				$product_obj                             = wc_get_product( $variation_id );
			}

			$stock_status = WFOB_Common::check_manage_stock( $product_obj, $quantity );

			if ( false == $stock_status ) {

				$resp['error']  = __( 'Sorry, we do not have enough stock to fulfill your order. Please change quantity and try again. We apologize for any inconvenience caused.', 'woofunnels-order-bump' );
				$resp['status'] = false;

				return $resp;
			}

			$t_k = "product_{$product_key}";


			if ( isset( $product_design["{$t_k}_title"] ) && '' != $product_design["{$t_k}_title"] ) {
				$product['title'] = $product_design["{$t_k}_title"];
			}

			$custom_image_url = '';
			$featured_image   = wc_string_to_bool( isset( $product_design["{$t_k}_featured_image"] ) ? $product_design["{$t_k}_featured_image"] : 'false' );
			if ( true == $featured_image && isset( $product_design["{$t_k}_featured_image_options"] ) && ! empty( $product_design["{$t_k}_featured_image_options"] ) ) {
				$image_options = $product_design["{$t_k}_featured_image_options"];
				if ( 'custom' == $image_options['type'] && ! empty( $image_options['custom_url'] ) ) {
					$custom_image_url = $image_options['custom_url'];
				}
			}


			$custom_data['_wfob_product']                 = true;
			$custom_data['_wfob_product_key']             = $product_key;
			$custom_data['_wfob_options']                 = $product;
			$custom_data['_wfob_options']['_wfob_id']     = $wfob_id;
			$custom_data['_wfob_options']['custom_image'] = $custom_image_url;

			$post['product_id']                          = $product_id;
			$custom_data['_wfob_options']['posted_data'] = $post;


			add_filter( 'woocommerce_add_cart_item', [ 'WFOB_Common', 'handle_swap_product' ], 10, 2 );
			$success = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes, $custom_data );

			if ( '' == $success || false == $success ) {

				$resp['error']  = __( 'Sorry, we do not have enough stock to fulfill your order. Please change quantity and try again. We apologize for any inconvenience caused.', 'woofunnels-order-bump' );
				$resp['status'] = false;
				wc_clear_notices();

				return $resp;
			}
			$post['cart_key'] = $success;


			if ( '' !== $success ) {
				if ( ! isset( $session_products[ $wfob_id ] ) ) {
					$session_products[ $wfob_id ] = [];
				}
				$session_products[ $wfob_id ][ $product_key ]             = [];
				$session_products[ $wfob_id ][ $product_key ]['cart_key'] = $success;
				WC()->session->set( 'wfob_added_bump_product', $session_products );
				do_action( 'wfob_after_add_to_cart', $post );
				$cart_data                 = [];
				$cart_data[ $product_key ] = WFOB_Common::analytics_item( $product_obj, WC()->cart->get_cart_item( $success ) );
				$cart_data                 = apply_filters( 'wfob_add_bump_order_analytics_data', $cart_data, $product_obj, WC()->cart->get_cart_item( $success ) );
				$resp                      = array(
					'new_item'  => $success,
					'cart_item' => $cart_data,
					'status'    => true,
				);
			}

			return $resp;
		}

		private static function remove_order_bump( $post ) {
			$resp = array(
				'msg'           => '',
				'status'        => false,
				'cart_is_empty' => false,
				'products'      => [],
			);

			$temp = WFOB_Common::remove_bump_from_cart( $post );
			if ( ! empty( $temp ) ) {
				$resp = $temp;
			}

			return $resp;
		}


		private static function update_quantity( $post ) {
			$resp        = [ 'status' => true ];
			$qty         = wc_clean( $post['qty'] );
			$cart_key    = wc_clean( $post['cart_key'] );
			$product_key = wc_clean( $post['product_key'] );
			$qty         = absint( $qty );
			$wfob_id     = absint( wc_clean( $post['wfob_id'] ) );
			$products    = WFOB_Common::get_bump_products( absint( $wfob_id ) );
			if ( ! isset( $products[ $product_key ] ) ) {

				return $resp;
			}
			if ( 0 == $qty ) {
				$temp = WFOB_Common::remove_bump_from_cart( $post );
				if ( ! empty( $temp ) ) {
					$resp = $temp;
				}
			} else {
				$org_quantity = $products[ $product_key ]['quantity'];

				$new_quantity = $qty * $org_quantity;
				$bump_data    = WFOB_Common::get_cart_item_key( $product_key );

				if ( is_null( $bump_data ) ) {
					return $resp;
				}
				$cart_key  = $bump_data[0];
				$cart_item = $bump_data[1];
				if ( empty( $cart_item ) ) {
					return $resp;
				}
				/**
				 * @var $product_obj WC_Product;
				 */
				$product_obj  = $cart_item['data'];
				$stock_status = WFOB_Common::check_manage_stock( $product_obj, $new_quantity );
				if ( false == $stock_status ) {
					$resp['error']    = __( 'Sorry, we do not have enough stock to fulfill your order. Please change quantity and try again. We apologize for any inconvenience caused.', 'woofunnels-aero-checkout' );
					$resp['qty']      = $cart_item['quantity'];
					$resp['status']   = false;
					$resp['cart_key'] = $cart_key;

					return $resp;
				}
				$set = WC()->cart->set_quantity( $cart_key, $new_quantity );
				if ( $set ) {
					wc_clear_notices();
					if ( class_exists( 'WFACP_Common' ) ) {
						WFACP_Common::wfob_order_bump_fragments();
					}
					$resp = array(
						'qty'    => $qty,
						'status' => true,
					);

				}
			}

			return $resp;
		}


		public static function wf_quick_view_ajax() {
			self::check_nonce();
			$resp = [
				'msg'    => '',
				'status' => false,
			];

			if ( isset( $_POST['wfob_id'] ) && $_POST['wfob_id'] > 0 && isset( $_POST['product_id'] ) && $_POST['product_id'] > 0 ) {
				$wfob_id           = absint( $_POST['wfob_id'] );
				$item_key          = $_POST['item_key'];
				$cart_key          = isset( $_POST['cart_key'] ) ? $_POST['cart_key'] : '';
				$save_product_list = WFOB_Common::get_bump_products( $wfob_id );
				if ( isset( $save_product_list[ $item_key ] ) ) {
					$product_id = absint( $_POST['product_id'] );
					$params     = array(
						'p'         => $product_id,
						'post_type' => array( 'product', 'product_variation' ),
					);
					$query      = new WP_Query( $params );

					global $wfob_product, $wfob_post, $wfob_qv_data;
					$wfob_qv_data = $_POST;

					if ( $query->have_posts() ) {
						while ( $query->have_posts() ) {
							$query->the_post();
							ob_start();
							global $post, $product;
							global $wfob_item_data;
							$wfob_item_data             = $save_product_list[ $item_key ];
							$wfob_item_data['item_key'] = $item_key;
							$wfob_item_data['cart_key'] = $cart_key;

							// checking for product is variation or subscription_variation variation

							if ( in_array( $product->get_type(), [ 'variation', 'subscription_variation' ] ) ) {

								// stored child product object to our global variable
								// using this variable we
								$wfob_product = $product;
								$wfob_post    = $post;

								$parent_id = $product->get_parent_id();
								$product   = null;
								$post      = get_post( $parent_id );
								$product   = wc_get_product( $parent_id );
								// we set global product  variable to parent product because of child product not description and title of of the product
								// i am using some woocommerce part like add-to-cart button quantity
								//this template using Global $product variation
							}

							$product->add_meta_data( 'wfob_cart_key', $cart_key );
							require_once( __DIR__ . '/quick-view/qv-template.php' );
							$html           = ob_get_clean();
							$resp['status'] = true;
							$resp['html']   = $html;
							break;
						}
					}
					wp_reset_postdata();
				}
				self::send_resp( $resp );
			}
		}

		public static function make_wpml_duplicate() {
			self::check_nonce( true );
			$resp = [
				'msg'    => __( 'Something went wrong', 'woofunnel-order-bump' ),
				'status' => false,
			];
			if ( isset( $_POST['trid'] ) && $_POST['trid'] > 0 && class_exists( 'SitePress' ) && method_exists( 'SitePress', 'get_original_element_id_by_trid' ) ) {
				$trid          = absint( $_POST['trid'] );
				$lang          = isset( $_POST['lang'] ) ? trim( $_POST['lang'] ) : '';
				$language_code = isset( $_POST['language_code'] ) ? trim( $_POST['language_code'] ) : '';
				$lang          = empty( $lang ) ? $language_code : $lang;

				$master_post_id = SitePress::get_original_element_id_by_trid( $trid );
				if ( false !== $master_post_id ) {
					global $sitepress;
					$duplicate_id = $sitepress->make_duplicate( $master_post_id, $lang );
					if ( is_int( $duplicate_id ) && $duplicate_id > 0 ) {
						WFOB_Common::get_duplicate_data( $duplicate_id, $master_post_id );

						$resp['redirect_url'] = add_query_arg( [
							'section' => 'rules',
							'wfob_id' => $duplicate_id,
						], admin_url( 'admin.php?page=wfob' ) );
						$resp['duplicate_id'] = $duplicate_id;
						$resp['status']       = true;
					}
				}
			}
			self::send_resp( $resp );
		}

		public static function preview_details() {
			self::check_nonce( true );
			$resp = [
				'msg'    => '',
				'status' => false,
			];
			if ( isset( $_POST['wfob_id'] ) && ( $_POST['wfob_id'] ) > 0 ) {
				$wfob_id            = absint( $_POST['wfob_id'] );
				$post_data          = get_post( $wfob_id );
				$title              = $post_data->post_title;
				$post_status        = $post_data->post_status;
				$guid               = admin_url( 'admin.php?page=wfob&section=rules&wfob_id=' . $wfob_id );
				$productData        = WFOB_Common::get_bump_products( $wfob_id );
				$discount_type_keys = [
					'fixed_discount_reg'    => __( 'on Regular Price', 'woofunnels-aero-checkout' ),
					'fixed_discount_sale'   => __( 'on Sale Price', 'woofunnels-aero-checkout' ),
					'percent_discount_reg'  => '% on Regular Price',
					'percent_discount_sale' => '% on Sale Price',
				];

				ob_start();
				?>
                <div class="wfob-fp-wrap preview_sec product_preview">

					<?php
					if ( is_array( $productData ) && count( $productData ) > 0 ) {
						?>
                        <h3><?php _e( 'Products', 'woocommerce' ) ?></h3>
                        <div class="wfob-sec">
                            <div class="wfob-fp-offer-products">
								<?php
								foreach ( $productData as $product_key => $product_val ) {
									$discount_type   = $product_val['discount_type'];
									$discount_amount = $product_val['discount_amount'];
									$discounted_val  = '';
									if ( $discount_amount > 0 ) {
										if ( false !== strpos( $discount_type, 'percent_discount_' ) ) {
											$discounted_val = $discount_amount . '' . $discount_type_keys[ $discount_type ];
										} else {
											$discounted_val = wc_price( $discount_amount ) . ' ' . $discount_type_keys[ $discount_type ];
										}
									}

									$string = $product_val['title'];
									if ( $product_val['quantity'] > 0 ) {
										$string .= ' x ' . $product_val['quantity'];
									}
									if ( '' !== $discounted_val ) {
										$string .= ' @ ' . $discounted_val;
									}
									echo "<p>{$string}</p>";
								}
								?>

                            </div>
                        </div>
						<?php
					} else {
						echo '<p class="wfob-fp-offer-products">' . __( 'No Product Associated', 'woofunnels-order-bump' ) . '</p>';
					}
					?>
                </div>
				<?php
				$html                = ob_get_clean();
				$resp                = [
					'msg'    => 'Settings Data saved',
					'status' => true,
				];
				$resp['wfob_id']     = $post_data;
				$resp['post_name']   = $title;
				$resp['launch_url']  = $guid;
				$resp['post_status'] = $post_status;
				$resp['html']        = $html;
			}
			self::send_resp( $resp );
		}

		public static function output_resp() {
			return self::$output_resp;
		}
	}

	WFOB_AJAX_Controller::init();
}