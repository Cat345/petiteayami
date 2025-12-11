<?php
if ( ! class_exists( 'WFFN_REST_UPSELL_API_EndPoint' ) ) {
	class WFFN_REST_UPSELL_API_EndPoint extends WFFN_REST_Controller {

		private static $ins = null;
		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'funnel-upsell';

		/**
		 * WFFN_REST_API_EndPoint constructor.
		 */
		public function __construct() {
			add_action( 'rest_api_init', [ $this, 'register_endpoint' ], 12 );
		}

		/**
		 * @return WFFN_REST_UPSELL_API_EndPoint|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function register_endpoint() {
			// Get Offers for UPSELL page.
			register_rest_route( $this->namespace, '/' . 'funnel-upsell' . '/(?P<step_id>[\d]+)' . '/offers', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'wfocu_add_offer' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'wfocu_list_offers' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wfocu_remove_offer' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );


			// Add product to Offer for UPSELL page.
			register_rest_route( $this->namespace, '/' . 'funnel-offer' . '/(?P<step_id>[\d]+)' . '/products', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wfocu_offer_details' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'wfocu_add_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wfocu_remove_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Save Upsell Offers Funnel Products
			register_rest_route( $this->namespace, '/' . 'funnel-offer' . '/(?P<step_id>[\d]+)' . '/products' . '/save-layout', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'wfocu_save_offer_products' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			register_rest_route( $this->namespace, '/' . 'funnel-upsell' . '/(?P<step_id>[\d]+)' . '/rules', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_upsell_rules' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_upsell_rules' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );


			register_rest_route( $this->namespace, '/' . 'funnel-upsell' . '/(?P<step_id>[\d]+)' . '/dynamic-path', array(
				'args' => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_dynamic_path' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),

				'schema' => array( $this, 'get_public_item_schema' ),
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

		// Remove Product from Offer.
		public function wfocu_remove_product( WP_REST_Request $request ) {
			$resp = array(
				'status' => false,
				'msg'    => '',
			);

			$offer_id = $request->get_param( 'step_id' );
			$options  = $request->get_body();

			if ( absint( $offer_id ) > 0 && ! empty( $options ) ) {

				$options = $this->sanitize_custom( $options );

				$step_id     = get_post_meta( $offer_id, '_funnel_id', true );
				$funnel_id   = absint( $step_id );
				$offer_id    = absint( $offer_id );
				$product_key = ! empty( $options['product_key'] ) ? wc_clean( $options['product_key'] ) : '';

				if ( ! empty( $product_key ) && ! is_array( $product_key ) ) {
					$product_key = (array) $product_key;
				}

				if ( count( $product_key ) > 0 ) {

					foreach ( $product_key as $p_key ) {

						$updatable       = 0;
						$offer_meta_data = WFOCU_Core()->offers->get_offer( $offer_id );
						if ( isset( $offer_meta_data->products ) && isset( $offer_meta_data->products->{$p_key} ) ) {
							$updatable ++;
							unset( $offer_meta_data->products->{$p_key} );
						}
						if ( isset( $offer_meta_data->fields ) && isset( $offer_meta_data->fields->{$p_key} ) ) {
							$updatable ++;
							unset( $offer_meta_data->fields->{$p_key} );
						}
						if ( isset( $offer_meta_data->variations ) && isset( $offer_meta_data->variations->{$p_key} ) ) {
							$updatable ++;
							unset( $offer_meta_data->variations->{$p_key} );
						}

						if ( $updatable > 0 ) {

							WFOCU_Common::update_offer( $offer_id, $offer_meta_data, $funnel_id );
							WFOCU_Common::update_funnel_time( $funnel_id );
							do_action( 'wfocu_offer_updated', $offer_meta_data, $offer_id, $funnel_id );

						}

					}
					$offer_settings_fields = $this->get_offer_fields( $funnel_id, $offer_id );
					$offer_original        = WFOCU_Core()->offers->get_offer( $offer_id );
					$resp                  = array(
						'success'        => true,
						'fields_to_show' => $this->maybe_filter_fields( $offer_settings_fields, $offer_original, $offer_id ),
						'msg'            => __( 'Product removed from offer', 'funnel_builder' ),
					);

					$all_data          = wffn_rest_api_helpers()->get_step_post( $offer_id, true );
					$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
					$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;

				}

			}
			wp_send_json( $resp );
		}

		// Save Products for Offer.
		public function wfocu_save_offer_products( WP_REST_Request $request ) {
			$resp = array(
				'success' => false,
				'msg'     => '',
				'data'    => array(),
			);

			$offer_id = $request->get_param( 'step_id' );
			$options  = $request->get_body();

			if ( absint( $offer_id ) > 0 && ! empty( $options ) ) {

				$options = $this->sanitize_custom( $options );

				$offer_id = absint( $offer_id );
				$step_id  = get_post_meta( $offer_id, '_funnel_id', true );;

				if ( $step_id > 0 && $offer_id > 0 ) {

					$post = get_post( wc_clean( wp_unslash( $step_id ) ) );  //input var ok
					if ( ! is_null( $post ) ) {

						$funnel_id   = $post->ID;
						$offer_id    = isset( $offer_id ) ? wc_clean( wp_unslash( $offer_id ) ) : 0; //input var ok
						$offers      = ( isset( $options['offers'] ) && is_array( wc_clean( wp_unslash( $options['offers'] ) ) ) && count( wc_clean( wp_unslash( $options['offers'] ) ) ) ) > 0 ? wc_clean( wp_unslash( $options['offers'] ) ) : WFOCU_Core()->funnels->get_funnel_steps( $funnel_id ); //input var ok
						$get_options = $options; //input var ok

						$update_steps   = [];
						$offers_setting = new stdClass();

						if ( ! empty( $offers ) && count( $offers ) > 0 ) {

							if ( ! empty( $options['products'] ) && count( $options['products'] ) > 0 ) {

								$offers_setting->products   = new stdClass();
								$offers_setting->variations = new stdClass();
								$offers_setting->fields     = new stdClass();


								foreach ( $options['products'] as $pro ) {
									$hash_key = $pro['id'];

									$offers_setting->products->{$hash_key}                   = $pro['key'];
									$offers_setting->fields->{$hash_key}                     = new stdClass();
									$offers_setting->fields->{$hash_key}->discount_amount    = $pro['discount_amount'];
									$offers_setting->fields->{$hash_key}->discount_type      = WFOCU_Common::get_discount_setting( $pro['discount_type'] );
									$offers_setting->fields->{$hash_key}->quantity           = $pro['quantity'];
									$offers_setting->fields->{$hash_key}->shipping_cost_flat = isset( $pro['shipping_cost_flat'] ) ? floatval( $pro['shipping_cost_flat'] ) : 0;

									if ( isset( $pro['variations'] ) && count( $pro['variations'] ) > 0 ) {
										$offers_setting->variations->{$hash_key} = array();
										foreach ( $pro['variations'] as $variation_id => $settings ) {
											if ( isset( $pro['checkVarient'] ) && in_array( $settings['id'], $pro['checkVarient'] ) ) {
												$offers_setting->variations->{$hash_key}[ $settings['id'] ]                  = new stdClass();
												$offers_setting->variations->{$hash_key}[ $settings['id'] ]->vid             = $variation_id;
												$offers_setting->variations->{$hash_key}[ $settings['id'] ]->discount_amount = $settings['discount_amount'];
												$offers_setting->variations->{$hash_key}[ $settings['id'] ]                  = apply_filters( 'wfocu_variations_offers_setting_data', $offers_setting->variations->{$hash_key}[ $settings['id'] ] );
											}
										}

										$offers_setting->fields->{$hash_key}->default_variation = isset( $pro['radioVarient'] ) ? $pro['radioVarient'] : '';
									}
								}

								$offers_setting->have_multiple_product = is_array( $options['products'] ) && count( $options['products'] ) > 1 ? 2 : 1;
							}

							if ( ! empty( $options['settings'] ) && count( $options['settings'] ) > 0 ) {
								$options      = $options['settings'];
								$offer_meta   = get_post_meta( $offer_id, '_wfocu_setting', true );
								$ofr_settings = ! empty( $offer_meta->settings ) ? $offer_meta->settings : new stdClass();
								$ofr_settings = is_array( $ofr_settings ) ? (object) $ofr_settings : $ofr_settings;
								foreach ( $options as $key => $val ) {
									if ( isset( $options[ $key ] ) && is_array( $options[ $key ] ) ) {
										$ofr_settings->$key = ! empty( $options[ $key ][0] ) ? ( $options[ $key ][0] ) : "false";
									} else {
										$ofr_settings->$key = isset( $options[ $key ] ) && is_scalar( $options[ $key ] ) ? $options[ $key ] : '';
									}
								}

								$offers_setting->settings = $ofr_settings;
							}

						}

						$steps = WFOCU_Core()->funnels->get_funnel_steps( $funnel_id );
						if ( $steps && is_array( $steps ) && count( $steps ) > 0 ) {
							foreach ( $steps as $key => $step ) {
								if ( ! empty( $step ) ) {

									if ( intval( $step['id'] ) === intval( $offer_id ) ) {
										$step = WFOCU_Core()->offers->filter_step_object_for_db( $step );
									}
									$update_steps[ $key ] = $step;

								}
							}
						}


						WFOCU_Common::update_funnel_steps( $funnel_id, $update_steps );

						$getsettings = WFOCU_Common::get_offer( $offer_id );

						$offers_setting->template       = ( isset( $getsettings->template ) ? $getsettings->template : '' );
						$offers_setting->template_group = ( isset( $getsettings->template_group ) ? $getsettings->template_group : '' );

						if ( ! empty( $offers_setting ) ) {

							$offers_setting = apply_filters( 'wfocu_update_offer_save_setting', $offers_setting, $get_options, $offer_id, $funnel_id );
							WFOCU_Common::update_offer( $offer_id, $offers_setting, $funnel_id );
							if ( '' !== $funnel_id ) {
								WFOCU_Common::update_funnel_time( $funnel_id );
							}

							do_action( 'wfocu_offer_updated', $offers_setting, $offer_id, $funnel_id );

							$resp['msg']     = __( 'Data is saved', 'woofunnels-upstroke-one-click-upsell' );
							$resp['success'] = true;
							$resp['data']    = $offers_setting;
						}
						$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $update_steps );
						WFOCU_Common::update_funnel_upsell_downsell( $funnel_id, $upsell_downsell );
					}


					$woofunnels_transient_obj = WooFunnels_Transient::get_instance();
					$woofunnels_transient_obj->delete_all_transients( 'upstroke' );
				}

			}

			wp_send_json( $resp );
		}

		public function get_on_jump_offer( $step_id, $offer_id ) {
			$available_offers = array();
			$offers           = WFOCU_Core()->funnels->get_funnel_steps( $step_id );
			if ( is_array( $offers ) && count( $offers ) ) {

				$drop_option1 = [];
				$options      = [];
				$offer_ids    = array_column( $offers, 'id' );

				$jump_offers                        = $this->next_array_element( ( $offer_ids ), $offer_id );
				$drop_option2                       = [];
				$drop_option2['Terminate Funnel'][] = array( 'label' => __( 'Thank You Page', 'funel-builder' ), 'value' => 'terminate', 'key' => 'terminate' );


				if ( count( $jump_offers ) ) {

					$drop_option1['select'][] = array( 'label' => __( 'Select an offer', 'funnel-builder-powerpack' ), 'value' => 'automatic', 'key' => 'automatic' );

					foreach ( $offers as $_offer ) {
						if ( in_array( $_offer['id'], $jump_offers ) ) {
							$optionsKey = strtoupper( $_offer['type'] );

							$options[ $optionsKey ][] = [
								'value' => (string) $_offer['id'],
								'key'   => (string) $_offer['id'],
								'label' => $_offer['name'],
							];
						}
					}

				}

				$combined_offers  = array_merge( $drop_option1, $options, $drop_option2 );
				$available_offers = $this->format_rules_select( $combined_offers, 0 );

			}

			return $available_offers;
		}

		// Function to find next element in array.
		public function next_array_element( $array, $key ) {
			return array_slice( $array, array_search( $key, ( $array ) ) + 1 );
		}

		// Add Product to Offer.
		public function wfocu_add_product( WP_REST_Request $request ) {

			$resp = array(
				'success' => false,
				'msg'     => '',
				'data'    => array(),
			);

			$offer_id = $request->get_param( 'step_id' );
			$options  = $request->get_body();

			if ( absint( $offer_id ) > 0 && ! empty( $options ) ) {

				$options       = $this->sanitize_custom( $options );
				$product_data  = $options['products'];
				$data_products = [];

				$step_id       = get_post_meta( $offer_id, '_funnel_id', true );
				$offer_id      = absint( $offer_id );
				$funnel_id     = absint( $step_id );
				$products      = array();
				$fields        = array();
				$variations    = array();
				$products_list = ( isset( $product_data ) && is_array( wc_clean( $product_data ) ) && count( wc_clean( $product_data ) ) > 0 ) ? wc_clean( $product_data ) : array();

				if ( ! is_array( $products_list ) ) {
					$products_list = array();
					wp_send_json( $resp );

				}

				$variation_save  = array();
				$is_add_on_exist = WFOCU_Common::is_add_on_exist( 'MultiProduct' );
				if ( ! $is_add_on_exist ) {
					$first_prod    = $products_list[0];
					$products_list = array( $first_prod );
				}

				if ( method_exists( 'WFFN_REST_API_Helpers', 'remove_all_wc_price_action' ) ) {
					wffn_rest_api_helpers()->remove_all_wc_price_action();
				}

				foreach ( $products_list as $pid ) {
					$pro = wc_get_product( $pid );
					if ( $pro instanceof WC_Product ) {
						$image_url = wp_get_attachment_url( $pro->get_image_id() );

						if ( empty( $image_url ) ) {
							$image_url = WFOCU_PLUGIN_URL . '/assets/img/product_default_icon.jpg';
						}
						$hash_key                = WFOCU_Common::get_product_id_hash( $funnel_id, $offer_id, $pid );
						$product_details         = new stdClass();
						$product_details->id     = $pid;
						$product_details->name   = WFOCU_Common::get_formatted_product_name( $pro );
						$product_details->image  = $image_url;
						$product_details->type   = $pro->get_type();
						$product_details->status = $pro->get_status();
						if ( ! $pro->is_type( 'variable' ) ) {
							$product_details->regular_price     = wc_price( $pro->get_regular_price() );
							$product_details->regular_price_raw = $pro->get_regular_price();
							$product_details->price             = wc_price( $pro->get_price() );
							$product_details->price_raw         = $pro->get_price();
							if ( $product_details->regular_price === $product_details->price ) {
								unset( $product_details->price );
							}
						}

						$products[ $hash_key ]              = $product_details;
						$variation_save[ $hash_key ]        = array();
						$variations[ $hash_key ]            = array();
						$product_fields                     = new stdClass();
						$product_fields->discount_amount    = 0;
						$product_fields->discount_type      = 'percentage_on_reg';
						$product_fields->quantity           = 1;
						$product_fields->shipping_cost_flat = 0;

						if ( $pro->is_type( 'variable' ) ) {
							$first_variation = null;
							foreach ( $pro->get_children() as $child_id ) {
								$variation = wc_get_product( $child_id );

								$variation_id = $child_id;
								$vpro         = $variation;

								if ( $vpro ) {
									$variation_options                    = new stdClass();
									$variation_options->name              = WFOCU_Common::get_formatted_product_name( $vpro );
									$variation_options->vid               = $variation_id;
									$variation_options->attributes        = WFOCU_Common::get_variation_attribute( $vpro );
									$variation_options->regular_price     = wc_price( $vpro->get_regular_price() );
									$variation_options->regular_price_raw = $vpro->get_regular_price();
									$variation_options->price             = wc_price( $vpro->get_price() );
									$variation_options->price_raw         = $vpro->get_price();

									$variation_options->discount_amount = 0;
									$variation_options->discount_type   = 'percentage_on_reg';
									$variation_options->is_enable       = true;
									if ( is_null( $first_variation ) ) {
										$first_variation = true;

										$product_fields->default_variation = $variation_options->vid;
										$product_fields->variations_enable = true;

										$variation_save[ $hash_key ][ $variation_id ]                  = new stdClass();
										$variation_save[ $hash_key ][ $variation_id ]->discount_amount = 0;
										$variation_save[ $hash_key ][ $variation_id ]->disount_on      = 'regular';
										$variation_save[ $hash_key ][ $variation_id ]->vid             = $variation_id;
									}

									$variations[ $hash_key ][ $variation_id ] = $variation_options;
									unset( $variation_options );

								}
							}
						}

						if ( ! empty( $product_fields ) ) {
							foreach ( $product_fields as $fkey => $fval ) {
								$products[ $hash_key ]->{$fkey} = $fval;
							}
						}

						$fields[ $hash_key ] = $product_fields;
						unset( $product_fields );
					}
				}

				$output                     = new stdClass();
				$offer_meta_data            = WFOCU_Core()->offers->get_offer( $offer_id );
				$offer_data                 = new stdClass();
				$offer_data->products       = ( isset( $offer_meta_data ) && isset( $offer_meta_data->products ) ) && ! empty( $offer_meta_data->products ) ? $offer_meta_data->products : new stdClass();
				$offer_data->fields         = ( isset( $offer_meta_data ) && isset( $offer_meta_data->fields ) ) && ! empty( $offer_meta_data->fields ) ? $offer_meta_data->fields : new stdClass();
				$offer_data->variations     = ( isset( $offer_meta_data ) && isset( $offer_meta_data->variations ) && ! empty( $offer_meta_data->variations ) ) ? $offer_meta_data->variations : new stdClass();
				$offer_data->settings       = ( isset( $offer_meta_data ) && isset( $offer_meta_data->settings ) ) && ! empty( $offer_meta_data->settings ) ? $offer_meta_data->settings : WFOCU_Core()->offers->get_default_offer_setting();
				$offer_data->template       = ( isset( $offer_meta_data ) && isset( $offer_meta_data->template ) ) ? $offer_meta_data->template : '';
				$offer_data->template_group = ( isset( $offer_meta_data ) && isset( $offer_meta_data->template_group ) ) ? $offer_meta_data->template_group : '';

				if ( count( $products ) > 0 ) {
					$output->products = new stdClass();
					foreach ( $products as $hash => $pr ) {
						$output->products->{$hash}     = $pr;
						$offer_data->products->{$hash} = $pr->id;
						$prdct                         = $pr;
						$prdct->key                    = $prdct->id;
						$prdct->id                     = $hash;
						$prdct                         = wffn_rest_api_helpers()->unstrip_product_data( wffn_rest_api_helpers()->unstrip_product_data( $prdct ) );
						$data_products[]               = $this->update_product_schema( $prdct );
					}
				}
				if ( count( $fields ) > 0 ) {
					unset( $hash );
					$output->fields = new stdClass();
					foreach ( $fields as $hash => $field ) {
						$output->fields->{$hash}     = $field;
						$offer_data->fields->{$hash} = $field;
					}
				}
				if ( count( $variations ) > 0 ) {
					$variation = null;
					unset( $hash );
					$output->variations = new stdClass();
					if ( count( $variations ) > 0 ) {
						foreach ( $variations as $hash => $variation ) {
							if ( ! empty( $variation ) ) {
								$output->variations->{$hash}     = $variation;
								$offer_data->variations->{$hash} = $variation_save[ $hash ];
							}
						}
					} else {
						$offer_data->variations = new stdClass();
					}
				}

				$offer_data->settings = WFOCU_Common::maybe_filter_boolean_strings( $offer_data->settings );
				WFOCU_Common::update_offer( $offer_id, $offer_data, $funnel_id );
				WFOCU_Common::update_funnel_time( $funnel_id );

				do_action( 'wfocu_offer_updated', $offer_data, $offer_id, $funnel_id );

				$output = apply_filters( 'wfocu_offer_product_added', $output, $offer_data, $offer_id, $funnel_id );

				$output             = json_decode( wp_json_encode( $output ), 1 );
				$output['products'] = $data_products;

				$offer_settings_fields = $this->get_offer_fields( $funnel_id, $offer_id );
				$offer_original        = WFOCU_Core()->offers->get_offer( $offer_id );

				$resp['fields_to_show'] = $this->maybe_filter_fields( $offer_settings_fields, $offer_original, $offer_id );


				$resp['success'] = true;
				$resp['msg']     = __( 'Product saved to funnel', 'woofunnels-upstroke-one-click-upsell' );

				$all_data          = wffn_rest_api_helpers()->get_step_post( $offer_id, true );
				$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
				$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;

				$resp['data'] = $output;
			}

			wp_send_json( $resp );
		}

		public function wfocu_remove_offer( WP_REST_Request $request ) {
			$resp     = [];
			$offer_id = $request->get_param( 'step_id' );
			$data     = ! empty( $request->get_body() ) ? $this->sanitize_custom( $request->get_body() ) : '';

			if ( absint( $offer_id ) > 0 ) {

				if ( is_array( $data ) && ! empty( $data['upsell_id'] ) ) {
					$funnel_id = absint( $data['upsell_id'] );
				} else {
					$funnel_id = WFOCU_Core()->offers->get_parent_funnel( $offer_id );
				}

				$steps = WFOCU_Core()->funnels->get_funnel_steps( $funnel_id );

				$offer_id = absint( $offer_id );
				$status   = wp_delete_post( $offer_id, true );
				WFOCU_Common::update_funnel_time( $funnel_id );
				$offers = [];
				if ( count( $steps ) ) {
					foreach ( $steps as $step ) {
						if ( $offer_id !== absint( $step['id'] ) ) {
							$offers[] = $step;
						}
					}
					if ( isset( $offers ) ) {
						// Update Funnel Step Offers.
						update_post_meta( $funnel_id, '_funnel_steps', $offers );
					}
				}

				if ( null !== $status && false !== $status ) {
					$resp['status']    = true;
					$all_data          = wffn_rest_api_helpers()->get_step_post( $offer_id, true );
					$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
					$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;


					$get_object                               = WFFN_Core()->steps->get_integration_object( 'wc_upsells' );
					$upsell_step                              = $get_object->populate_data_properties( array( 'type' => 'wc_upsells', 'id' => $funnel_id ), 0 );
					$prepare_data                             = [];
					$prepare_data['steps_list']               = [];
					$prepare_data['groups']                   = [];
					$prepare_data['groups'][0]                = [ 'type' => 'wc_upsells', 'id' => $funnel_id, 'substeps' => [] ];
					$prepare_data['steps_list'][ $funnel_id ] = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $upsell_step );
					if ( isset( $upsell_step['substeps']['offer'] ) ) {

						foreach ( $upsell_step['substeps']['offer'] as $key => $offer ) {
							$prepare_data['groups'][0]['substeps'][] = [
								'id'   => $offer['id'],
								'type' => 'offer'
							];
							$offer['type']                           = 'offer';

							$prepare_data['steps_list'][ $offer['id'] ] = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $offer );
							$offer_settings                             = get_post_meta( $offer['id'], '_wfocu_setting', true );
							/**
							 * get offer accept and reject id
							 */
							if ( ! empty( $offer_settings ) && is_object( $offer_settings ) ) {
								if ( empty( $offer_settings->settings ) ) {
									$offer_settings->settings = (object) $offer_settings->settings;
								}

								$accepted_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_accepted ) && isset( $offer_settings->settings->jump_to_offer_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) ? $offer_settings->settings->jump_to_offer_on_accepted : 'automatic';
								$rejected_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_rejected ) && isset( $offer_settings->settings->jump_to_offer_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) ? $offer_settings->settings->jump_to_offer_on_rejected : 'automatic';

							} else {
								$accepted_id = 'automatic';
								$rejected_id = 'automatic';

							}

							/**
							 * handle case when offer flow next offer
							 * return next offer id for handle ui nodes
							 */
							if ( ( 'automatic' === $accepted_id || 'automatic' === $rejected_id ) && isset( $upsell_step['substeps']['offer'][ $key + 1 ] ) ) {
								$accepted_id = ( 'automatic' === $accepted_id ) ? absint( $upsell_step['substeps']['offer'][ $key + 1 ]['id'] ) : absint( $accepted_id );
								$rejected_id = ( 'automatic' === $rejected_id ) ? absint( $upsell_step['substeps']['offer'][ $key + 1 ]['id'] ) : absint( $rejected_id );
							}

							if ( 'terminate' === $accepted_id || 'automatic' === $accepted_id ) {
								$accepted_id = 0;
							}
							if ( 'terminate' === $rejected_id || 'automatic' === $rejected_id ) {
								$rejected_id = 0;
							}

							$offer_data           = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $offer );
							$offer_data['accept'] = $accepted_id;
							$offer_data['reject'] = $rejected_id;


							$prepare_data['steps_list'][ $offer['id'] ] = $offer_data;

						}
					}

					$prepare_data['steps_list'] = wffn_rest_api_helpers()->add_step_edit_details( $prepare_data['steps_list'] );
					$prepare_data['steps_list'] = apply_filters( 'wffn_rest_get_funnel_steps', $prepare_data['steps_list'], false );
					$resp['data']               = $prepare_data;


				}

			}
			$resp['msg'] = __( 'Deleted', 'woofunnels-upstroke-one-click-upsell' );

			return $resp;
		}

		// Update Rules for Upsell.
		public function update_upsell_rules( WP_REST_Request $request ) {
			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'funnel-builder-powerpack' );

			$step_id    = $request->get_param( 'step_id' );
			$wfocu_rule = $request->get_body();

			if ( ! empty( $wfocu_rule ) && absint( $step_id ) && class_exists( 'WFOCU_Rules' ) ) {


				$posted_data = $this->sanitize_custom( $wfocu_rule );

				$posted_data = $this->rectify_posted_rules( $posted_data );

				update_post_meta( $step_id, '_wfocu_rules', $posted_data );
				update_post_meta( $step_id, '_wfocu_is_rules_saved', 'yes' );

				$all_data          = wffn_rest_api_helpers()->get_step_post( $step_id, true );
				$resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
				$resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;


				$resp['success'] = true;
				$resp['msg']     = __( 'Rules Updated', 'funnel-builder-powerpack' );
			}

			return rest_ensure_response( $resp );
		}

		// Get rules for Upsell Step.
		public function get_upsell_rules( WP_REST_Request $request ) {
			$resp                       = array();
			$resp['success']            = false;
			$resp['msg']                = __( 'Failed', 'funnel-builder-powerpack' );
			$resp['data']['rules']      = array();
			$resp['data']['rules_list'] = array();


			$step_id   = $request->get_param( 'step_id' );
			$funnel_id = $request->get_param( 'funnel_id' );

			wffn_rest_api_helpers()->maybe_step_not_exits( $step_id );

			$step_post = wffn_rest_api_helpers()->get_step_post( $step_id );

			if ( 0 === absint( $funnel_id ) ) {
				$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );

			}
			if ( 0 === absint( $funnel_id ) ) {
				$upsell_id = get_post_meta( $step_id, '_funnel_id', true );
				$funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );
			}

			$resp['data']['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );
			$resp['data']['step_data']   = $step_post;

			if ( class_exists( 'WFOCU_Rules' ) && absint( $step_id ) > 0 ) {

				$wfocu_is_rules_saved = get_post_meta( $step_id, '_wfocu_is_rules_saved', true );
				$wfocu_rules          = get_post_meta( $step_id, '_wfocu_rules', true );
				$list_rules           = $this->get_offer_rules( $wfocu_rules );

				$resp['success'] = true;
				$resp['msg']     = __( 'Rules list loaded', 'funnel-builder-powerpack' );

				$resp['data']['rules_list'] = ! empty( $list_rules ) ? $list_rules : [];


				if ( 'yes' === $wfocu_is_rules_saved && ! empty( $wfocu_rules ) ) {
					$wfocu_rules = $this->filter_general_always_rules( $wfocu_rules );
					$wfocu_rules = $this->maybe_migrate_rules_before_rendering( $wfocu_rules );

					$basic   = ! empty( $wfocu_rules['basic'] ) ? $this->strip_group_rule_keys( $wfocu_rules['basic'] ) : array();
					$product = ! empty( $wfocu_rules['product'] ) ? $this->strip_group_rule_keys( $wfocu_rules['product'] ) : array();

					$formatted_rules = array_merge_recursive( $product, $basic );
					$remove_rule_keys = [];

					/**
					 * remove all rule in selected list which is need to index order and check for 'custom-html' type
					 */
					if( ! empty( $list_rules ) ) {
						foreach ( $list_rules as $item ) {
							if ( ! empty( $item['fields'] ) && is_array( $item['fields'] ) ) {
								foreach ( $item['fields'] as $field ) {
									if ( isset( $field['type'] ) && $field['type'] === 'custom-html' ) {
										$remove_rule_keys[] = $item['key'];
										break;
									}
								}
							}
						}
						// If there are keys to be removed, filter the formatted rules
						if ( is_array( $remove_rule_keys ) && count( $remove_rule_keys ) > 0 ) {
							foreach ( $formatted_rules as $groupKey => &$group ) { //phpcs:ignore
								$group = array_filter( $group, function ( $rule ) use ( $remove_rule_keys ) {
									return ! in_array( $rule['rule_type'], $remove_rule_keys, true );
								} );
								$group = array_values( $group );
							}
						}
					}

					$resp['data']['rules'] = $formatted_rules;
				}

			}

			return rest_ensure_response( $resp );
		}


		public function maybe_migrate_rules_before_rendering( $wfocu_rules ) {

			if ( empty( $wfocu_rules ) ) {
				return $wfocu_rules;
			}
			if ( empty( $wfocu_rules['product'] ) ) {
				return $wfocu_rules;
			}
			$all_basic_rules   = $wfocu_rules['basic'];
			$all_product_rules = $wfocu_rules['product'];


			/**
			 * Here we are iterating over each product rules and basic as well and adding combining each basic rule with product rule
			 */
			if ( count( $all_product_rules ) > 0 ) {
				$wfocu_rules['basic'] = [];
				foreach ( $all_product_rules as $k1 => $rulep ) {
					if ( count( $all_basic_rules ) > 0 ) {
						foreach ( $all_basic_rules as $k => $rulesb ) {
							$wfocu_rules['basic'][ $k . '_' . $k1 ] = array_merge( $rulep, $rulesb );
						}
					} else {
						$wfocu_rules['basic'][ $k1 ] = $rulep;
					}
				}
			}

			$wfocu_rules['product'] = [];

			return $wfocu_rules;
		}

		/**
		 * At this time we already know that we have some rules already set
		 * lets remove general rules and filer the api before giving results.
		 *
		 * @param $wfocu_rules
		 *
		 * @return array
		 */
		public function filter_general_always_rules( $wfocu_rules ) {

			$new_rules = [];
			foreach ( $wfocu_rules as $k => $rule_cats ) {
				$new_rules[ $k ] = [];
				foreach ( $rule_cats as $grpk => $grp ) {

					foreach ( $grp as $rule_key => $rule ) {
						if ( ! in_array( $rule['rule_type'], [ 'general_always_2', 'general_always' ], true ) ) {
							if ( ! isset( $new_rules[ $k ][ $grpk ] ) ) {
								$new_rules[ $k ][ $grpk ] = [];
							}
							$new_rules[ $k ][ $grpk ][ $rule_key ] = $rule;
						}
					}
				}
			}

			return $new_rules;
		}

		public function get_offer_rules( $saved_rules ) {
			$rule_list = [];
			if ( class_exists( 'WFOCU_Rules' ) ) {
				WFOCU_Core()->rules->load_rules_classes();
				$wfocu_advanced_rules = apply_filters( 'wfocu_wfocu_rule_get_rule_types', array() );
				$wfocu_products_rules = apply_filters( 'wfocu_wfocu_rule_get_rule_types_product', array() );

				$wfocu_products_rules[__( 'Products', 'woofunnels-upstroke-one-click-upsell' ) ] = $wfocu_products_rules[__( 'Order', 'woofunnels-upstroke-one-click-upsell' )];
				unset( $wfocu_products_rules[__( 'Order', 'woofunnels-upstroke-one-click-upsell' )] );

				if ( ! empty( $wfocu_advanced_rules[__( 'Default', 'woofunnels-upstroke-one-click-upsell' )] ) ) {
					unset( $wfocu_advanced_rules[__( 'Default', 'woofunnels-upstroke-one-click-upsell' )] );
				}

				$rule_set                              = array_merge_recursive( $wfocu_products_rules, $wfocu_advanced_rules );
				$rule_set[__( 'Default', 'woofunnels-upstroke-one-click-upsell' )]['general_always'] = $rule_set[__( 'Default', 'woofunnels-upstroke-one-click-upsell' )]['general_always_2'];
				unset( $rule_set[__( 'Default', 'woofunnels-upstroke-one-click-upsell' )]['general_always_2'] );
				$rule_set = $this->format_rules_select( $rule_set );

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
						$rule_object          = WFOCU_Rules::get_instance()->woocommerce_wfocu_rule_get_rule_object( $rule['key'] );
						$rule_type            = $rule_object->get_condition_input_type();
						$values               = $rule_object->get_possible_rule_values();
						$options['rule_type'] = $rule_type;
						$options              = array_merge( $defaults, $options );
						$operators            = $rule_object->get_possible_rule_operators();
						$operators            = ! empty( $operators ) && is_array( $operators ) ? wffn_rest_api_helpers()->array_to_nvp( array_flip( $operators ), "label", "value", "value", "key" ) : array();
						$rule['operators']    = $operators;
						$condition_input_type = $rule_object->get_condition_input_type();

						$data_args['condition_input_type'] = $condition_input_type;
						if ( in_array( $rule_type, [ 'Cart_Product_Select', 'Product_Select' ], true ) ) {
							$products            = ( ! empty( $saved_rules ) && isset( $saved_rules['product'] ) && is_array( $saved_rules['product'] ) ) ? $this->get_product_from_conditions( $saved_rules['product'] ) : [];
							$products_from_basic = ( ! empty( $saved_rules ) && isset( $saved_rules['basic'] ) && is_array( $saved_rules['basic'] ) ) ? $this->get_product_from_conditions( $saved_rules['basic'] ) : [];

							$values = array_merge( $products, $products_from_basic );
						}
						if ( in_array( $rule_type, [ 'Coupon_Select' ], true ) ) {
							$coupons = ( ! empty( $saved_rules ) && is_array( $saved_rules ) ) ? $this->get_coupons_from_conditions( $saved_rules['basic'] ) : [];

							if ( ! empty( $coupons ) ) {

								foreach ( $coupons as $coup ) {
									$values[ $coup ] = $coup;
								}
							}
						}

						$data_args['value_args'] = array(
							'input'   => $condition_input_type,
							'name'    => 'wfocu_rule[' . $options['category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][condition]',
							'choices' => $values
						);

						$rule['fields'] = $this->render_input_fields( $data_args );
						$rule_list[]    = $rule;

					}
				}
			}

			return $rule_list;
		}


		// Get Offer Details for Upsells.
		public function get_wfocu_offer_details( WP_REST_Request $request ) {
			$resp                        = array();
			$resp['success']             = false;
			$resp['msg']                 = __( 'Failed', 'woofunnels-upstroke-one-click-upsell' );
			$resp['data']                = array();
			$resp['data']['funnel_data'] = array();

			$offer_id  = $request->get_param( 'step_id' );
			$funnel_id = $request->get_param( 'funnel_id' );

			wffn_rest_api_helpers()->maybe_step_not_exits( $offer_id );

			$step_post = wffn_rest_api_helpers()->get_step_post( $offer_id );
			$upsell_id = ! empty( $step_post['upsell_id'] ) ? $step_post['upsell_id'] : 0;

			if ( class_exists( 'WFOCU_Common' ) && absint( $offer_id ) > 0 && absint( $upsell_id ) > 0 ) {

				$offer_id       = absint( $offer_id );
				$products       = array();
				$offer          = WFOCU_Core()->offers->get_offer( $offer_id );
				$offer_original = ! empty( $offer ) ? clone $offer : $offer;
				if ( empty( $offer ) ) {
					$offer               = new stdClass();
					$offer->settings     = [];
					$offer->ship_dynamic = [];
					$offer->InitialValue = [];
					$offer->fields       = [];
					$offer->products     = [];
					$offer->variations   = [];
				}

				$_REQUEST['offer_id']         = $offer_id;
				$offer->offer_id              = $offer_id;
				$offer->state                 = ! empty( $offer->state ) ? wc_string_to_bool( $offer->state ) : false;
				$offer->have_multiple_product = ! empty( $offer->products ) && is_array( $offer->products ) && count( $offer->products ) > 1 ? 2 : 1;

				$offer_fields = ! empty( $offer->fields ) ? json_decode( wp_json_encode( $offer->fields ), 1 ) : array();
				if ( ! empty( $offer->products ) ) {

					$offer_variations = array();
					if ( ! empty( $offer->variations ) ) {
						$offer_variations = $this->sanitize_custom( wp_json_encode( $offer->variations ) );
					}


					$offer_products = $this->sanitize_custom( wp_json_encode( $offer->products ) );

					foreach ( $offer_products as $key => $product_id ) {
						$current_product    = $offer_fields[ $key ];
						$product            = wc_get_product( $product_id );
						$product_variations = array();
						$selected_variants  = isset( $offer_variations[ $key ] ) ? array_keys( $offer_variations[ $key ] ) : array();

						if ( $product instanceof WC_Product ) {

							if ( $product->is_type( 'variable' ) ) {
								$available_variations = $product->get_children();
								if ( ! empty( $available_variations ) ) {
									$product_variations = isset( $offer_variations[ $key ] ) ? $this->get_formatted_variations( $available_variations, $offer_variations[ $key ] ) : [];
								}
							}

							if ( is_a( $product, 'WC_Product_Variation' ) ) {
								$variation_name = wffn_rest_api_helpers()->get_name_part( $product->get_name(), 1 );
							}

							$product_availability = wffn_rest_api_helpers()->get_availability_price_text( $product->get_id() );
							$product_stock        = $product_availability['text'];
							$stock_status         = ( $product->is_in_stock() ) ? true : false;

							$product_image = ! empty( wp_get_attachment_thumb_url( $product->get_image_id() ) ) ? wp_get_attachment_thumb_url( $product->get_image_id() ) : WFFN_PLUGIN_URL . '/admin/assets/img/product_default_icon.jpg';
							$regular_price = ! empty( $product->get_regular_price() ) ? $product->get_regular_price() : 0;
							$sale_price    = ! empty( $product->get_sale_price() ) ? $product->get_sale_price() : 0;

							$offer_schemes = [];

							if ( class_exists( 'WFOCU_WC_ATTS_Compatibility' ) && method_exists( 'WFOCU_WC_ATTS_Compatibility', 'get_scheme_plan_data' ) ) {
								$wcs_att_compatibility = new WFOCU_WC_ATTS_Compatibility();

								if ( $wcs_att_compatibility->is_enable() ) {
									$offer->schemes = ! empty( $offer->schemes ) ? $offer->schemes : new stdClass();
									$offer_schemes  = $wcs_att_compatibility->get_scheme_plan_data( $offer, $offer_id, $funnel_id );
								}
							}

							if ( ! empty( $offer_schemes ) ) {
								$selected_schemes = ( isset( $offer->schemes->{$key} ) && is_array( $offer->schemes->{$key} ) ) ? array_keys( $offer->schemes->{$key} ) : [];

								/**
								 * return
								 */
								if ( ! empty( $offer->schemes->{$key} ) ) {
									$save_schemes  = $offer->schemes->{$key};
									$offer_schemes = array_map( function ( $scheme ) use ( $save_schemes ) {
										if ( isset( $save_schemes[ $scheme['value'] ] ) && is_object( $save_schemes[ $scheme['value'] ] ) ) {
											if ( ! empty( $save_schemes[ $scheme['value'] ]->discount_amount ) ) {
												$scheme['discount_amount'] = $save_schemes[ $scheme['value'] ]->discount_amount;

											}
										}

										return $scheme;
									}, $offer_schemes );
								}

								$product_details = [
									'key'                  => $product_id,
									'id'                   => $key,
									'title'                => wffn_rest_api_helpers()->get_name_part( $product->get_title(), 0 ),
									'regular_price'        => $regular_price,
									'sale_price'           => $sale_price,
									'is_on_sale'           => $product->is_on_sale(),
									'currency_symbol'      => get_woocommerce_currency_symbol(),
									'product_type'         => $product->get_type(),
									'product_attribute'    => ! empty( $variation_name ) ? $variation_name : "-",
									'product_image'        => $product_image,
									'product_stock_status' => $stock_status,
									'product_stock'        => $product_stock,
									'schemes'              => $offer_schemes,
									'checkVarient'         => $selected_schemes,
									'product_status'       => $product->get_status(),
									'radioVarient'         => ! empty( $current_product['default_scheme'] ) ? $current_product['default_scheme'] : "",
									'has_schemes'          => true,
								];
							} else {
								$product_details = [
									'key'                  => $product_id,
									'id'                   => $key,
									'title'                => wffn_rest_api_helpers()->get_name_part( $product->get_title(), 0 ),
									'regular_price'        => $regular_price,
									'sale_price'           => $sale_price,
									'is_on_sale'           => $product->is_on_sale(),
									'currency_symbol'      => get_woocommerce_currency_symbol(),
									'product_type'         => $product->get_type(),
									'product_attribute'    => ! empty( $variation_name ) ? $variation_name : "-",
									'product_image'        => $product_image,
									'product_stock_status' => $stock_status,
									'product_stock'        => $product_stock,
									'product_status'       => $product->get_status(),
									'variations'           => $product_variations,
									'checkVarient'         => $selected_variants,
									'radioVarient'         => ! empty( $current_product['default_variation'] ) ? absint( $current_product['default_variation'] ) : "",
									'has_schemes'          => false,
								];
							}

							$offer_product = array_merge( $product_details, $offer_fields[ $key ] );
							$products[]    = $offer_product;
						}
					}

					$offer->products = $products;
				}

				$offer_values = new stdClass();
				if ( ! empty( $offer->settings ) ) {
					$settings = $offer->settings;

					$offer_values->jump_on_accepted             = ( ! empty( $settings->jump_on_accepted ) && true === wc_string_to_bool( $settings->jump_on_accepted ) ) ? ( array ) 'true' : [];
					$offer_values->jump_on_rejected             = ( ! empty( $settings->jump_on_rejected ) && true === wc_string_to_bool( $settings->jump_on_rejected ) ) ? ( array ) 'true' : [];
					$offer_values->jump_to_offer_on_accepted    = ! empty( $settings->jump_to_offer_on_accepted ) ? (string) $settings->jump_to_offer_on_accepted : "automatic";
					$offer_values->jump_to_offer_on_rejected    = ! empty( $settings->jump_to_offer_on_rejected ) ? (string) $settings->jump_to_offer_on_rejected : "automatic";
					$offer_values->subscription_discount        = ( ! empty( $settings->subscription_discount ) && true === wc_string_to_bool( $settings->subscription_discount ) ) ? ( array ) 'true' : [];
					$offer_values->subscription_signup_discount = ( ! empty( $settings->subscription_signup_discount ) && true === wc_string_to_bool( $settings->subscription_signup_discount ) ) ? ( array ) 'true' : [];
					$offer_values->free_trial_length            = ! empty( $settings->free_trial_length ) ? $settings->free_trial_length : 0;
					$offer_values->free_trial_period            = ! empty( $settings->free_trial_period ) ? $settings->free_trial_period : '';
					$offer_values->is_override_free_trial       = ( ! empty( $settings->is_override_free_trial ) && true === wc_string_to_bool( $settings->is_override_free_trial ) ) ? ( array ) 'true' : [];
					$offer_values->qty_selector                 = ( ! empty( $settings->qty_selector ) && true === wc_string_to_bool( $settings->qty_selector ) ) ? ( array ) 'true' : [];
					$offer_values->qty_max                      = ! empty( $settings->qty_max ) ? $settings->qty_max : 0;
					$offer_values->skip_exist                   = ( ! empty( $settings->skip_exist ) && true === wc_string_to_bool( $settings->skip_exist ) ) ? ( array ) 'true' : [];
					$offer_values->skip_purchased               = ( ! empty( $settings->skip_purchased ) && true === wc_string_to_bool( $settings->skip_purchased ) ) ? ( array ) 'true' : [];

					$offer->ship_dynamic = ! empty( $settings->ship_dynamic ) ? $settings->ship_dynamic : false;
					$offer->InitialValue = $offer_values;
				}

				$offer_settings_fields = $this->get_offer_fields( $upsell_id, $offer_id );

				$tabs = [
					'fields'         => $offer_settings_fields,
					'fields_to_show' => $this->maybe_filter_fields( $offer_settings_fields, $offer_original, $offer_id ),
					'settingName'    => __( 'Product Selection Settings', 'funnel-builder-powerpack' ),
					'priority'       => 10,
					'values'         => $offer_values,
				];

				$offer->settings       = $tabs;
				$offer->discount_types = WFFN_Common::get_discount_type_keys();

				if ( 0 === absint( $funnel_id ) ) {
					$funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );

				}
				$resp['success']           = true;
				$resp['msg']               = __( 'Offer Loaded', 'woofunnels-upstroke-one-click-upsell' );
				$resp['data']              = $offer;
				$resp['data']->funnel_data = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );
				$resp['data']->step_data   = wffn_rest_api_helpers()->get_step_post( $offer_id );

			}


			return rest_ensure_response( $resp );
		}

		public function get_offer_fields( $upsell_id, $offer_id ) {
			$jump_on_offer = $this->get_on_jump_offer( $upsell_id, $offer_id );

			$jump_on_offer           = wffn_rest_api_helpers()->array_change_key( $jump_on_offer, 'label', 'name' );
			$offer_settings_fields   = [];
			$offer_settings_fields[] = [
				'type'   => 'checklist',
				'key'    => 'subscription_discount',
				'label'  => __( 'Subscription Discount', 'funnel-builder-powerpack' ),
				'hint'   => '',
				'values' => [
					0 => [
						'value' => "true",
						'name'  => __( 'By default discount applies to first charge for subscription. Check this box if you want to apply discount for all future recurring payments.', 'funnel-builder-powerpack' ),
					],
				],
			];

			$offer_settings_fields[] = [
				'type'   => 'checklist',
				'key'    => 'subscription_signup_discount',
				'label'  => __( 'SignUp Fee Discount', 'funnel-builder-powerpack' ),
				'hint'   => '',
				'values' => [
					0 => [
						'value' => "true",
						'name'  => __( 'Check this box if you want to apply discount on sign up fees as well. By default discount applies to the regular price only.', 'funnel-builder-powerpack' ),
					],
				],
			];

			$offer_settings_fields[] = [
				'type'   => 'checklist',
				'key'    => 'is_override_free_trial',
				'label'  => __( 'Override Free Trial', 'funnel-builder-powerpack' ),
				'hint'   => '',
				'values' => [
					0 => [
						'value' => "true",
						'name'  => __( 'Check this box if you want to give free trial for this offer.', 'funnel-builder-powerpack' ),
					],
				],
			];

			$offer_settings_fields[] = [
				'type'    => 'number',
				'key'     => 'free_trial_length',
				'label'   => '',
				'hint'    => '',
				'toggler' => [
					'key'   => 'is_override_free_trial',
					'value' => "true",
				]
			];
			$offer_settings_fields[] = [
				'type'    => 'select',
				'key'     => 'free_trial_period',
				'values'  => array(
					array(
						'key'   => 'day',
						'value' => 'day',
						'label' => __( 'Day' )
					),
					array(
						'key'   => 'week',
						'value' => 'week',
						'label' => __( 'Week' )
					),
					array(
						'key'   => 'week',
						'value' => 'month',
						'label' => __( 'Month' )
					),
					array(
						'key'   => 'year',
						'value' => 'year',
						'label' => __( 'Year' )
					),
				),
				'label'   => '',
				'hint'    => '',
				'toggler' => [
					'key'   => 'is_override_free_trial',
					'value' => "true",
				]
			];

			if ( is_array( $jump_on_offer ) && count( $jump_on_offer ) > 1 ) {
				$offer_settings_fields[] = [
					'type'   => 'checklist',
					'key'    => 'jump_on_accepted',
					'label'  => __( 'Dynamic Offer Path', 'funnel-builder-powerpack' ),
					'hint'   => '',
					'values' => [
						0 => [
							'value' => "true",
							'name'  => __( 'On acceptance, redirect buyers to', 'funnel-builder-powerpack' ),
						],
					],
				];
				$offer_settings_fields[] = [
					'type'    => 'offer-select',
					'key'     => 'jump_to_offer_on_accepted',
					'label'   => '',
					'hint'    => '',
					'values'  => $jump_on_offer,
					'toggler' => [
						'key'   => 'jump_on_accepted',
						'value' => "true",
					]

				];
				$offer_settings_fields[] = [
					'type'   => 'checklist',
					'key'    => 'jump_on_rejected',
					'label'  => '',
					'hint'   => '',
					'values' => [
						0 => [
							'value' => "true",
							'name'  => __( 'On rejection, redirect buyers to', 'funnel-builder-powerpack' ),
						],
					],
				];
				$offer_settings_fields[] = [
					'type'    => 'offer-select',
					'key'     => 'jump_to_offer_on_rejected',
					'label'   => '',
					'hint'    => '',
					'values'  => $jump_on_offer,
					'toggler' => [
						'key'   => 'jump_on_rejected',
						'value' => "true",
					]

				];
			}

			$offer_settings_fields[] = [
				'type'   => 'checklist',
				'key'    => 'skip_exist',
				'label'  => __( 'Skip Offer', 'funnel-builder-powerpack' ),
				'hint'   => '',
				'values' => [
					0 => [
						'value' => "true",
						'name'  => __( 'Skip this offer if product(s) exist in parent order', 'funnel-builder-powerpack' ),
					],
				],
			];
			$offer_settings_fields[] = [
				'type'   => 'checklist',
				'key'    => 'skip_purchased',
				'label'  => '',
				'hint'   => '',
				'values' => [
					0 => [
						'value' => "true",
						'name'  => __( 'Skip this offer if buyer had ever purchased this product(s)', 'funnel-builder-powerpack' ),
					],
				],
			];

			$state = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );
			if ( in_array( $state, array( 0, 1, 2, 3, 6 ), true ) ) {
				$offer_settings_fields[] = [
					'type'    => 'custom-html',
					'key'     => 'rule_unavailable',
					'label'   => $this->rule_unavailable(),
					'hint'    => '',
					'toggler' => [
						'key'   => 'skip_purchased',
						'value' => "true",
					]
				];
			}

			$offer_settings_fields[] = [
				'type'   => 'checklist',
				'key'    => 'qty_selector',
				'label'  => __( 'Quantity Selector', 'funnel-builder-powerpack' ),
				'hint'   => '',
				'values' => [
					0 => [
						'value' => "true",
						'name'  => __( 'Allow buyer to choose the quantity while purchasing this upsell product(s)', 'funnel-builder-powerpack' ),
					],
				],
			];
			$offer_settings_fields[] = [
				'type'    => 'number',
				'key'     => 'qty_max',
				'label'   => __( 'Maximum Quantity', 'funnel-builder-powerpack' ),
				'hint'    => '',
				'toggler' => [
					'key'   => 'qty_selector',
					'value' => "true",
				]
			];

			return $offer_settings_fields;
		}

		// List Offers for Upsells.
		public function wfocu_list_offers( WP_REST_Request $request ) {
			$resp                   = array();
			$resp['success']        = false;
			$resp['msg']            = __( 'Failed', 'woofunnels-upstroke-one-click-upsell' );
			$resp['data']['offers'] = array();

			$step_id = $request->get_param( 'step_id' );

			if ( class_exists( 'WFOCU_Core' ) && absint( $step_id ) > 0 ) {
				$funnel_offers = WFOCU_Core()->funnels->get_funnel_steps( $step_id );
				$offers        = array();
				if ( is_array( $funnel_offers ) && ! empty( $funnel_offers ) ) {
					foreach ( $funnel_offers as $offer ) {
						$offer['state'] = wc_string_to_bool( $offer['state'] );
						$offer['id']    = ( string ) $offer['id'];
						$offer['url']   = ( ! empty( $offer['slug'] ) && ! empty( $offer['url'] ) ) ? str_replace( $offer['slug'] . '/', '', $offer['url'] ) : '';
						$offers[]       = $offer;
					}
				}

				$resp['success']        = true;
				$resp['msg']            = __( 'Offers Loaded', 'woofunnels-upstroke-one-click-upsell' );
				$resp['data']['offers'] = $offers;

			}

			return rest_ensure_response( $resp );
		}

		// Upsells Add Offer.
		public function wfocu_add_offer( WP_REST_Request $request ) {

			$resp           = array();
			$resp['msg']    = __( 'Unable to create offer', 'woofunnels-upstroke-one-click-upsell' );
			$resp['status'] = false;
			$resp['data']   = array();

			$posted_data  = $request->get_body();
			$upsell_id    = $request->get_param( 'step_id' );
			$posted_data  = $this->sanitize_custom( $posted_data );
			$step_type    = ! empty( $posted_data['step_type'] ) ? $posted_data['step_type'] : '';
			$duplicate_id = isset( $posted_data['duplicate_id'] ) ? $posted_data['duplicate_id'] : 0;
			$inherit_id   = isset( $posted_data['inherit_from'] ) ? $posted_data['inherit_from'] : 0;
			$title        = isset( $posted_data['title'] ) ? $posted_data['title'] : __( 'New Sub Step', 'funnel-builder-powerpack' );
			$builder      = isset( $request['builder'] ) ? $request['builder'] : '';
			$template     = isset( $request['template'] ) ? $request['template'] : '';
			$canvas_data  = isset( $request['canvas'] ) ? $request['canvas'] : '';

			if ( method_exists( 'WFFN_REST_API_Helpers', 'check_builder_status' ) ) {
				$builder_status = wffn_rest_api_helpers()->check_builder_status( $builder, $template );
				if ( false === $builder_status['status'] ) {
					return rest_ensure_response( $builder_status['data'] );
				}
			}

			if ( ! empty( $posted_data ) && absint( $upsell_id ) > 0 ) {  // Input var okay.

				if ( $inherit_id > 0 && '' !== $title ) {
					$new_offer_id = WFOCU_Core()->offers->duplicate_offer( $inherit_id, $title, $upsell_id );

					if ( empty( $builder ) ) {
						$offer_post = get_post( $inherit_id );
						if ( ! empty( $offer_post ) && ! empty( $offer_post->post_content ) ) {
							$update_offer_post               = get_post( $new_offer_id );
							$update_offer_post->post_content = $offer_post->post_content;
							wp_update_post( $update_offer_post );
						}
					}

				} else if ( $duplicate_id > 0 ) {
					$new_offer_id = WFOCU_Core()->offers->duplicate_offer( $duplicate_id, '', $upsell_id );

					if( empty( $builder )) {
						$offer_post = get_post( $duplicate_id );
						if ( ! empty( $offer_post ) && ! empty( $offer_post->post_content ) ) {
							$update_offer_post               = get_post( $new_offer_id );
							$update_offer_post->post_content = $offer_post->post_content;
							wp_update_post( $update_offer_post );
						}
					}
				} else {
					$upsell_id = wc_clean( $upsell_id );  // Input var okay.
					if ( isset( $step_type ) && '' !== $step_type ) {  // Input var okay.
						$offer_type = wc_clean( wp_unslash( $posted_data['step_type'] ) );  // Input var okay.
					} else {
						$offer_type = 'upsell';
					}
					$post_type = WFOCU_Common::get_offer_post_type_slug();
					$post      = array(
						'post_title'  => wc_clean( wp_unslash( $title ) ), // Input var okay.
						'post_type'   => $post_type,
						'post_status' => 'publish',
					);

					$new_offer_id = wp_insert_post( $post );
					if ( ! is_wp_error( $new_offer_id ) ) {

						$default_settings = array(
							'type'  => $offer_type,
							'url'   => get_the_permalink( $new_offer_id ),
							'slug'  => get_post( $new_offer_id )->post_name,
							'name'  => wc_clean( wp_unslash( $title ) ),
							'state' => 1,
							'id'    => $new_offer_id,
						);

						$steps = WFOCU_Core()->funnels->get_funnel_steps( $upsell_id, false );
						$steps = ! empty( $steps ) ? $steps : array();
						array_push( $steps, $default_settings );

						update_post_meta( $new_offer_id, '_funnel_id', $upsell_id );
						update_post_meta( $new_offer_id, '_offer_type', $offer_type );

						update_post_meta( $upsell_id, '_funnel_steps', $steps );

						/**save default offer setting*/
						$offer_settings                 = new stdClass();
						$offer_settings->products       = new stdClass();
						$offer_settings->fields         = new stdClass();
						$offer_settings->template       = $template;
						$offer_settings->template_group = $builder;
						$offer_settings->settings       = new stdClass();


						if ( ! empty( $offer_settings->template ) && ! empty( $offer_settings->template_group ) ) {
							/**
							 * Import offer design
							 */
							$import_status = $this->import_offer( $new_offer_id, $offer_settings );

							if ( isset( $import_status['success'] ) && false === $import_status['success'] ) {
								$import_status['data'] = array();

								if ( empty( $canvas_data ) ) {
									return rest_ensure_response( $import_status );
								}
							}
						} else {
							update_post_meta( $new_offer_id, '_wfocu_setting', $offer_settings );
						}

						update_post_meta( $upsell_id, '_wfocu_is_rules_saved', 'yes' );
						WFOCU_Common::update_funnel_time( $upsell_id );
					}
				}
				$steps_current = WFOCU_Core()->funnels->get_funnel_steps( $upsell_id, false );
				array_pop( $steps_current );
				/**
				 * reposition the steps array for the canvas mode
				 */
				$this->handle_canvas_insertion( $canvas_data, $new_offer_id, $upsell_id, $steps_current );

				$all_steps       = WFOCU_Core()->funnels->get_funnel_steps( $upsell_id, false );
				$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $all_steps );
				WFOCU_Common::update_funnel_upsell_downsell( $upsell_id, $upsell_downsell );

				if ( ! empty( $new_offer_id ) && absint( $new_offer_id ) > 0 ) {
					// TO DO : Move to respective Class later

					if ( ! empty( $canvas_data ) ) {
						$get_object                               = WFFN_Core()->steps->get_integration_object( 'wc_upsells' );
						$upsell_step                              = $get_object->populate_data_properties( array( 'type' => 'wc_upsells', 'id' => $upsell_id ), 0 );
						$prepare_data                             = [];
						$prepare_data['steps_list']               = [];
						$prepare_data['groups']                   = [];
						$prepare_data['groups'][0]                = [ 'type' => 'wc_upsells', 'id' => $upsell_id, 'substeps' => [] ];
						$prepare_data['steps_list'][ $upsell_id ] = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $upsell_step );
						if ( isset( $upsell_step['substeps']['offer'] ) ) {

							foreach ( $upsell_step['substeps']['offer'] as $key => $offer ) {
								$prepare_data['groups'][0]['substeps'][] = [
									'id'   => $offer['id'],
									'type' => 'offer'
								];
								$offer['type']                           = 'offer';

								$prepare_data['steps_list'][ $offer['id'] ] = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $offer );
								$offer_settings                             = get_post_meta( $offer['id'], '_wfocu_setting', true );
								/**
								 * get offer accept and reject id
								 */
								if ( ! empty( $offer_settings ) && is_object( $offer_settings ) ) {
									if ( empty( $offer_settings->settings ) ) {
										$offer_settings->settings = (object) $offer_settings->settings;
									}

									$accepted_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_accepted ) && isset( $offer_settings->settings->jump_to_offer_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) ? $offer_settings->settings->jump_to_offer_on_accepted : 'automatic';
									$rejected_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_rejected ) && isset( $offer_settings->settings->jump_to_offer_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) ? $offer_settings->settings->jump_to_offer_on_rejected : 'automatic';

								} else {
									$accepted_id = 'automatic';
									$rejected_id = 'automatic';

								}

								/**
								 * handle case when offer flow next offer
								 * return next offer id for handle ui nodes
								 */
								if ( ( 'automatic' === $accepted_id || 'automatic' === $rejected_id ) && isset( $upsell_step['substeps']['offer'][ $key + 1 ] ) ) {
									$accepted_id = ( 'automatic' === $accepted_id ) ? absint( $upsell_step['substeps']['offer'][ $key + 1 ]['id'] ) : absint( $accepted_id );
									$rejected_id = ( 'automatic' === $rejected_id ) ? absint( $upsell_step['substeps']['offer'][ $key + 1 ]['id'] ) : absint( $rejected_id );
								}

								if ( 'terminate' === $accepted_id || 'automatic' === $accepted_id ) {
									$accepted_id = 0;
								}
								if ( 'terminate' === $rejected_id || 'automatic' === $rejected_id ) {
									$rejected_id = 0;
								}

								$offer_data           = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $offer );
								$offer_data['accept'] = $accepted_id;
								$offer_data['reject'] = $rejected_id;


								$prepare_data['steps_list'][ $offer['id'] ] = $offer_data;

							}
						}

						$prepare_data['steps_list'] = wffn_rest_api_helpers()->add_step_edit_details( $prepare_data['steps_list'] );
						$prepare_data['steps_list'] = apply_filters( 'wffn_rest_get_funnel_steps', $prepare_data['steps_list'], false );
						$offer                      = $prepare_data;
					} else {
						$tags                   = WFFN_Step_WC_Upsells::get_instance()->get_substep_entity_tags( $new_offer_id );
						$offer                  = [];
						$offer['id']            = $new_offer_id;
						$offer['tags']          = $tags;
						$offer['supports']      = [];
						$offer['_data']         = new stdClass();
						$offer['_data']->title  = WFFN_Step_WC_Upsells::get_instance()->get_entity_title( $new_offer_id );
						$offer['_data']->edit   = WFFN_Step_WC_Upsells::get_instance()->get_entity_edit_link( $upsell_id );
						$offer['_data']->view   = WFFN_Step_WC_Upsells::get_instance()->get_entity_view_link( $new_offer_id );
						$offer['_data']->status = WFFN_Step_WC_Upsells::get_instance()->get_entity_status( $new_offer_id );
						$offer['substeps']      = [ 'offer' => array( $offer ) ];

					}
					$resp['data']   = $offer;
					$resp['status'] = true;
					$resp['msg']    = __( 'Offer Added Successfully', 'woofunnels-upstroke-one-click-upsell' );
				}
			}

			return rest_ensure_response( $resp );

		}


		public function handle_canvas_insertion( $canvas_data, $new_offer_id, $upsell_id, $current_steps ) {
			if ( empty( $canvas_data ) ) {
				// No canvas data, nothing to do
				return;
			}

			$steps = get_post_meta( $upsell_id, '_funnel_steps', true );
			if ( empty( $steps ) ) {
				// No steps to reposition
				return;
			}

			/**
			 * Lets reposition the newly added step into the list so that it would occur on the best position
			 */
			if ( $canvas_data['empty'] !== true ) {
				if ( ! empty( $canvas_data['offer_id'] ) ) {
					$insert_position = array_search( absint( $canvas_data['offer_id'] ), array_map( 'intval', wp_list_pluck( $steps, 'id' ) ), true );

					$lastElement = array_pop( $steps );

					if ( $insert_position !== false && ( $canvas_data['position'] === 'accept' || $canvas_data['position'] === 'reject' || $canvas_data['position'] !== 'end' ) ) {
						$insert_index = ( $canvas_data['position'] === 'accept' || $canvas_data['position'] === 'reject' ) ? $insert_position + 1 : $insert_position;


						array_splice( $steps, $insert_index, 0, [ $lastElement ] );
						update_post_meta( $upsell_id, '_funnel_steps', $steps );
					}
					$offer_settings = WFOCU_Core()->offers->get_offer( $canvas_data['offer_id'], false );

				}

				/**
				 * fetch settings for both offers the new and the targeted one
				 */
				$offer_settings_new_offer = WFOCU_Core()->offers->get_offer( $new_offer_id, false );
				if ( empty( $offer_settings_new_offer ) || ! is_object( $offer_settings_new_offer ) ) {
					$offer_settings_new_offer           = new stdClass();
					$offer_settings_new_offer->settings = new stdClass();
				}
				/**
				 * get offer accept and reject id
				 */
				if ( isset( $offer_settings ) && ! empty( $offer_settings ) && is_object( $offer_settings ) ) {
					if ( empty( $offer_settings->settings ) ) {
						$offer_settings->settings = (object) $offer_settings->settings;
					}

					$accepted_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_accepted ) && isset( $offer_settings->settings->jump_to_offer_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) ? $offer_settings->settings->jump_to_offer_on_accepted : 'automatic';
					$rejected_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_rejected ) && isset( $offer_settings->settings->jump_to_offer_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) ? $offer_settings->settings->jump_to_offer_on_rejected : 'automatic';

				} else {
					$accepted_id = 'automatic';
					$rejected_id = 'automatic';

				}

				/**
				 * handle case when offer flow next offer
				 * return next offer id for handle ui nodes
				 */
				if ( ( 'automatic' === $accepted_id || 'automatic' === $rejected_id ) && isset( $insert_position ) && isset( $current_steps[ $insert_position + 1 ] ) ) {
					$accepted_id = ( 'automatic' === $accepted_id ) ? absint( $current_steps[ $insert_position + 1 ]['id'] ) : absint( $accepted_id );
					$rejected_id = ( 'automatic' === $rejected_id ) ? absint( $current_steps[ $insert_position + 1 ]['id'] ) : absint( $rejected_id );
				}
				if ( ( 'terminate' === $accepted_id || 'automatic' === $accepted_id ) ) {
					$accepted_id = 0;
				}
				if ( ( 'terminate' === $rejected_id || 'automatic' === $rejected_id ) ) {
					$rejected_id = 0;
				}


				if ( $canvas_data['position'] === 'accept' ) {

					/**
					 * If we get the request to add on 'accept'
					 * 1. we set target offer accept settings point to new offer
					 * 2. we set target offer reject settings point to the existing reject ID
					 * 3. we set new offer accept/reject settings point to the existing accept/reject ID
					 */
					$offer_settings->settings->{"jump_to_offer_on_accepted"} = absint( $new_offer_id );
					$offer_settings->settings->{"jump_on_accepted"}          = true;


					$offer_settings->settings->{"jump_to_offer_on_rejected"} = 0 === $rejected_id ? 'terminate' : absint( $rejected_id );
					$offer_settings->settings->{"jump_on_rejected"}          = true;

					update_post_meta( $canvas_data['offer_id'], '_wfocu_setting', $offer_settings );

					$offer_settings_new_offer->settings->{"jump_on_accepted"}          = true;
					$offer_settings_new_offer->settings->{"jump_on_rejected"}          = true;
					$offer_settings_new_offer->settings->{"jump_to_offer_on_accepted"} = 0 === $accepted_id ? 'terminate' : absint( $accepted_id );
					$offer_settings_new_offer->settings->{"jump_to_offer_on_rejected"} = 0 === $accepted_id ? 'terminate' : absint( $accepted_id );


					update_post_meta( $canvas_data['offer_id'], '_wfocu_setting', $offer_settings );
					update_post_meta( $new_offer_id, '_wfocu_setting', $offer_settings_new_offer );

				} elseif ( $canvas_data['position'] === 'reject' ) {


					/**
					 * If we get the request to add on 'reject'
					 * 1. we set target offer reject settings point to new offer
					 * 2. we set target offer accept settings point to the existing accept ID
					 * 3. we set new offer accept/reject settings point to the existing accept/reject ID
					 */
					$offer_settings->settings->{"jump_to_offer_on_rejected"} = absint( $new_offer_id );
					$offer_settings->settings->{"jump_on_rejected"}          = true;

					$offer_settings->settings->{"jump_to_offer_on_accepted"} = 0 === $accepted_id ? 'terminate' : absint( $accepted_id );
					$offer_settings->settings->{"jump_on_accepted"}          = true;


					$offer_settings_new_offer->settings->{"jump_on_accepted"}          = true;
					$offer_settings_new_offer->settings->{"jump_on_rejected"}          = true;
					$offer_settings_new_offer->settings->{"jump_to_offer_on_accepted"} = 0 === $rejected_id ? 'terminate' : absint( $rejected_id );
					$offer_settings_new_offer->settings->{"jump_to_offer_on_rejected"} = 0 === $rejected_id ? 'terminate' : absint( $rejected_id );

					update_post_meta( $canvas_data['offer_id'], '_wfocu_setting', $offer_settings );
					update_post_meta( $new_offer_id, '_wfocu_setting', $offer_settings_new_offer );


				} elseif ( $canvas_data['position'] === 'before' ) {


					foreach ( $current_steps as $key => $step ) {
						$offer_settings = get_post_meta( $step['id'], '_wfocu_setting', true );

						/**
						 * get offer accept and reject id
						 */
						if ( ! empty( $offer_settings ) && is_object( $offer_settings ) ) {
							if ( empty( $offer_settings->settings ) ) {
								$offer_settings->settings = (object) $offer_settings->settings;
							}

							$accepted_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_accepted ) && isset( $offer_settings->settings->jump_to_offer_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) ? $offer_settings->settings->jump_to_offer_on_accepted : 'automatic';
							$rejected_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_rejected ) && isset( $offer_settings->settings->jump_to_offer_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) ? $offer_settings->settings->jump_to_offer_on_rejected : 'automatic';

						} else {
							$accepted_id = 'automatic';
							$rejected_id = 'automatic';

						}

						/**
						 * handle case when offer flow next offer
						 * return next offer id for handle ui nodes
						 */
						if ( ( 'automatic' === $accepted_id || 'automatic' === $rejected_id ) && isset( $current_steps[ $key + 1 ] ) ) {
							$accepted_id = ( 'automatic' === $accepted_id ) ? absint( $current_steps[ $key + 1 ]['id'] ) : absint( $accepted_id );
							$rejected_id = ( 'automatic' === $rejected_id ) ? absint( $current_steps[ $key + 1 ]['id'] ) : absint( $rejected_id );
						}


						/**
						 * if we found any termination or automatic node then we need to skip that node
						 */
						if ( ( 'terminate' === $accepted_id || 'automatic' === $accepted_id ) && ( 'terminate' === $rejected_id || 'automatic' === $rejected_id ) ) {
							continue;
						}
						if ( absint( $accepted_id ) === absint( $canvas_data['offer_id'] ) ) {
							$offer_settings->settings->jump_to_offer_on_accepted = $new_offer_id;
							$offer_settings->settings->jump_on_accepted          = true;
							update_post_meta( $step['id'], '_wfocu_setting', $offer_settings );
						}

						if ( absint( $rejected_id ) === absint( $canvas_data['offer_id'] ) ) {
							$offer_settings->settings->jump_to_offer_on_rejected = $new_offer_id;
							$offer_settings->settings->jump_on_rejected          = true;
							update_post_meta( $step['id'], '_wfocu_setting', $offer_settings );
						}
					}
				} elseif ( $canvas_data['position'] === 'end' ) {

					foreach ( $current_steps as $key => $step ) {
						$offer_settings = get_post_meta( $step['id'], '_wfocu_setting', true );

						/**
						 * get offer accept and reject id
						 */
						if ( ! empty( $offer_settings ) && is_object( $offer_settings ) ) {
							if ( empty( $offer_settings->settings ) ) {
								$offer_settings->settings = (object) $offer_settings->settings;
							}

							$accepted_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_accepted ) && isset( $offer_settings->settings->jump_to_offer_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) ? $offer_settings->settings->jump_to_offer_on_accepted : 'automatic';
							$rejected_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_rejected ) && isset( $offer_settings->settings->jump_to_offer_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) ? $offer_settings->settings->jump_to_offer_on_rejected : 'automatic';

						} else {
							$accepted_id = 'automatic';
							$rejected_id = 'automatic';

						}

						/**
						 * handle case when offer flow next offer
						 * return next offer id for handle ui nodes
						 */
						if ( ( 'automatic' === $accepted_id || 'automatic' === $rejected_id ) && isset( $current_steps[ $key + 1 ] ) ) {
							$accepted_id = ( 'automatic' === $accepted_id ) ? absint( $current_steps[ $key + 1 ]['id'] ) : absint( $accepted_id );
							$rejected_id = ( 'automatic' === $rejected_id ) ? absint( $current_steps[ $key + 1 ]['id'] ) : absint( $rejected_id );
						}


						if ( 'terminate' === $accepted_id || 'automatic' === $accepted_id ) {
							$offer_settings->settings->jump_to_offer_on_accepted = $new_offer_id;
							$offer_settings->settings->jump_on_accepted          = true;
							update_post_meta( $step['id'], '_wfocu_setting', $offer_settings );
						}

						if ( 'terminate' === $rejected_id || 'automatic' === $rejected_id ) {
							$offer_settings->settings->jump_to_offer_on_rejected = $new_offer_id;
							$offer_settings->settings->jump_on_rejected          = true;
							update_post_meta( $step['id'], '_wfocu_setting', $offer_settings );
						}
					}
				}
			}

		}

		public function import_offer( $offer_id, $offer_settings ) {
			$resp = array();

			$response = WFOCU_Core()->importer->maybe_import_data( wffn_clean( $offer_settings->template_group ), wffn_clean( $offer_settings->template ), $offer_id, $offer_settings );
			if ( is_string( $response ) ) {
				$resp['success'] = false;
				$resp['msg']     = $response;

				return $resp;
			}
			$resp['success'] = true;
			update_post_meta( $offer_id, '_wfocu_setting', $offer_settings );

			return $resp;
		}

		public function maybe_filter_fields( $options, $offer, $offer_id ) {
			$offer        = WFOCU_Core()->offers->build_offer_product( $offer, $offer_id );
			$keys_to_show = [];
			foreach ( $options as $k ) {
				if ( in_array( $k['key'], [
						'subscription_discount',
						'subscription_signup_discount',
						'is_override_free_trial',
						'free_trial_length',
						'free_trial_period'
					], true ) && isset( $offer->products ) ) {

					if ( class_exists( 'WC_Subscriptions' ) && true === UpStroke_Subscriptions::get_instance()->offer_contains_subscription( $offer->products ) ) {
						$keys_to_show[] = $k['key'];

					}

				} else {
					$keys_to_show[] = $k['key'];
				}

			}

			return $keys_to_show;
		}

		public function update_product_schema( $posted_product ) {

			$product            = wc_get_product( $posted_product['key'] );
			$product_variations = [];
			if ( $product instanceof WC_Product ) {
				$default_variation = '';
				$check_variant     = [];

				if ( $product->is_type( 'variable' ) ) {
					$available_variations = $product->get_children();
					if ( ! empty( $available_variations ) ) {
						$product_variations = $this->get_formatted_variations( $available_variations, [] );

						if ( ! empty( $product_variations ) ) {
							$default_variation = $product_variations[0]['id'];
							$check_variant     = (array) $default_variation;
						}
					}
				}

				if ( is_a( $product, 'WC_Product_Variation' ) ) {
					$variation_name = wffn_rest_api_helpers()->get_name_part( $product->get_name(), 1 );
				}

				$product_availability = wffn_rest_api_helpers()->get_availability_price_text( $product->get_id() );
				$product_stock        = $product_availability['text'];
				$stock_status         = ( $product->is_in_stock() ) ? true : false;

				$product_image   = ! empty( wp_get_attachment_thumb_url( $product->get_image_id() ) ) ? wp_get_attachment_thumb_url( $product->get_image_id() ) : WFFN_PLUGIN_URL . '/admin/assets/img/product_default_icon.jpg';
				$regular_price   = ! empty( $product->get_regular_price() ) ? $product->get_regular_price() : 0;
				$sale_price      = ! empty( $product->get_sale_price() ) ? $product->get_sale_price() : 0;
				$product_details = [
					'key'                  => $posted_product['key'],
					'id'                   => $posted_product['id'],
					'title'                => wffn_rest_api_helpers()->get_name_part( $product->get_title(), 0 ),
					'regular_price'        => $regular_price,
					'sale_price'           => $sale_price,
					'is_on_sale'           => $product->is_on_sale(),
					'currency_symbol'      => get_woocommerce_currency_symbol(),
					'product_type'         => $product->get_type(),
					'product_attribute'    => ! empty( $variation_name ) ? $variation_name : "-",
					'product_image'        => $product_image,
					'product_stock_status' => $stock_status,
					'product_stock'        => $product_stock,
					'variations'           => $product_variations,
					'checkVarient'         => $check_variant,
					'radioVarient'         => $default_variation,
					'discount_amount'      => 0,
					'discount_type'        => 'percentage_on_reg',
					'quantity'             => 1
				];

				return $product_details;
			}

			return $posted_product;
		}

		public function save_upsell_order( $step_id, $order ) {
			$return       = false;
			$update_steps = array();
			if ( is_array( $order ) && ! empty( $order ) ) {
				$steps = WFOCU_Core()->funnels->get_funnel_steps( $step_id );
				$steps = ! empty( $steps ) ? $steps : array();
				// Convert array to keys based on ID
				$steps = array_column( $steps, null, 'id' );
				foreach ( $order as $key ) {
					if ( ! empty( $steps[ $key ] ) ) {
						$update_steps[] = $steps[ $key ];
					}
				}

				if ( ! empty( $update_steps ) ) {


					$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $update_steps );

					/* Validating upsell downsell if offer to jump is above the current offer */
					$available_offer_ids = array_map( 'absint', wp_list_pluck( $update_steps, 'id' ) );
					foreach ( $upsell_downsell as $offer_id => $move_path ) {
						$accepted       = $move_path['y'];
						$rejected       = $move_path['n'];
						$need_update    = false;
						$offer_settings = WFOCU_Core()->offers->get_offer( $offer_id, false );

						if ( isset( $offer_settings->settings->jump_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) {
							if ( array_search( absint( $accepted ), $available_offer_ids, true ) < array_search( absint( $offer_id ), $available_offer_ids, true ) ) {
								$offer_settings->settings->jump_to_offer_on_accepted = 'automatic';
								$need_update                                         = true;
							}
						}

						if ( isset( $offer_settings->settings->jump_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) {
							if ( array_search( absint( $rejected ), $available_offer_ids, true ) < array_search( absint( $offer_id ), $available_offer_ids, true ) ) {
								$offer_settings->settings->jump_to_offer_on_rejected = 'automatic';
								$need_update                                         = true;
							}
						}
						if ( true === $need_update ) {
							WFOCU_Common::update_offer( $offer_id, $offer_settings );
						}
					}
					$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $update_steps );

					WFOCU_Common::update_funnel_steps( $step_id, $update_steps );
					WFOCU_Common::update_funnel_upsell_downsell( $step_id, $upsell_downsell );

					WFOCU_Common::update_funnel_time( $step_id );
				}
			}

			return $return;
		}

		public function update_dynamic_path( $request ) {
			$posted_data = $request->get_body();
			$upsell_id   = $request->get_param( 'step_id' );
			$posted_data = $this->sanitize_custom( $posted_data );

			if ( empty( $posted_data['path'] ) ) {
				return rest_ensure_response( array(
					'success' => false,
					'message' => __( 'No path found', 'woofunnels-upstroke-one-click-upsell' ),
				) );

			}

			$offer_id  = $request->get_param( 'offer_id' );
			$is_delete = $request->get_param( 'delete' );
			foreach ( $posted_data['path'] as $key => $value ) {

				$offer_settings = WFOCU_Core()->offers->get_offer( $key, false );
				if ( empty( $offer_settings ) || ! is_object( $offer_settings ) ) {
					$offer_settings           = new stdClass();
					$offer_settings->settings = new stdClass();
				}
				if ( is_array( $offer_settings->settings ) ) {
					$offer_settings->settings = ( object) $offer_settings->settings;
				}

				$offer_settings->settings->{"jump_on_accepted"}          = true;
				$offer_settings->settings->{"jump_on_rejected"}          = true;
				$offer_settings->settings->{"jump_to_offer_on_accepted"} = absint( $value['accept'] ) === 0 ? 'terminate' : absint( $value['accept'] );
				$offer_settings->settings->{"jump_to_offer_on_rejected"} = absint( $value['reject'] ) === 0 ? 'terminate' : absint( $value['reject'] );

				update_post_meta( $key, '_wfocu_setting', $offer_settings );


			}
			$steps = WFOCU_Core()->funnels->get_funnel_steps( $upsell_id );

			if ( absint( $offer_id ) > 0 && ! empty( $is_delete ) ) {
				$offer_id = absint( $offer_id );
				wp_delete_post( $offer_id, true );
				WFOCU_Common::update_funnel_time( $upsell_id );
				$offers = [];
				if ( count( $steps ) ) {
					foreach ( $steps as $step ) {
						if ( absint( $offer_id ) !== absint( $step['id'] ) ) {
							$offers[] = $step;
						}
					}
					if ( isset( $offers ) ) {
						// Update Funnel Step Offers.
						update_post_meta( $upsell_id, '_funnel_steps', $offers );
						$steps = $offers;
					}

				}

			}
			$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $steps );
			WFOCU_Common::update_funnel_upsell_downsell( $upsell_id, $upsell_downsell );


			//prepare return data
			$get_object                               = WFFN_Core()->steps->get_integration_object( 'wc_upsells' );
			$upsell_step                              = $get_object->populate_data_properties( array( 'type' => 'wc_upsells', 'id' => $upsell_id ), 0 );
			$prepare_data                             = [];
			$prepare_data['steps_list']               = [];
			$prepare_data['groups']                   = [];
			$prepare_data['groups'][0]                = [ 'type' => 'wc_upsells', 'id' => $upsell_id, 'substeps' => [] ];
			$prepare_data['steps_list'][ $upsell_id ] = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $upsell_step );
			if ( isset( $upsell_step['substeps']['offer'] ) ) {

				foreach ( $upsell_step['substeps']['offer'] as $key => $offer ) {
					$prepare_data['groups'][0]['substeps'][] = [
						'id'   => $offer['id'],
						'type' => 'offer'
					];
					$offer['type']                           = 'offer';

					$prepare_data['steps_list'][ $offer['id'] ] = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $offer );
					$offer_settings                             = get_post_meta( $offer['id'], '_wfocu_setting', true );
					/**
					 * get offer accept and reject id
					 */
					if ( ! empty( $offer_settings ) && is_object( $offer_settings ) ) {
						if ( empty( $offer_settings->settings ) ) {
							$offer_settings->settings = (object) $offer_settings->settings;
						}

						$accepted_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_accepted ) && isset( $offer_settings->settings->jump_to_offer_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) ? $offer_settings->settings->jump_to_offer_on_accepted : 'automatic';
						$rejected_id = ( isset( $offer_settings->settings ) && isset( $offer_settings->settings->jump_on_rejected ) && isset( $offer_settings->settings->jump_to_offer_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) ? $offer_settings->settings->jump_to_offer_on_rejected : 'automatic';

					} else {
						$accepted_id = 'automatic';
						$rejected_id = 'automatic';

					}

					/**
					 * handle case when offer flow next offer
					 * return next offer id for handle ui nodes
					 */
					if ( ( 'automatic' === $accepted_id || 'automatic' === $rejected_id ) && isset( $upsell_step['substeps']['offer'][ $key + 1 ] ) ) {
						$accepted_id = ( 'automatic' === $accepted_id ) ? absint( $upsell_step['substeps']['offer'][ $key + 1 ]['id'] ) : absint( $accepted_id );
						$rejected_id = ( 'automatic' === $rejected_id ) ? absint( $upsell_step['substeps']['offer'][ $key + 1 ]['id'] ) : absint( $rejected_id );
					}

					if ( 'terminate' === $accepted_id || 'automatic' === $accepted_id ) {
						$accepted_id = 0;
					}
					if ( 'terminate' === $rejected_id || 'automatic' === $rejected_id ) {
						$rejected_id = 0;
					}

					$offer_data           = WFFN_REST_Funnel_Canvas::get_instance()->map_list_step( $offer );
					$offer_data['accept'] = $accepted_id;
					$offer_data['reject'] = $rejected_id;


					$prepare_data['steps_list'][ $offer['id'] ] = $offer_data;

				}
			}

			$prepare_data['steps_list'] = wffn_rest_api_helpers()->add_step_edit_details( $prepare_data['steps_list'] );
			$prepare_data['steps_list'] = apply_filters( 'wffn_rest_get_funnel_steps', $prepare_data['steps_list'], false );
			$offer                      = $prepare_data;

			return rest_ensure_response( array(
				'success' => true,
				'data'    => $offer,
			) );

		}

	}

	WFFN_REST_UPSELL_API_EndPoint::get_instance();
}