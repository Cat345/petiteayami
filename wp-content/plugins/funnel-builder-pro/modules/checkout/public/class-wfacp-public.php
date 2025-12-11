<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_Public' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Public {
		public static $is_checkout = null;
		private static $ins = null;
		public $page_id = 0;
		public $added_products = [];
		public $products_in_cart = [];
		public $applied_coupon_in_cart = '';
		public $product_settings = [];
		public $variable_product = false;
		public $is_hide_qty = false;
		public $is_checkout_override = false;
		public $billing_details = false;
		public $paypal_billing_address = false;
		public $paypal_shipping_address = false;
		public $shipping_details = false;
		public $is_paypal_express_active_session = false;
		public $is_amazon_express_active_session = false;
		protected $products = [];
		protected $settings = [];
		protected $image_src = [];
		protected $already_discount_apply = [];
		protected $products_count = 0;
		protected $add_to_cart_via_url = false;
		public $have_product = false;

		public $months = [];
		public $min_age = 0;


		protected function __construct() {

			add_action( 'wfacp_changed_default_woocommerce_page', [ $this, 'wfacp_changed_default_woocommerce_page' ] );
			/**
			 * We only process checkout page data if header is valid
			 * @since 1.6.0
			 */
			if ( $this->check_valid_header_of_page() ) {

				$hook = 'wfacp_checkout_page_found';
				if ( WFACP_Common::is_theme_builder() ) {
					$hook = 'wfacp_after_checkout_page_found';
				}
				add_action( $hook, [ $this, 'add_to_cart_action' ], 0 );
				add_action( 'wfacp_after_checkout_page_found', [ $this, 'apply_matched_coupons' ], 3 );
				add_action( 'wfacp_after_checkout_page_found', [ $this, 'other_hooks' ] );
			}
			// get All setting when AJax is running
			add_action( 'wfacp_before_process_checkout_template_loader', [ $this, 'get_page_data' ], 1 );

			add_action( 'wfacp_before_add_to_cart', [ $this, 'best_value_via_url' ] );
			add_action( 'wfacp_before_add_to_cart', [ $this, 'add_to_cart_via_url' ] );
			add_action( 'wfacp_before_add_to_cart', [ $this, 'default_value_via_url' ] );
			add_action( 'wfacp_before_add_to_cart', [ $this, 'wfacp_before_add_to_cart' ] );
			add_action( 'wfacp_after_add_to_cart', [ $this, 'wfacp_after_add_to_cart' ] );

			add_action( 'woocommerce_before_calculate_totals', [ $this, 'calculate_totals' ], 1 );
			add_action( 'woocommerce_before_cart', [ $this, 'apply_matched_coupons' ] );
			add_filter( 'woocommerce_order_item_quantity_html', [ $this, 'change_woocommerce_checkout_cart_item_quantity' ], 999, 2 );
			add_filter( 'woocommerce_email_order_item_quantity', [ $this, 'change_woocommerce_email_quantity' ], 999, 2 );
			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_meta_cart_data' ], 10, 4 );
			add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ $this, 'hide_out_meta_data' ], 10, 4 );
			add_filter( 'woocommerce_coupon_message', [ $this, 'hide_coupon_msg' ], 959 );
			add_filter( 'woocommerce_get_checkout_url', [ $this, 'woocommerce_get_checkout_url' ], 99999 );
			add_action( 'woocommerce_checkout_process', [ $this, 'set_session_when_place_order_btn_pressed' ], - 1 );

			add_action( 'woocommerce_checkout_update_user_meta', [ $this, 'woocommerce_checkout_process' ] );
			add_action( 'woocommerce_applied_coupon', [ $this, 'set_session_when_coupon_applied' ] );
			add_action( 'woocommerce_removed_coupon', [ $this, 'reset_session_when_coupon_removed' ] );

			add_action( 'wp_enqueue_scripts', [ $this, 'global_script' ] );
			add_filter( 'wfacp_form_section', [ $this, 'remove_shipping_method' ], 10, 3 );
			add_filter( 'wfacp_hide_section', [ $this, 'skip_empty_section' ], 10, 2 );

			/* presist cart for global checkout */
			$this->persistent_cart_merging();

			/**
			 * @since 1.6.0
			 */
			if ( apply_filters( 'wfacp_remove_persistent_cart_after_merging', true ) ) {
				/**
				 * We store the cart items into session when user is not logged in
				 * after logged in we restore the stored cart for preventing the persistent cart issue in woocommerce             *
				 **/
				add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'save_wfacp_session' ], 99 );
				add_filter( 'woocommerce_cart_contents_changed', [ $this, 'set_save_session' ], 99 );
			}

			add_action( 'woocommerce_thankyou', [ $this, 'reset_our_localstorage' ] );

			add_action( 'woocommerce_cart_is_empty', [ $this, 'woocommerce_cart_is_empty' ] );

			add_filter( 'woocommerce_order_item_name', [ $this, 'change_item_name' ], 9, 2 );
			add_filter( 'woocommerce_cart_item_name', [ $this, 'change_item_name' ], 9, 2 );

			add_filter( 'woocommerce_before_order_itemmeta', [ $this, 'change_order_item_name_edit_screen' ], 9, 2 );

			add_filter( 'wfacp_default_product', [ $this, 'merge_default_product' ], 10, 3 );

			/**
			 * Change woocommerce ajax endpoint only for our checkout pages only
			 * not for every page
			 *
			 */
			add_action( 'wfacp_after_checkout_page_found', function () {
				add_filter( 'woocommerce_ajax_get_endpoint', [ $this, 'woocommerce_ajax_get_endpoint' ], 0, 2 );
			} );


			add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', [ $this, 'restrict_sold_individual' ], 10, 2 );

			add_filter( 'woocommerce_checkout_no_payment_needed_redirect', [ $this, 'reset_session_when_order_processed' ] );
			add_filter( 'woocommerce_payment_successful_result', [ $this, 'reset_session_when_order_processed' ] );
			add_action( 'woocommerce_thankyou', [ $this, 'reset_session_when_order_processed' ] );

			add_action( 'pre_get_posts', [ $this, 'load_page_to_home_page' ], 9999 );
			add_filter( 'wfacp_tracking_options_data', [ $this, 'update_tracking_data' ], 10, 1 );

			// tracking script for native checkout page
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ], 100 );
			add_filter( 'woocommerce_add_cart_item', [ $this, 'calculate_item_discount' ] );

			/* Shimmer Styling*/
			add_action( 'wfacp_internal_css', [ $this, 'shimmer_css' ] );
			add_filter( 'woocommerce_get_item_data', array( $this, 'unset_aero_line_item_data_in_cart' ), 20, 2 );
			add_action( 'woocommerce_checkout_order_processed', [ $this, 'attach_awaiting_order_id' ] );


