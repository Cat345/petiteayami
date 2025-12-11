<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WFOB_Public class
 */
class WFOB_Public {

	private static $ins = null;
	public $posted_data = [];
	private $is_footer_loaded = false;

	private $setup_bump_runs = false;
	public $shown_bump_ids = [];

	protected function __construct() {
		add_action( 'wp_loaded', [ $this, 'make_cart_empty' ], 99 );
		add_action( 'wp', [ $this, 'attach_hooks' ] );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'calculate_totals' ], PHP_INT_MAX );
		add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'calculate_totals' ], 2 );
		add_action( 'wp', [ $this, 'reset_wc_session' ] );


		// for first we display bump in payment.php and leve div container for further fragment of orderbump
		add_action( 'woocommerce_before_template_part', [ $this, 'add_order_bump' ], 11 );
		add_action( 'woocommerce_before_template_part', [ $this, 'add_order_bump_above_the_gateway' ], 999 );

		add_action( 'wfacp_after_gateway_list', [ $this, 'print_below_gateway' ] );

		add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'send_cart_total_fragment' ], 12 );
		add_filter( 'wfacp_show_item_quantity', [ $this, 'wfacp_skip_global_switcher_item' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_quantity', [ $this, 'remove_quantity_selector_from_cart' ], 10, 3 );
		add_action( 'template_redirect', [ $this, 'execute_bump_action' ], 20 );
		add_action( 'woocommerce_add_to_cart_sold_individually_found_in_cart', [ $this, 'restrict_sold_individual' ], 10, 2 );
		add_action( 'woocommerce_before_checkout_process', [ $this, 'capture_posted_data' ] );
		add_filter( 'wfacp_display_quantity_increment', [ $this, 'do_not_display_order_bump_quantity' ], 10, 2 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'add_input_hidden' ] );
		add_action( 'init', [ $this, 'execute_bump_fragments' ] );

		add_action( 'woocommerce_remove_cart_item', [ $this, 'woocommerce_remove_cart_item' ] );
		add_filter( 'wfacp_show_undo_message_for_item', [ $this, 'hide_undo_message' ], 10, 2 );


		add_filter( 'wfacp_cart_item_thumbnail', [ $this, 'apply_custom_url' ], 10, 2 );
		add_filter( 'wfacp_enable_delete_item', [ $this, 'show_mini_cart_delete_icon' ], 10, 3 );
		add_filter( 'wfacp_delete_item_from_order_summary', [ $this, 'show_order_summary_delete_icon' ], 10, 3 );
		add_filter( 'wfob_exclude_cart_item_in_rule', [ $this, 'hide_undo_message' ], 10, 2 );
		add_filter( 'wfob_exclude_cart_item_in_rule', [ $this, 'unset_replace_by_key' ], 11, 2 );
		add_filter( 'wfob_do_not_remove_failed_product', [ $this, 'do_not_remove_failed_product' ], 10, 2 );
		add_filter( 'wfob_dont_allow_bump_item_in_rule', [ $this, 'do_not_apply_rules_on_swap_bump' ], 10, 2 );


		add_filter( 'woocommerce_product_get_price', array( $this, 'handle_discount_product_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'handle_discount_product_price' ), 99, 2 );

		add_action( 'wfob_after_add_to_cart', [ $this, 're_run_rules_after_bump_removed' ], 10 );
		add_action( 'wfob_after_remove_bump_from_cart', [ $this, 're_run_rules_after_bump_removed' ], 10 );
		add_action( 'wfacp_order_bump_restored_end', [ $this, 're_run_rules_after_bump_removed' ], 10 );

		add_filter( 'woocommerce_add_cart_item', [ $this, 'set_selected_bump_in_session' ], 997, 2 );
		add_action( 'woocommerce_remove_cart_item', [ $this, 'unset_selected_bump_in_session' ], 25, 2 );
		add_action( 'wfacp_before_update_cart_multiple_page', [ $this, 're_add_bump_to_cart' ], 10, 3 );
		add_action( 'wfacp_reset_checkout_session_data', [ $this, 'unset_checkout_session_data' ] );
		add_action( 'wfacp_after_checkout_page_found', [ $this, 'unset_current_checkout_session_data' ] );
	}

	public function add_input_hidden() {
		?>
        <input type="hidden" name="wfob_input_hidden_data" id="wfob_input_hidden_data">
        <input type="hidden" name="wfob_input_bump_shown_ids" id="wfob_input_bump_shown_ids">
        <input type="hidden" name="wfob_input_bump_global_data" id="wfob_input_bump_global_data">
		<?php
	}

	public static function get_instance() {
		if ( null == self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	public function attach_hooks() {
		if ( apply_filters( 'wfob_skip_order_bump', false, $this ) ) {
			return;
		}


		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'get_cart_choosed_gateway' ), - 1 );
		add_action( 'woocommerce_after_calculate_totals', [ $this, 'setup_order_bumps' ], 999 );

		add_action( 'wfob_before_add_to_cart', [ $this, 'wfob_before_add_to_cart' ] );
		add_action( 'wfob_after_add_to_cart', [ $this, 'wfob_after_add_to_cart' ] );

		if ( is_checkout() ) {
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'wfacp_hooks' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
			add_filter( 'wp_footer', [ $this, 'footer' ], 9, 2 );
		}


	}

	/**
	 * @param $ins WC_Cart
	 */
	public function calculate_totals( $ins ) {

		if ( apply_filters( 'wfob_disabled_discounting', false, $this ) ) {
			return;
		}
		$cart_content = $ins->get_cart_contents();
		if ( count( $cart_content ) > 0 ) {
			foreach ( $cart_content as $key => $item ) {
				$item                       = $this->modify_calculate_price_per_session( $item, $key );
				$item                       = apply_filters( 'wfob_after_discount_added_to_item', $item, $key, $ins );
				$ins->cart_contents[ $key ] = $item;
			}
		}
	}


	/**
	 * Apply discount on basis of input for product raw prices
	 *
	 * @param $item WC_cart;
	 *
	 * @return mixed
	 */

	public function modify_calculate_price_per_session( $item, $key ) {

		if ( ! isset( $item['_wfob_product'] ) ) {
			return $item;
		}

		if ( floatval( $item['_wfob_options']['discount_amount'] ) == 0 && true == apply_filters( 'wfob_allow_zero_discounting', true, $item ) ) {
			return $item;
		}
		/**
		 * @var $product WC_product;
		 */
		$product         = $item['data'];
		$raw_data        = $product->get_data();
		$raw_data        = apply_filters( 'wfob_product_raw_data', $raw_data, $product, $key );
		$regular_price   = apply_filters( 'wfob_discount_regular_price_data', $raw_data['regular_price'], $key );
		$price           = apply_filters( 'wfob_discount_price_data', $raw_data['price'], $key );
		$discount_amount = apply_filters( 'wfob_discount_amount_data', $item['_wfob_options']['discount_amount'], $item['_wfob_options']['discount_type'], $key );
		$discount_data   = [
			'wfob_product_rp'      => $regular_price,
			'wfob_product_p'       => $price,
			'wfob_discount_amount' => $discount_amount,
			'wfob_discount_type'   => $item['_wfob_options']['discount_type'],
		];
		$new_price       = WFOB_Common::calculate_discount( $discount_data );
		if ( is_null( $new_price ) ) {
			return $item;
		} else {
			$parse_data = apply_filters( 'wfob_discounted_price_data', [ 'regular_price' => $regular_price, 'price' => $new_price ], '', $product, $raw_data );
			if ( apply_filters( 'wfob_set_bump_product_price_params', true, $item['data'] ) ) {
				$item['data']->update_meta_data( '_wfob_regular_price', $parse_data['regular_price'] );
				$item['data']->update_meta_data( '_wfob_price', $parse_data['price'] );
				$item['data']->update_meta_data( '_wfob_sale_price', $parse_data['price'] );
				$item['data']->update_meta_data( '_wfob_options', $item['_wfob_options'] );
				$item['data']->update_meta_data( '_wfob_product', $item['_wfob_product'] );
			}
			$item['data']->set_regular_price( $parse_data['regular_price'] );
			$item['data']->set_price( $parse_data['price'] );
			$item['data']->set_sale_price( $parse_data['price'] );

			do_action( 'wfob_after_discount_calculated_per_item', $item, $parse_data );

		}

		return $item;
	}

	/**
	 * @param string $return
	 *
	 * @return bool|string
	 * Function to setup all the gifts available for the cart
	 */
	public function get_cart_choosed_gateway( $return = '' ) {
		global $woocommerce;

		$arr = array();

		wp_parse_str( $return, $arr );

		if ( isset( $arr['_wfacp_post_id'] ) && empty( $this->posted_data ) ) {
			$this->posted_data = $arr;
		}
		if ( is_array( $arr ) && isset( $arr['payment_method'] ) && ! empty( $arr['payment_method'] ) ) {
			WC()->session->set( 'wfob_payment_method', $arr['payment_method'] );
		}
		if ( is_array( $arr ) && isset( $arr['billing_country'] ) && ! empty( $arr['billing_country'] ) ) {
			WC()->session->set( 'wfob_billing_country', $arr['billing_country'] );
		}
		if ( is_array( $arr ) && isset( $arr['shipping_country'] ) && ! empty( $arr['shipping_country'] ) ) {
			WC()->session->set( 'wfob_shipping_country', $arr['shipping_country'] );
		}

		if ( is_array( $arr ) && isset( $arr['shipping_method'] ) && ! empty( $arr['shipping_method'] ) ) {
			WC()->session->set( 'wfob_shipping_method', $arr['shipping_method'] );
		}
	}

	public function make_cart_empty() {
		// make cart empty when bump product present in cart
		if ( $this->all_item_is_bump_in_cart() ) {
			$this->empty_cart();

			return;
		}

	}

	private function empty_cart() {
		if ( apply_filters( 'wfob_do_not_make_empty_cart', true, $this ) ) {
			WC()->cart->empty_cart();
		}
	}


	public function setup_order_bumps( $postdata = [], $rematch_group = false ) {
		if ( true == $this->setup_bump_runs || is_customize_preview() || ( class_exists( 'WFACP_Common' ) && WFACP_Common::is_theme_builder() ) || ( ! wp_doing_ajax() && false == $this->show_on_load() ) ) {
			return;
		}

		// make cart empty when bump product present in cart
		if ( $this->all_item_is_bump_in_cart() ) {
			$this->empty_cart();

			return;
		}


		if ( isset( $_POST['post_data'] ) && empty( $this->posted_data ) ) {
			$postdata  = $_POST['post_data'];
			$post_data = [];
			parse_str( $postdata, $post_data );
			if ( isset( $post_data['_wfacp_post_id'] ) ) {
				$this->posted_data = $post_data;
			}
		}


		$wfob_transient_obj = WooFunnels_Transient::get_instance();
		$wfob_cache_obj     = WooFunnels_Cache::get_instance();

		$key = 'wfob_instances';

		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE !== '' && function_exists( 'wpml_get_current_language' ) ) {
			$key .= '_' . wpml_get_current_language();

		}
		WFOB_Rules::get_instance()->load_rules_classes();
		$bumps_from_base = apply_filters( 'wfob_bumps_from_external_base', false, $this->posted_data );

		/**
		 * If no funnel, then try to find the regular setup of bumps
		 */
		if ( false === $bumps_from_base ) {

			/**
			 * Check for the valid license here, if not found set bumps as blank
			 */
			$licenses = $this->license_data( '95f0c7de452bc5a2093f487689658a6144786d4f' );

			if ( ! defined( 'WFFN_PRO_FILE' ) && ( empty( $licenses['license'] ) || ( ! empty( $licenses['license']['expires'] ) && absint( $licenses['license']['expires'] ) > 0 && time() > strtotime( $licenses['license']['expires'] ) ) ) ) {
				$contents = array();

			} else {
				$contents = array();
				do_action( 'wfob_before_query' );

				$cache_data = $wfob_cache_obj->get_cache( $key, WFOB_SLUG );

				if ( false !== $cache_data ) {
					$contents = $cache_data;
				} else {
					$transient_data = $wfob_transient_obj->get_transient( $key, WFOB_SLUG );

					if ( false !== $transient_data ) {
						$contents = $transient_data;
					} else {
						$args = array(
							'post_type'        => WFOB_Common::get_bump_post_type_slug(),
							'post_status'      => 'publish',
							'nopaging'         => true,
							'order'            => 'ASC',
							'orderby'          => 'menu_order',
							'fields'           => 'ids',
							'suppress_filters' => false,
						);
						$args = apply_filters( 'wfob_add_control_meta_query', $args );

						$query_result = new WP_Query( $args );
						if ( $query_result instanceof WP_Query && $query_result->have_posts() ) {
							$contents = $query_result->posts;
							$wfob_transient_obj->set_transient( $key, $contents, 21600, WFOB_SLUG );
						}
					}
					$wfob_cache_obj->set_cache( $key, $contents, WFOB_SLUG );
				}
				do_action( 'wfob_after_query' );
			}


		} else {
			$contents = $bumps_from_base;
		}

		$passed_rules = [];
		$failed_rules = [];
		if ( is_array( $contents ) && count( $contents ) > 0 ) {
			foreach ( $contents as $content_single ) {
				/**
				 * post instance extra checking added as some plugins may modify wp_query args on pre_get_posts filter hook
				 */
				$content_id = ( $content_single instanceof WP_Post && is_object( $content_single ) ) ? $content_single->ID : $content_single;

				if ( WFOB_Core()->rules->match_groups( $content_id, $rematch_group ) ) {
					$passed_rules[] = $content_id;
				} else {
					$failed_rules[] = $content_id;
				}
			}
		}

		$add_to_cart_bump = WC()->session->get( 'wfob_added_bump_product', [] );
		if ( ! empty( $failed_rules ) ) {
			if ( ! did_action( 'woocommerce_before_checkout_process' ) ) {
				foreach ( $failed_rules as $failed_id ) {
					unset( $add_to_cart_bump[ $failed_id ] );
					$this->remove_items_by_bump_id( $failed_id );
				}
			}
		}

		$final_bumps  = array();
		$passed_rules = is_array( $passed_rules ) ? $passed_rules : array();
		if ( ! empty( $passed_rules ) ) {
			foreach ( $passed_rules as $bump_id ) {
				$final_bumps[ $bump_id ] = $bump_id;
			}
		}
		$this->setup_bump_runs = true;
		$final_bumps           = apply_filters( 'wfob_filter_final_bumps', $final_bumps, $this->posted_data );
		do_action( 'wfob_before_bump_created', $this, $final_bumps );
		WFOB_Common::store_removed_bump_items();
		WFOB_Common::get_pre_checked_bumps();
		WFOB_Bump_Fc::reset_bumps();
		$bumps_object = null;
		foreach ( $final_bumps as $bump_id ) {
			$bumps_object[] = WFOB_Bump_Fc::create( $bump_id );
		}

		if ( ! is_null( $bumps_object ) ) {
			do_action( 'wfob_after_bump_created', $this, $final_bumps, $bumps_object );
		}

		return $this;
	}

	public function enqueue() {
		wp_enqueue_style( 'photoswipe' );
		wp_enqueue_style( 'photoswipe-default-skin' );
		wp_enqueue_script( 'wc-single-product' );
		wp_enqueue_script( 'zoom' );
		wp_enqueue_script( 'flexslider' );
		wp_enqueue_script( 'photoswipe' );
		wp_enqueue_script( 'photoswipe-ui-default' );
		wp_enqueue_script( 'wp-util' );
		wp_enqueue_script( 'wc-add-to-cart-variation' );
		wp_enqueue_style( 'wfob-style', WFOB_PLUGIN_URL . '/assets/css/public.min.css', false, WFOB_VERSION_DEV );

		if ( is_rtl() ) {
			wp_enqueue_style( 'wfob-public-rtl', WFOB_PLUGIN_URL . '/assets/css/wfob-public-rtl.css', false, WFOB_VERSION_DEV );
		}

		wp_enqueue_script( 'wfob-bump', WFOB_PLUGIN_URL . '/assets/js/public.min.js', [ 'jquery' ], WFOB_VERSION_DEV, true );
		wp_localize_script( 'wfob-bump', 'wfob_frontend', [
			'admin_ajax'      => admin_url( 'admin-ajax.php' ),
			'wc_endpoints'    => WFOB_AJAX_Controller::get_public_endpoints(),
			'wfob_nonce'      => wp_create_nonce( 'wfob_secure_key' ),
			'cart_total'      => ! is_null( WC()->cart ) ? WC()->cart->get_total( 'edit' ) : 0,
			'cart_is_virtual' => ! is_null( WC()->cart ) ? WFOB_Common::is_cart_is_virtual() : false,
			'quick_popup'     => [
				'choose_an_option' => __( 'Choose an option', 'woocommerce' ),
				'add_to_cart_text' => __( 'Add to cart', 'woocommerce' ),
				'update'           => __( 'Update', 'woocommerce' ),
			],
			'track'           => $this->track_events()
		] );

	}

	public function is_global_pageview_enabled() {
		$admin_general = BWF_Admin_General_Settings::get_instance();

		return wc_string_to_bool( $admin_general->get_option( 'is_ga_page_view_global' ) );
	}

	public function is_global_add_to_cart_enabled() {
		$admin_general = BWF_Admin_General_Settings::get_instance();

		return wc_string_to_bool( $admin_general->get_option( 'is_ga_add_to_cart_global' ) );
	}

	public function track_events() {
		$admin_general = BWF_Admin_General_Settings::get_instance();

		$tracks = [
			'pixel'      => [
				'add_to_cart' => wc_string_to_bool( $admin_general->get_option( 'is_fb_add_to_cart_global' ) ) ? wc_string_to_bool( $admin_general->get_option( 'is_fb_add_to_cart_global' ) ) : wc_string_to_bool( $admin_general->get_option( 'is_fb_add_to_cart_bump' ) ),
				'custom_bump' => wc_string_to_bool( $admin_general->get_option( 'is_fb_custom_bump' ) ),
			],
			'google_ua'  => [
				'add_to_cart' => wc_string_to_bool( $admin_general->get_option( 'is_ga_add_to_cart_global' ) ) ? wc_string_to_bool( $admin_general->get_option( 'is_ga_add_to_cart_global' ) ) : wc_string_to_bool( $admin_general->get_option( 'is_ga_add_to_cart_bump' ) ),
				'custom_bump' => wc_string_to_bool( $admin_general->get_option( 'is_ga_custom_bump' ) ),
			],
			'google_ads' => [
				'add_to_cart' => wc_string_to_bool( $admin_general->get_option( 'is_gad_add_to_cart_global' ) ) ? wc_string_to_bool( $admin_general->get_option( 'is_gad_add_to_cart_global' ) ) : wc_string_to_bool( $admin_general->get_option( 'is_gad_add_to_cart_bump' ) ),
				'custom_bump' => wc_string_to_bool( $admin_general->get_option( 'is_gad_custom_bump' ) ),
				'cart_labels' => $admin_general->get_option( 'gad_addtocart_bump_conversion_label' ),
			],
			'pint'       => [
				'add_to_cart' => wc_string_to_bool( $admin_general->get_option( 'is_pint_add_to_cart_global' ) ) ? wc_string_to_bool( $admin_general->get_option( 'is_pint_add_to_cart_global' ) ) : wc_string_to_bool( $admin_general->get_option( 'is_pint_add_to_cart_bump' ) ),
				'custom_bump' => $admin_general->get_option( 'is_pint_custom_bump' ),
			],
			'tiktok'     => [
				'add_to_cart' => wc_string_to_bool( $admin_general->get_option( 'is_tiktok_add_to_cart_global' ) ) ? wc_string_to_bool( $admin_general->get_option( 'is_tiktok_add_to_cart_global' ) ) : wc_string_to_bool( $admin_general->get_option( 'is_tiktok_add_to_cart_bump' ) ),
				'custom_bump' => $admin_general->get_option( 'is_tiktok_custom_bump' ),
			],
			'snapchat'   => [
				'add_to_cart' => wc_string_to_bool( $admin_general->get_option( 'is_snapchat_add_to_cart_global' ) ) ? wc_string_to_bool( $admin_general->get_option( 'is_snapchat_add_to_cart_global' ) ) : wc_string_to_bool( $admin_general->get_option( 'is_snapchat_add_to_cart_bump' ) ),
				'custom_bump' => $admin_general->get_option( 'is_snapchat_custom_bump' ),
			]
		];

		return $tracks;
	}


	public function print_below_gateway() {
		if ( wp_doing_ajax() ) {
			// print div container when at in ajax
			$this->woocommerce_checkout_order_review_below_payment_gateway();
		} else {
			// print div container when at time page load
			do_action( 'wfob_below_payment_gateway' );
		}
		remove_action( 'woocommerce_before_template_part', [ $this, 'add_order_bump' ], 11 );
	}

	public function add_order_bump( $template_name ) {
		if ( 'checkout/terms.php' === $template_name ) {
			if ( wp_doing_ajax() ) {
				// print div container when at in ajax
				$this->woocommerce_checkout_order_review_below_payment_gateway();
			} else {
				// print div container when at time page load
				do_action( 'wfob_below_payment_gateway' );
			}
			remove_action( 'woocommerce_before_template_part', [ $this, 'add_order_bump' ], 11 );
		}
	}

	public function add_order_bump_above_the_gateway( $template_name ) {
		if ( 'checkout/payment.php' === $template_name ) {
			do_action( 'wfob_above_payment_gateway' );
		}
	}

	public function send_cart_total_fragment( $fragments ) {
		$fragments['.cart_total'] = WC()->cart->get_total( 'edit' );

		return $fragments;
	}

	public function wfob_before_add_to_cart() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'split_product_individual_cart_items' ), 10, 1 );
	}

	public function wfob_after_add_to_cart() {
		remove_filter( 'woocommerce_add_cart_item_data', array( $this, 'split_product_individual_cart_items' ), 10 );
	}

	function split_product_individual_cart_items( $cart_item_data ) {
		$cart_item_data['unique_key'] = uniqid();

		return $cart_item_data;
	}


	public function footer() {
		if ( false == $this->is_footer_loaded ) {
			$this->is_footer_loaded = true;
			woocommerce_photoswipe();
			include( __DIR__ . '/quick-view/quick-view-container.php' );
		}
	}


	public function woocommerce_template_single_add_to_cart() {
		global $product;
		do_action( 'wfob_woocommerce_' . $product->get_type() . '_add_to_cart' );
	}

	public function woocommerce_variable_add_to_cart() {
		global $product;

		// Enqueue variation scripts.

		// Get Available variations?
		$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

		$available_variations = $get_variations ? $product->get_available_variations() : false;
		$attributes           = $product->get_variation_attributes();
		$selected_attributes  = $product->get_default_attributes();

		include __DIR__ . '/quick-view/add-to-cart/variable.php';
	}


	public function woocommerce_variable_subscription_add_to_cart() {
		global $product;

		// Enqueue variation scripts.

		// Get Available variations?
		$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

		$available_variations = $get_variations ? $product->get_available_variations() : false;
		$attributes           = $product->get_variation_attributes();
		$selected_attributes  = $product->get_default_attributes();

		include __DIR__ . '/quick-view/add-to-cart/variable-subscription.php';
	}

	public function woocommerce_simple_add_to_cart() {
		include __DIR__ . '/quick-view/add-to-cart/simple.php';

	}

	public function woocommerce_subscription_add_to_cart() {
		include __DIR__ . '/quick-view/add-to-cart/subscription.php';
	}

	public function woocommerce_single_variation_add_to_cart_button() {
		include __DIR__ . '/quick-view/add-to-cart/variation-add-to-cart-button.php';
	}

	public function reset_wc_session() {
		if ( ! is_admin() && ! wp_doing_ajax() && ! is_null( WC()->session ) ) {
			WC()->session->set( 'wfob_added_bump_product', [] );
		}
	}

	public function wfacp_hooks() {
		add_action( 'wfacp_header_print_in_head', [ $this, 'footer' ] );
	}

	/**
	 * SHow BUmp item in global product switcher
	 *
	 * @param $status
	 * @param $cart_item
	 *
	 * @return false|mixed
	 */
	public function wfacp_skip_global_switcher_item( $status, $cart_item ) {

		if ( isset( $cart_item['_wfob_product'] ) && ! isset( $cart_item['_wfob_swap_cart_key'] ) ) {
			$status = false;
		}

		return $status;
	}

	public function remove_quantity_selector_from_cart( $product_quantity, $cart_item_key, $cart_item = [] ) {
		if ( empty( $cart_item ) ) {
			$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		}

		if ( isset( $cart_item['_wfob_product'] ) ) {
			$product_quantity = sprintf( '<input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key );
		}

		return $product_quantity;
	}


	public function execute_bump_action() {
		$available_position = WFOB_Common::get_bump_position( true );
		if ( empty( $available_position ) ) {
			return '';
		}

		foreach ( $available_position as $position ) {
			$hook        = $position['hook'];
			$priority    = $position['priority'];
			$position_id = $position['id'];
			add_action( $hook, function () use ( $position_id ) {
				$this->print_bump_placeholder( $position_id );
			}, $priority );

		}


	}

	public function print_bump_placeholder( $position_id ) {
		$this->print_placeholder( $position_id );
	}

	public function execute_bump_fragments() {
		$available_position = WFOB_Common::get_bump_position( true );
		if ( empty( $available_position ) ) {
			return '';
		}
		foreach ( $available_position as $position ) {
			$priority    = $position['priority'];
			$position_id = $position['id'];
			add_filter( 'woocommerce_update_order_review_fragments', function ( $fragments ) use ( $position_id ) {
				return $this->get_bump_fragment_html( $fragments, $position_id );
			}, $priority );
		}


	}

	public function get_bump_fragment_html( $fragments, $position_id ) {
		return $this->get_bump_html( $fragments, $position_id );
	}

	private function print_position_bump( $position ) {

		try {
			WC()->session->set( 'wfob_no_of_bump_shown', [] );
			if ( empty( $position ) ) {
				return '';
			}
			if ( apply_filters( 'wfob_do_not_print_bump_position', false, $this ) ) {
				return '';
			}
			$bumps = WFOB_Bump_Fc::get_bumps();
			if ( empty( $bumps ) ) {
				return '';
			}
			$shown_bump_ids = [];
			/**
			 * @var $bump WFOB_Bump
			 */
			ob_start();

			foreach ( $bumps as $bump_id => $bump ) {
				$bump_product = WFOB_Common::get_bump_products( $bump_id );
				if ( empty( $bump_product ) ) {
					continue;
				}
				$shown_bump_ids[] = $bump_id;
				if ( ! $bump->have_bumps() || $position != $bump->get_position() ) {
					continue;
				}
				$bump->print_bump();
			}

			$this->shown_bump_ids = $shown_bump_ids;
			WC()->session->set( 'wfob_no_of_bump_shown', $shown_bump_ids );

			return ob_get_clean();
		} catch ( Exception|Error $e ) {
			return '';
		}
	}

	public function get_bump_html( $fragments, $slug ) {

		if ( apply_filters( 'wfob_do_not_execute_bump_fragments', false, $this ) ) {

			return $fragments;
		}

		$uniqued = ".wfob_bump_wrapper.{$slug}";
		$html    = $this->print_position_bump( $slug );
		$html    = sprintf( "<div id='wfob_wrap' class='wfob_bump_wrapper %s' data-time='%s'>%s</div>", $slug, time(), $html );

		$fragments[ $uniqued ]            = apply_filters( 'wfob_bump_html_fragment', $html, $this, $slug );
		$fragments['wfob_bump_shown_ids'] = ( is_array( $this->shown_bump_ids ) && count( $this->shown_bump_ids ) > 0 ) ? implode( ',', $this->shown_bump_ids ) : '';

		return $fragments;
	}

	public function show_on_load() {
		return apply_filters( 'wfob_show_on_load', false, $this );
	}

	public function woocommerce_before_checkout_form_above_the_form_frg( $fragments ) {
		$slug = 'woocommerce_before_checkout_form_above_the_form';

		return $this->get_bump_html( $fragments, $slug );

	}

	public function wfacp_below_mini_cart_coupon_frg( $fragments ) {

		$slug = 'wfacp_below_mini_cart_coupon';

		return $this->get_bump_html( $fragments, $slug );
	}

	private function print_placeholder( $slug ) {
		$html = '';
		if ( $this->show_on_load() ) {
			$html = $this->print_position_bump( $slug );
		}
		if ( apply_filters( 'wfob_print_placeholder', true, $slug ) ) {
			printf( "<div id='wfob_wrap' class='wfob_bump_wrapper %s'>%s</div>", $slug, $html );

		}

	}

	public function woocommerce_checkout_order_review_below_payment_gateway() {
		$this->print_placeholder( 'woocommerce_checkout_order_review_below_payment_gateway' );
	}

	public function wfacp_below_mini_cart_coupon() {

		$this->print_placeholder( 'wfacp_below_mini_cart_coupon' );
	}

	public function remove_items_by_bump_id( $bump_id ) {
		if ( false == apply_filters( 'wfob_remove_items_by_bump_id', true, $bump_id ) ) {
			return;
		}
		$items = WC()->cart->get_cart();
		foreach ( $items as $index => $item ) {
			if ( true == apply_filters( 'wfob_do_not_remove_failed_product', false, $item ) ) {
				continue;
			}

			if ( isset( $item['_wfob_options'] ) && $item['_wfob_options']['_wfob_id'] == $bump_id ) {
				WC()->cart->remove_cart_item( $index );
			}
		}

	}

	/**
	 * Make cart empty when all item is bump products
	 * We not proceed to checkout when only bump products present in cart
	 */
	public function all_item_is_bump_in_cart() {
		$status = false;
		if ( is_null( WC()->cart ) ) {
			return $status;
		}

		$cart       = WC()->cart->get_cart();
		$bump_count = 0;
		if ( empty( $cart ) ) {
			return $status;
		}
		// Reduce Child product count in main cart
		$other_child_product = 0;
		foreach ( $cart as $item ) {
			if ( isset( $item['_wfob_swap_cart_key'] ) || isset( $item['_wfob_swap_cart_key'] ) ) {
				continue;
			}
			if ( isset( $item['_wfob_options'] ) ) {
				$bump_count ++;
			}

			$child_item_key = '';
			if ( isset( $item['bundled_by'] ) || isset( $item['bundled_by'] ) ) {
				$child_item_key = $item['bundled_by'];
			} else if ( isset( $item['chained_item_of'] ) ) {
				$child_item_key = $item['chained_item_of'];
			}
			if ( ! empty( $child_item_key ) && isset( $cart[ $child_item_key ] ) && isset( $cart[ $child_item_key ]['_wfob_options'] ) && $cart[ $child_item_key ]['_wfob_options'] ) {
				$other_child_product ++;
			}
		}

		$cart_item_count = count( $cart );
		if ( $other_child_product > 0 ) {
			$cart_item_count = $cart_item_count - $other_child_product;
		}

		if ( $bump_count > 0 && $cart_item_count == $bump_count ) {
			$status = true;
		}


		return apply_filters( 'wfob_allow_order_bump_item_as_last_item', $status, $this );
	}

	public function restrict_sold_individual( $status, $product_id ) {
		if ( class_exists( 'WFACP_Core' ) ) {
			return $status;
		}


		$cart_content = WC()->cart->get_cart_contents();
		if ( ! empty( $cart_content ) ) {
			foreach ( $cart_content as $item_key => $item_data ) {
				if ( $item_data['product_id'] == $product_id ) {
					$status = true;
					break;
				}
			}
		}

		return $status;
	}

	public function capture_posted_data() {
		if ( isset( $_REQUEST['_wfacp_post_id'] ) ) {
			$this->posted_data = $_REQUEST;
		}
	}

	public function do_not_display_order_bump_quantity( $status, $cart_item ) {
		if ( isset( $cart_item['_wfob_options'] ) ) {
			$status = false;
		}

		return $status;
	}

	public function woocommerce_remove_cart_item( $cart_item_key ) {
		$item_data = WC()->cart->get_cart_item( $cart_item_key );
		WFOB_Common::restore_replaced_products( $item_data );
	}


	public function hide_undo_message( $status, $cart_item ) {
		if ( isset( $cart_item['_wfob_replace_by'] ) ) {
			$status = true;
		}

		return $status;
	}

	public function unset_replace_by_key( $status, $cart_item ) {

		if ( isset( $cart_item['_wfob_replace_by'] ) ) {
			return false;
		}

		return $status;
	}


	public function apply_custom_url( $image_url, $cart_item ) {
		if ( isset( $cart_item['_wfob_options'] ) && ! empty( $cart_item['_wfob_options']['custom_image'] ) ) {
			$image_url = $cart_item['_wfob_options']['custom_image'];
		}

		return $image_url;
	}

	public function show_mini_cart_delete_icon( $status, $cart_item, $cart_item_key ) {


		if ( ! isset( $cart_item['_wfob_options'] ) ) {
			return $status;
		}
		$bump_id     = $cart_item['_wfob_options']['_wfob_id'];
		$hide_status = apply_filters( 'wfob_hide_order_bump_after_selected', false, $bump_id, $cart_item_key );
		if ( true == $hide_status ) {
			$status = true;
		}

		return $status;
	}

	public function show_order_summary_delete_icon( $status, $cart_item_key, $cart_item ) {

		return $this->show_mini_cart_delete_icon( $status, $cart_item, $cart_item_key );
	}

	/**
	 * Sustain replacer bump when Primary Rules Failed for replace bump after Replaced
	 *
	 * @param $status
	 * @param $item_data
	 *
	 * @return mixed|true
	 */
	public function do_not_remove_failed_product( $status, $item_data ) {
		if ( isset( $item_data['_wfob_swap_cart_key'] ) ) {
			$status = true;
		}

		return $status;
	}

	/***
	 * Do not apply Rules on replacer bump Treat as Normal Cart Item
	 *
	 * @param $status
	 * @param $item_data
	 *
	 * @return mixed|true
	 */
	public function do_not_apply_rules_on_swap_bump( $status, $item_data ) {
		if ( isset( $item_data['_wfob_swap_cart_key'] ) ) {
			$status = false;
		}

		return $status;
	}

	/**
	 * @param $price
	 * @param $product \WC_Product
	 *
	 * @return mixed
	 */
	public function handle_discount_product_price( $price, $product ) {
		if ( $product instanceof \WC_Product && ! empty( $product->get_meta( '_wfob_price' ) ) ) {
			return $product->get_meta( '_wfob_price' );
		}

		return $price;
	}

	public function re_run_rules_after_bump_removed() {
		$this->setup_bump_runs = false;
		$this->setup_order_bumps( [], true );
	}


	/**
	 * Store Recently added Bump into  Session Latest Used in Update Multiple Checkout Page Case
	 *
	 * @param $cart_item_data
	 * @param $cart_item_key
	 *
	 * @return mixed
	 */
	public function set_selected_bump_in_session( $cart_item_data, $cart_item_key ) {
		try {
			if ( ! class_exists( 'WFACP_Common' ) || ! isset( $cart_item_data['_wfob_product_key'] ) ) {
				return $cart_item_data;
			}
			$bumps       = WC()->session->get( '_wfob_selected_bump', [] );
			$checkout_id = WFACP_Common::get_id();
			if ( ! isset( $bumps[ $checkout_id ] ) ) {
				$key                           = $cart_item_data['_wfob_product_key'];
				$bumps[ $checkout_id ][ $key ] = [ $cart_item_key, $cart_item_data ];
				WC()->session->set( '_wfob_selected_bump', $bumps );
			}
		} catch ( Exception|Error $e ) {
		}

		return $cart_item_data;
	}


	/**
	 * Remove Bump from session
	 *
	 * @param $cart_item_key
	 * @param $instance
	 *
	 * @return void
	 */
	public function unset_selected_bump_in_session( $cart_item_key, $instance ) {
		try {
			if ( ! class_exists( 'WFACP_Common' ) ) {
				return;
			}
			$cart_item = $instance->removed_cart_contents[ $cart_item_key ];
			if ( isset( $cart_item['_wfob_product_key'] ) ) {
				$key   = $cart_item['_wfob_product_key'];
				$bumps = WC()->session->get( '_wfob_selected_bump', [] );
				if ( isset( $bumps[ $key ] ) ) {
					unset( $bumps );
				}
				WC()->session->set( '_wfob_selected_bump', $bumps );
			}
		} catch ( Exception|Error $e ) {
		}
	}

	/**
	 * Re add bump If page switch btw two checkout pages.
	 *
	 * @param $post
	 * @param $success
	 * @param $wfacp_id
	 *
	 * @return void
	 * @throws Exception
	 */
	public function re_add_bump_to_cart( $post, $success, $wfacp_id ) {
		try {
			$bumps = WC()->session->get( '_wfob_selected_bump', [] );
			if ( ! isset( $bumps[ $wfacp_id ] ) ) {
				return;
			}
			$items = $bumps[ $wfacp_id ];
			foreach ( $items as $data ) {
				$item_data      = $data[1];
				$bump_item_data = [ '_wfob_product' => true, '_wfob_options' => $item_data['_wfob_options'], '_wfob_product_key' => $item_data['_wfob_product_key'] ];
				WC()->cart->add_to_cart( $item_data['product_id'], $item_data['quantity'], $item_data['variation_id'], $item_data['variation'], $bump_item_data );
			}
		} catch ( Exception|Error $e ) {
		}
	}

	public function unset_checkout_session_data() {
		WC()->session->__unset( '_wfob_selected_bump' );
	}

	public function unset_current_checkout_session_data() {
		$bumps = WC()->session->get( '_wfob_selected_bump', [] );
		if ( ! isset( $bumps[ WFACP_Common::get_id() ] ) ) {
			return;
		}
		unset( $bumps[ WFACP_Common::get_id() ] );
		WC()->session->set( '_wfob_selected_bump', $bumps );

	}

	public function license_data( $hash ) {

		$License = WooFunnels_licenses::get_instance();
		$License->get_plugins_list();
		if ( is_object( $License ) && is_array( $License->plugins_list ) && count( $License->plugins_list ) ) {
			foreach ( $License->plugins_list as $license ) {
				if ( $license['product_file_path'] !== $hash ) {
					continue;
				}
				if ( isset( $license['_data'] ) && isset( $license['_data']['data_extra'] ) ) {
					$license_data = $license['_data']['data_extra'];

					return array(
						'id'                      => $license['product_file_path'],
						'label'                   => $license['plugin'],
						'type'                    => 'license',
						'key'                     => $license['product_file_path'],
						'license'                 => ! empty( $license_data ) ? $license_data : false,
						'is_manually_deactivated' => ( isset( $license['_data']['manually_deactivated'] ) && true === bwf_string_to_bool( $license['_data']['manually_deactivated'] ) ) ? 1 : 0,
						'activated'               => ( isset( $license['_data']['activated'] ) && true === bwf_string_to_bool( $license['_data']['activated'] ) ) ? 1 : 0,
						'expired'                 => ( isset( $license['_data']['expired'] ) && true === bwf_string_to_bool( $license['_data']['expired'] ) ) ? 1 : 0
					);
				}


			}


		}

		return [];

	}

}

if ( class_exists( 'WFOB_Core' ) ) {
	WFOB_Core::register( 'public', 'WFOB_Public' );
}