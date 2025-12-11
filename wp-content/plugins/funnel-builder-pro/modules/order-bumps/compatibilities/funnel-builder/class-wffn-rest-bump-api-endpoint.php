<?php

if ( ! class_exists( 'WFFN_REST_BUMP_API_EndPoint' ) ) {
	class WFFN_REST_BUMP_API_EndPoint extends WFFN_REST_Controller {

		private static $ins = null;
		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'funnel-bump';

		/**
		 * WFFN_REST_API_EndPoint constructor.
		 */
		public function __construct() {
			add_action( 'rest_api_init', [ $this, 'register_endpoint' ], 12 );
		}

		/**
		 * @return WFFN_REST_API_EndPoint|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function register_endpoint() {

			// Get Rules for Order Bump page.
			register_rest_route( $this->namespace, '/' . 'funnel-bump' . '/(?P<step_id>[\d]+)' . '/rules', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_wfob_rules' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wfob_rules' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes for Order Bumps.
			register_rest_route( $this->namespace, '/' . 'funnel-bump' . '/(?P<step_id>[\d]+)' . '/products', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'wfob_add_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'wfob_get_products' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wfob_remove_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes for Order Bumps Layout.
			register_rest_route( $this->namespace, '/' . 'funnel-bump' . '/(?P<step_id>[\d]+)' . '/products' . '/save-layout', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'wfob_save_products' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );


			register_rest_route( $this->namespace, '/' . 'funnel-bump' . '/(?P<bump_id>[\d]+)/' . '/', array(
				'args' => array(
					'bump_id' => array(
						'description' => __( 'Unique Bump id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_bump' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [],
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_design' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),

			) );

			register_rest_route( $this->namespace, '/' . 'funnel-bump' . '/(?P<bump_id>[\d]+)/' . 'import/skin', array(
				'args' => array(
					'bump_id' => array(
						'description' => __( 'Unique Bump id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_skin' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),

			) );


			register_rest_route( $this->namespace, '/' . 'funnel-bump' . '/(?P<bump_id>[\d]+)/' . 'skins/all', array(
				'args' => array(
					'bump_id' => array(
						'description' => __( 'Unique Bump id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_bumps' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [],
				),

			) );

		}

		public function get_read_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'read' );
		}

		public function get_write_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
		}

		// Get rules for Order Bump Step.
		public function get_wfob_rules( WP_REST_Request $request ) {
			$resp                        = array();
			$resp['success']             = false;
			$resp['msg']                 = __( 'Failed', 'funnel-builder-powerpack' );
			$resp['data']['rules']       = array();
			$resp['data']['rules_list']  = array();
			$resp['data']['hasProducts'] = false;

			$step_id   = $request->get_param( 'step_id' );
			$funnel_id = $request->get_param( 'funnel_id' );

			wffn_rest_api_helpers()->maybe_step_not_exits( $step_id );

			$step_post = wffn_rest_api_helpers()->get_step_post( $step_id );

			if ( 0 === absint( $funnel_id ) ) {
				$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );

			}
			$resp['data']['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );
			$resp['data']['step_data']   = $step_post;

			if ( class_exists( 'WFOB_Rules' ) && absint( $step_id ) > 0 ) {

				$wfob_rules      = WFOB_Common::get_bump_rules( $step_id );
				$formatted_rules = $this->strip_group_rule_keys( $wfob_rules );
				$rules_list      = $this->get_ob_rules( $wfob_rules );

				$resp['data']['hasProducts'] = ! empty( WFOB_Common::get_bump_products( $step_id ) ) ? true : false;

				$resp['success'] = true;
				$resp['msg']     = __( 'Rules list loaded', 'funnel-builder-powerpack' );

				$resp['data']['rules_list'] = ! empty( $rules_list ) ? $rules_list : [];

				if ( ! empty( $formatted_rules ) ) {
					$resp['data']['rules'] = $formatted_rules;

				}
			}

			return rest_ensure_response( $resp );
		}

		// Update Rules for ORDER BUMP.
		public function update_wfob_rules( WP_REST_Request $request ) {
			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'funnel-builder-powerpack' );

			$step_id   = $request->get_param( 'step_id' );
			$wfob_rule = $request->get_body();

			if ( ! empty( $wfob_rule ) && absint( $step_id ) && class_exists( 'WFOB_Rules' ) ) {

				$posted_data = $this->sanitize_custom( $wfob_rule );
				$posted_data = $this->rectify_posted_rules( $posted_data );

				update_post_meta( $step_id, '_wfob_rules', $posted_data );
				update_post_meta( $step_id, '_wfob_is_rules_saved', 'yes' );

				$all_data          = wffn_rest_api_helpers()->get_step_post( $step_id, true );
				$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
				$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;

				$resp['success'] = true;
				$resp['msg']     = __( 'Rules Updated', 'funnel-builder-powerpack' );
			}

			return rest_ensure_response( $resp );
		}

		public function get_ob_rules( $saved_rules ) {

			$rule_list = [];
			if ( class_exists( 'WFOB_Rules' ) ) {
				WFOB_Rules::get_instance()->load_rules_classes();
				$wfob_rules = WFOB_Rules::get_instance()->default_rule_types( 'all' );
				$rule_set   = $this->format_rules_select( $wfob_rules );

				if ( ! empty( $rule_set ) ) {

					if ( ! function_exists( 'get_editable_roles' ) ) {
						require_once ABSPATH . 'wp-admin/includes/user.php';
					}

					$defaults = array(
						'group_id'  => 0,
						'rule_id'   => 0,
						'rule_type' => null,
						'condition' => null,
						'operator'  => null,
						'category'  => 'basic',
					);


					foreach ( $rule_set as $rule ) {
						$data_args            = [];
						$options              = array();
						$rule_object          = WFOB_Rules::get_instance()->woocommerce_wfob_rule_get_rule_object( $rule['key'] );
						$rule_type            = $rule_object->get_condition_input_type();
						$values               = $rule_object->get_possible_rule_values();
						$options['rule_type'] = $rule_type;
						$options              = array_merge( $defaults, $options );
						$operators            = $rule_object->get_possible_rule_operators();
						$operators            = ! empty( $operators ) && is_array( $operators ) ? wffn_rest_api_helpers()->array_to_nvp( array_flip( $operators ), "label", "value", "value", "key" ) : array();
						$rule['operators']    = $operators;
						$condition_input_type = $rule_object->get_condition_input_type();

						$data_args['condition_input_type'] = $condition_input_type;
						$data_args['condition']            = $saved_rules;

						if ( in_array( $rule_type, [ 'Cart_Product_Select', 'Product_Select' ], true ) ) {
							$products = ( ! empty( $saved_rules ) && is_array( $saved_rules ) ) ? $this->get_product_from_conditions( $saved_rules ) : [];
							$values   = $products;
						}
						if ( in_array( $rule_type, [ 'Chosen_Select', 'Coupon_Select' ], true ) ) {
							$coupons = ( ! empty( $saved_rules ) && is_array( $saved_rules ) ) ? $this->get_coupons_from_conditions( $saved_rules ) : [];

							if ( ! empty( $coupons ) ) {

								foreach ( $coupons as $coup ) {
									$values[ $coup ] = $coup;
								}
							}
						}

						if ( method_exists( 'WFFN_REST_Controller', 'get_user_from_conditions' ) && in_array( $rule_type, [ 'User_Select' ], true ) ) {
							$users = ( ! empty( $saved_rules ) && is_array( $saved_rules ) ) ? $this->get_user_from_conditions( $saved_rules ) : [];

							if ( ! empty( $users ) ) {
								foreach ( $users as $user_id ) {
									$values[ $user_id ] = $user_id;
								}
							}
						}

						$data_args['value_args'] = array(
							'input'   => $condition_input_type,
							'name'    => 'wfob_rule[' . $options['category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][condition]',
							'choices' => $values,
						);

						$rule['fields'] = $this->render_input_fields( $data_args );
						$rule_list[]    = $rule;

					}

				}
			}

			return $rule_list;
		}

		public function wfob_add_product( WP_REST_Request $request ) {
			$resp = array();

			$resp['success']          = false;
			$resp['msg']              = __( 'Failed', 'funnel-builder-powerpack' );
			$resp['data']['products'] = array();
			$resp_products            = array();

			$step_id = $request->get_param( 'step_id' );

			$options = $request->get_body();

			if ( absint( $step_id ) > 0 && ! empty( $options ) ) {
				$posted_data = $this->sanitize_custom( $options );

				$wfob_id          = absint( $step_id );
				$products         = $posted_data['products'];
				$existing_product = get_post_meta( $wfob_id, '_wfob_selected_products', true );

				$get_design_data         = get_post_meta( $wfob_id, '_wfob_design_data', true );
				$tmp_default_design_data = WFOB_Common::get_default_model_data( $wfob_id );


				if ( isset( $posted_data['layout'] ) && ! empty( $posted_data['layout'] ) ) {
					$layout_slug = $posted_data['layout'];
				} elseif ( isset( $get_design_data['layout'] ) && ! empty( $get_design_data['layout'] ) ) {
					$layout_slug = $get_design_data['layout'];
				}

				$default_design_data = WFOB_Common::get_override_design_keys( $layout_slug, $tmp_default_design_data );


				if ( empty( $get_design_data ) ) {
					$default_slug = WFOB_Common::$design_default_layout;
					if ( isset( $default_design_data[ $default_slug ] ) ) {
						$get_design_data = $default_design_data[ $default_slug ];
						WFOB_Common::update_design_data( $wfob_id, $default_design_data[ $default_slug ] );
					}
				}


				if ( empty( $existing_product ) ) {
					$existing_product = [];
				}
				foreach ( $products as $pid ) {

					$unique_id = uniqid( 'wfob_' );
					$product   = wc_get_product( $pid );

					if ( method_exists( 'WFFN_REST_API_Helpers', 'remove_all_wc_price_action' ) ) {
						wffn_rest_api_helpers()->remove_all_wc_price_action();
					}

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
						$default['stock']                = ( $product->is_in_stock() ) ? 'true' : 'false';
						$default['parent_product_id']    = $product->get_parent_id();
						$default['title']                = $product->get_title();
						$default['is_sold_individually'] = ( true === $product->is_sold_individually() ) ? true : 'false';

						if ( in_array( $product_type, WFOB_Common::get_variable_product_type() ) ) {
							$default['variable'] = 'yes';
							$default['price']    = $product->get_price_html();


							$pro                = WFOB_Common::wc_get_product( $default['id'] );
							$is_found_variation = WFOB_Common::get_default_variation( $pro );
							if ( count( $is_found_variation ) > 0 ) {
								$default['default_variation']      = $is_found_variation['variation_id'];
								$default['default_variation_attr'] = $is_found_variation['attributes'];
							}


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
						$resp_products[ $unique_id ] = $default;


						$default = WFOB_Common::remove_product_keys( $default );

						$existing_product[ $unique_id ] = $default;
						$default['key']                 = $default['id'];
						$default['id']                  = $unique_id;


						$resp['data']['products'][] = wffn_rest_api_helpers()->unstrip_product_data( $default );
					}
				}

				WFOB_Common::delete_transient( $step_id );

				if ( is_array( $existing_product ) && ! empty( $existing_product ) ) {
					WFOB_Common::update_page_product( $wfob_id, $existing_product );
				} else {
					WFOB_Common::update_page_product( $wfob_id, $resp_products );
				}

				$product_settings = WFOB_Common::get_product_settings( $wfob_id );
				$wfob_settings    = WFOB_Common::get_setting_data( $wfob_id );

				WFOB_Common::update_product_settings( $wfob_id, $product_settings );
				WFOB_Common::update_setting_data( $wfob_id, $wfob_settings );


				if ( isset( $posted_data['layout'] ) && ! empty( $posted_data['layout'] ) ) {
					$default_slug     = $posted_data['layout'];
					$design_bump_data = $default_design_data[ $default_slug ];
				} elseif ( isset( $get_design_data['layout'] ) && ! empty( $get_design_data['layout'] ) ) {
					$default_slug     = $get_design_data['layout'];
					$design_bump_data = $get_design_data;
				}


				if ( is_array( $design_bump_data ) && count( $design_bump_data ) > 0 ) {
					$design_bump_data = WFOB_Common::check_default_bump_keys( $design_bump_data );
				}


				$temp = WFOB_Common::add_product_details_default_layout( $existing_product, $design_bump_data );
				WFOB_Common::update_design_data( $wfob_id, $temp );
				if ( count( $resp['data']['products'] ) > 0 ) {
					$all_data          = wffn_rest_api_helpers()->get_step_post( $wfob_id, true );
					$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
					$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;
					$resp['success']   = true;
					$resp['msg']       = __( 'Products added to order bump', 'woofunnels-order-bump' );
				}

			}

			return rest_ensure_response( $resp );
		}

		// Get Products List for Order BUMPs.
		public function wfob_get_products( WP_REST_Request $request ) {

			$resp                     = array();
			$resp['success']          = false;
			$resp['msg']              = __( 'Failed', 'woofunnels-aero-checkout' );
			$resp['data']['products'] = array();

			$step_id   = $request->get_param( 'step_id' );
			$funnel_id = $request->get_param( 'funnel_id' );

			if ( absint( $step_id ) && class_exists( 'WFOB_Common' ) ) {

				wffn_rest_api_helpers()->maybe_step_not_exits( $step_id );

				$step_post = wffn_rest_api_helpers()->get_step_post( $step_id );

				if ( 0 === absint( $funnel_id ) ) {
					$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );

				}
				$resp['data']['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );
				$resp['data']['step_data']   = $step_post;

				$bump_products = get_post_meta( $step_id, '_wfob_selected_products', true );
				$products      = array();

				if ( is_array( $bump_products ) && count( $bump_products ) ) {
					foreach ( $bump_products as $key => $_product ) {
						$this_product = wc_get_product( $_product['id'] );

						if ( $this_product instanceof WC_Product ) {

							if ( is_a( $this_product, 'WC_Product_Variation' ) ) {
								$variation_name = wffn_rest_api_helpers()->get_name_part( $this_product->get_name(), 1 );
							}

							$product_availability = wffn_rest_api_helpers()->get_availability_price_text( $this_product->get_id() );
							$sale_price           = ! empty( $this_product->get_sale_price() ) ? $this_product->get_sale_price() : 0;
							$regular_price        = ! empty( $this_product->get_regular_price() ) ? $this_product->get_regular_price() : 0;
							$price_range          = '';
							if ( 'variable' === $this_product->get_type() ) {
								if ( is_array( $product_availability['price'] ) ) {
									$price_range   = isset( $product_availability['price']['price_range'] ) ? $product_availability['price']['price_range'] : '';
									$sale_price    = $product_availability['price']['sale_price'];
									$regular_price = $product_availability['price']['reg_price'];
								} else {
									$price_range = $product_availability['price'];
								}
							}

							$product_stock = $product_availability['text'];
							$stock_status  = ( $this_product->is_in_stock() ) ? true : false;

							$product_image                 = ! empty( wp_get_attachment_thumb_url( $this_product->get_image_id() ) ) ? wp_get_attachment_thumb_url( $this_product->get_image_id() ) : WFFN_PLUGIN_URL . '/admin/assets/img/product_default_icon.jpg';
							$_product['title']             = wffn_rest_api_helpers()->get_name_part( $this_product->get_name() );
							$_product['product_image']     = $product_image;
							$_product['product_type']      = $this_product->get_type();
							$_product['product_attribute'] = ! empty( $variation_name ) ? $variation_name : '-';
							$_product['regular_price']     = ! empty( $regular_price ) ? $regular_price : 0;
							$_product['sale_price']        = ! empty( $sale_price ) ? $sale_price : 0;;
							$_product['is_on_sale']           = $this_product->is_on_sale();
							$_product['currency_symbol']      = get_woocommerce_currency_symbol();
							$_product['product_stock_status'] = $stock_status;
							$_product['product_stock']        = $product_stock;
							$_product['price_range']          = $price_range;
							$_product['product_status']       = $this_product->get_status();
							// Swap ID with key for Product Component
							$_product['key'] = $_product['id'];
							$_product['id']  = $key;
							$products[]      = $_product;

						}
					}
				}

				$product_settings = WFOB_Common::get_instance()::get_product_settings( $step_id );

				$wfob_settings  = WFOB_Common::get_instance()->get_setting_data( $step_id );
				$discount_types = WFFN_Common::get_discount_type_keys();
				$bump_positions = WFOB_Common::get_bump_position();
				$bump_positions = wffn_rest_api_helpers()->array_change_key( $bump_positions, 'id', 'value' );

				foreach ( $bump_positions as $key => $bp ) {

					switch ( $bp['value'] ) {
						case 'woocommerce_checkout_order_review_above_order_summary':
						case 'woocommerce_checkout_order_review_below_order_summary':
							$bp['hint'] = __( 'Note: Order Summary field should be present', 'woofunnels-order-bump' );
							break;
						case 'wfacp_below_mini_cart_items':
							$bp['hint'] = __( 'Note: Mini Cart widget should be present', 'woofunnels-order-bump' );
							break;
						default:
							$bp['hint'] = '';
					}

					$bump_positions[ $key ] = $bp;

				}

				$ob_tabs_data                              = array();
				$ob_tabs_data['bump_action_type']          = ! empty( $product_settings['bump_action_type'] ) ? $product_settings['bump_action_type'] : 0;
				$ob_tabs_data['order_bump_position_hooks'] = ! empty( $wfob_settings['order_bump_position_hooks'] ) ? $wfob_settings['order_bump_position_hooks'] : 0;
				$ob_tabs_data['bump_replace_type']         = ! empty( $product_settings['bump_replace_type'] ) ? $product_settings['bump_replace_type'] : 'all';

				$default_product  = [];
				$product_defaults = array(
					'category'         => 0,
					'orderby'          => 'date',
					'order'            => 'DESC',
					'include'          => array(),
					'exclude'          => array(),
					'post_type'        => 'product',
					'suppress_filters' => true,
					'fields'           => 'ids',
				);

				$product_ids = get_posts( $product_defaults );
				if ( $product_ids ) {
					foreach ( $product_ids as $product_id ) {
						$product           = wc_get_product( $product_id );
						$product_name      = strip_tags( BWF_WC_Compatibility::woocommerce_get_formatted_product_name( $product ) );
						$default_product[] = [ 'label' => $product_name, 'product' => $product_name, 'id' => $product_id ];
					}
				}


				if ( isset( $product_settings['selected_replace_product']['id'] ) ) {
					$selected_product = [ $product_settings['selected_replace_product'] ];
				} else {
					$selected_product = $product_settings['selected_replace_product'];
				}

				if ( ! empty( $selected_product ) ) {
					$selected_product = array_map( function ( $item ) {
						if ( isset( $item['product'] ) ) {
							$item['label'] = $item['product'];
						}

						return $item;
					}, $selected_product );

					$default_product = array_merge( $default_product, $selected_product );
				}
				$selected_product                         = is_array( $selected_product ) ? $selected_product : [];
				$ob_tabs_data['selected_replace_product'] = $selected_product;
				$tabs                                     = [
					'fields'      => [
						[
							'type'   => 'radios',
							'key'    => 'bump_action_type',
							'label'  => __( 'Behaviour', 'funnel-builder-powerpack' ),
							'hint'   => '',
							'values' => [
								0 => [
									'value' => '1',
									'label' => __( 'Add Order Bumps to Cart Items', 'funnel-builder-powerpack' ),
								],
								1 => [
									'value' => '2',
									'label' => __( 'Replace Order Bumps with a Cart Item (used for upgrades)', 'funnel-builder-powerpack' ),
								],
							],
						],
						[
							'type'        => 'select',
							'key'         => 'bump_replace_type',
							'label'       => '',
							'hint'        => '',
							'values'      => [
								0 => [
									'value' => 'all',
									'label' => __( 'Replace All Products', 'funnel-builder-powerpack' ),
								],
								1 => [
									'value' => 'specific',
									'label' => __( 'Replace Specific Product(s)', 'funnel-builder-powerpack' ),
								],
							],
							'toggler'     => [
								'key'   => 'bump_action_type',
								'value' => '2',
							],
							'apiEndPoint' => '/funnels/products/search',
						],
						[
							'type'        => 'chosen-select',
							'key'         => 'selected_replace_product',
							'label'       => '',
							'hint'        => '',
							'hintLabel'   => __( 'Enter minimum 3 letters.', 'funnel-builder-powerpack' ),
							'toggler'     => [
								'key'   => 'bump_replace_type',
								'value' => 'specific',
							],
							'apiEndPoint' => '/funnels/products/search',
							'options'     => $default_product,
						]
					],
					'priority'    => 10,
					'values'      => $ob_tabs_data,
					'settingName' => __( 'Product Settings', 'funnel-builder-powerpack' ),
				];
				$tabs_settings_data                       = $tabs;
				$resp['success']                          = true;
				$resp['msg']                              = __( 'Loaded', 'funnel-builder-powerpack' );
				$resp['data']['products']                 = $products;
				$resp['data']['discount_types']           = $discount_types;
				$resp['data']['settings']                 = $tabs_settings_data;
				$resp['data']['InitialValue']             = $ob_tabs_data;

			}

			return rest_ensure_response( $resp );
		}

		// Remove product from Order Bump.
		public function wfob_remove_product( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-order-bump' );

			$step_id = $request->get_param( 'step_id' );
			$options = $request->get_body();

			if ( absint( $step_id ) && ! empty( $options ) && class_exists( 'WFOB_Common' ) ) {
				$posted_data = $this->sanitize_custom( $options );
				$wfob_id     = absint( $step_id );

				if ( ! empty( $posted_data['product_key'] ) && ! is_array( $posted_data['product_key'] ) ) {
					$posted_data['product_key'] = (array) $posted_data['product_key'];
				}

				if ( count( $posted_data['product_key'] ) > 0 ) {
					$product_key = $posted_data['product_key'];
					foreach ( $product_key as $p_key ) {
						// force products to load from database instead of cache
						$existing_product = get_post_meta( $step_id, '_wfob_selected_products', true );
						if ( isset( $existing_product[ $p_key ] ) ) {
							WFOB_Common::delete_transient( $wfob_id );
							unset( $existing_product[ $p_key ] );
							WFOB_Common::update_page_product( $wfob_id, $existing_product );
						}
					}

					$all_data          = wffn_rest_api_helpers()->get_step_post( $wfob_id, true );
					$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
					$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;

					$resp['msg']     = __( 'Product removed from order bump page', 'woofunnels-order-bump' );
					$resp['success'] = true;
				}

			}

			return rest_ensure_response( $resp );
		}

		// Save Products to Order Bump.
		public function wfob_save_products( WP_REST_Request $request ) {


			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-order-bump' );

			$step_id = $request->get_param( 'step_id' );
			$options = $request->get_body();

			if ( absint( $step_id ) && ! empty( $options ) && class_exists( 'WFOB_Common' ) ) {

				$posted_data = $this->sanitize_custom( $options );

				$wfob_id                                      = absint( $step_id );
				$product_settings['bump_action_type']         = isset( $posted_data['settings']['bump_action_type'] ) ? ( $posted_data['settings']['bump_action_type'] ) : [];
				$product_settings['bump_replace_type']        = isset( $posted_data['settings']['bump_replace_type'] ) ? ( $posted_data['settings']['bump_replace_type'] ) : 'all';
				$product_settings['selected_replace_product'] = isset( $posted_data['settings']['selected_replace_product'] ) ? ( $posted_data['settings']['selected_replace_product'] ) : '';
				unset( $posted_data['settings']['bump_action_type'] );

				if ( ! empty( $posted_data['settings']['selected_replace_product'] ) ) {
					if ( isset( $product_settings['selected_replace_product']['id'] ) ) {
						$temp_Product                                   = [
							'id'      => $product_settings['selected_replace_product']['id'],
							'product' => $product_settings['selected_replace_product']['label']
						];
						$product_settings['selected_replace_product']   = [];
						$product_settings['selected_replace_product'][] = $temp_Product;
					} else {
						$temp_products = $product_settings['selected_replace_product'];

						$product_settings['selected_replace_product'] = array_map( function ( $item ) {
							$item['product'] = $item['label'];

							return $item;
						}, $temp_products );


					}
				}


				$wfob_settings   = isset( $posted_data['settings'] ) ? ( $posted_data['settings'] ) : [];
				$posted_products = $posted_data['products'];
				$products        = [];


				foreach ( $posted_products as $key => $val ) {
					// Swap Key and ID Value for Product Component
					$wfob_key              = $posted_products[ $key ]['id'];
					$_product              = $this->strip_product_data( $val );
					$products[ $wfob_key ] = $_product;

					if ( isset( $products[ $wfob_key ]['variable'] ) ) {
						$pro                = WFOB_Common::wc_get_product( $products[ $wfob_key ]['id'] );
						$is_found_variation = WFOB_Common::get_default_variation( $pro );
						if ( count( $is_found_variation ) > 0 ) {
							$products[ $wfob_key ]['default_variation']      = $is_found_variation['variation_id'];
							$products[ $wfob_key ]['default_variation_attr'] = $is_found_variation['attributes'];
						}
					}
					$products[ $wfob_key ] = WFOB_Common::remove_product_keys( $products[ $wfob_key ] );
				}


				$default_product_settings = WFOB_Common::get_product_settings( $wfob_id );
				$default_wfob_settings    = WFOB_Common::get_setting_data( $wfob_id );

				$wfob_settings    = wp_parse_args( $wfob_settings, $default_wfob_settings );
				$product_settings = wp_parse_args( $product_settings, $default_product_settings );


				WFOB_Common::update_page_product( $wfob_id, $products );
				WFOB_Common::update_product_settings( $wfob_id, $product_settings );
				WFOB_Common::update_setting_data( $wfob_id, $wfob_settings );

				$resp['success'] = true;
				$resp['msg']     = __( 'Changes Saved', 'woofunnels-order-bump' );

			}

			return rest_ensure_response( $resp );
		}

		public function get_bump( WP_REST_Request $request ) {

			add_filter( 'woocommerce_is_purchasable', '__return_true', 9999 );// Allow purchasable For product in rest api.
			$bump_id = $request->get_param( 'bump_id' );

			if ( method_exists( 'WFFN_REST_API_Helpers', 'remove_all_wc_price_action' ) ) {
				wffn_rest_api_helpers()->remove_all_wc_price_action();
			}
			$bump = WFOB_Bump_Fc::create( $bump_id );

			$data = [
				'success' => false,
			];

			if ( is_null( $bump ) ) {
				$data['message'] = __( 'We are unable to find any bump with this ID', 'funnel-builder-powerpack' );

				return rest_ensure_response( $data );
			}

			if ( empty( $bump_id ) ) {
				return rest_ensure_response( $data );
			}

			wffn_rest_api_helpers()->maybe_step_not_exits( $bump_id );


			$schema = [];


			$schema['structure'] = [];

			$admin_schema = $bump->get_admin_schema();

			$data['data']['funnel_data'] = $admin_schema['funnel_data'];
			$data['data']['step_data']   = $admin_schema['step_data'];

			if ( ! is_array( $admin_schema['products'] ) || count( $admin_schema['products'] ) == 0 ) {
				$data['message']        = 'Bump Products List are Empty';
				$wfob_selected_products = get_post_meta( $bump_id, '_wfob_selected_products', true );


				if ( isset( $wfob_selected_products ) && is_array( $wfob_selected_products ) && count( $wfob_selected_products ) == 0 ) {
					$data['data']['structure'] = [];
					$data['success']           = true;
				}


				return rest_ensure_response( $data );
			}


			$schema['funnel_data'] = $data['data']['funnel_data'];
			$schema['step_data']   = $data['data']['step_data'];


			$schema['structure']['content'] = $admin_schema['contents']['content'];
			$schema['structure']['design']  = $admin_schema['design'];
			$schema['bump_settings']        = $admin_schema['bump-settings'];

			$schema['products'] = $admin_schema['products'];

			$schema['values']        = $admin_schema['values'];
			$schema['layouts']       = array_values( WFOB_Bump_Fc::get_layouts_info() );
			$schema['active_layout'] = $bump->get_bump_selected_layout();
			$schema['merge_tags']    = WFOB_Product_Switcher_Merge_Tags::get_tags_list();
			$schema['html']          = $admin_schema['html'];
			$schema['default_css']   = $admin_schema['default_css'];


			$data['success'] = false;
			$temp            = $schema;

			if ( isset( $schema['html'] ) && ! empty( $schema['html'] ) ) {
				$data['success'] = true;
				$data['data']    = $temp;
				unset( $data['message'] );
			} else {
				$data = [
					'success'       => false,
					'message'       => "Bump Html Not Created",
					'product_count' => sizeof( $schema['products'] ),
				];
			}


			return rest_ensure_response( $data );
		}

		public function get_all_bumps( WP_REST_Request $request ) {
			$bump_id        = $request->get_param( 'bump_id' );
			$default_models = WFOB_Bump_Fc::get_default_models();

			$all_layout = WFOB_Bump_Fc::get_layouts();

			$default_bumps_preview = apply_filters( 'wfob_default_preview_templates', [
				'layout_1',
				'layout_8',
				'layout_5',
				'layout_7',
				'layout_11',
				'layout_10',
				'layout_9',
				'layout_6',
			] );

			$temp      = [];
			$bump_list = [];
			$bump_html = [];

			add_filter( 'wfob_maximum_bump_print', function () {
				return 1;
			} );

			wffn_rest_api_helpers()->maybe_step_not_exits( $bump_id );

			foreach ( $default_bumps_preview as $key => $slug ) {

				$overide_keys = [];

				if ( ! isset( $default_models[ $slug ] ) ) {
					continue;
				}

				$design_data = $default_models[ $slug ];

				if ( ! isset( $design_data['layout'] ) || empty( $design_data['layout'] ) ) {
					continue;
				}
				$layout = $design_data['layout'];


				WFOB_Bump_Fc::$number_of_bump_print = [];
				$temp[ $bump_id ]                   = new $all_layout[ $layout ]( $bump_id );

				$design_data = $temp[ $bump_id ]->override_design_data_keys( $design_data, $layout );


				$temp[ $bump_id ]->prepare_frontend_data();


				$temp[ $bump_id ]->set_design_data( $design_data, true );


				$temp[ $bump_id ]->get_order_bump_html( false );
				$bump_list[ $layout ]['html']               = $temp[ $bump_id ]->get_single_bump_html();
				$bump_list[ $layout ]['dynamic_inline_css'] = $temp[ $bump_id ]->get_dynamic_inline_css();

			}


			ob_start();
			include WFOB_PLUGIN_DIR . '/assets/css/public.min.css';
			$css_file = ob_get_clean();

			$temp_data['skin_all']    = $bump_list;
			$temp_data['default_css'] = $css_file;


			return rest_ensure_response( $temp_data );
		}


		public function save_design( WP_REST_Request $request ) {

			$resp = array(
				'status' => false,
				'msg'    => __( 'Importing of template failed', 'funnel-builder-powerpack' ),
			);

			$bump_id = $request->get_param( 'bump_id' );
			$options = $request->get_body();

			if ( absint( $bump_id ) > 0 && ! empty( $options ) ) {
				$settings = array();
				$design   = array();
				$options  = $this->sanitize_custom( $options );

				/*-------------------------------Update Design Setting of bump----------------------------- */
				if ( isset( $options['design'] ) && ! empty( $options['design'] ) ) {
					$design = $options['design'];
				}

				/*-------------------------------Update Default Setting of bump----------------------------- */
				if ( isset( $options['settings'] ) && ! empty( $options['settings'] ) ) {
					$settings      = $options['settings'];
					$wfob_settings = get_post_meta( $bump_id, '_wfob_settings', true );
					if ( isset( $settings['order_bump_position_hooks'] ) && ! empty( $settings['order_bump_position_hooks'] ) ) {
						$wfob_settings['order_bump_position_hooks'] = $settings['order_bump_position_hooks'];
					}

					if ( isset( $settings['order_bump_position_hooks_mobile'] ) && ! empty( $settings['order_bump_position_hooks_mobile'] ) ) {
						$wfob_settings['order_bump_position_hooks_mobile'] = $settings['order_bump_position_hooks_mobile'];
					}

					if ( isset( $settings['order_bump_auto_added'] ) ) {
						$wfob_settings['order_bump_auto_added'] = $settings['order_bump_auto_added'];
					}
					if ( isset( $settings['order_bump_auto_hide'] ) ) {
						$wfob_settings['order_bump_auto_hide'] = $settings['order_bump_auto_hide'];
					}

					update_post_meta( $bump_id, '_wfob_settings', $wfob_settings );
				}

				// Delete transient before update
				WFOB_Common::delete_transient( $bump_id );
				WFOB_Common::update_design_data( $bump_id, $design );
				$resp = array(
					'msg'    => __( 'Changes saved' ),
					'status' => true,
				);
			}

			return rest_ensure_response( $resp );
		}

		public function import_skin( WP_REST_Request $request ) {

			$resp           = array();
			$resp['status'] = false;
			$resp['msg']    = __( 'Failed', 'funnel-builder-powerpack' );

			$bump_id = $request->get_param( 'bump_id' );
			$layout  = $request->get_param( 'layout' );

			if ( absint( $bump_id ) <= 0 || empty( $layout ) ) {
				return rest_ensure_response( $resp );
			}
			$default_data = WFOB_Bump_Fc::get_default_models();


			if ( ! isset( $default_data[ $layout ] ) ) {
				$resp['msg'] = __( 'Layout Not Found', 'funnel-builder-powerpack' );

				return rest_ensure_response( $resp );
			}

			$products = WFOB_Common::get_prepared_products( $bump_id );


			$design_data = $default_data[ $layout ];

			if ( isset( $design_data['class_name'] ) ) {
				$temp = new $design_data['class_name']( $bump_id );

				$design_data = $temp->override_design_data_keys( $design_data, $design_data['layout'] );

			}


			$temp = WFOB_Common::add_product_details_default_layout( $products, $design_data );


			// Delete transient before update
			WFOB_Common::delete_transient( $bump_id );
			WFOB_Common::update_design_data( $bump_id, $temp );

			$resp['status'] = true;
			$resp['msg']    = __( 'Skin imported', 'funnel-builder-powerpack' );


			return rest_ensure_response( $resp );

		}

	}

	WFFN_REST_BUMP_API_EndPoint::get_instance();
}