//Disable Quantity switcher and delete icon form free product
			add_filter( 'wfacp_show_item_quantity', [ $this, 'check_for_free_product' ], 100, 2 );
			add_filter( 'wfacp_display_quantity_increment', [ $this, 'check_for_free_product' ], 100, 2 );
			add_filter( 'wfacp_show_you_save_text', [ $this, 'check_for_free_product' ], 100, 2 );
			add_filter( 'wfacp_enable_delete_item', [ $this, 'check_for_free_product' ], 100, 2 );

			add_filter( 'wfacp_product_switcher_show_quantity_incrementer', [ $this, 'hide_quantity_for_free_product' ], 100, 3 );
			add_filter( 'wfacp_admin_order_field', [ $this, 'add_blank_dropdown_field_in_options' ], 30 );
			add_filter( 'wfacp_forms_field', [ $this, 'add_blank_dropdown_field_in_options' ], 30 );

			add_filter( 'wfacp_display_shipping_placeholder_message', array( $this, 'display_shipping_placeholder_message' ) );


			/**
			 * Date Of birth fields
			 */


			add_filter( 'wfacp_html_fields_bwfan_birthday_date', function($staus){

               if(!$this->is_dob_field_enabled()){
	                $staus=false;
                }

    			return $staus;
            } );
			add_action( 'process_wfacp_html', [ $this, 'call_bwf_dob_field' ], 10, 3 );
			add_filter( 'woocommerce_checkout_posted_data', [ $this, 'move_dob_to_post_data' ], 9999, 3 );
			add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'move_dob_order_meta' ], 9999, 2 );
			add_action( 'woocommerce_after_checkout_validation', [ $this, 'wfacp_validate_dob_field' ], 10, 2 );
			/**
			 * Display FIeld under the order admin screen
			 */
			add_filter( 'wfacp_admin_order_field', [ $this, 'change_options' ], 30, 3 );

		}

        public function is_dob_field_enabled() {
            return class_exists('BWFAN_Birthday');

        }


		public function add_to_cart_action( $page_id ) {
			//Do Not Process Dedicated checkout page id order is awaiting against page
			if ( ! is_null( WC()->cart ) && ! is_null( WC()->session ) ) {
				$wc_await_order        = WC()->session->get( 'order_awaiting_payment', null );
				$dedicated_await_order = WC()->session->get( 'wfacp_await_order_' . WFACP_Common::get_id(), null );
				if ( ! is_null( $wc_await_order ) && ! is_null( $dedicated_await_order ) && $wc_await_order == $dedicated_await_order ) {
					return;
				}
			}
			/** Checking for embed forms if embedded on the same page */
			$design = WFACP_Common::get_page_design( $page_id );
			if ( $design['selected_type'] === 'embed_forms' ) {
				$override_checkout_page_id = WFACP_Common::get_checkout_page_id();
				if ( intval( $page_id ) === intval( $override_checkout_page_id ) ) {
					return;
				}
			}

			$this->check_advanced_setting( $page_id );
			$this->maybe_pass_no_cache_header( $page_id );
			$this->get_page_data( $page_id );
			$this->add_to_cart( $page_id );
		}

		public function persistent_cart_merging() {
			$global_settings = get_option( '_wfacp_global_settings', [] );
			if ( isset( $global_settings['override_checkout_page_id'] ) && 0 !== absint( $global_settings['override_checkout_page_id'] ) ) {
				add_filter( 'wfacp_remove_persistent_cart_after_merging', '__return_false' );
			}
		}

		/**
		 * Check valid header of the page (Text/Html)
		 * We only process text/html header
		 * If client enqueue script like this /wfacp_age/?script=frontend
		 * then we not process this call for our checkout page
		 * This issue occur with Oxygen Builder
		 * @return bool
		 * @since 1.6.0
		 *
		 */
		public function check_valid_header_of_page() {

			if ( wp_doing_ajax() ) {
				return true;
			}

			if ( isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'text/html' ) ) {
				return true;
			}

			return false;

		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function check_advanced_setting( $page_id ) {
			// Unset Remove Cart item our page is loaded ticket no #8247856
			WC()->cart->removed_cart_contents = [];

			if ( $this->is_checkout_override ) {
				return;
			}

			$this->settings               = WFACP_Common::get_page_settings( $page_id );
			$close_checkout_redirect_url  = ( '' != $this->settings['close_checkout_redirect_url'] ) ? $this->settings['close_checkout_redirect_url'] : home_url();
			$total_purchased_redirect_url = ( '' != $this->settings['total_purchased_redirect_url'] ) ? $this->settings['total_purchased_redirect_url'] : home_url();

			do_action( 'wfacp_before_checking_advanced_settings', $this->settings, $this );

			if ( wc_string_to_bool( $this->settings['close_after_x_purchase'] ) && '' !== $this->settings['total_purchased_allowed'] && $this->settings['total_purchased_allowed'] > 0 ) {

				global $wpdb;
				$result = $wpdb->get_results( "select ID from {$wpdb->prefix}wfacp_stats where wfacp_id={$page_id}", ARRAY_A );
				if ( ! empty( $result ) ) {
					$total_purchased = count( $result );
					if ( $total_purchased > 0 && $total_purchased >= $this->settings['total_purchased_allowed'] ) {
						wp_redirect( $total_purchased_redirect_url );
						exit;
					}
				}
			}
			if ( ! WFACP_Common::is_theme_builder() && wc_string_to_bool( $this->settings['close_checkout_after_date'] ) ) {
				$current_time = date( 'Y-m-d h:i:s', current_time( 'timestamp' ) );
				$current_time = strtotime( $current_time );
				if ( '' !== $this->settings['close_checkout_on'] && $current_time > strtotime( $this->settings['close_checkout_on'] ) ) {
					wp_redirect( $close_checkout_redirect_url );
					exit;
				}
			}
			do_action( 'wfacp_after_checking_advanced_settings', $this->settings, $this );
		}

		public function wfacp_changed_default_woocommerce_page() {
			if ( ! is_null( WC()->session ) ) {
				WC()->cart->removed_cart_contents = [];
				WC()->session->set( 'removed_cart_contents', [] );
				$this->is_checkout_override = true;
				//Override WooCommerce Block Checkout
				WFACP_Common::override_block_cart_checkout();

			}

		}

		public function other_hooks() {
			add_action( 'wfacp_internal_css', [ $this, 'wp_footer' ] );
		}

		public function wfacp_before_add_to_cart() {
			add_filter( 'woocommerce_add_cart_item', [ $this, 'check_custom_name' ] );
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'split_product_individual_cart_items' ), 10, 1 );
		}

		public function wfacp_after_add_to_cart() {
			remove_filter( 'woocommerce_add_cart_item_data', array( $this, 'split_product_individual_cart_items' ), 10 );
		}


		public function get_page_data( $page_id ) {

			$this->products         = WFACP_Common::get_page_product( $page_id );
			$this->products_count   = ! empty( $this->products ) ? count( $this->products ) : 0;
			$this->product_settings = WFACP_Common::get_page_product_settings( $page_id );
			$this->settings         = WFACP_Common::get_page_settings( $page_id );
		}

		public function get_settings() {
			return $this->settings;
		}

		public function get_product_list( $wfacp_id = 0 ) {
			if ( $wfacp_id > 0 ) {
				return WC()->session->get( 'wfacp_product_data_' . $wfacp_id, $this->products );

			}

			return $this->products;
		}

		public function get_product_settings() {
			return $this->product_settings;
		}

		/**
		 * add to cart product after checkout page is found
		 * checkout page id
		 *
		 * @param $page_id
		 */
		public function add_to_cart( $page_id ) {

			do_action( 'wfacp_add_to_cart_init', $this );
			$add_checkout_parameter = $this->aero_add_to_checkout_parameter();
			if ( isset( $_GET[ $add_checkout_parameter ] ) && '' != $_GET[ $add_checkout_parameter ] ) {
				$this->add_to_cart_via_url = true;
			}

			$aero_default_value     = $this->aero_default_value_parameter();
			$aero_default_value_set = false;
			if ( isset( $_GET[ $aero_default_value ] ) && '' != $_GET[ $aero_default_value ] ) {
				$aero_default_value_set = true;
			}

			if ( isset( $_GET['cancel_order'] ) ) {
				return;
			}

			if ( WFACP_Common::is_customizer() && false == WC()->cart->is_empty() && 0 == $this->get_product_count() ) {
				return;
			}


			$wfacp_woocommerce_applied_coupon = WC()->session->get( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id(), [] );

			if ( $page_id > 0 && isset( $wfacp_woocommerce_applied_coupon[ $page_id ] ) && ( false == $this->add_to_cart_via_url && false == $aero_default_value_set ) ) {
				return;
			} else {
				WC()->session->set( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id(), [] );
			}


			if ( ! is_super_admin() ) {

				$wfacp_checkout_processed = WC()->session->get( 'wfacp_checkout_processed_' . WFACP_Common::get_id() );
				// Do Not sustain cart when aero-add-to-checkout parameter used
				if ( isset( $wfacp_checkout_processed ) && false == $this->add_to_cart_via_url ) {

					$session_return         = false;
					$add_checkout_parameter = $this->aero_add_to_checkout_parameter();

					if ( isset( $_GET[ $add_checkout_parameter ] ) && '' != $_GET[ $add_checkout_parameter ] ) {
						$session_aero_add_to_checkout_parameter = WC()->session->get( 'aero_add_to_checkout_parameter_' . WFACP_Common::get_id(), false );
						if ( true !== $session_aero_add_to_checkout_parameter && $session_aero_add_to_checkout_parameter == $_GET[ $add_checkout_parameter ] ) {
							$session_return = true;
						}
					} else {
						$session_return = true;
					}
					if ( $session_return ) {
						$this->merge_session_product_with_actual_product();

						return;
					}
				}
			}

			// for third party system
			if ( apply_filters( 'wfacp_skip_add_to_cart', false, $this ) ) {
				return;
			}


			if ( $this->is_checkout_override ) {
				WC()->session->set( 'wfacp_id', WFACP_Common::get_id() );
				WC()->session->set( 'wfacp_is_override_checkout', WFACP_Common::get_id() );

				return;
			} else {
				if ( ! wp_doing_ajax() ) {
					WC()->session->set( 'wfacp_is_override_checkout', 0 );
				}
			}
			if ( isset( $_REQUEST['wc-ajax'] ) ) {
				return;
			}
			if ( wp_doing_ajax() ) {
				return;
			}
			if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
				return;
			}


			if ( ( ! is_array( $this->products ) || $this->get_product_count() == 0 ) && false == $this->add_to_cart_via_url ) {
				return;
			}
			if ( apply_filters( 'wfacp_cart_empty', true, $this ) ) {
				WC()->cart->empty_cart();
			}


			$no_checkouts   = WC()->session->get( 'wfacp_no_checkouts', [] );
			$no_checkouts[] = WFACP_Common::get_id();
			WC()->session->set( 'wfacp_no_checkouts', array_unique( $no_checkouts ) );
			$this->push_product_to_cart();
		}

		/**
		 * @since 1.5.2
		 */
		public function merge_session_product_with_actual_product() {
			$session_products = WC()->session->get( 'wfacp_product_data_' . WFACP_Common::get_id(), [] );
			if ( ! empty( $session_products ) && ! empty( $this->products ) ) {

				$merge_session_product = [];
				foreach ( $session_products as $pkey => $session_product ) {
					if ( ! isset( $this->products[ $pkey ] ) ) {
						continue;
					}
					if ( isset( $session_product['is_added_cart'] ) ) {
						$merge_session_product[ $pkey ] = $session_product;
					} else {
						$merge_session_product[ $pkey ]                 = $this->products[ $pkey ];
						$merge_session_product[ $pkey ]['org_quantity'] = $this->products[ $pkey ]['quantity'];
					}
				}

				if ( ! empty( $merge_session_product ) ) {
					WC()->session->set( 'wfacp_id', WFACP_Common::get_id() );
					WC()->session->set( 'wfacp_product_data_' . WFACP_Common::get_id(), $merge_session_product );
				}
			}
		}

		public function apply_matched_coupons() {
			if ( WFACP_Common::is_customizer() ) {
				return;
			}
			$coupon_ids = [];
			if ( isset( $this->settings['enable_coupon'] ) && 'true' === $this->settings['enable_coupon'] && isset( $this->settings['coupons'] ) && $this->settings['coupons'] != '' ) {
				$coupon_id  = $this->settings['coupons'];
				$coupon_ids = explode( ',', $coupon_id );
			}

			$coupon_parameter = $this->aero_coupons_value_parameter();
			if ( isset( $_GET[ $coupon_parameter ] ) ) {
				$coupon_parameter_ids = explode( ',', trim( $_GET[ $coupon_parameter ] ) );

				if ( ! empty( $coupon_parameter_ids ) ) {
					$coupon_ids = array_merge( $coupon_ids, $coupon_parameter_ids );
				}

			}

			remove_action( 'woocommerce_applied_coupon', [ $this, 'set_session_when_coupon_applied' ] );
			if ( ! empty( $coupon_ids ) ) {
				$wfacp_woocommerce_applied_coupon = WC()->cart->get_applied_coupons();
				foreach ( $coupon_ids as $coupon_id ) {
					$coupon_id = trim( $coupon_id );
					if ( ! empty( $wfacp_woocommerce_applied_coupon ) && in_array( $coupon_id, $wfacp_woocommerce_applied_coupon ) ) {
						continue;
					}
					WC()->cart->add_discount( $coupon_id );
				}
			}
		}

		public function default_value_via_url() {
			if ( wp_doing_ajax() ) {
				return;
			}
			$default_value_parameter = $this->aero_default_value_parameter();
			if ( isset( $_GET[ $default_value_parameter ] ) && '' != $_GET[ $default_value_parameter ] ) {
				$best_value = $_GET[ $default_value_parameter ];
				WC()->session->set( 'wfacp_product_default_value_parameter_' . WFACP_Common::get_id(), $best_value );
			} else {
				WC()->session->set( 'wfacp_product_default_value_parameter_' . WFACP_Common::get_id(), '' );
			}
		}

		public function best_value_via_url() {
			if ( wp_doing_ajax() ) {
				return;
			}
			$best_value_parameter = $this->aero_best_value_parameter();
			if ( isset( $_GET[ $best_value_parameter ] ) && '' != $_GET[ $best_value_parameter ] ) {
				$best_value = $_GET[ $best_value_parameter ];
				WC()->session->set( 'wfacp_product_best_value_by_parameter_' . WFACP_Common::get_id(), $best_value );
			} else {
				WC()->session->set( 'wfacp_product_best_value_by_parameter_' . WFACP_Common::get_id(), '' );
			}
		}


		private function find_existing_match_product( $pid ) {
			foreach ( $this->products as $index => $data ) {
				if ( $pid == $data['id'] ) {
					return array(
						'key'  => $index,
						'data' => $data,
					);
				}
			}

			return null;
		}

		public function split_product_individual_cart_items( $cart_item_data ) {
			$cart_item_data['unique_key'] = uniqid();

			return $cart_item_data;
		}

		/**
		 * @param $ins WC_Cart
		 */
		public function calculate_totals( $ins ) {

			if ( WFACP_Common::get_id() == 0 || apply_filters( 'wfacp_disabled_discounting', false, $this ) ) {
				return $ins;
			}
			$cart_content = $ins->get_cart_contents();

			if ( count( $cart_content ) == 0 ) {
				return $ins;
			}
			$currency = get_woocommerce_currency();
			foreach ( $cart_content as $key => $item ) {
				if ( ! isset( $item['_wfacp_item_discount'] ) ) {
					continue;
				}

				if ( ! isset( $item['_wfacp_item_discount'][ $currency ] ) || apply_filters( 'wfacp_force_calculate_discount', false, $key, $item ) ) {
					$item = $this->calculate_item_discount( $item, $currency );
				}

				$discounts = $item['_wfacp_item_discount'][ $currency ];

				$item['data']->set_regular_price( $discounts['regular_price'] );
				$item['data']->set_price( $discounts['sale_price'] );
				$item['data']->set_sale_price( $discounts['sale_price'] );


				do_action( 'wfacp_discount_added_to_item', $item['data'] );
				$item                       = apply_filters( 'wfacp_after_discount_added_to_item', $item, $key, $ins );
				$ins->cart_contents[ $key ] = $item;
			}

		}

		public function calculate_item_discount( $cart_item_data, $currency = '' ) {


			if ( apply_filters( 'wfacp_disabled_item_discounting', false, $this ) ) {
				return $cart_item_data;
			}
			if ( empty( $currency ) ) {
				$currency = get_woocommerce_currency();
			}

			return $this->modify_calculate_price_per_session( $cart_item_data, $currency );

		}

		/**
		 * Apply discount on basis of input for product raw prices
		 *
		 * @param $item WC_cart;
		 *
		 * @return mixed
		 */

		public function modify_calculate_price_per_session( $item, $currency ) {
			if ( ! isset( $item['_wfacp_product'] ) ) {
				return $item;
			}
			if ( isset( $item['_wfacp_options']['add_to_cart_via_url'] ) && isset( $item['_wfacp_options']['not_existing_product'] ) ) {
				return $item;
			}
			$discount_amount = $item['_wfacp_options']['discount_amount'];

			if ( floatval( $discount_amount ) == 0 && true == apply_filters( 'wfacp_allow_zero_discounting', true, $item ) ) {
				return $item;
			}

			/**
			 * @var $product WC_product;
			 */
			$product                                                     = $item['data'];
			$raw_data                                                    = $product->get_data();
			$raw_data                                                    = apply_filters( 'wfacp_product_raw_data', $raw_data, $product );
			$regular_price                                               = apply_filters( 'wfacp_discount_regular_price_data', $raw_data['regular_price'] );
			$price                                                       = apply_filters( 'wfacp_discount_price_data', $raw_data['price'] );
			$discount_amount                                             = apply_filters( 'wfacp_discount_amount_data', $item['_wfacp_options']['discount_amount'], $item['_wfacp_options']['discount_type'] );
			$discount_data                                               = [
				'wfacp_product_rp'      => $regular_price,
				'wfacp_product_p'       => $price,
				'wfacp_discount_amount' => $discount_amount,
				'wfacp_discount_type'   => $item['_wfacp_options']['discount_type'],
			];
			$new_price                                                   = WFACP_Common::calculate_discount( $discount_data );
			$this->already_discount_apply[ $item['_wfacp_product_key'] ] = true;


			if ( is_null( $new_price ) ) {
				return $item;
			}
			if ( ! isset( $item['_wfacp_item_discount'] ) ) {
				$item['_wfacp_item_discount'] = [];
			}
			if ( ! isset( $item['_wfacp_item_discount'][ $currency ] ) ) {
				$item['_wfacp_item_discount'][ $currency ] = [];
			}
			$item['_wfacp_item_discount'][ $currency ]['regular_price'] = $regular_price;
			$item['_wfacp_item_discount'][ $currency ]['sale_price']    = $new_price;


			return $item;
		}

		/**
		 *
		 * @param $cart WC_Cart;
		 */
		public function save_wfacp_session( $cart ) {
			if ( ! is_user_logged_in() ) {
				$cart_content = $cart->get_cart_contents();
				if ( ! empty( $cart_content ) && WFACP_Common::get_id() > 0 ) {
					WC()->session->set( 'wfacp_sustain_cart_content_' . WFACP_Common::get_id(), $cart_content );
				}
			}

		}

		public function global_script() {
			if ( WFACP_Common::is_customizer() ) {
				add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
			}
		}

		public function check_custom_name( $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product instanceof WC_Product || empty( $cart_item['_wfacp_options']['title'] ) ) {
				return $cart_item;
			}


			$switcher_settings = WFACP_Common::get_product_switcher_data( WFACP_Common::get_id() );
			$settings          = $switcher_settings['settings'];
			if ( ! isset( $settings['enable_custom_name_in_order_summary'] ) || true !== wc_string_to_bool( $settings['enable_custom_name_in_order_summary'] ) ) {
				return $cart_item;
			}


			$cart_item["_wfacp_custom_title"] = $cart_item['_wfacp_options']['title'];
			if ( ! isset( $item_data['variation_id'] ) || $item_data['variation_id'] < 1 ) {
				return $cart_item;
			}

			$temp_item_name = $cart_item["_wfacp_custom_title"];
			$item_name      = $product->get_name();
			$item_name      = strip_tags( $item_name );
			$position       = strpos( $item_name, '-' );
			if ( false === $position ) {
				return $cart_item;
			}

			$substr = trim( substr( $item_name, $position, strlen( $item_name ) ) );

			if ( apply_filters( 'wfacp_variation_order_summary_attributes', true, $substr, $temp_item_name ) && '' !== $substr && false == stripos( $temp_item_name, $substr ) ) {
				$cart_item["_wfacp_custom_title"] = $item_name . $substr;
			}


			return $cart_item;
		}


		/**
		 *
		 * @param $item_data WC_Order_Item
		 *
		 * @return String
		 */
		public function change_item_name( $item_name, $item_data ) {
			return isset( $item_data["_wfacp_custom_title"] ) ? $item_data["_wfacp_custom_title"] : $item_name;
		}


		public function change_order_item_name_edit_screen( $item_id, $item ) {

			$data     = $item->get_data();
			$order_id = $data['order_id'];
			$aero_id  = wfacp_get_order_meta( wc_get_order( $order_id ), '_wfacp_post_id' );
			$aero_id  = absint( $aero_id );
			WFACP_Common::set_id( $aero_id );
			$switcher_settings = WFACP_Common::get_product_switcher_data( $aero_id );
			if ( empty( $switcher_settings ) ) {
				return '';
			}

			if ( ! isset( $switcher_settings['settings']['enable_custom_name_in_order_summary'] ) || true !== wc_string_to_bool( $switcher_settings['settings']['enable_custom_name_in_order_summary'] ) ) {
				return '';
			}

			if ( ! isset( $item['_wfacp_options'] ) || ! is_array( $item['_wfacp_options'] ) || '' == $item['_wfacp_options']['title'] ) {
				return '';
			}

			$item_name_is = $item['_wfacp_options']['title'];
			if ( isset( $item['_wfacp_options']['old_title'] ) && $item['_wfacp_options']['title'] !== $item['_wfacp_options']['old_title'] ) {
				echo '<div class="wc-order-item-sku"><strong>FunnelKit Custom Title: </strong><span>' . $item_name_is . '</span></div>';
			}

		}


		public function get_image_src( $image_id, $size = 'full' ) {

			if ( isset( $this->image_src[ $image_id ][ $size ] ) && ! empty( $this->image_src[ $image_id ][ $size ] ) ) {
				return $this->image_src[ $image_id ][ $size ];
			} else {
				if ( $image_id == '' ) {
					return;
				}
				$img_src_arr = wp_get_attachment_image_src( $image_id, $size );
				$img_src     = $img_src_arr[0];
				if ( ! isset( $this->image_src[ $image_id ][ $size ] ) ) {
					$this->image_src[ $image_id ][ $size ] = $img_src;
				}

				return $img_src;
			}
		}

		/**
		 * @var $cart_item WC_Order_Item
		 * this function for using hiding quantity in order review
		 */
		public function change_woocommerce_checkout_cart_item_quantity( $text, $cart_item ) {


			if ( ! isset( $cart_item['_wfacp_options'] ) || ! is_array( $cart_item['_wfacp_options'] ) ) {
				return $text;
			}


			if ( ! isset( $cart_item['wfacp_product'] ) ) {
				return $text;
			}
			if ( $this->is_hide_qty || isset( $cart_item['wfacp_hide_quantity'] ) ) {
				return '';
			}

			$data              = $cart_item->get_data();
			$order_id          = $data['order_id'];
			$aero_id           = wfacp_get_order_meta( wc_get_order( $order_id ), '_wfacp_post_id' );
			$aero_id           = absint( $aero_id );
			$wfacp_options     = $cart_item['_wfacp_options'];
			$switcher_settings = WFACP_Common::get_product_switcher_data( $aero_id );
			if ( isset( $switcher_settings['settings']['enable_custom_name_in_order_summary'] ) && wc_string_to_bool( $switcher_settings['settings']['enable_custom_name_in_order_summary'] ) && $wfacp_options['title'] !== $wfacp_options['old_title'] ) {
				$wfacp_qty = absint( $wfacp_options['org_quantity'] );
				$cart_qty  = absint( $cart_item['quantity'] );
				if ( $wfacp_qty > 0 && $cart_qty > 0 ) {
					return ' <strong class="product-quantity">' . sprintf( '&times; %s', ( $cart_qty / $wfacp_qty ) ) . '</strong>';
				}
			}

			return $text;
		}

		public function change_woocommerce_email_quantity( $quantity, $cart_item ) {

			if ( ! isset( $cart_item['_wfacp_options'] ) || ! is_array( $cart_item['_wfacp_options'] ) ) {
				return $quantity;
			}

			if ( $this->is_hide_qty || isset( $cart_item['wfacp_hide_quantity'] ) ) {
				return '';
			}
			$wfacp_options = $cart_item['_wfacp_options'];

			$wfacp_qty = absint( $wfacp_options['quantity'] );
			$cart_qty  = absint( $cart_item['org_quantity'] );
			if ( $wfacp_qty > 0 && $cart_qty > 0 ) {
				return ( $cart_qty / $wfacp_qty );
			}


			return $quantity;
		}

		/**
		 * @param $item WC_Order_Item
		 * @param $cart_item_key String
		 * @param $values Object
		 * @param $order WC_Order
		 */
		public function save_meta_cart_data( $item, $cart_item_key, $values, $order ) {
			if ( $order instanceof WC_Order && ! empty( $values ) ) {
				foreach ( $values as $key => $value ) {
					if ( false !== strpos( $key, 'wfacp_' ) ) {
						$item->add_meta_data( $key, $value );
					}
				}
			}

			if ( $this->is_hide_qty ) {
				$item->add_meta_data( 'wfacp_hide_quantity', 1 );
			}

		}

		/**
		 * @param $formatted_meta Array
		 * @param $instance WC_Order_Item
		 */

		public function hide_out_meta_data( $formatted_meta, $instance ) {
			if ( $instance instanceof WC_Order_Item && ! empty( $formatted_meta ) ) {
				foreach ( $formatted_meta as $key => $value ) {
					if ( false !== strpos( $value->key, 'wfacp_' ) && apply_filters( 'wfacp_hide_out_meta_data', true, $key, $value ) ) {
						unset( $formatted_meta[ $key ] );
					}
				}
			}

			return $formatted_meta;
		}

		public function hide_coupon_msg( $msg ) {

			if ( isset( $this->settings['disable_coupon'] ) && 'true' === $this->settings['disable_coupon'] ) {
				$msg = '';
			}

			return $msg;

		}

		public function woocommerce_template_single_add_to_cart() {
			global $product;

			do_action( 'wfacp_woocommerce_' . $product->get_type() . '_add_to_cart' );
		}

		public function woocommerce_variable_add_to_cart() {
			global $product;

			// Enqueue variation scripts.

			// Get Available variations?
			$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

			$available_variations = $get_variations ? $product->get_available_variations() : false;
			$attributes           = $product->get_variation_attributes();
			$selected_attributes  = $product->get_default_attributes();

			include WFACP_TEMPLATE_COMMON . '/quick-view/add-to-cart/variable.php';
		}

		public function woocommerce_variable_subscription_add_to_cart() {
			global $product;

			// Enqueue variation scripts.

			// Get Available variations?
			$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

			$available_variations = $get_variations ? $product->get_available_variations() : false;
			$attributes           = $product->get_variation_attributes();
			$selected_attributes  = $product->get_default_attributes();

			include WFACP_TEMPLATE_COMMON . '/quick-view/add-to-cart/variable-subscription.php';
		}

		public function woocommerce_simple_add_to_cart() {
			include WFACP_TEMPLATE_COMMON . '/quick-view/add-to-cart/simple.php';

		}

		public function woocommerce_subscription_add_to_cart() {
			include WFACP_TEMPLATE_COMMON . '/quick-view/add-to-cart/subscription.php';
		}

		public function woocommerce_single_variation_add_to_cart_button() {
			include WFACP_TEMPLATE_COMMON . '/quick-view/add-to-cart/variation-add-to-cart-button.php';
		}

		public function is_checkout_override() {
			if ( is_null( WC()->session ) ) {
				return $this->is_checkout_override;
			}

			$wfacp_is_override_checkout = WC()->session->get( 'wfacp_is_override_checkout', 0 );

			if ( $wfacp_is_override_checkout > 0 ) {
				$this->is_checkout_override = true;
			}

			if ( isset( $_REQUEST['wfacp_is_checkout_override'] ) && 'yes' == $_REQUEST['wfacp_is_checkout_override'] ) {
				$this->is_checkout_override = true;
			}

			if ( isset( $_REQUEST['wfacp_is_checkout_override'] ) && 'no' == $_REQUEST['wfacp_is_checkout_override'] ) {
				$this->is_checkout_override = false;
			}


			return $this->is_checkout_override;
		}

		public function wp_footer() {

			include( WFACP_TEMPLATE_COMMON . '/quick-view/quick-view-container.php' );
		}


		public function woocommerce_ajax_get_endpoint( $url, $request ) {
			if ( WFACP_Common::get_id() > 0 ) {
				$query = [
					'wfacp_id'                   => WFACP_Common::get_id(),
					'wfacp_is_checkout_override' => ( $this->is_checkout_override ) ? 'yes' : 'no',
				];
				if ( isset( $_REQUEST['currency'] ) ) {
					$query['currency'] = $_REQUEST['currency'];
				}
				if ( isset( $_REQUEST['lang'] ) ) {
					$query['lang'] = $_REQUEST['lang'];
				}
				if ( isset( $_REQUEST['wmc-currency'] ) ) {
					$query['lang'] = $_REQUEST['wmc-currency'];
				}
				$query            = apply_filters( 'wfacp_ajax_endpoint_parameters', $query, $this );
				$query['wc-ajax'] = $request;
				$url              = add_query_arg( $query, $url );
			}

			return $url;
		}

		public function unset_wcct_campaign( $status, $instance ) {

			if ( $this->get_product_count() > 0 ) {
				foreach ( $this->products as $index => $data ) {
					$product_id = absint( $data['id'] );
					if ( $data['parent_product_id'] && $data['parent_product_id'] > 0 ) {
						$product_id = absint( $data['parent_product_id'] );
					}
					if ( true === apply_filters( 'wfacp_unset_finale_campaign', true, $data ) ) {
						unset( $instance->single_campaign[ $product_id ] );
						$status = false;
					}
				}
			}

			return $status;

		}

		public function maybe_pass_no_cache_header() {

			$this->set_nocache_constants();
			nocache_headers();

		}

		/**
		 * @param $value
		 *
		 * @return mixed
		 */
		public function set_nocache_constants() {

			$this->maybe_define_constant( 'DONOTCACHEPAGE', true );
			$this->maybe_define_constant( 'DONOTCACHEOBJECT', true );
			$this->maybe_define_constant( 'DONOTCACHEDB', true );

			return null;
		}

		function maybe_define_constant( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		public function woocommerce_template_single_excerpt() {

			global $product;
			$type = $product->get_type();
			if ( in_array( $type, WFACP_Common::get_variable_product_type() ) ) {
				return '';
			}
			if ( in_array( $type, WFACP_Common::get_variation_product_type() ) ) {
				return '';
			}


			include WFACP_TEMPLATE_COMMON . '/quick-view/short-description.php';
		}

		public function woocommerce_get_checkout_url( $url ) {

			if ( WFACP_Core()->pay instanceof WFACP_Order_pay && WFACP_Core()->pay->is_order_pay() ) {
				return $url;
			}
			$id = WFACP_Common::get_id();
			if ( $id > 0 ) {
				$posts  = get_post( $id );
				$loader = WFACP_Core()->template_loader;
				if ( ! is_null( $posts ) && $posts->post_status == 'publish' && $loader->is_valid_state_for_data_setup() ) {
					$override_checkout_page_id = WFACP_Common::get_checkout_page_id();
					if ( $override_checkout_page_id !== $id ) {
						return get_the_permalink( $id );
					}
				}
			}

			return $url;
		}

		public function remove_shipping_method( $section, $section_index, $step ) {


			if ( ! is_array( $section ) || count( $section ) == 0 || ! isset( $section['fields'] ) || count( $section['fields'] ) == 0 ) {
				return $section;
			}
			$shipping_calculator_index = false;

			foreach ( $section['fields'] as $index => $field ) {
				if ( isset( $field['id'] ) && 'shipping_calculator' == $field['id'] ) {
					$shipping_calculator_index = $index;
					break;
				}
			}

			if ( false !== $shipping_calculator_index ) {

				WC()->session->set( 'wfacp_shipping_method_parent_fields_count_' . WFACP_Common::get_id(), [
					'count' => count( $section['fields'] ),
					'index' => $section_index,
					'step'  => $step,
				] );
			}

			return $section;
		}

		public function skip_empty_section( $status, $section ) {
			if ( ! is_array( $section ) || count( $section ) == 0 || ! isset( $section['fields'] ) || count( $section['fields'] ) == 0 ) {
				return true;
			}

			return $status;
		}

		public function set_session_when_place_order_btn_pressed() {

			$no_checkouts = WC()->session->get( 'wfacp_no_checkouts', [] );
			if ( ! empty( $no_checkouts ) & count( $no_checkouts ) > 1 ) {
				WC()->session->__unset( 'wfacp_checkout_processed_' . WFACP_Common::get_id() );

				return;
			}


			WC()->session->set( 'wfacp_checkout_processed_' . WFACP_Common::get_id(), true );
			if ( ! empty( $_POST ) && isset( $_POST['_wfacp_post_id'] ) ) {
				WC()->session->set( 'wfacp_posted_data', $_POST );
			}
		}

		public function reset_session_when_order_processed( $data ) {
			if ( is_null( WC()->session ) ) {
				return $data;
			}
			// if Reload set by woocommerce in any case then won`t unset any our sessions for Preventing Multiple Order issue.
			if ( true === WC()->session->reload_checkout && current_action() !== 'woocommerce_thankyou' ) {
				return $data;
			}
			$checkout_id = WFACP_Common::get_id();
			WC()->session->__unset( 'wfacp_checkout_processed_' . $checkout_id );
			WC()->session->__unset( 'aero_add_to_checkout_parameter_' . $checkout_id );
			WC()->session->__unset( 'wfacp_cart_hash' );
			WC()->session->__unset( 'wfacp_product_objects_' . $checkout_id );
			WC()->session->__unset( 'wfacp_product_data_' . $checkout_id );
			WC()->session->__unset( 'wfacp_is_override_checkout' );
			WC()->session->__unset( 'wfacp_product_best_value_by_parameter_' . $checkout_id );
			WC()->session->__unset( 'wfacp_sustain_cart_content_' . $checkout_id );
			WC()->session->__unset( 'removed_cart_contents' );
			WC()->session->__unset( 'wfacp_woocommerce_applied_coupon_' . $checkout_id );
			WC()->session->__unset( 'wfacp_no_checkouts' );
			WC()->session->__unset( '_wfacp_email_check_attempt' );
			$template = wfacp_template();
			if ( $template instanceof WFACP_Template_Common ) {
				WFACP_Common::unset_session( 'wfacp_mini_cart_widgets_' . $template->get_template_type() );
			}
			do_action( 'wfacp_reset_checkout_session_data', $checkout_id );

			return $data;
		}

		public function set_session_when_coupon_applied() {

			$no_checkouts = WC()->session->get( 'wfacp_no_checkouts', [] );
			if ( ! empty( $no_checkouts ) & count( $no_checkouts ) > 1 ) {
				WC()->session->__unset( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id() );

				return;
			}


			$c = WC()->session->get( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id(), [] );
			if ( isset( $_REQUEST['wfacp_id'] ) ) {
				$id       = absint( $_REQUEST['wfacp_id'] );
				$c[ $id ] = true;
			}
			WC()->session->set( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id(), $c );
		}

		public function reset_session_when_coupon_removed() {
			$coupons = WC()->cart->get_applied_coupons();
			if ( empty( $coupons ) ) {
				WC()->session->__unset( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id() );
			}
		}

		/**
		 * validate cart hash of multiple checkout page when open in same browser
		 * Make sure latest open checkout page is open
		 */
		public function woocommerce_checkout_process() {

			if ( isset( $_POST['wfacp_has_active_multi_checkout'] ) && $_POST['wfacp_has_active_multi_checkout'] != 'no' ) {
				return;
			}
			if ( isset( $_POST['wfacp_cart_hash'] ) && '' !== $_POST['wfacp_cart_hash'] ) {
				$form_cart_hash = trim( $_POST['wfacp_cart_hash'] );
				$cart_hash      = trim( WC()->session->get( 'wfacp_cart_hash', '' ) );
				if ( '' !== $cart_hash && ( $form_cart_hash !== $cart_hash ) ) {
					/**
					 * We found two separate cart hash now send reload trigger to checkout.js
					 */
					wp_send_json( [
						'reload' => true,
					] );
				}
			}
		}


		public function set_save_session( $cart_content ) {
			if ( is_user_logged_in() && WFACP_Common::get_id() > 0 ) {

				$cart_conm = WC()->session->get( 'wfacp_sustain_cart_content_' . WFACP_Common::get_id(), [] );
				if ( ! empty( $cart_conm ) ) {
					WC()->session->__unset( 'wfacp_sustain_cart_content_' . WFACP_Common::get_id() );

					return $cart_conm;

				}
			}

			return $cart_content;
		}

		public function reset_our_localstorage() {
			?>
            <script>

                if (typeof Storage !== 'undefined') {
                    window.localStorage.removeItem('wfacp_checkout_page_id');
                }
            </script>
			<?php
		}


		public function woocommerce_cart_is_empty() {
			if ( is_null( WC()->session ) ) {
				return;
			}
			WC()->session->__unset( 'wfacp_sustain_cart_content_' . WFACP_Common::get_id() );
			WC()->session->__unset( 'wfacp_woocommerce_applied_coupon_' . WFACP_Common::get_id() );

		}

		public function add_to_cart_via_url() {

			$add_checkout_parameter = $this->aero_add_to_checkout_parameter();

			if ( isset( $_GET[ $add_checkout_parameter ] ) && '' != $_GET[ $add_checkout_parameter ] ) {

				$this->add_to_cart_via_url = true;
				WC()->session->set( 'aero_add_to_checkout_parameter_' . WFACP_Common::get_id(), $_GET[ $add_checkout_parameter ] );
				$products     = explode( ',', $_GET[ $add_checkout_parameter ] );
				$products_qty = [];

				$quantity_parameter = $this->aero_add_to_checkout_product_quantity_parameter();

				if ( isset( $_GET[ $quantity_parameter ] ) ) {
					$products_qty = explode( ',', $_GET[ $quantity_parameter ] );
				}
				$product_settings     = $this->get_product_settings();
				$add_to_cart_setting  = $product_settings['add_to_cart_setting'];
				$product_default_set  = false;
				$default_parameter    = $this->aero_default_value_parameter();
				$default_parameter_no = null;
				if ( isset( $_GET[ $default_parameter ] ) && '' !== $_GET[ $default_parameter ] ) {
					$default_parameter_no = absint( $_GET[ $default_parameter ] );

				}
				if ( is_array( $products ) && count( $products ) > 0 ) {
					$new_products = [];

					$count = 1;
					foreach ( $products as $pid_index => $pid ) {
						$unique_id     = uniqid( 'wfacp_' );
						$existing_data = $this->find_existing_match_product( $pid );

						if ( ! is_null( $existing_data ) ) {

							$existing_data['data']['whats_included'] = '';
							if ( isset( $products_qty[ $pid_index ] ) && $products_qty[ $pid_index ] > 0 ) {
								$existing_data['data']['org_quantity']                 = 1;
								$existing_data['data']['add_to_cart_via_url_quantity'] = $products_qty[ $pid_index ];
							}

							$existing_data['data']['add_to_cart_via_url'] = true;

							if ( ! is_null( $default_parameter_no ) && $default_parameter_no > 0 && count( $products ) >= $default_parameter_no ) {
								if ( ( 2 == $add_to_cart_setting || 3 == $add_to_cart_setting ) && $count == $default_parameter_no && false == $product_default_set ) {
									$existing_data['data']['is_default'] = true;
									$product_default_set                 = true;
								}
							} else {
								if ( 2 == $add_to_cart_setting ) {
									if ( false == $product_default_set ) {
										$existing_data['data']['is_default'] = true;
										$product_default_set                 = true;
									}
								} else {
									$existing_data['data']['is_default'] = true;
								}
							}

							$new_products[ $existing_data['key'] ] = $existing_data['data'];
							$count ++;
							continue;
						}
						$product = wc_get_product( $pid );
						if ( $product instanceof WC_Product ) {
							$product_type = $product->get_type();
							$image_id     = $product->get_image_id();
							$default      = WFACP_Common::get_default_product_config();
							$image        = '';

							if ( ! empty( $image_id ) ) {
								$image = wp_get_attachment_image_src( $image_id );
							}

							$default['image']             = ( is_array( $image ) ) ? $image[0] : '';
							$default['type']              = $product_type;
							$default['id']                = $product->get_id();
							$default['parent_product_id'] = $product->get_parent_id();
							$default['title']             = $product->get_title();
							$default['quantity']          = 1;
							$default['org_quantity']      = 1;

							if ( ! is_null( $default_parameter_no ) && $default_parameter_no > 0 && count( $products ) >= $default_parameter_no ) {

								if ( ( 2 == $add_to_cart_setting || 3 == $add_to_cart_setting ) && $count == $default_parameter_no && false == $product_default_set ) {
									$default['is_default'] = true;
									$product_default_set   = true;
								}
							} else {
								if ( 2 == $add_to_cart_setting ) {
									if ( false == $product_default_set ) {
										$default['is_default'] = true;
										$product_default_set   = true;
									}
								} else {
									$default['is_default'] = true;
								}
							}

							$default['not_existing_product'] = true;
							if ( isset( $products_qty[ $pid_index ] ) && $products_qty[ $pid_index ] > 0 ) {
								$default['add_to_cart_via_url_quantity'] = $products_qty[ $pid_index ];
							}


							if ( 'variable' === $product_type ) {
								$default['variable'] = 'yes';
								$default['price']    = $product->get_price_html();
								$is_found_variation  = WFACP_Common::get_default_variation( $product );

								if ( count( $is_found_variation ) > 0 ) {
									$default['default_variation']      = $is_found_variation['variation_id'];
									$default['default_variation_attr'] = $is_found_variation['attributes'];
								}
							} else {
								$row_data                 = $product->get_data();
								$sale_price               = $row_data['sale_price'];
								$default['price']         = wc_price( $row_data['price'] );
								$default['regular_price'] = wc_price( $row_data['regular_price'] );
								if ( '' != $sale_price ) {
									$default['sale_price'] = wc_price( $sale_price );
								}
							}
							$default                        = WFACP_Common::remove_product_keys( $default );
							$default['add_to_cart_via_url'] = true;
							$default['whats_included']      = '';
							$new_products[ $unique_id ]     = $default;
							$count ++;
						}
					}

					if ( count( $new_products ) > 0 ) {
						$this->products       = $new_products;
						$this->products_count += count( $new_products );
					} else {
						$this->add_to_cart_via_url = false;
					}


				}
			}
		}

		private function push_product_to_cart() {


			do_action( 'wfacp_before_add_to_cart', $this->products );

			$product_switcher_data = WFACP_Common::get_product_switcher_data( WFACP_Common::get_id() );
			$add_to_cart_setting   = isset( $product_switcher_data['product_settings']['add_to_cart_setting'] ) ? $product_switcher_data['product_settings']['add_to_cart_setting'] : '';
			$default_products      = [];

			// only check for default product if aero-add-to-checkout parameter not set
			if ( false == $this->add_to_cart_via_url ) {
				if ( isset( $product_switcher_data['default_products'] ) ) {
					if ( is_string( $product_switcher_data['default_products'] ) ) {
						$default_products[] = trim( $product_switcher_data['default_products'] );
					} elseif ( is_array( $product_switcher_data['default_products'] ) ) {
						$default_products = $product_switcher_data['default_products'];
					}
				}

				if ( ( 2 == $add_to_cart_setting || 3 == $add_to_cart_setting ) && $this->get_product_count() > 1 ) {

					if ( is_array( $default_products ) && count( $default_products ) > 0 ) {
						$may_be_skip_product = [];
						if ( 2 == $add_to_cart_setting && count( $default_products ) > 1 ) {
							$temp_first = $default_products[0];
							unset( $default_products );
							$default_products[] = $temp_first;
						}

						foreach ( $default_products as $dpk => $dp ) {
							if ( isset( $this->products[ $dp ] ) ) {
								$product_available = $this->product_available_form_purchase( $this->products[ $dp ], $dp );
								if ( false == $product_available ) {
									unset( $default_products[ $dpk ] );
									$may_be_skip_product[] = $dp;
								}
							} else {
								unset( $default_products[ $dpk ] );
							}
						}

						if ( empty( $default_products ) ) {
							foreach ( $this->products as $index => $data ) {
								if ( in_array( $index, $may_be_skip_product ) ) {
									continue;
								}
								$product_available = $this->product_available_form_purchase( $data, $index );
								if ( true == $product_available ) {
									$default_products[] = $index;
									break;
								}
							}
						}
					}
					unset( $data, $product_id, $quantity, $variation_id, $product_obj );
				} else {
					if ( ! empty( $this->products ) ) {
						$key                    = key( $this->products );
						$value                  = reset( $this->products );
						$value['is_default']    = true;
						$this->products[ $key ] = $value;
					}
				}
			}

			$hide_best_value     = wc_string_to_bool( $product_switcher_data['settings']['hide_best_value'] );
			$best_value_product  = trim( $product_switcher_data['settings']['best_value_product'] );
			$best_value_text     = trim( $product_switcher_data['settings']['best_value_text'] );
			$best_value_position = trim( $product_switcher_data['settings']['best_value_position'] );

			if ( function_exists( 'WCCT_Core' ) && class_exists( 'WCCT_discount' ) ) {
				add_filter( 'wcct_force_do_not_run_campaign', [ $this, 'unset_wcct_campaign' ], 10, 2 );
			}

			$virtual_product       = 0;
			$best_value_by_session = WC()->session->get( 'wfacp_product_best_value_by_parameter_' . WFACP_Common::get_id(), '' );

			$best_value_by_parameter = [];
			if ( '' !== $best_value_by_session ) {
				$best_value_by_parameter = explode( ',', $best_value_by_session );
			}

			$best_value_counter = 1;

			$product_count                 = $this->get_product_count();
			$apply_best_value_by_parameter = false;

			if ( ! empty( $best_value_by_parameter ) && count( $best_value_by_parameter ) <= $product_count ) {
				$apply_best_value_by_parameter = true;
				$best_value_product            = '';
			}

			foreach ( $this->products as $index => $data ) {
				$product_id   = absint( $data['id'] );
				$quantity     = absint( $data['quantity'] );
				$variation_id = 0;
				if ( $data['parent_product_id'] && $data['parent_product_id'] > 0 ) {
					$product_id   = absint( $data['parent_product_id'] );
					$variation_id = absint( $data['id'] );
				}

				$product_obj = WFACP_Common::wc_get_product( ( $variation_id > 0 ? $variation_id : $product_id ), $index );
				if ( ! $product_obj instanceof WC_Product ) {
					continue;
				}
				if ( $product_obj->is_virtual() ) {
					$virtual_product ++;
				}

				// do not check default product setting when product is added from aero add to checkout parameter
				if ( ! isset( $data['add_to_cart_via_url'] ) ) {
					//force all condition
					if ( 1 == $add_to_cart_setting || empty( $default_products ) ) {
						// make all product default
						$data['is_default'] = true;
					} elseif ( 2 == $add_to_cart_setting || 3 == $add_to_cart_setting ) {
						if ( in_array( $index, $default_products ) ) {
							$data['is_default'] = true;
						}
					}
				}

				if ( ! isset( $data['add_to_cart_via_url'] ) || ! isset( $data['add_to_cart_via_url_quantity'] ) ) {
					$data['org_quantity'] = $quantity;
					$data['quantity']     = 1;
				}

				// assign url quantity to quantity key in product data for sustainability
				if ( isset( $data['add_to_cart_via_url_quantity'] ) ) {
					$data['quantity'] = $data['add_to_cart_via_url_quantity'];
				}
				$data = apply_filters( 'wfacp_single_product_data', $data, $index, $this );

				// merger product switcher data
				if ( isset( $product_switcher_data['products'][ $index ] ) ) {
					$data = wp_parse_args( $product_switcher_data['products'][ $index ], $data );
				}

				if ( false == $hide_best_value && '' != $best_value_text ) {

					if ( $apply_best_value_by_parameter && in_array( $best_value_counter, $best_value_by_parameter ) ) {
						$data['best_value']          = true;
						$data['best_value_text']     = $best_value_text;
						$data['best_value_position'] = $best_value_position;
					} elseif ( $index == $best_value_product ) {
						$data['best_value']          = true;
						$data['best_value_text']     = $best_value_text;
						$data['best_value_position'] = $best_value_position;
					}
				}

				$data['item_key']         = $index;
				$this->products[ $index ] = $data;

				$product_obj->add_meta_data( 'wfacp_data', $data );
				$this->added_products[ $index ] = $product_obj;
				if ( in_array( $product_obj->get_type(), WFACP_Common::get_variable_product_type() ) ) {
					$this->variable_product = true;
				}
				$best_value_counter ++;
			}

			$is_product_added_to_cart = false;

			$all_notices = wc_get_notices();
			$success     = [];

			$run_status = apply_filters( 'wfacp_run_add_to_cart_at_load', true, $this );
			if ( true == $run_status ) {

				foreach ( $this->added_products as $index => $product_obj ) {
					$data = $product_obj->get_meta( 'wfacp_data' );
					$data = apply_filters( 'wfacp_single_added_product_data', $data, $index, $product_obj, $this );
					if ( ! is_array( $data ) ) {
						continue;
					}

					if ( ! isset( $data['is_default'] ) ) {
						continue;
					}

					$product_id = absint( $data['id'] );
					$quantity   = absint( $data['org_quantity'] );
					if ( isset( $data['not_existing_product'] ) ) {
						$quantity = absint( $data['quantity'] );
					}
					if ( isset( $data['add_to_cart_via_url_quantity'] ) ) {
						$quantity = $data['add_to_cart_via_url_quantity'];
					}
					$variation_id = 0;
					if ( $data['parent_product_id'] && $data['parent_product_id'] > 0 ) {
						$product_id   = absint( $data['parent_product_id'] );
						$variation_id = absint( $data['id'] );
					}
					try {
						$attributes  = [];
						$custom_data = [];

						if ( isset( $data['variable'] ) ) {
							$variation_id                             = isset( $data['default_variation'] ) ? absint( $data['default_variation'] ) : 0;
							$attributes                               = isset( $data['default_variation_attr'] ) ? $data['default_variation_attr'] : [];
							$custom_data['wfacp_variable_attributes'] = $attributes;
							$default_variation                        = WFACP_Common::get_default_variation( $product_obj );
							if ( count( $default_variation ) > 0 ) {
								$variation_id = absint( $default_variation['variation_id'] );
								$attributes   = $default_variation['attributes'];
							}
						} else if ( in_array( $product_obj->get_type(), WFACP_Common::get_variation_product_type() ) ) {
							$attributes = $product_obj->get_attributes();
							if ( empty( $attributes ) ) {
								continue;
							}
							if ( WFACP_Common::is_invalid_variation_attribute( $attributes ) ) {
								$attributes = WFACP_Common::map_variation_attributes( $attributes, wc_get_product( $product_id )->get_variation_attributes() );
							} else {
								$new_attributes = [];
								foreach ( $attributes as $ts => $attribute ) {
									$ts                    = 'attribute_' . $ts;
									$new_attributes[ $ts ] = $attribute;
								}
								$attributes = $new_attributes;
							}

						}
						$custom_data['_wfacp_product']     = true;
						$custom_data['_wfacp_product_key'] = $index;
						$custom_data['_wfacp_options']     = $data;
						$attributes                        = apply_filters( 'wfacp_product_attributes', $attributes, $product_obj, $product_id, $variation_id, $custom_data );
						$cart_key                          = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes, $custom_data );

						if ( is_string( $cart_key ) ) {
							$success[]                        = $cart_key;
							$this->products_in_cart[ $index ] = 1;
							$data['is_added_cart']            = $cart_key;
							$this->added_products[ $index ]->update_meta_data( 'wfacp_data', $data );;
							if ( isset( $data['org_quantity'] ) ) {
								$this->products[ $index ]['org_quantity'] = $data['org_quantity'];
							}
							$this->products[ $index ]['is_added_cart'] = $cart_key;
							$this->have_product                        = true;
							$is_product_added_to_cart                  = true;
							do_action( 'wfacp_product_added_to_cart', $cart_key, $this->added_products[ $index ] );
						} else {
							unset( $this->added_products[ $index ], $this->products[ $index ] );
						}
					} catch ( Exception $e ) {

					}
				}
			}

			if ( false == $is_product_added_to_cart ) {
				$all_notices = array_merge( wc_get_notices(), $all_notices );
				WC()->session->set( 'wc_notices', $all_notices );
			} else {
				WC()->session->set( 'wc_notices', $all_notices );
			}

			do_action( 'wfacp_after_add_to_cart' );
			if ( count( $success ) > 0 || false == $run_status ) {
				WC()->cart->removed_cart_contents = [];

				WC()->session->set( 'wfacp_id', WFACP_Common::get_id() );
				WC()->session->set( 'wfacp_cart_hash', md5( maybe_serialize( WC()->cart->get_cart_contents() ) ) );
				WC()->session->set( 'wfacp_product_objects_' . WFACP_Common::get_id(), $this->added_products );
				WC()->session->set( 'wfacp_product_data_' . WFACP_Common::get_id(), $this->products );
			}

		}

		public function aero_add_to_checkout_parameter() {
			return apply_filters( 'wfacp_aero_add_to_checkout_parameter', 'aero-add-to-checkout' );
		}

		public function aero_add_to_checkout_product_quantity_parameter() {
			return apply_filters( 'wfacp_add_to_checkout_product_quantity_parameter', 'aero-qty' );
		}


		public function aero_default_value_parameter() {
			return apply_filters( 'wfacp_aero_default_value_parameter', 'aero-default' );
		}

		public function aero_best_value_parameter() {
			return apply_filters( 'wfacp_aero_best_value_parameter', 'aero-best-value' );
		}

		public function aero_coupons_value_parameter() {
			return apply_filters( 'wfacp_aero_coupon_parameter', 'aero-coupons' );
		}

		public function merge_default_product( $default_products, $products, $settings ) {
			$default = $this->aero_default_value_parameter();
			if ( isset( $_GET[ $default ] ) && '' !== $_GET[ $default ] ) {

				$data = WC()->session->get( 'wfacp_product_default_value_parameter_' . WFACP_Common::get_id(), '' );

				if ( '' !== $data ) {

					$default_data = explode( ',', $_GET[ $default ] );

					if ( ! empty( $default_data ) ) {
						$default_products = [];
					}

					if ( true == $this->add_to_cart_via_url ) {
						$products = $this->products;

					}
					$counter = 1;
					foreach ( $products as $key => $product ) {
						if ( in_array( $counter, $default_data ) ) {
							$default_products[] = $key;
						}
						$counter ++;
					}
					$default_products = array_unique( $default_products );

				}
			}

			return $default_products;
		}

		/**
		 * @param $data array;
		 */
		public function product_available_form_purchase( $data, $unique_key ) {

			$product_available = true;
			$product_id        = absint( $data['id'] );
			$quantity          = absint( $data['quantity'] );
			$variation_id      = 0;
			if ( $data['parent_product_id'] && $data['parent_product_id'] > 0 ) {
				$product_id   = absint( $data['parent_product_id'] );
				$variation_id = absint( $data['id'] );
			}
			$product_obj = WFACP_Common::wc_get_product( ( $variation_id > 0 ? $variation_id : $product_id ), $unique_key );
			if ( ! $product_obj instanceof WC_Product ) {
				return false;
			}
			$stock_status = WFACP_Common::check_manage_stock( $product_obj, $quantity );

			if ( ! $product_obj->is_purchasable() || false == $stock_status ) {
				$product_available = false;
			}

			return $product_available;
		}

		public function get_product_count() {
			return $this->products_count;
		}


		public function restrict_sold_individual( $status, $product_id ) {
			$cart_content = WC()->cart->get_cart_contents();
			if ( ! empty( $cart_content ) ) {
				foreach ( $cart_content as $item_key => $item_data ) {
					if ( $item_data['product_id'] == $product_id && isset( $item_data['_wfacp_options'] ) ) {
						$status = true;
						break;
					}
				}
			}

			return $status;
		}

		/**
		 * @param $query WP_Query
		 */
		public function load_page_to_home_page( $query ) {
			if ( $query->is_main_query() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {

				$post_type = $query->get( 'post_type' );

				$page_id = $query->get( 'page_id' );

				if ( empty( $post_type ) && ! empty( $page_id ) ) {
					$t_post = get_post( $page_id );
					if ( $t_post && $t_post->post_type == WFACP_Common::get_post_type_slug() ) {
						$query->set( 'post_type', get_post_type( $page_id ) );
					}
				}
			}
		}

		public function force_purchasable_quick_view( $variation_data ) {
			$variation_data['is_purchasable'] = true;

			return $variation_data;
		}

		/**
		 * hide add to cart tracking for global checkout
		 *
		 * @param $data
		 *
		 * @return array|mixed
		 */
		public function update_tracking_data( $data ) {
			try {
				/*
				 * Run add to cart on checkout page when add to cart run for custom ajax by theme
				*/
				if ( function_exists( 'WC' ) && ! is_null( WC()->session ) && WC()->session->has_session() ) {
					$events = WC()->session->get( 'wffn_pending_data' );
					if ( ! is_null( $events ) && is_array( $events ) && count( $events ) > 0 ) {
						$data['settings']['add_to_cart'] = 'true';
						$data['wffn_pending_event']      = 'true';

						return $data;
					}
				}
				if ( ! isset( $data['settings']['add_to_cart'] ) || 'true' !== $data['settings']['add_to_cart'] ) {
					return $data;
				}

				if ( isset( $_GET['add-to-cart'] ) && $_GET['add-to-cart'] > 0 ) {
					return $data;
				}

				if ( $this->is_checkout_override ) {
					$data['settings']['add_to_cart'] = 'false';
				}
			} catch ( Exception|Error $e ) {

			}

			return $data;
		}


		/**
		 * script render for on native checkout
		 * @return void
		 */
		public function enqueue_script() {
			if ( empty( $this->is_native_checkout() ) ) {
				return;
			}
			wp_enqueue_script( 'jquery' );

			if ( defined( 'BWF_DEV' ) ) {
				wp_enqueue_script( 'wfacp_track_checkout_js', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/js/native-tracks.js', [ 'jquery' ], WFACP_VERSION_DEV, false );
			} else {
				wp_enqueue_script( 'wfacp_track_checkout_js', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/js/native-tracks.min.js', [ 'jquery' ], WFACP_VERSION_DEV, false );
			}
			wp_localize_script( 'wfacp_track_checkout_js', 'wfacp_analytics_data', $this->get_analytics_data() );
		}

		/**
		 * localize tracking data for native checkout
		 * @return array|void
		 */
		public function get_analytics_data() {
			if ( empty( $this->is_native_checkout() ) ) {
				return;
			}
			$checkout = $this->is_native_checkout();

			// prepare data for native checkout
			do_action( 'wfacp_after_native_checkout_page_found' );

			$final    = [];
			$services = WFACP_Analytics::get_available_service();

			foreach ( $services as $service => $analytic ) {
				/**
				 * @var $analytic WFACP_Analytics;
				 */
				$final[ $service ] = $analytic->get_prepare_data();
			}
			$do_tracking = true;
			if ( is_checkout_pay_page() ) {
				$do_tracking = false;
			}
			$final['shouldRender'] = apply_filters( 'wfacp_do_tracking', $do_tracking );

			$final['conversion_api'] = 'false';
			$admin_general           = BWF_Admin_General_Settings::get_instance();
			if (  ! empty( $admin_general->get_option( 'conversion_api_access_token' ) ) ) {
				$final['conversion_api'] = 'true';
			}
			$final['wfacp_frontend']  = [
				'id'            => $checkout->ID,
				'title'         => get_the_title( $checkout->ID ),
				'edit_mode'     => WFACP_Common::is_theme_builder() ? 'yes' : 'no',
				'is_customizer' => false,
				'admin_ajax'    => admin_url( 'admin-ajax.php' ),
				'wc_endpoints'  => WFACP_AJAX_Controller::get_public_endpoints(),
				'wfacp_nonce'   => wp_create_nonce( 'wfacp_secure_key' ),
			];
			$final['fb_advanced']     = WFACP_Common::pixel_advanced_matching_data();
			$final['tiktok_advanced'] = WFACP_Common::tiktok_advanced_matching_data();

            WFACP_Common::clear_pending_events_data_from_session();
			return $final;
		}

		/**
		 * check is site native checkout
		 * @return array|false|int|mixed|WP_Post
		 */
		public function is_native_checkout() {
			global $post;
			if ( is_null( $post ) ) {
				return false;
			}
			if ( $post->post_status !== 'publish' || $post->post_type === WFACP_Common::get_post_type_slug() || 0 !== did_action( 'wfacp_after_checkout_page_found' ) ) {
				return false;
			}
			$meta_exist = get_post_meta( $post->ID, '_is_aero_checkout_page', true );

			if ( '' != $meta_exist ) {
				return false;
			}

			if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
				return false;
			}

			return $post;
		}

		public function shimmer_css() {
			if ( apply_filters( 'wfacp_shimmer_active', true ) ) {
				echo "<style>";
				include plugin_dir_path( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-shimmer.min.css';
				echo "</style>";
			} else {

				$path = plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/img/spinner.gif';
				echo "<style>";
				echo 'body .wfacp_main_form #wfacp_checkout_form .blockUI.blockOverlay {  background: url(' . $path . ') no-repeat 50% rgb(255, 255, 255) !important;display:block !important;}';
				echo '.wfacp_anim_active .wfacp_main_form #wfacp_checkout_form #product_switching_field .wfacp_product_switcher_container:before {  background:#ffffff60 url(' . $path . ') no-repeat 50% !important;margin: auto;z-index: 99;position: absolute;content: "";left: 0;right: 0;bottom: 0;top: 0;}';
				echo '.wfacp_anim_active .wfacp_main_form #wfacp_checkout_form #product_switching_field .wfacp_product_switcher_container{position: relative;}';
				echo "</style>";
			}
		}

		/**
		 * Unset Aero line item data on cart page
		 *
		 * @param array $cart_item_data
		 * @param array $cart_item
		 */
		public function unset_aero_line_item_data_in_cart( $cart_item_data, $cart_item ) {

			if ( empty( $cart_item_data ) ) {
				return $cart_item_data;
			}

			foreach ( $cart_item_data as $index => $line_item ) {
				if ( isset( $line_item['key'] ) && false !== strpos( $line_item['key'], 'wfacp' ) ) {
					unset( $cart_item_data[ $index ] );
				}
			}


			return $cart_item_data;
		}

		public function attach_awaiting_order_id( $order_id ) {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return;
			}
			WC()->session->set( 'wfacp_await_order_' . WFACP_Common::get_id(), $order_id );

		}

		/**
		 * HIde quantity switcher and delete icon form free item
		 *
		 * @param $status
		 * @param $cart_item
		 *
		 * @return false|mixed
		 */

		public function check_for_free_product( $status, $cart_item ) {


			$exclude_post_types = [ 'composite', 'bundle' ,'easy_product_bundle' ];
			if ( ! ( $cart_item['line_subtotal'] > 0 ) && ! in_array( $cart_item['data']->get_type(), $exclude_post_types ) ) {
				$status = false;
			}

			return $status;
		}

		public function hide_quantity_for_free_product( $status, $product_data, $pro ) {
			if ( ! $pro instanceof WC_Product || $pro->get_price() > 0 || $pro->get_price( 'edit' ) > 0 ) {
				return $status;
			}


			return false;
		}

		public function add_blank_dropdown_field_in_options( $field ) {

			if ( isset( $field['type'] ) && in_array( $field['type'], [ 'select', 'dropdown' ] ) && isset( $field['placeholder'] ) && ! empty( $field['placeholder'] ) ) {
				$field['options'] = [ '' => $field['placeholder'] ] + $field['options'];
			}

			return $field;
		}

		/**
		 * Display shipping placeholder message based on shipping address status
		 *
		 * @param bool $status Current placeholder display status
		 *
		 * @return bool Updated placeholder display status
		 */
		public function display_shipping_placeholder_message( $status ) {
			try {
				// Early return if shipping address is not required
				$shipping_address_required = get_option( 'woocommerce_shipping_cost_requires_address', 'yes' );
				if ( 'no' === $shipping_address_required ) {
					return $status;
				}

				// Check if WooCommerce is active and properly initialized
				if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->shipping() ) {
					return $status;
				}

				// Get shipping packages
				$packages = WC()->shipping()->get_packages();

				// If no packages exist, return the original status
				if ( empty( $packages ) ) {
					return $status;
				}

				$display_placeholder = false;

				foreach ( $packages as $i => $package ) {
					// Check if package has a destination
					if ( ! isset( $package['destination'] ) || empty( $package['destination'] ) ) {
						continue;
					}

					// Get formatted destination and check if shipping has been calculated
					$formatted_destination = '';
					if ( is_callable( array( WC()->countries, 'get_formatted_address' ) ) ) {
						$formatted_destination = WC()->countries->get_formatted_address( $package['destination'], ', ' );
					}

					$has_calculated_shipping = false;
					if ( is_callable( array( WC()->customer, 'has_calculated_shipping' ) ) ) {
						$has_calculated_shipping = ! empty( WC()->customer->has_calculated_shipping() );
					}

					if ( ! $has_calculated_shipping || ! $formatted_destination ) {
						// Skip if we're in cart and shipping calculation is disabled
						if ( is_cart() && 'no' === get_option( 'woocommerce_enable_shipping_calc', 'yes' ) ) {
							continue;
						} else {
							$display_placeholder = true;
							break; // We found one package that needs a placeholder, exit loop
						}
					}
				}

				return $display_placeholder ? true : $status;

			} catch ( Exception $e ) {
				// Log the error if a logging facility is available
				if ( function_exists( 'wc_get_logger' ) ) {
					$logger = wc_get_logger();
					$logger->error( 'Error in display_shipping_placeholder_message: ' . $e->getMessage(), array( 'source' => 'shipping-placeholder' ) );
				}

				// Return original status on error to prevent breaking the checkout
				return $status;
			}
		}

		/**
		 * @return void Date of field Printing code
		 */


		public function call_bwf_dob_field( $field, $key, $args ) {

			if($this->is_dob_field_enabled()){
				return;
			}

			if ( empty( $key ) || $key !== 'bwfan_birthday_date' ) {

				return;
			}

			$labels = [ 'months', 'days', 'years' ];
			$days   = $months = $years = [''=>__('Please select...', 'woocommerce')];


			$required=false;
			$show_custom_field_at_email=false;
			$show_custom_field_at_thankyou=false;


            if(isset($args['required']) && $args['required'] === true){
	            $required=true;
            }
			if(isset($args['show_custom_field_at_email']) && $args['show_custom_field_at_email'] === true){
				$show_custom_field_at_email=true;
			}

			if(isset($args['show_custom_field_at_thankyou']) && $args['show_custom_field_at_thankyou'] === true){
				$show_custom_field_at_thankyou=true;
			}

			$min_age=0;

            if(isset($args['bwfan_birthday_date']) && !empty($args['bwfan_birthday_date'])){
	            $min_age=absint( $args['bwfan_birthday_date'] );
            }




			foreach ( $labels as $label ) {
				$min = 1;
				if ( $label == 'days' ) {
					$max = 31;
				} elseif ( $label == 'months' ) {
					$max = 12;
				} elseif ( $label == 'years' ) {
					$min = absint( date( 'Y' ) ) - 115;
					$max = absint( date( 'Y' ) ) - absint( $this->min_age );
				}
				for ( $i = $min; $i <= $max; $i ++ ) {
					$m = ( $i < 10 ) ? '0' . $i : $i;
					if ( $label == 'months' ) {
						$months[ $m ] = date( 'M', mktime( 0, 0, 0, $i, 1, date( 'Y' ) ) );
					} elseif ( $label == 'days' ) {
						$days[ $m ] = $i;
					} elseif ( $label == 'years' ) {
						$years[ $i ] = $i;
					}
				}
			}



			$dob_fields = array(
				'bwfan_birthday_date_dd'   => [
					'id' => 'bwfan_birthday_date_dd',
					'label' => __( 'Day' ),
					'data_label' =>__( 'Day' ),
					'placeholder' => __( 'Day'),
					'type' => 'select',
					'required' => $required,
					'options' => $this->get_date_select_options( 'date', 'aerocheckout' ),
					'default' => '',
					'show_custom_field_at_thankyou' => $show_custom_field_at_thankyou,
					'show_custom_field_at_email' => $show_custom_field_at_email,
					'is_wfacp_field' => true,
					'field_type' => 'advanced',
					'allow_delete' => true,


				],
				'bwfan_birthday_date_mm' => [
                    'id' => 'bwfan_birthday_date_mm',
					'label' => __( 'Month' ),
					'data_label' =>__( 'Month' ),
					'placeholder' =>__( 'Month' ),
					'type' => 'select',
					'required' => $required,
					'options' => $this->get_date_select_options( 'month', 'aerocheckout' ),
                    'show_custom_field_at_thankyou' => $show_custom_field_at_thankyou,
                    'show_custom_field_at_email' => $show_custom_field_at_email,
					'is_wfacp_field' => true,
					'field_type' => 'advanced',
					'allow_delete' => true,
                    'default' => '',

                ],
				'bwfan_birthday_date_yy'  => [
					'id' => 'bwfan_birthday_date_yy',
					'label' => __( 'Year' ),
					'data_label' =>__( 'Year' ),
					'placeholder' => __( 'Year'),
					'cssready' => array ( ),
					'type' => 'select',
					'required' => $required,
					'options' => $this->get_date_select_options( 'year', 'aerocheckout' ,$min_age),
					'default' => '',
					'show_custom_field_at_thankyou' => $show_custom_field_at_thankyou,
					'show_custom_field_at_email' => $show_custom_field_at_email,
					'is_wfacp_field' => true,
					'field_type' => 'advanced',
					'allow_delete' => true,

				]
			);
			$default_fields      = [
				'input_class'=>			[
					'wfacp-form-control',

				],
				'label_class'=>			[
					'wfacp-form-control-label',
				],
	            'class'=>			[
	                'wfacp-dob-field-wrap',
	                'form-row',
	                'wfacp-dob-wrapper',
	                'wfacp-form-control-wrapper',
		            'wfacp-col-left-third',
                    'wfacp_drop_list',
                    'wfacp_dropdown',

                ],
				'cssready'=>			[
					'wfacp-col-left-third',
				],

			];


            $dob_label='';

            if(isset($args['label'])){
	            $dob_label =$args['label'];
            }
			$required='';
			if(isset($args['required'])){
				$required =$args['required'];
			}




            if(is_array($dob_fields) && count($dob_fields) > 0){

                echo "<div class='wfacp-dob-field-sec'>";

                ?>

                <label for="bwfan_birthday_date" class="wfacp-form-control-label wfacp-birthday-label">
                    <?php echo $dob_label; ?>
		            <?php if ( ! empty( $required ) ) { ?>
                        <abbr class="required" title="required">*</abbr>
		            <?php } ?>
                </label>
<?php

                foreach ( $dob_fields as $k => $field ) {
	                $field['input_class']=$default_fields['input_class'];
	                $field['label_class']=$default_fields['label_class'];
	                $field['class']=$default_fields['class'];
	                $field['cssready']=$default_fields['cssready'];
	                woocommerce_form_field( $k, $field );
                }
                echo "</div>";
            }
		}



		public function wfacp_validate_dob_field( $fields, $errors ) {

			$template = wfacp_template();
			if ( ! $template instanceof WFACP_Template_Common ) {
				return $fields;
			}
			$checkout_fields = $template->get_checkout_fields();
			$advancedFields  = $checkout_fields['advanced'];
			$this->min_age   = ! empty( $advancedFields['bwfan_birthday_date']['bwfan_birthday_date'] ) ? $advancedFields['bwfan_birthday_date']['bwfan_birthday_date'] : $this->min_age;
            if(isset($advancedFields['bwfan_birthday_date']['required']) && $advancedFields['bwfan_birthday_date']['required']===true){
	            $min_date_of_birth = strtotime( "-" . $this->min_age . " years" );
	            $selected_date     = strtotime( $fields['bwfan_birthday_date'] );
	            $field_value       = explode( '-', $fields['bwfan_birthday_date'] );



	            $year             = (int) $field_value[0];
	            $month            = (int) $field_value[1];
	            $day              = (int) $field_value[2];


	            if ( (empty( $month ) || empty( $day ) || empty( $year )) && !$this->is_dob_field_enabled() ) {

		            return;
	            }

	            if ( ! checkdate( $month, $day, $year ) ) {
		            $errors->add( 'validation', __( 'Please enter a valid date.', 'woocommerce' ) );
	            } elseif ( $selected_date > $min_date_of_birth ) {
		            $errors->add( 'validation', sprintf(
			            __( 'Your age must be %d years or greater.', 'woofunnels-aero-checkout' ),
			            $this->min_age
		            ) );
	            }

            }

		}

		public function move_dob_to_post_data( $posted_data ) {

			if ( ! isset( $posted_data['bwfan_birthday_date'] ) ) {
				return $posted_data;
			}

			$day   = isset( $_POST['bwfan_birthday_date_dd'] ) ? trim( $_POST['bwfan_birthday_date_dd'] ) : '';
			$month = isset( $_POST['bwfan_birthday_date_mm'] ) ? trim( $_POST['bwfan_birthday_date_mm'] ) : '';
			$year  = isset( $_POST['bwfan_birthday_date_yy'] ) ? trim( $_POST['bwfan_birthday_date_yy'] ) : '';

			if ( $day && $month && $year && checkdate( (int)$month, (int)$day, (int)$year ) ) {
				$dob = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				$posted_data['bwfan_birthday_date'] = $dob;
			}else{
				$posted_data['bwfan_birthday_date']='';
			}

			return $posted_data;
		}

		public function move_dob_order_meta( $order_id, $data ) {

			if($this->is_dob_field_enabled()){
				return;
			}

			if ( isset( $data['bwfan_birthday_date'] ) ) {
				$order = wc_get_order( $order_id );
				$order->update_meta_data( 'bwfan_birthday_date', $data['bwfan_birthday_date'] );
				$order->save();
				/**
				 * Date field value save in the usermeta
				 */
				$user_id = $order->get_user_id() ? $order->get_user_id() : "";
				if (! empty( $user_id ) ) {
					update_user_meta( $user_id, 'bwfan_birthday_date', $data['bwfan_birthday_date'] );
				}


			}
		}

		public function change_options( $field, $field_key ,$order) {

			if($this->is_dob_field_enabled()){
				return $field;
			}

			if ( 'wfacp_bwfan_birthday_date' !== $field_key ) {
				return $field;
			}

			if ( ! $order instanceof WC_Order ) {
				return $field;
			}

			$wfacp_id        = wfacp_get_order_meta( $order, '_wfacp_post_id' );
			$c_fields        = WFACP_Common::get_checkout_fields( $wfacp_id );
			$advanced_fields = isset( $c_fields['advanced'] ) ? $c_fields['advanced'] : [];
			if ( empty( $advanced_fields ) || ! isset( $advanced_fields['bwfan_birthday_date'] ) ) {
				return $field;
			}

			$field['type'] = "text";
			return $field;
		}


		public function get_date_select_options( $type = 'date', $page = '' ,$min_age=0) {

			$options = [];

			switch ( $type ) {
				case 'date':
					$options = 'aerocheckout' === $page ? array( '' => __( 'Select Day', 'woofunnels-aero-checkout' ) ) : [];
					for ( $i = 1; $i <= 31; $i ++ ) {
						$key             = sprintf( "%02d", $i );
						$options[ $key ] = $i;
					}
					break;

				case 'month':
					$options = 'aerocheckout' === $page ? array( '' => __( 'Select Month', 'woofunnels-aero-checkout' ) ) : [];
					for ( $i = 1; $i <= 12; $i ++ ) {
						$key             = sprintf( "%02d", $i );
						$options[ $key ] = date( 'F', mktime( 0, 0, 0, $i, 1 ) );
					}
					break;

				case 'year':
					$options      = 'aerocheckout' === $page ? array( '' => __( 'Select Year', 'woofunnels-aero-checkout' ) ) : [];
					$current_year = (int) current_time( 'Y' );


					$years_back = apply_filters( 'bwfan_birthday_min_years', 100 );
					$years_back = max( 1, intval( $years_back ) );


					if($min_age > 0){
						$current_year=$current_year-$min_age;
					}


					$min_year = $current_year - $years_back;

					for ( $i = $current_year; $i >= $min_year; $i -- ) {
						$options[ (string) $i ] = (string) $i;
					}
					break;
			}

			return $options;
		}


	}

	if ( class_exists( 'WFACP_Core' ) && ! WFACP_Common::is_disabled() ) {
		WFACP_Core::register( 'public', 'WFACP_Public' );
	}


}