<?php
if ( ! class_exists( 'WFOCU_AJAX_Controller' ) ) {
	/**
	 * Class WFOCU_AJAX_Controller
	 * Handles All the request came from front end or the backend
	 */
	class WFOCU_AJAX_Controller {

		const CHARGE_ACTION = 'wfocu_front_charge';
		const SHIPPING_CALCULATION_ACTION = 'wfocu_front_calculate_shipping';

		public static function init() {


			add_action( 'init', array( __CLASS__, 'maybe_set_error_reporting_false' ) );
			/**
			 * Front End AJAX actions
			 */
			add_action( 'wp_ajax_wfocu_front_charge', array( __CLASS__, 'handle_charge' ) );
			add_action( 'wp_ajax_nopriv_wfocu_front_charge', array( __CLASS__, 'handle_charge' ) );

			add_action( 'wc_ajax_wfocu_front_charge', array( __CLASS__, 'handle_charge' ) );

			add_action( 'wp_ajax_wfocu_front_offer_skipped', array( __CLASS__, 'handle_offer_skipped' ) );
			add_action( 'wp_ajax_nopriv_wfocu_front_offer_skipped', array( __CLASS__, 'handle_offer_skipped' ) );

			add_action( 'wc_ajax_wfocu_front_offer_skipped', array( __CLASS__, 'handle_offer_skipped' ) );

			add_action( 'wp_ajax_wfocu_front_calculate_shipping', array( __CLASS__, 'calculate_shipping' ) );
			add_action( 'wp_ajax_nopriv_wfocu_front_calculate_shipping', array( __CLASS__, 'calculate_shipping' ) );

			add_action( 'wc_ajax_wfocu_front_calculate_shipping', array( __CLASS__, 'calculate_shipping' ) );

			add_action( 'wp_ajax_wfocu_front_register_views', array( __CLASS__, 'register_views' ) );
			add_action( 'wp_ajax_nopriv_wfocu_front_register_views', array( __CLASS__, 'register_views' ) );

			add_action( 'wc_ajax_wfocu_front_register_views', array( __CLASS__, 'register_views' ) );

			add_action( 'wp_ajax_wfocu_front_offer_expired', array( __CLASS__, 'offer_expired' ) );
			add_action( 'wp_ajax_nopriv_wfocu_front_offer_expired', array( __CLASS__, 'offer_expired' ) );

			add_action( 'wc_ajax_wfocu_front_offer_expired', array( __CLASS__, 'offer_expired' ) );

			add_action( 'wp_ajax_wfocu_front_catch_error', array( __CLASS__, 'catch_error' ) );
			add_action( 'wp_ajax_nopriv_wfocu_front_catch_error', array( __CLASS__, 'catch_error' ) );
			add_action( 'wc_ajax_wfocu_fire_conv_api_event', array( __CLASS__, 'fire_conv_api_events' ) );

			add_filter( 'bwf_global_save_settings_upstroke', array( __CLASS__, 'update_global_settings_fields' ) );

			/**
			 * Backend AJAX actions
			 */
			if ( is_admin() ) {
				self::handle_admin_ajax();
			}

		}

		public static function handle_admin_ajax() {

			add_action( 'wp_ajax_wfocu_add_new_funnel', array( __CLASS__, 'add_funnel' ) );

			add_action( 'wp_ajax_wfocu_add_offer', array( __CLASS__, 'add_offer' ) );

			add_action( 'wp_ajax_wfocu_add_product', array( __CLASS__, 'add_product' ) );

			add_action( 'wp_ajax_wfocu_remove_product', array( __CLASS__, 'remove_product' ) );

			add_action( 'wp_ajax_wfocu_save_funnel_steps', array( __CLASS__, 'save_funnel_steps' ) );

			add_action( 'wp_ajax_wfocu_save_funnel_offer_products', array( __CLASS__, 'save_funnel_offer_products' ) );

			add_action( 'wp_ajax_wfocu_save_funnel_offer_settings', array( __CLASS__, 'save_funnel_offer_settings' ) );

			add_action( 'wp_ajax_wfocu_product_search', array( __CLASS__, 'product_search' ) );

			add_action( 'wp_ajax_wfocu_page_search', array( __CLASS__, 'page_search' ) );

			add_action( 'wp_ajax_wfocu_update_offer', array( __CLASS__, 'update_offer' ) );

			add_action( 'wp_ajax_wfocu_update_funnel', array( __CLASS__, 'update_funnel' ) );

			add_action( 'wp_ajax_wfocu_remove_offer_from_funnel', array( __CLASS__, 'removed_offer_from_funnel' ) );

			add_action( 'wp_ajax_wfocu_get_custom_page', array( __CLASS__, 'get_custom_page' ) );

			add_action( 'wp_ajax_wfocu_save_rules_settings', array( __CLASS__, 'update_rules' ) );

			add_action( 'wp_ajax_wfocu_update_template', array( __CLASS__, 'update_template' ) );

			add_action( 'wp_ajax_wfocu_save_funnel_settings', array( __CLASS__, 'save_funnel_settings' ) );

			add_action( 'wp_ajax_wfocu_save_global_settings', array( __CLASS__, 'save_global_settings' ) );

			add_action( 'wp_ajax_wfocu_preview_details', array( __CLASS__, 'preview_details' ) );

			add_action( 'wp_ajax_wfocu_duplicate_funnel', array( __CLASS__, 'duplicate_funnel' ) );

			add_action( 'wp_ajax_wfocu_toggle_funnel_state', array( __CLASS__, 'toggle_funnel_state' ) );
			add_action( 'wp_ajax_wfocu_save_template', array( __CLASS__, 'save_template' ) );
			add_action( 'wp_ajax_wfocu_apply_template', array( __CLASS__, 'apply_template' ) );
			add_action( 'wp_ajax_wfocu_delete_template', array( __CLASS__, 'delete_template' ) );

			add_action( 'wp_ajax_wfocu_admin_refund_offer', array( __CLASS__, 'refund_offer' ) );
			add_action( 'wp_ajax_wfocu_clear_template', array( __CLASS__, 'clear_template' ) );
			add_action( 'wp_ajax_wfocu_activate_plugins', array( __CLASS__, 'activate_plugins' ) );
			add_action( 'wp_ajax_wfocu_make_wpml_duplicate', array( __CLASS__, 'make_wpml_duplicate' ) );
			add_action( 'wp_ajax_wfocu_get_wpml_edit_url', array( __CLASS__, 'get_wpml_edit_url' ) );
			add_action( 'wp_ajax_wfocu_update_edit_url', array( __CLASS__, 'update_edit_url' ) );
			add_action( 'wp_ajax_wfocu_normalize_order_from_wc_list', array( __CLASS__, 'normalize_order_from_wc_list' ) );
		}

		public static function handle_offer_skipped() {
			check_ajax_referer( 'wfocu_front_offer_skipped', 'nonce' );
			$data                 = array();
			$data_posted          = isset( $_POST['data'] ) ? wc_clean( $_POST['data'] ) : [];
			$get_type_of_offer    = $data_posted['offer_type'];
			$get_type_index_offer = $data_posted['offer_type_index'];
			$get_current_offer    = WFOCU_Core()->data->get_current_offer();
			$get_order            = WFOCU_Core()->data->get_current_order();
			$args                 = array(
				'order_id'         => WFOCU_WC_Compatibility::get_order_id( $get_order ),
				'funnel_id'        => WFOCU_Core()->data->get_funnel_id(),
				'offer_id'         => $get_current_offer,
				'funnel_unique_id' => WFOCU_Core()->data->get_funnel_key(),
				'offer_type'       => $get_type_of_offer,
				'offer_index'      => $get_type_index_offer,
				'email'            => WFOCU_Core()->data->get( 'useremail' ),
			);
			WFOCU_Core()->data->set( '_offer_result', false );
			do_action( 'wfocu_offer_rejected_event', $args );
			WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': UpSell Reject For Offer: ' . $get_current_offer );

			$get_offer = WFOCU_Core()->offers->get_the_next_offer( 'no' );

			$data['redirect_url'] = WFOCU_Core()->public->get_the_upsell_url( $get_offer );

			WFOCU_Core()->data->set( 'current_offer', $get_offer );
			WFOCU_Core()->data->save();

			$response_ajax = array(
				'success' => 'true',
			);

			if ( isset( $data ) ) {
				$response_ajax['data'] = $data;
			}
			wp_send_json( $response_ajax );
		}


		public static function offer_expired() {

			check_ajax_referer( 'wfocu_front_offer_expired', 'nonce' );
			$data = array();

			$get_current_offer = WFOCU_Core()->data->get_current_offer();

			$get_type_of_offer    = WFOCU_Core()->data->get( '_current_offer_type' );
			$get_type_index_offer = WFOCU_Core()->data->get( '_current_offer_type_index' );

			$get_order = WFOCU_Core()->data->get_current_order();
			$args      = array(
				'order_id'         => WFOCU_WC_Compatibility::get_order_id( $get_order ),
				'funnel_id'        => WFOCU_Core()->data->get_funnel_id(),
				'offer_id'         => $get_current_offer,
				'funnel_unique_id' => WFOCU_Core()->data->get_funnel_key(),
				'offer_type'       => $get_type_of_offer,
				'offer_index'      => $get_type_index_offer,
				'email'            => WFOCU_Core()->data->get( 'useremail' ),
				'next_action'      => filter_input( INPUT_POST, 'next_action', FILTER_UNSAFE_RAW ),
			);
			do_action( 'wfocu_offer_expired_event', $args );

			if ( 'redirect_to_next' === filter_input( INPUT_POST, 'next_action', FILTER_UNSAFE_RAW ) ) {
				$get_offer = WFOCU_Core()->offers->get_the_next_offer( 'yes' );

				$data['redirect_url'] = WFOCU_Core()->public->get_the_upsell_url( $get_offer );

				WFOCU_Core()->data->set( 'current_offer', $get_offer );
				WFOCU_Core()->data->save();
			}
			$response_ajax = array(
				'success' => 'true',
			);

			$response_ajax = array_merge( $response_ajax, $data );
			wp_send_json( $response_ajax );
		}


		public static function handle_charge() {

			check_ajax_referer( 'wfocu_front_charge', 'nonce' );

			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			WFOCU_Core()->data->set( '_offer_result', true );
			$posted_data = WFOCU_Core()->process_offer->parse_posted_data( $_POST );
			$response    = false;
			$data        = [];
			$get_order   = WFOCU_Core()->data->get_current_order();

			WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': UpSell Accept Call Received for Offer: ' . $get_current_offer );

			if ( true === self::validate_charge_request( $posted_data ) ) {
				$get_integration = '';
				if ( $get_order instanceof WC_Order ) {
					$get_payment_gateway = WFOCU_WC_Compatibility::get_payment_gateway_from_order( $get_order );
					if ( ! empty( $get_payment_gateway ) ) {
						$get_integration = WFOCU_Core()->gateways->get_integration( $get_payment_gateway );
					}
				}
				$message = '';
				if ( ! empty( $get_integration ) ) {
					try {
						WFOCU_Core()->process_offer->execute( $get_current_offer_meta );

						/**
						 * Perform Charge by Gateway
						 */
						$response = WFOCU_Core()->public->charge_upsell();

						/**
						 * Handle order creation or batching
						 */
						$data = WFOCU_Core()->process_offer->_handle_upsell_charge( $response );

						$message = $data['message'];

					} /** @noinspection PhpUndefinedClassInspection */ catch ( Error $e ) {

						$data = $get_integration->handle_api_error( 'Offer payment failed. Reason: Some PHP error occurred', 'Error Captured: ' . print_r( $e->getMessage() . " <-- Generated on" . $e->getFile() . ":" . $e->getLine(), true ), $get_order ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					} catch ( WFOCU_Payment_Gateway_Exception $e ) {
						WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Payment Failed' );
						$data = $get_integration->handle_api_error( sprintf( esc_attr__( 'Offer payment failed. Reason: %s', 'woofunnels-upstroke-one-click-upsell' ), esc_attr( $e->getMessage() ) ), print_r( $e->getMessage(), true ), $get_order, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					} catch ( Exception $e ) {
						$data = $get_integration->handle_api_error( 'Offer payment failed. Reason: Some PHP error occurred', 'Exception Captured: Details' . print_r( $e->getMessage(), true ), $get_order ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					}
				}
			} else {
				$message              = __( 'Unable to process, Validation Failed!', 'woofunnels-upstroke-one-click-upsell' );
				$data['redirect_url'] = WFOCU_Core()->public->get_clean_order_received_url();
			}

			$response_ajax            = array(
				'success' => $response,
			);
			$response_ajax['message'] = $message;

			if ( isset( $data ) ) {
				$response_ajax['data'] = $data;
			}

			wp_send_json( $response_ajax );

		}

		/**
		 * perform checks to validate if all the hashes that came in the charge call belongs to the current running offer and funnel
		 *
		 * @param $posted_data
		 *
		 * @return bool
		 */
		public static function validate_charge_request( $posted_data ) {

			$status = true;
			if ( empty( $posted_data ) ) {
				return false;
			}
			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			if ( is_object( $get_current_offer_meta ) && isset( $get_current_offer_meta->products ) ) {

				foreach ( $posted_data as $data ) {

					if ( ! isset( $get_current_offer_meta->products->{$data['hash']} ) ) {
						$status = false;
						break;
					}
				}
			}


			return apply_filters( 'wfocu_validate_charge_request', $status, $get_current_offer, $get_current_offer_meta );

		}

		public static function add_funnel() {
			$resp = array(
				'msg'    => __( 'Unable to create funnels', 'woofunnels-upstroke-one-click-upsell' ),
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_add_new_funnel', '_nonce' );
			$resp = array(
				'msg'    => __( 'Unable to create funnels', 'woofunnels-upstroke-one-click-upsell' ),
				'status' => false,
			);
			if ( isset( $_POST['funnel_name'] ) && '' !== $_POST['funnel_name'] ) {
				$post                 = array();
				$post['post_title']   = wc_clean( $_POST['funnel_name'] );
				$post['post_type']    = WFOCU_Common::get_funnel_post_type_slug();
				$post['post_status']  = WFOCU_SLUG . '-disabled';
				$post['post_content'] = isset( $_POST['funnel_desc'] ) ? wc_clean( $_POST['funnel_desc'] ) : '';
				$post['menu_order']   = WFOCU_Common::get_next_funnel_priority();

				if ( ! empty( $post ) ) {
					$funnel_id = wp_insert_post( $post );
					if ( 0 !== $funnel_id && ! is_wp_error( $funnel_id ) ) {
						$resp['status']       = true;
						$resp['redirect_url'] = add_query_arg( array(
							'page'    => 'upstroke',
							'section' => 'offers',
							'edit'    => $funnel_id,
						), admin_url( 'admin.php' ) );
						$resp['msg']          = 'Funnel Successfully Created';
						WFOCU_Core()->funnels->save_funnel_priority( $funnel_id, $post['menu_order'] );

					} else {
						$resp['redirect_url'] = '#';
						$resp['msg']          = __( 'Funnel Successfully Updated', 'woofunnels-upstroke-one-click-upsell' );
					}
				}
			}
			wp_send_json( $resp );
		}

		public static function add_offer( $posted_data = null ) {
			$skip_nonce = false;

			if ( empty( $posted_data ) ) {
				$posted_data = bwf_clean( $_POST ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$skip_nonce = true;
			}
			$resp = array(
				'msg'    => __( 'Unable to create offer', 'woofunnels-upstroke-one-click-upsell' ),
				'status' => false,
			);

			if ( false === $skip_nonce ) {

				if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {

					wp_send_json( $resp );
				}

				check_admin_referer( 'wfocu_add_offer', '_nonce' );
			}


			if ( isset( $posted_data['funnel_id'] ) && ! empty( $posted_data['funnel_id'] ) && isset( $posted_data['step_name'] ) && ! empty( $_POST['step_name'] ) ) {  // Input var okay.

				$funnel_id = wc_clean( $posted_data['funnel_id'] );  // Input var okay.

				if ( isset( $posted_data['step_type'] ) && '' !== $posted_data['step_type'] ) {  // Input var okay.

					$offer_type = wc_clean( wp_unslash( $posted_data['step_type'] ) );  // Input var okay.
				} else {
					$offer_type = 'upsell';
				}
				$post_type = WFOCU_Common::get_offer_post_type_slug();
				$post      = array(
					'post_title'  => wc_clean( wp_unslash( $posted_data['step_name'] ) ), // Input var okay.
					'post_type'   => $post_type,
					'post_status' => 'publish',
				);

				$id = wp_insert_post( $post );
				if ( ! is_wp_error( $id ) ) {
					$default_settings = array(
						'funnel_id' => $funnel_id,
						'type'      => $offer_type,
						'products'  => array(),
						'fields'    => array(),
						'settings'  => array(),
					);
					update_post_meta( $id, '_funnel_id', $funnel_id );
					update_post_meta( $id, '_offer_type', $offer_type );

					update_post_meta( $funnel_id, '_wfocu_is_rules_saved', 'yes' );
					WFOCU_Common::update_funnel_time( $funnel_id );

					$resp['msg']       = 'Offer Add Successfully';
					$resp['type']      = $offer_type;
					$resp['id']        = $id;
					$resp['url']       = get_the_permalink( $id );
					$resp['slug']      = get_post( $id )->post_name;
					$resp['title']     = wc_clean( wp_unslash( $_POST['step_name'] ) ); // Input var okay.
					$resp['funnel_id'] = $funnel_id;
					$resp['status']    = true;
					$resp['data']      = $default_settings;
				}
			}

			wp_send_json( $resp );

		}

		public static function save_funnel_offer_settings() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_save_funnel_offer_settings', '_nonce' );


			if ( isset( $_POST['funnel_id'] ) && ! empty( $_POST['funnel_id'] ) ) { // Input var okay.
				$post = get_post( wc_clean( wp_unslash( $_POST['funnel_id'] ) ) ); // Input var okay.
				if ( ! is_null( $post ) ) {
					$funnel_id = $post->ID;
					$offer_id  = isset( $_POST['offer_id'] ) ? wc_clean( wp_unslash( $_POST['offer_id'] ) ) : 0; // Input var okay
					$settings  = ( isset( $_POST['settings'] ) && is_array( ( wp_unslash( $_POST['settings'] ) ) ) && count( ( wp_unslash( $_POST['settings'] ) ) ) ) > 0 ? (object) ( wp_unslash( $_POST['settings'] ) ) : new stdClass(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

					foreach ( is_object( $settings ) ? $settings : array() as $key => $value ) {
						if ( ! in_array( $key, [ 'upsell_page_track_code', 'upsell_page_purchase_code' ], true ) ) {
							$settings->{$key} = wc_clean( $value );
						}
					}

					$old_data = WFOCU_Common::get_offer( $offer_id );

					if ( '' !== $old_data ) {

						$old_data->settings = WFOCU_Common::maybe_filter_boolean_strings( $settings );
						WFOCU_Common::update_offer( $offer_id, $old_data, $funnel_id );
						WFOCU_Common::update_funnel_time( $funnel_id );

						do_action( 'wfocu_offer_updated', $old_data, $offer_id, $funnel_id );
						$resp['msg']    = 'Setting Updated';
						$resp['status'] = true;
					}

					$funnel_steps    = WFOCU_Core()->funnels->get_funnel_steps( $funnel_id );
					$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $funnel_steps );
					WFOCU_Common::update_funnel_upsell_downsell( $funnel_id, $upsell_downsell );
				}
			}

			wp_send_json( $resp );
		}

		public static function save_funnel_steps() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_save_funnel_steps', '_nonce' );


			if ( isset( $_POST['funnel_id'] ) ) { //Input var OK

				$post = get_post( wc_clean( wp_unslash( $_POST['funnel_id'] ) ) ); //Input var OK

				$update_steps = array();
				if ( ! is_null( $post ) ) {
					$funnel_id = $post->ID;
					$steps     = ( isset( $_POST['steps'] ) && is_array( wc_clean( wp_unslash( $_POST['steps'] ) ) ) && count( wc_clean( wp_unslash( $_POST['steps'] ) ) ) ) > 0 ? wp_unslash( wc_clean( $_POST['steps'] ) ) : new stdClass();  //Input var OK

					foreach ( $steps as $key => $step ) {
						if ( ! empty( $step ) ) {
							$step = WFOCU_Core()->offers->filter_step_object_for_db( $step );

							$update_steps[ $key ] = $step;
						}
					}
					if ( count( $update_steps ) > 0 ) {
						$update_steps = array_values( $update_steps );
					}

					$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $update_steps );

					/* Validating upsell downsell if offer to jump is above the current offer */
					$available_offer_ids = array_map( 'absint', wp_list_pluck( $update_steps, 'id' ) );
					foreach ( $upsell_downsell as $offer_id => $move_path ) {
						$accepted       = $move_path['y'];
						$rejected       = $move_path['n'];
						$need_update    = false;
						$offer_settings = WFOCU_Core()->offers->get_offer( $offer_id, false );

						if ( $accepted > 0 && isset( $offer_settings->settings->jump_on_accepted ) && true === $offer_settings->settings->jump_on_accepted ) {
							if ( array_search( absint( $accepted ), $available_offer_ids, true ) < array_search( absint( $offer_id ), $available_offer_ids, true ) ) {
								$offer_settings->settings->jump_to_offer_on_accepted = 'automatic';
								$need_update                                         = true;
							}
						}

						if ( $rejected > 0 && isset( $offer_settings->settings->jump_on_rejected ) && true === $offer_settings->settings->jump_on_rejected ) {
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

					WFOCU_Common::update_funnel_steps( $funnel_id, $update_steps );
					WFOCU_Common::update_funnel_upsell_downsell( $funnel_id, $upsell_downsell );

					WFOCU_Common::update_funnel_time( $funnel_id );

					$resp = array(
						'msg'    => __( 'All Offers Saved', 'woofunnels-upstroke-one-click-upsell' ),
						'status' => true,
					);
				}
			}
			wp_send_json( $resp );
		}

		public static function save_funnel_offer_products( $posted_data = null ) {

			$skip_nonce = false;

			if ( empty( $posted_data ) ) {
				$posted_data = bwf_clean( $_POST ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$skip_nonce = true;
			}
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === $skip_nonce ) {

				if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
					wp_send_json( $resp );
				}
				check_admin_referer( 'wfocu_save_funnel_offer_products', '_nonce' );
			}


			$products_list = array();

			if ( isset( $posted_data['funnel_id'] ) ) {

				$post = get_post( wc_clean( wp_unslash( $posted_data['funnel_id'] ) ) );  //input var ok
				if ( ! is_null( $post ) ) {

					$funnel_id      = $post->ID;
					$offer_id       = isset( $posted_data['offer_id'] ) ? wc_clean( wp_unslash( $posted_data['offer_id'] ) ) : 0; //input var ok
					$offers         = ( isset( $posted_data['offers'] ) && is_array( wc_clean( wp_unslash( $posted_data['offers'] ) ) ) && count( wc_clean( wp_unslash( $_POST['offers'] ) ) ) ) > 0 ? wc_clean( wp_unslash( $_POST['offers'] ) ) : array(); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
					$offer_state    = ( isset( $posted_data['offer_state'] ) && wc_clean( wp_unslash( $posted_data['offer_state'] ) ) === 'on' ) ? '1' : '0';  //input var ok
					$update_steps   = [];
					$offers_setting = new stdClass();
					if ( ! empty( $offers ) && count( $offers ) > 0 && isset( $offers[ $offer_id ] ) ) {

						$offer = $offers[ $offer_id ];
						if ( ! empty( $offer['products'] ) && count( $offer['products'] ) > 0 ) {
							$offers_setting->products   = new stdClass();
							$offers_setting->variations = new stdClass();
							$offers_setting->fields     = new stdClass();

							foreach ( $offer['products'] as $hash_key => $pro ) {
								$offers_setting->products->{$hash_key}                   = $pro['id'];
								$offers_setting->fields->{$hash_key}                     = new stdClass();
								$offers_setting->fields->{$hash_key}->discount_amount    = $pro['discount_amount'];
								$offers_setting->fields->{$hash_key}->discount_type      = WFOCU_Common::get_discount_setting( $pro['discount_type'] );
								$offers_setting->fields->{$hash_key}->quantity           = $pro['quantity'];
								$offers_setting->fields->{$hash_key}->shipping_cost_flat = floatval( $pro['shipping_cost_flat'] );
								array_push( $products_list, $pro['id'] );
								if ( isset( $pro['variations'] ) && count( $pro['variations'] ) > 0 ) {
									$offers_setting->variations->{$hash_key} = array();
									foreach ( $pro['variations'] as $variation_id => $settings ) {
										if ( isset( $settings['is_enable'] ) && 'on' === $settings['is_enable'] ) {
											$offers_setting->variations->{$hash_key}[ $variation_id ]                  = new stdClass();
											$offers_setting->variations->{$hash_key}[ $variation_id ]->vid             = $variation_id;
											$offers_setting->variations->{$hash_key}[ $variation_id ]->discount_amount = $settings['discount_amount'];
											$offers_setting->variations->{$hash_key}[ $variation_id ]                  = apply_filters( 'wfocu_variations_offers_setting_data', $offers_setting->variations->{$hash_key}[ $variation_id ] );
										}
									}

									$offers_setting->fields->{$hash_key}->default_variation = isset( $pro['default_variation'] ) ? $pro['default_variation'] : '';
								}
							}

							$offers_setting->have_multiple_product = is_array( $offer['products'] ) && count( $offer['products'] ) > 1 ? 2 : 1;
						}
					}

					$steps = WFOCU_Core()->funnels->get_funnel_steps( $funnel_id );
					if ( $steps && is_array( $steps ) && count( $steps ) > 0 ) {
						foreach ( $steps as $key => $step ) {
							if ( ! empty( $step ) ) {

								if ( intval( $step['id'] ) === intval( $offer_id ) ) {

									$step['state'] = $offer_state;
									$step          = WFOCU_Core()->offers->filter_step_object_for_db( $step );

								}
								$update_steps[ $key ] = $step;
							}
						}
					}

					$upsell_downsell = WFOCU_Core()->funnels->prepare_upsell_downsells( $update_steps );

					WFOCU_Common::update_funnel_steps( $funnel_id, $update_steps );
					WFOCU_Common::update_funnel_upsell_downsell( $funnel_id, $upsell_downsell );

					$getsettings = WFOCU_Common::get_offer( $offer_id );

					$offers_setting->template       = ( isset( $getsettings->template ) ? $getsettings->template : '' );
					$offers_setting->template_group = ( isset( $getsettings->template_group ) ? $getsettings->template_group : '' );

					if ( ! empty( $offers_setting ) ) {

						$offers_setting = apply_filters( 'wfocu_update_offer_save_setting', $offers_setting, $offers, $offer_id, $funnel_id );
						WFOCU_Common::update_offer( $offer_id, $offers_setting, $funnel_id );
						if ( '' !== $funnel_id ) {
							WFOCU_Common::update_funnel_time( $funnel_id );
						}

						do_action( 'wfocu_offer_updated', $offers_setting, $offer_id, $funnel_id );

						$resp['msg']    = 'data is saved';
						$resp['status'] = true;
						$resp['offers'] = $offers_setting;
					}
				}

				/** @noinspection PhpUndefinedClassInspection */
				$woofunnels_transient_obj = WooFunnels_Transient::get_instance();
				$woofunnels_transient_obj->delete_all_transients( 'upstroke' );
			}
			wp_send_json( $resp );
		}

		public static function product_search( $term = false ) {

			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_die();
			}
			check_admin_referer( 'wfocu_product_search', '_nonce' );
			$term = empty( $term ) ? ( isset( $_POST['term'] ) ) ? stripslashes( wc_clean( $_POST['term'] ) ) : '' : $term;

			if ( empty( $term ) ) {
				wp_die();
			}

			$variations = true;
			if ( isset( $_POST['variations'] ) && 'true' !== $_POST['variations'] ) {
				$variations = false;
			}
			$ids = WFOCU_Common::search_products( $term, $variations );

			/**
			 * Products types that are allowed in the offers
			 */
			$allowed_types   = apply_filters( 'wfocu_offer_product_types', array(
				'simple',
				'variable',
				'variation',
			) );
			$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );
			$product_objects = array_filter( $product_objects, function ( $arr ) use ( $allowed_types ) {

				return $arr && is_a( $arr, 'WC_Product' ) && in_array( $arr->get_type(), $allowed_types, true );

			} );
			$products        = array();
			foreach ( $product_objects as $product_object ) {
				if ( 'publish' === $product_object->get_status() ) {
					$products[] = array(
						'id'      => $product_object->get_id(),
						'product' => rawurldecode( WFOCU_Common::get_formatted_product_name( $product_object ) ),
					);
				}
			}
			wp_send_json( apply_filters( 'wfocu_woocommerce_json_search_found_products', $products ) );
		}

		public static function add_product() {
			$resp = array(
				'status' => false,
				'msg'    => '',
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_add_product', '_nonce' );


			if ( isset( $_POST['funnel_id'] ) && $_POST['funnel_id'] > 0 && isset( $_POST['offer_id'] ) && $_POST['offer_id'] > 0 ) {

				$offer_id = wc_clean( $_POST['offer_id'] );

				$funnel_id     = wc_clean( $_POST['funnel_id'] );
				$products      = array();
				$fields        = array();
				$variations    = array();
				$products_list = ( isset( $_POST['products'] ) && is_array( wc_clean( $_POST['products'] ) ) && count( wc_clean( $_POST['products'] ) ) > 0 ) ? wc_clean( $_POST['products'] ) : array();

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
				$offer_data->products       = ( isset( $offer_meta_data ) && isset( $offer_meta_data->products ) ) ? (object) $offer_meta_data->products : new stdClass();
				$offer_data->fields         = ( isset( $offer_meta_data ) && isset( $offer_meta_data->fields ) ) ? (object) $offer_meta_data->fields : new stdClass();
				$offer_data->variations     = ( isset( $offer_meta_data ) && isset( $offer_meta_data->variations ) ) ? (object) $offer_meta_data->variations : new stdClass();
				$offer_data->settings       = ( isset( $offer_meta_data ) && isset( $offer_meta_data->settings ) ) ? $offer_meta_data->settings : WFOCU_Core()->offers->get_default_offer_setting();
				$offer_data->state          = ( isset( $offer_meta_data ) && isset( $offer_meta_data->state ) ) ? $offer_meta_data->state : '0';
				$offer_data->template       = ( isset( $offer_meta_data ) && isset( $offer_meta_data->template ) ) ? $offer_meta_data->template : '';
				$offer_data->template_group = ( isset( $offer_meta_data ) && isset( $offer_meta_data->template_group ) ) ? $offer_meta_data->template_group : '';

				if ( count( $products ) > 0 ) {
					$output->products = new stdClass();
					foreach ( $products as $hash => $pr ) {
						$output->products->{$hash}     = $pr;
						$offer_data->products->{$hash} = $pr->id;
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

				$resp['status'] = true;
				$resp['msg']    = __( 'Product saved to funnel', 'woofunnels-upstroke-one-click-upsell' );
				$resp['data']   = $output;
			}

			wp_send_json( $resp );
		}

		public static function remove_product() {
			$resp = array(
				'status' => false,
				'msg'    => '',
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_remove_product', '_nonce' );

			if ( isset( $_POST['funnel_id'] ) && $_POST['funnel_id'] > 0 && isset( $_POST['offer_id'] ) && $_POST['offer_id'] > 0 && isset( $_POST['product_key'] ) && $_POST['product_key'] !== '' ) {

				$funnel_id   = wc_clean( $_POST['funnel_id'] );
				$offer_id    = wc_clean( $_POST['offer_id'] );
				$product_key = wc_clean( $_POST['product_key'] );

				$updatable       = 0;
				$offer_meta_data = WFOCU_Core()->offers->get_offer( $offer_id );
				if ( isset( $offer_meta_data->products ) && isset( $offer_meta_data->products->{$product_key} ) ) {
					$updatable ++;
					unset( $offer_meta_data->products->{$product_key} );
				}
				if ( isset( $offer_meta_data->fields ) && isset( $offer_meta_data->fields->{$product_key} ) ) {
					$updatable ++;
					unset( $offer_meta_data->fields->{$product_key} );
				}
				if ( isset( $offer_meta_data->variations ) && isset( $offer_meta_data->variations->{$product_key} ) ) {
					$updatable ++;
					unset( $offer_meta_data->variations->{$product_key} );
				}

				if ( $updatable > 0 ) {

					WFOCU_Common::update_offer( $offer_id, $offer_meta_data, $funnel_id );
					WFOCU_Common::update_funnel_time( $funnel_id );
					do_action( 'wfocu_offer_updated', $offer_meta_data, $offer_id, $funnel_id );

					$resp = array(
						'status' => true,
						'msg'    => 'product removed from database',
					);
				}
			}
			wp_send_json( $resp );
		}

		public static function update_offer() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_update_offer', '_nonce' );

			if ( isset( $_POST['funnel_id'] ) && isset( $_POST['offer_id'] ) && isset( $_POST['step_name'] ) && $_POST['step_name'] !== '' ) {
				$offer_id  = wc_clean( $_POST['offer_id'] );
				$funnel_id = wc_clean( $_POST['funnel_id'] );
				$title     = wc_clean( $_POST['step_name'] );
				$post      = get_post( $offer_id );
				if ( ! is_wp_error( $post ) ) {
					$args        = array(
						'ID'         => $offer_id,
						'post_title' => $title,
						'post_name'  => isset( $_POST['funnel_step_slug'] ) ? wc_clean( $_POST['funnel_step_slug'] ) : '',
					);
					$update_post = wp_update_post( $args );

					if ( ! is_wp_error( $update_post ) ) {
						WFOCU_Common::update_funnel_time( $funnel_id );
						$resp['status'] = true;
						$resp['msg']    = __( 'Offer Updated successfully', 'woofunnels-upstroke-one-click-upsell' );
						$resp['url']    = get_the_permalink( $offer_id );
						$resp['name']   = $title;
						$resp['slug']   = wc_clean( $_POST['funnel_step_slug'] );
						$resp['type']   = isset( $_POST['step_type'] ) ? wc_clean( $_POST['step_type'] ) : 'upsell';
					}
				}
			}
			wp_send_json( $resp );
		}

		public static function update_funnel() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_update_funnel', '_nonce' );

			if ( isset( $_POST['funnel_id'] ) ) {
				$funnel_id = wc_clean( $_POST['funnel_id'] );
				$args      = array(
					'ID' => $funnel_id,
				);
				if ( isset( $_POST['funnel_name'] ) && $_POST['funnel_name'] !== '' ) {
					$args['post_title'] = wc_clean( $_POST['funnel_name'] );
				}
				if ( isset( $_POST['funnel_desc'] ) && $_POST['funnel_desc'] !== '' ) {
					$args['post_content'] = wc_clean( $_POST['funnel_desc'] );
				}

				if ( count( $args ) > 1 ) {
					$post = wp_update_post( $args );
					if ( ! is_wp_error( $post ) ) {
						$resp['status'] = true;
						$resp['msg']    = __( 'funnel updated successfully', 'woofunnels-upstroke-one-click-upsell' );
						$resp['data']   = array(
							'name' => $args['post_title'],
							'desc' => isset( $args['post_content'] ) ? $args['post_content'] : '',
						);
					}
				}
			}

			wp_send_json( $resp );
		}

		public static function removed_offer_from_funnel( $posted_data = null ) {
			$skip_nonce = false;

			if ( empty( $posted_data ) ) {
				$posted_data = bwf_clean( $_POST ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$skip_nonce = true;
			}
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( false === $skip_nonce ) {

				if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
					wp_send_json( $resp );
				}
				check_admin_referer( 'wfocu_remove_offer_from_funnel', '_nonce' );
			}

			if ( isset( $posted_data['offer_id'] ) && $posted_data['offer_id'] > 0 && isset( $posted_data['funnel_id'] ) ) {
				$offer_id  = wc_clean( $posted_data['offer_id'] );
				$funnel_id = wc_clean( $posted_data['funnel_id'] );
				$status    = wp_delete_post( $offer_id, true );
				WFOCU_Common::update_funnel_time( $funnel_id );

				if ( null !== $status && false !== $status ) {
					$resp['status'] = true;
					$resp['msg']    = __( 'Offer Removed from funnel', 'woofunnels-upstroke-one-click-upsell' );
				}
				/**
				 * Updating overall funnel products
				 */
				$funnel_products = WFOCU_Core()->funnels->get_funnel_products( $funnel_id );
				if ( isset( $funnel_products[ $offer_id ] ) ) {
					unset( $funnel_products[ $offer_id ] );
				}
			}

			wp_send_json( $resp );
		}

		public static function update_rules() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_save_rules_settings', '_nonce' );

			$data = array();
			if ( isset( $_POST['data'] ) ) {
				wp_parse_str( $_POST['data'], $data );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( isset( $data['funnel_id'] ) && $data['funnel_id'] > 0 && isset( $data['wfocu_rule'] ) && ! empty( $data['wfocu_rule'] ) > 0 ) {
					$funnel_id = $data['funnel_id'];
					$rules     = $data['wfocu_rule'];
					$post      = get_post( $funnel_id );
					if ( ! is_wp_error( $post ) ) {
						WFOCU_Common::update_funnel_rules( $funnel_id, $rules );
						WFOCU_Common::update_funnel_time( $funnel_id );
						$resp = array(
							'msg'    => __( 'Rules Updated successfully', 'woofunnels-upstroke-one-click-upsell' ),
							'status' => true,
						);
					}
				}
			}

			wp_send_json( $resp );
		}

		/**
		 * @hooked over `wp_ajax_wfocu_calculate_shipping`
		 * When user accepts upsell and clicks on "add to my order" button, we check & calculate shipping for the current bucket
		 */
		public static function calculate_shipping() {

			check_ajax_referer( 'wfocu_front_calculate_shipping', 'nonce' );

			//prepare shipping call package
			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;

			$products                          = array();
			$get_order                         = WFOCU_Core()->data->get_current_order();
			$posted_data                       = WFOCU_Core()->offers->parse_posted_data( $_POST );
			$existing_methods                  = array();
			$methods                           = $get_order->get_shipping_methods();
			$shipping                          = array();
			$old_shipping_cost                 = ( $is_batching_on ) ? array(
				'cost' => $get_order->get_shipping_total(),
				'tax'  => $get_order->get_shipping_tax(),
			) : array(
				'cost' => 0,
				'tax'  => 0,
			);
			$get_shipping_methods_from_session = WFOCU_Core()->data->get( 'chosen_shipping_methods', array() );

			//If parent order have a shipping applied
			if ( $methods && is_array( $methods ) && count( $methods ) ) {
				foreach ( $methods as $method ) {
					$method_id = WFOCU_WC_Compatibility::get_method_id( $method ) . ':' . WFOCU_WC_Compatibility::get_instance_id( $method );

					/**
					 * Detect if user opted free shipping in the previous order
					 * return from there straight away so we do not need to check for shipping, just apply it free
					 */
					if ( $is_batching_on && ( WFOCU_Core()->shipping->is_free_shipping( WFOCU_WC_Compatibility::get_method_id( $method ) ) ) && true === apply_filters( 'wfocu_skip_shipping_if_free_shipping', true ) ) {

						$get_free_shipping = array(
							$method_id => array(
								'method'       => WFOCU_WC_Compatibility::get_method_id( $method ),
								'label'        => $method->get_name(),
								'cost'         => 0,
								'shipping_tax' => 0,
							),
						);

						$response_ajax = array(
							'success' => 'true',
						);

						$response_ajax['data'] = array(
							'free_shipping' => $get_free_shipping,
							'shipping'      => $shipping,
							'shipping_prev' => $old_shipping_cost,

						);

						wp_send_json( $response_ajax );

					}

					array_push( $existing_methods, $method_id );

					/**
					 * Since WooCommerce 2.1, WooCommerce allows to add multiple shipping methods in one single order
					 * The idea was to split a cart in some logical grouping, more info here https://www.xadapter.com/woocommerce-split-cart-items-order-ship-via-multiple-shipping-methods/
					 * For now we just need to break it after one iteration, so that we always know which shipping method we need to process & replace.
					 */

					//break;
				}
			}
			//if previous order have some shipping methods pass it as existing methods to calculate new shipping methods
			if ( ! empty( $get_shipping_methods_from_session ) ) {
				$existing_methods = $get_shipping_methods_from_session;
			}
			//If previous order opted free shipping then passing remove all shipping from existing methods
			if ( count( $existing_methods ) > 0 && WFOCU_Core()->shipping->is_free_shipping( $existing_methods[0] ) ) {
				$existing_methods = array();
			}

			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );

			$build_offer_data = WFOCU_Core()->offers->build_offer_product( $get_current_offer_meta );

			/**
			 * In case of fixed shipping, cost is returned as shipping gateway array
			 */
			if ( 'flat' === $build_offer_data->shipping_preferece ) {
				$response_ajax = array(
					'success' => 'true',
				);

				$response_ajax['data'] = array(
					'free_shipping' => array(),
					'shipping'      => array(
						'fixed' => array(
							'cost'         => $build_offer_data->shipping_fixed,
							'shipping_tax' => WFOCU_Core()->shipping->get_flat_shipping_rates( $build_offer_data->shipping_fixed ),
							'label'        => WFOCU_Core()->data->get_option( 'flat_shipping_label' ),
						),
					),
					'shipping_prev' => array(
						'cost' => 0,
						'tax'  => 0,
					),

				);

				wp_send_json( $response_ajax );

			} else {
				/**
				 * Add previous order items in the queue to checking shipping over
				 */
				if ( $is_batching_on ) {
					foreach ( $get_order->get_items() as $item_id => $item ) {

						/** @noinspection PhpUnhandledExceptionInspection */
						$price = wc_get_order_item_meta( $item_id, '_line_total', true );
						if ( true === WFOCU_WC_Compatibility::display_prices_including_tax() ) {
							/** @noinspection PhpUnhandledExceptionInspection */
							$price = $price + wc_get_order_item_meta( $item_id, '_line_tax', true );
						}

						$item_attribute = array();
						if ( $item->get_variation_id() && ! empty( $item->get_meta_data() ) ) {
							foreach ( $item->get_meta_data() as $meta_data ) {
								if ( ! empty( $meta_data->get_data() ) && $meta_data->get_data()['key'] ) {
									$item_attribute[ 'attribute_' . $meta_data->get_data()['key'] ] = $meta_data->get_data()['value'];
								}

							}
						}

						array_push( $products, array(
							'product_id'           => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
							'qty'                  => $item->get_quantity(),
							'price'                => $price / $item->get_quantity(),
							'variation_attributes' => $item_attribute,
						) );
					}
				}

				$upsell_package = WFOCU_Core()->offers->prepare_shipping_package( $get_current_offer_meta, $posted_data );

				foreach ( $upsell_package as $item ) {
					$price = $item['price'];
					if ( true === WFOCU_WC_Compatibility::display_prices_including_tax() ) {
						$price = $item['price_with_tax'];
					}
					$variation_attribute = array();
					if ( isset( $item['_product'] ) && $item['_product'] instanceof WC_Product_Variation ) {
						$variation_attribute = $item['meta'];
					}
					array_push( $products, array(
						'product_id'           => $item['product'],
						'qty'                  => (int) $item['qty'],
						'price'                => $price / (int) $item['qty'],
						'variation_attributes' => $variation_attribute,
						'offer_product'        => true,
					) );
				}

				/**
				 * Setting the location
				 */
				$country     = empty( $get_order->get_shipping_country() ) ? $get_order->get_billing_country() : $get_order->get_shipping_country();
				$state       = empty( $get_order->get_shipping_state() ) ? $get_order->get_billing_state() : $get_order->get_shipping_state();
				$city        = empty( $get_order->get_shipping_city() ) ? $get_order->get_billing_city() : $get_order->get_shipping_city();
				$postcode    = empty( $get_order->get_shipping_postcode() ) ? $get_order->get_billing_postcode() : $get_order->get_shipping_postcode();
				$customer_id = WFOCU_WC_Compatibility::get_order_data( $get_order, '_customer_user' );

				if ( $customer_id > 0 ) {
					$customer = new WC_Customer( $customer_id );

					if ( empty( $country ) ) {
						$country = empty( $customer->get_shipping_country() ) ? $customer->get_billing_country() : $customer->get_shipping_country();
					}

					if ( empty( $state ) ) {
						$state = empty( $customer->get_shipping_state() ) ? $customer->get_billing_state() : $customer->get_shipping_state();
					}

					if ( empty( $city ) ) {
						$city = empty( $customer->get_shipping_city() ) ? $customer->get_billing_city() : $customer->get_shipping_city();
					}

					if ( empty( $postcode ) ) {
						$postcode = empty( $customer->get_shipping_postcode() ) ? $customer->get_billing_postcode() : $customer->get_shipping_postcode();
					}
				}

				$location = array( $country, $state, $city, $postcode );

				if ( class_exists( 'WooFunnels_UpStroke_Dynamic_Shipping' ) ) {
					$get_dynamic_shipping_module = WooFunnels_UpStroke_Dynamic_Shipping::instance();

					/**
					 * Calculate shipping
					 */
					$get_shipping = $get_dynamic_shipping_module->calculate_dynamic_shipping( $products, $location, $existing_methods, $get_order );

					$response_ajax = array(
						'success' => 'true',
					);

					$response_ajax['data'] = wp_parse_args( $get_shipping, array(
						'free_shipping' => array(),
						'shipping'      => array(),
						'shipping_prev' => $old_shipping_cost,

					) );
				} else {
					$response_ajax['data'] = array(
						'free_shipping' => array(),
						'shipping'      => array(),
						'shipping_prev' => $old_shipping_cost,

					);
				}
			}

			wp_send_json( $response_ajax );
		}

		/**
		 * Register product and offer views
		 */
		public static function register_views() {
			check_ajax_referer( 'wfocu_front_register_views', 'nonce' );

			if ( isset( $_POST['data'] ) ) {
				$data                 = wc_clean( $_POST['data'] );
				$get_current_offer    = WFOCU_Core()->data->get_current_offer();
				$get_order            = WFOCU_Core()->data->get_current_order();
				$get_type_of_offer    = $data['offer_type'];
				$get_type_index_offer = $data['offer_type_index'];
				do_action( 'wfocu_offer_viewed_event', $get_current_offer, WFOCU_WC_Compatibility::get_order_id( $get_order ), WFOCU_Core()->data->get_funnel_id(), $get_type_of_offer, $get_type_index_offer, WFOCU_Core()->data->get( 'useremail' ) );

			}

		}

		public static function page_search() {
			$args      = array(
				'post_type'        => array( 'page' ),
				'post_status'      => 'publish',
				'suppress_filters' => false,
				's'                => filter_input( INPUT_POST, 'term', FILTER_UNSAFE_RAW ),
			);
			$args      = apply_filters( 'wfocu_allow_cpt_for_design', $args );
			$page_list = new WP_Query( $args );
			$pages     = array();
			foreach ( $page_list->posts as $page_object ) {

				$get_assignment    = get_post_meta( $page_object->ID, '_wfocu_offer', true );
				$assignment_string = '';
				if ( '' !== $get_assignment ) {
					$get_assignment_offer = get_post( $get_assignment );
					if ( is_object( $get_assignment_offer ) ) {
						$assignment_string = sprintf( '(associated with : %s)', rawurldecode( $get_assignment_offer->post_title ) );
					}
				}

				$pages[] = array(
					'id'        => $page_object->ID,
					'page_name' => '#' . $page_object->ID . ': ' . rawurldecode( $page_object->post_title ) . ' ' . $assignment_string,
					'url'       => get_permalink( $page_object->ID ),
				);
			}
			wp_send_json( $pages );
		}

		public static function get_custom_page() {
			$resp = [];
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_get_custom_page', '_nonce' );
			$offer_id = ( isset( $_POST['offer_id'] ) ) ? wc_clean( $_POST['offer_id'] ) : '';
			$page_id  = ( isset( $_POST['page_id'] ) ) ? wc_clean( $_POST['page_id'] ) : '';

			update_post_meta( $offer_id, '_wfocu_custom_page', $page_id );
			update_post_meta( $page_id, '_wfocu_offer', $offer_id );
			$resp['status'] = true;
			$resp['msg']    = __( 'Product saved to funnel', 'woofunnels-upstroke-one-click-upsell' );
			$resp['data']   = array(
				'title' => get_the_title( $page_id ),
				'link'  => get_edit_post_link( $page_id ),
				'id'    => $page_id,
			);

			wp_send_json( $resp );
		}

		public static function update_template() {
			$resp = [];

			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_update_template', '_nonce' );
			$offer     = ( isset( $_POST['offer_id'] ) && wc_clean( $_POST['offer_id'] ) ) ? wc_clean( $_POST['offer_id'] ) : 0;
			$funnel_id = ( isset( $_POST['id'] ) && wc_clean( $_POST['id'] ) ) ? wc_clean( $_POST['id'] ) : 0;
			$resp      = [];
			$meta      = get_post_meta( $offer, '_wfocu_setting', true );

			if ( is_object( $meta ) ) {
				$meta->template       = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : '';
				$meta->template_group = isset( $_POST['template_group'] ) ? sanitize_text_field( $_POST['template_group'] ) : '';

				$response = WFOCU_Core()->importer->maybe_import_data( wc_clean( $_POST['template_group'] ), wc_clean( $_POST['template'] ), $offer, $meta );

				if ( is_string( $response ) ) {
					$resp['status'] = false;
					$resp['msg']    = $response;
					$resp['data']   = '';
					wp_send_json( $resp );
				}
				update_post_meta( $offer, '_wfocu_setting', $meta );
				do_action( 'wfocu_offer_update_template', $offer, $funnel_id, $meta );

			}
			if ( '' !== $funnel_id ) {
				WFOCU_Common::update_funnel_time( $funnel_id );
			}

			$resp['status'] = true;
			$resp['msg']    = __( 'Product saved to funnel', 'woofunnels-upstroke-one-click-upsell' );
			$resp['data']   = '';
			wp_send_json( $resp );
		}


		public static function save_funnel_settings() {
			$resp = [];
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_save_funnel_settings', '_nonce' );
			$funnel_id = ( isset( $_POST['funnel_id'] ) && wc_clean( $_POST['funnel_id'] ) ) ? wc_clean( $_POST['funnel_id'] ) : 0;
			$options   = ( isset( $_POST['data'] ) ) ? $_POST['data'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( is_array( $options ) ) {

				$options['offer_success_message_pop'] = sanitize_textarea_field( $options['offer_success_message_pop'] );
				$options['offer_failure_message_pop'] = sanitize_textarea_field( $options['offer_failure_message_pop'] );
				$options['offer_wait_message_pop']    = sanitize_textarea_field( $options['offer_wait_message_pop'] );
				$options['funnel_priority']           = sanitize_text_field( $options['funnel_priority'] );
				$options['funnel_success_script']     = wp_unslash( $options['funnel_success_script'] );
			}

			$options = WFOCU_Common::maybe_filter_boolean_strings( $options );
			WFOCU_Core()->funnels->save_funnel_options( $funnel_id, $options );
			WFOCU_Core()->funnels->save_funnel_priority( $funnel_id, $options['funnel_priority'] );
			WFOCU_Common::update_funnel_time( $funnel_id );

			$resp['status'] = true;
			$resp['msg']    = __( 'Settings are updated', 'woofunnels-upstroke-one-click-upsell' );
			$resp['data']   = '';
			wp_send_json( $resp );
		}

		public static function save_global_settings() {
			$resp = [];
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_save_global_settings', '_nonce' );
			$options = isset( $_POST['data'] ) ? $_POST['data'] : 0; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$resp    = self::update_global_settings_fields( $options );
			wp_send_json( $resp );
		}

		public static function update_global_settings_fields( $options ) {
			$options = ( is_array( $options ) && count( $options ) > 0 ) ? wp_unslash( $options ) : 0;

			$resp = [
				'status' => false,
				'msg'    => __( 'Settings Updated', 'woofunnels-upstroke-one-click-upsell' ),
				'data'   => '',
			];

			if ( ! is_array( $options ) || count( $options ) === 0 ) {
				return $resp;
			}

			$options['primary_order_status_title'] = wp_unslash( sanitize_text_field( $options['primary_order_status_title'] ) );
			$options['ttl_funnel']                 = sanitize_text_field( $options['ttl_funnel'] );
			$options['scripts']                    = wp_unslash( $options['scripts'] );
			$options['scripts_head']               = wp_unslash( $options['scripts_head'] );
			$options['offer_header_text']          = wp_unslash( $options['offer_header_text'] );
			$options['offer_yes_btn_text']         = wp_unslash( $options['offer_yes_btn_text'] );
			$options['offer_skip_link_text']       = wp_unslash( $options['offer_skip_link_text'] );
			$options['offer_header_text']          = wp_unslash( $options['offer_header_text'] );
			$options['cart_opener_text']           = wp_unslash( $options['cart_opener_text'] );

			$options = WFOCU_Common::maybe_filter_boolean_strings( $options );

			/**
			 * This code ensures that intersection of supported and enabled gateways will used for checking to prevent any value/gateway to save when its not enabled.
			 *
			 */
			$supported_gateways = WFOCU_Core()->gateways->get_gateways_list();
			if ( isset( $options['gateways'] ) && $supported_gateways && is_array( $supported_gateways ) && count( $supported_gateways ) > 0 ) {
				$supported_gateways_keys = wp_list_pluck( $supported_gateways, 'value' );

				$parsed = array_filter( $options['gateways'], function ( $val ) use ( $supported_gateways_keys ) {
					return in_array( $val, $supported_gateways_keys, true );
				} );
				if ( isset( $options['gateways'] ) ) {
					$options['gateways'] = $parsed;
				}
			}

			if ( ! isset( $options['gateways'] ) ) {
				$options['gateways'] = [];
			}

			WFOCU_Core()->data->update_options( $options );
			$resp['status'] = true;

			return $resp;
		}

		public static function preview_details() {
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_preview_details', '_nonce' );

			if ( isset( $_POST['funnel_id'] ) && $_POST['funnel_id'] > 0 ) {
				$funnel_id    = wc_clean( $_POST['funnel_id'] );
				$funnel_post  = get_post( $funnel_id );
				$data_funnels = WFOCU_Core()->funnels->get_funnel_offers_admin( $funnel_id );

				$resp['status']      = ( 'publish' === $funnel_post->post_status ) ? __( 'Active', 'woofunnels-upstroke-one-click-upsell' ) : __( 'Deactivated', 'woofunnels-upstroke-one-click-upsell' );
				$resp['funnel_id']   = $funnel_id;
				$resp['funnel_name'] = get_the_title( $funnel_post );
				$resp['launch_url']  = admin_url( 'admin.php' ) . '?page=upstroke&section=rules&edit=' . $funnel_id;
				$resp['offers']      = array();
				if ( ! empty( $data_funnels['steps'] ) ) {
					foreach ( $data_funnels['steps'] as $key => $steps ) {
						$resp['offers'][ $key ]                = array();
						$resp['offers'][ $key ]['offer_name']  = $steps['name'];
						$resp['offers'][ $key ]['offer_state'] = $steps['state'];
						$resp['offers'][ $key ]['offer_type']  = ucfirst( $steps['type'] );

						$get_offer      = $data_funnels['offers'][ $steps['id'] ];
						$product_offers = array();
						if ( $get_offer->products && 0 < count( get_object_vars( $get_offer->products ) ) ) {
							foreach ( $get_offer->products as $product ) {

								$qty = $product->quantity;
								if ( 'percentage' === $product->discount_type ) {
									$discount = $product->discount_amount . '%';
								} else {
									$discount = '<span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>' . $product->discount_amount;
								}
								array_push( $product_offers, $product->name . ' x' . $qty . ' @' . $discount );
							}
						}
						$resp['offers'][ $key ]['offer_products'] = implode( ', ', $product_offers );
					}
					$resp['launch_url'] = admin_url( 'admin.php' ) . '?page=upstroke&section=offers&edit=' . $funnel_id;
				}
				$resp['msg'] = __( 'Funnel Data from funnel', 'woofunnels-upstroke-one-click-upsell' );
			}
			wp_send_json( $resp );
		}

		public static function duplicate_funnel() {
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_duplicate_funnel', '_nonce' );
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( isset( $_POST['funnel_id'] ) && $_POST['funnel_id'] > 0 ) {
				$funnel_id = wc_clean( $_POST['funnel_id'] );

				$resp = self::duplicating_funnel( $funnel_id, $resp );

				$resp['msg']    = __( 'Funnel duplicated successfully.', 'woofunnels-upstroke-one-click-upsell' );
				$resp['status'] = true;
			}
			wp_send_json( $resp );
		}

		/**
		 * @param $funnel_id
		 * @param $resp
		 *
		 * @return mixed
		 */
		public static function duplicating_funnel( $funnel_id, $resp ) {
			$funnel_post  = get_post( $funnel_id );
			$data_funnels = WFOCU_Core()->funnels->get_funnel_offers_admin( $funnel_id );

			$suffix_text = ' - ' . __( 'Copy', 'woofunnels-upstroke-one-click-upsell' );
			if ( did_action( 'wffn_duplicate_funnel' ) > 0 ) {
				$suffix_text = '';
			}

			$funnel_name_new     = get_the_title( $funnel_post ) . $suffix_text;
			$funnel_desc         = $funnel_post->post_content;
			$funnel_priority_new = WFOCU_Common::get_next_funnel_priority();

			$funnel_post_type = WFOCU_Common::get_funnel_post_type_slug();
			$funnel_post_new  = array(
				'post_title'   => $funnel_name_new,
				'post_type'    => $funnel_post_type,
				'post_status'  => get_post_status( $funnel_post ),
				'post_content' => $funnel_desc,
				'menu_order'   => $funnel_priority_new,
			);

			$new_funnel_id        = wp_insert_post( $funnel_post_new );
			$resp['duplicate_id'] = $new_funnel_id;

			if ( ! is_wp_error( $new_funnel_id ) && $new_funnel_id ) {
				$funnel_rules       = get_post_meta( $funnel_id, '_wfocu_rules', true );
				$funnel_rules_saved = get_post_meta( $funnel_id, '_wfocu_is_rules_saved', true );
				$funnel_settings    = get_post_meta( $funnel_id, '_wfocu_settings', true );
				$funnel_settings    = is_array( $funnel_settings ) ? $funnel_settings : array();

				$funnel_settings['funnel_priority'] = $funnel_priority_new;

				update_post_meta( $new_funnel_id, '_wfocu_rules', $funnel_rules );
				update_post_meta( $new_funnel_id, '_wfocu_is_rules_saved', $funnel_rules_saved );
				update_post_meta( $new_funnel_id, '_wfocu_settings', $funnel_settings );

				WFOCU_Common::update_funnel_time( $new_funnel_id );

			} else {
				$resp['msg']    = is_wp_error( $new_funnel_id ) ? $new_funnel_id->get_error_message() : 'Error in duplicating funnel, Please try again later!!!';
				$resp['status'] = false;
				wp_send_json( $resp );
			}
			$new_steps = array();

			if ( ! empty( $data_funnels['steps'] ) ) {
				foreach ( $data_funnels['steps'] as $steps ) {

					$offer_name_new  = $steps['name'] . $suffix_text;
					$offer_state_new = $steps['state'];
					$step_content    = '';

					$offer_post = get_post( $steps['id'] );
					if ( $offer_post instanceof WP_Post ) {
						$step_content = $offer_post->post_content;
					}

					$offer_post_type = WFOCU_Common::get_offer_post_type_slug();
					$offer_post_new  = array(
						'post_title'   => $offer_name_new,
						'post_type'    => $offer_post_type,
						'post_status'  => ( 1 === absint( $offer_state_new ) ) ? 'publish' : 'draft',
						'post_content' => $step_content,
						'post_name'    => sanitize_title( $offer_name_new ),

					);

					$offer_id_new = wp_insert_post( $offer_post_new );
					if ( ! is_wp_error( $offer_id_new ) && $offer_id_new ) {
						$new_step       = [];
						$get_offer      = $steps['id'];
						$offer_type_new = $steps['type'];

						$offer_custom = get_option( 'wfocu_c_' . $get_offer, '' );

						update_post_meta( $offer_id_new, '_funnel_id', $new_funnel_id );
						update_post_meta( $offer_id_new, '_wfocu_edit_last', time() );

						if ( ! empty( $offer_custom ) ) {
							update_option( 'wfocu_c_' . $offer_id_new, $offer_custom, 'no' );
						}

						$new_offer_slug = get_post( $offer_id_new )->post_name;

						$new_step['id']     = $offer_id_new;
						$new_step['name']   = $offer_name_new;
						$new_step['type']   = $offer_type_new;
						$new_step['state']  = $offer_state_new;
						$new_step['slug']   = $new_offer_slug;
						$new_step['old_id'] = $get_offer;
						$new_step['url']    = get_site_url() . '?wfocu_offer=' . $new_offer_slug;
						array_push( $new_steps, $new_step );

						$exclude_meta_keys_to_copy = apply_filters( 'wfocu_do_not_duplicate_meta', [
							'_funnel_id',
							'_wfocu_edit_last',
							'_elementor_page_assets'
						], $get_offer, $offer_id_new, $new_step );

						global $wpdb;

						$post_meta_all = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$get_offer" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

						if ( ! empty( $post_meta_all ) ) {
							$sql_query_selects = [];

							foreach ( $post_meta_all as $meta_info ) {

								$meta_key = $meta_info->meta_key;

								if ( in_array( $meta_key, $exclude_meta_keys_to_copy, true ) ) {
									continue;
								}

								/**
								 * Good to remove slashes before adding
								 */
								if ( $meta_key === '_wfocu_setting' ) {
									$meta_val_settings = maybe_unserialize( $meta_info->meta_value );
									if ( is_object( $meta_val_settings ) && isset( $meta_val_settings->settings ) && is_object( $meta_val_settings->settings ) ) {
										$meta_val_settings->settings = (array) $meta_val_settings->settings;
										$meta_value                  = maybe_serialize( $meta_val_settings );
									} else {
										$meta_value = $meta_info->meta_value;
									}
								} else {
									$meta_value = $meta_info->meta_value;
								}

								$meta_key   = esc_sql( $meta_key );
								$meta_value = esc_sql( $meta_value );

								$sql_query_selects[] = "( $offer_id_new, '$meta_key', '$meta_value')"; //db call ok; no-cache ok; WPCS: unprepared SQL ok.

							}

							$sql_query_meta_val = implode( ',', $sql_query_selects );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta ) );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared

						}

						do_action( 'wfocu_offer_duplicated', $offer_id_new, $get_offer );


						// Need to Re save for Gutenberg
						$temp_post               = get_post( $offer_id_new );
						$temp_post->post_content = $step_content;
						wp_update_post( $temp_post );

					} else {
						$resp['msg']    = is_wp_error( $new_funnel_id ) ? $new_funnel_id->get_error_message() : 'Error in duplicating offer, Please try again later!!!';
						$resp['status'] = false;
						wp_send_json( $resp );
						break;
					}
				}
			}

			$old_offer_ids = array_map( 'absint', wp_list_pluck( $new_steps, 'old_id' ) );

			foreach ( $new_steps as $new_step ) {

				$old_settings      = WFOCU_Core()->offers->get_offer( $new_step['old_id'], false );
				$old_jump_accepted = ( isset( $old_settings->settings ) && isset( $old_settings->settings->jump_to_offer_on_accepted ) ) ? $old_settings->settings->jump_to_offer_on_accepted : 'automatic';
				$old_jump_rejected = ( isset( $old_settings->settings ) && isset( $old_settings->settings->jump_to_offer_on_rejected ) ) ? $old_settings->settings->jump_to_offer_on_rejected : 'automatic';

				$old_accept_index = array_search( absint( $old_jump_accepted ), $old_offer_ids, true );
				$old_reject_index = array_search( absint( $old_jump_rejected ), $old_offer_ids, true );

				$new_settings = WFOCU_Core()->offers->get_offer( $new_step['id'], false );
				if ( is_object( $new_settings ) && isset( $new_settings->settings ) && is_array( $new_settings->settings ) && count( $new_settings->settings ) > 0 ) {
					$new_settings->settings = ( object ) $new_settings->settings;
				}
				if ( is_object( $new_settings ) && isset( $new_settings->settings ) && is_object( $new_settings->settings ) ) {
					$new_settings->settings->jump_to_offer_on_accepted = ( 'automatic' === $old_jump_accepted || 'terminate' === $old_jump_accepted ) ? $old_jump_accepted : ( empty( $old_accept_index ) ? 'automatic' : $new_steps[ $old_accept_index ]['id'] );
					$new_settings->settings->jump_to_offer_on_rejected = ( 'automatic' === $old_jump_rejected || 'terminate' === $old_jump_rejected ) ? $old_jump_rejected : ( empty( $old_reject_index ) ? 'automatic' : $new_steps[ $old_reject_index ]['id'] );
				}

				WFOCU_Common::update_offer( $new_step['id'], $new_settings );

			}

			update_post_meta( $new_funnel_id, '_funnel_steps', $new_steps );
			$new_funnel_upsell_downsells = WFOCU_Core()->funnels->prepare_upsell_downsells( $new_steps );
			update_post_meta( $new_funnel_id, '_funnel_upsell_downsell', $new_funnel_upsell_downsells );

			return $resp;
		}

		/**
		 * @param $funnel_id
		 * @param $new_funnel_id
		 * @param $resp
		 *
		 * @return mixed
		 */
		public static function duplicate_offers( $funnel_id, $new_funnel_id, $resp ) {
			$new_steps    = array();
			$data_funnels = WFOCU_Core()->funnels->get_funnel_offers_admin( $funnel_id );

			if ( ! empty( $data_funnels['steps'] ) ) {
				foreach ( $data_funnels['steps'] as $steps ) {

					$offer_name_new  = $steps['name'] . ' Copy';
					$offer_state_new = $steps['state'];
					$step_post       = get_post( $steps['id'] );

					$offer_post_type = WFOCU_Common::get_offer_post_type_slug();
					$offer_post_new  = array(
						'post_title'   => $offer_name_new,
						'post_type'    => $offer_post_type,
						'post_status'  => 'publish',
						'post_name'    => sanitize_title( $offer_name_new ),
						'post_content' => $step_post->post_content,
					);

					$offer_id_new = wp_insert_post( $offer_post_new );
					if ( ! is_wp_error( $offer_id_new ) && $offer_id_new ) {
						$new_step       = [];
						$get_offer      = $steps['id'];
						$offer_type_new = $steps['type'];

						$offer_custom = get_option( 'wfocu_c_' . $get_offer, '' );

						update_post_meta( $offer_id_new, '_funnel_id', $new_funnel_id );
						update_post_meta( $offer_id_new, '_wfocu_edit_last', time() );

						if ( ! empty( $offer_custom ) ) {
							update_option( 'wfocu_c_' . $offer_id_new, $offer_custom, 'no' );
						}

						$new_offer_slug = get_post( $offer_id_new )->post_name;

						$new_step['id']     = $offer_id_new;
						$new_step['name']   = $offer_name_new;
						$new_step['type']   = $offer_type_new;
						$new_step['state']  = $offer_state_new;
						$new_step['slug']   = $new_offer_slug;
						$new_step['old_id'] = $get_offer;
						$new_step['url']    = get_site_url() . '?wfocu_offer=' . $new_offer_slug;
						$new_steps[]        = $new_step;

						$exclude_meta_keys_to_copy = apply_filters( 'wfocu_do_not_duplicate_meta', [ '_funnel_id', '_wfocu_edit_last' ], $get_offer, $offer_id_new, $new_step );

						global $wpdb;

						$post_meta_all = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$get_offer" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

						if ( ! empty( $post_meta_all ) ) {
							$sql_query_selects = [];
							foreach ( $post_meta_all as $meta_info ) {

								$meta_key = $meta_info->meta_key;

								if ( in_array( $meta_key, $exclude_meta_keys_to_copy, true ) ) {
									continue;
								}

								$meta_key   = esc_sql( $meta_key );
								$meta_value = esc_sql( $meta_info->meta_value );

								$sql_query_selects[] = "( $offer_id_new, '$meta_key', '$meta_value')"; //db call ok; no-cache ok; WPCS: unprepared SQL ok.
							}

							$sql_query_meta_val = implode( ',', $sql_query_selects );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta ) );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared

						}

						do_action( 'wfocu_offer_duplicated', $offer_id_new, $get_offer );

					} else {
						$resp['msg']    = is_wp_error( $new_funnel_id ) ? $new_funnel_id->get_error_message() : 'Error in duplicating offer, Please try again later!!!';
						$resp['status'] = false;

						return $resp;
					}
				}
			}

			$old_offer_ids = array_map( 'absint', wp_list_pluck( $new_steps, 'old_id' ) );

			foreach ( $new_steps as $new_step ) {

				$old_settings      = WFOCU_Core()->offers->get_offer( $new_step['old_id'], false );
				$old_jump_accepted = ( isset( $old_settings->settings ) && isset( $old_settings->settings->jump_to_offer_on_accepted ) ) ? $old_settings->settings->jump_to_offer_on_accepted : 'automatic';
				$old_jump_rejected = ( isset( $old_settings->settings ) && isset( $old_settings->settings->jump_to_offer_on_rejected ) ) ? $old_settings->settings->jump_to_offer_on_rejected : 'automatic';

				$old_accept_index = array_search( absint( $old_jump_accepted ), $old_offer_ids, true );
				$old_reject_index = array_search( absint( $old_jump_rejected ), $old_offer_ids, true );

				$new_settings = WFOCU_Core()->offers->get_offer( $new_step['id'], false );

				$new_settings           = is_object( $new_settings ) ? $new_settings : new stdClass();
				$new_settings->settings = isset( $new_settings->settings ) ? $new_settings->settings : new stdClass();

				$new_settings->settings->jump_to_offer_on_accepted = ( 'automatic' === $old_jump_accepted || 'terminate' === $old_jump_accepted ) ? $old_jump_accepted : ( empty( $old_accept_index ) ? 'automatic' : $new_steps[ $old_accept_index ]['id'] );
				$new_settings->settings->jump_to_offer_on_rejected = ( 'automatic' === $old_jump_rejected || 'terminate' === $old_jump_rejected ) ? $old_jump_rejected : ( empty( $old_reject_index ) ? 'automatic' : $new_steps[ $old_reject_index ]['id'] );

				WFOCU_Common::update_offer( $new_step['id'], $new_settings );

			}

			update_post_meta( $new_funnel_id, '_funnel_steps', $new_steps );
			$new_funnel_upsell_downsells = WFOCU_Core()->funnels->prepare_upsell_downsells( $new_steps );
			update_post_meta( $new_funnel_id, '_funnel_upsell_downsell', $new_funnel_upsell_downsells );

			return $resp;
		}

		//Duplicating a funnel ajax function

		public static function toggle_funnel_state() {
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_toggle_funnel_state', '_nonce' );

			if ( isset( $_POST['id'] ) && $_POST['id'] > 0 ) {
				$funnel_id = wc_clean( $_POST['id'] );
				$status    = WFOCU_SLUG . '-disabled';
				if ( isset( $_POST['state'] ) && 'true' === wc_clean( $_POST['state'] ) ) {
					$status = 'publish';
				}
				wp_update_post( array(
					'ID'          => $funnel_id,
					'post_status' => $status,
				) );

			}
			wp_send_json( $resp );
		}

		/**
		 * Maybe hide php errors from coming to the page in woocommerce ajax on live environments
		 */
		public static function maybe_set_error_reporting_false() {

			if ( self::is_wfocu_front_ajax() ) {

				if ( ! WP_DEBUG || ( WP_DEBUG && ! WP_DEBUG_DISPLAY ) ) {
					// Turn off display_errors during AJAX events to prevent malformed JSON.
					@ini_set( 'display_errors', 0 ); // @codingStandardsIgnoreLine.
				}
				$GLOBALS['wpdb']->hide_errors();
			}

		}

		public static function is_wfocu_front_ajax() {

			if ( ( ( defined( 'DOING_AJAX' ) && true === DOING_AJAX ) || ( defined( 'WC_DOING_AJAX' ) && true === WC_DOING_AJAX ) ) && null !== filter_input( INPUT_POST, 'action', FILTER_UNSAFE_RAW ) && false !== strpos( filter_input( INPUT_POST, 'action', FILTER_UNSAFE_RAW ), 'wfocu_front' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Maybe log php errors that coming from the page to let the log system know about the JS error triggered on page.
		 */
		public static function catch_error() {
			check_ajax_referer( 'wfocu_front_catch_error', 'nonce' );
			$get_order = WFOCU_Core()->data->get_current_order();
			WFOCU_Core()->log->log( 'Order: #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ' JS Error logged: ' . filter_input( INPUT_POST, 'message', FILTER_UNSAFE_RAW ) );
		}

		public static function save_template() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_save_template', '_nonce' );

			if ( isset( $_POST['template_name'] ) && '' !== $_POST['template_name'] && isset( $_POST['offer_id'] ) ) {

				$offer_id = absint( $_POST['offer_id'] );
				if ( $offer_id > 0 ) {
					$template_name = trim( wc_clean( $_POST['template_name'] ) );
					$customize_key = WFOCU_SLUG . '_c_' . $offer_id;
					$template_data = get_option( $customize_key, [] );

					if ( is_array( $template_data ) && count( $template_data ) > 0 ) {
						$template_data_keys = array_keys( $template_data );
						foreach ( $template_data_keys as $tkey ) {
							if ( false !== strpos( $tkey, 'wfocu_product' ) ) {
								unset( $template_data[ $tkey ] );
							}
						}

						$template_names                   = get_option( 'wfocu_template_names', [] );
						$template_slug                    = sanitize_title( $template_name );
						$template_slug                    .= '_' . time();
						$template_names[ $template_slug ] = [
							'name' => $template_name,
							'time' => time(),
						];
						update_option( 'wfocu_template_names', $template_names );
						update_option( $template_slug, $template_data, 'no' );

						ob_start();

						self::output_template_save_html( $template_slug, $template_name );
						$message        = ob_get_clean();
						$resp['msg']    = $message;
						$resp['status'] = true;
					}
				}
			}
			wp_send_json( $resp );
		}

		public static function output_template_save_html( $template_slug, $template_name ) {
			?>
			<span class="customize-inside-control-row wfocu_template_holder">
					<input type="radio" value="<?php echo esc_attr( $template_slug ); ?>" name="wfocu_save_templates" id="wfocu_save_templates_<?php echo esc_attr( $template_slug ); ?>" class="wfocu_template">
					<label for="wfocu_save_templates_<?php echo esc_attr( $template_slug ); ?>"><?php echo esc_html( $template_name ); ?></label>
						<a href="javascript:void(0);" class="wfocu_delete_template" data-slug="<?php echo esc_attr( $template_slug ); ?>"><?php esc_html_e( 'Delete', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
						<span class="wfocu-ajax-delete-loader hide"><img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>"></span>
					</span>
			<?php
		}

		public static function apply_template() {
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_apply_template', '_nonce' );

			if ( isset( $_POST['template_slug'] ) && '' !== $_POST['template_slug'] && isset( $_POST['offer_id'] ) ) {

				$offer_id = absint( $_POST['offer_id'] );
				if ( $offer_id > 0 ) {
					$template_name = trim( wc_clean( $_POST['template_slug'] ) );
					$current_data  = get_option( WFOCU_SLUG . '_c_' . $offer_id, [] );
					$data          = get_option( $template_name, [] );
					if ( is_array( $data ) && count( $data ) > 0 ) {
						foreach ( $data as $key => $val ) {
							$current_data[ $key ] = $val;
						}
						update_option( WFOCU_SLUG . '_c_' . $offer_id, $current_data, 'no' );
						$resp['status'] = true;
					}
				}
			}
			wp_send_json( $resp );
		}

		public static function delete_template() {
			$resp = array(
				'msg'    => '',
				'status' => true,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_delete_template', '_nonce' );

			if ( isset( $_POST['template_slug'] ) && '' !== $_POST['template_slug'] && isset( $_POST['offer_id'] ) ) {

				$offer_id = absint( $_POST['offer_id'] );
				if ( $offer_id > 0 ) {
					$template_name  = trim( wc_clean( $_POST['template_slug'] ) );
					$template_names = get_option( 'wfocu_template_names', [] );
					if ( isset( $template_names[ $template_name ] ) ) {
						unset( $template_names[ $template_name ] );
						update_option( 'wfocu_template_names', $template_names );
						delete_option( $template_name );
						$resp['status'] = true;
					}
				}
			}
			wp_send_json( $resp );
		}

		/**
		 * Handling refund offer request
		 *
		 * @throws Exception
		 */
		public static function refund_offer() {

			check_ajax_referer( 'wfocu_admin_refund_offer', 'nonce' );

			$refund_data   = $_POST;
			$order_id      = isset( $refund_data['order_id'] ) ? $refund_data['order_id'] : 0;
			$amount        = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$offer_id      = isset( $refund_data['offer_id'] ) ? $refund_data['offer_id'] : '';
			$txn_id        = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$funnel_id     = isset( $refund_data['funnel_id'] ) ? $refund_data['funnel_id'] : '';
			$event_id      = isset( $refund_data['event_id'] ) ? $refund_data['event_id'] : '';
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';
			$refund_txn_id = false;

			WFOCU_Core()->log->log( "Info: Beginning refund for order: {$order_id}, offer:{$offer_id} for the amount of {$amount}" );

			$result = array(
				'success' => false,
				'msg'     => __( 'Refund unsuccessful', 'woofunnels-upstroke-one-click-upsell' ),
			);

			if ( $order_id ) {

				$order = wc_get_order( $order_id );

				$payment_method = $order->get_payment_method();

				$gateway = WFOCU_Core()->gateways->get_integration( $payment_method );

				if ( $gateway->is_refund_supported() ) {
					$refund_txn_id = $gateway->process_refund_offer( $order );
				}

				if ( false !== $refund_txn_id ) {

					$refunded_offers = $order->get_meta( '_wfocu_refunded_offers', true );
					if ( empty( $refunded_offers ) ) {
						$refunded_offers = WFOCU_Common::get_order_meta( $order, '_wfocu_refunded_offers' );;
					}
					$refunded_offers = empty( $refunded_offers ) ? array() : $refunded_offers;

					/**
					 * Collect all the items upstoke added against the purchase of thie offer refunding
					 * Add them to the line items so that we can tell the woocommerce that these items are getting refunded
					 */
					$get_items_added = WFOCU_Core()->track->get_meta( $event_id, '_items_added' );
					$line_items      = [];
					$order_taxes     = wc_get_order( $order_id )->get_taxes();
					if ( ! empty( $get_items_added ) ) {
						$get_items_added = json_decode( $get_items_added, true );
						foreach ( $get_items_added as $item_id ) {

							$line_items[ $item_id ] = array(
								'qty'          => 0,
								'refund_total' => 0,
								'refund_tax'   => array(),
							);
							$get_item               = WC_Order_Factory::get_order_item( $item_id );

							$line_items[ $item_id ]['qty'] = max( $get_item->get_quantity(), 0 );

							$line_items[ $item_id ]['refund_total'] = wc_format_decimal( $get_item->get_total() );
							$tax_data                               = $get_item->get_taxes();
							$tax_item_total                         = [];
							foreach ( $order_taxes as $tax_item ) {
								$tax_item_id                    = $tax_item->get_rate_id();
								$tax_item_total[ $tax_item_id ] = isset( $tax_data['total'][ $tax_item_id ] ) ? $tax_data['total'][ $tax_item_id ] : 0;
							}

							$line_items[ $item_id ]['refund_tax'] = array_filter( array_map( 'wc_format_decimal', $tax_item_total ) );

						}
					}

					/**
					 * Collect shipping item ID in which upstroke added the shipping (if applicable)
					 * add the amount and taxes against the $line_items so that refund also reflects on shipping.
					 */
					$get_shipping_item_id = WFOCU_Core()->track->get_meta( $event_id, '_shipping_batch_id' );
					if ( ! empty( $get_shipping_item_id ) ) {

						$get_shipping_batch = WFOCU_Core()->track->get_meta( $event_id, '_total_shipping' );
						if ( ! empty( $get_shipping_batch ) ) {
							$get_shipping_batch                                  = json_decode( $get_shipping_batch, ARRAY_A );
							$line_items[ $get_shipping_item_id ]['refund_total'] = wc_format_decimal( $get_shipping_batch['cost'] );
							$tax_item_total                                      = [];
							foreach ( $order_taxes as $tax_item ) {
								$tax_item_id                    = $tax_item->get_rate_id();
								$tax_item_total[ $tax_item_id ] = $get_shipping_batch['tax'];//tax here;
							}
							$line_items[ $get_shipping_item_id ]['refund_tax'] = array_filter( array_map( 'wc_format_decimal', $tax_item_total ) );
						}
					}

					array_push( $refunded_offers, $offer_id );
					$order->update_meta_data( '_wfocu_refunded_offers', $refunded_offers );
					$order->save();
					$refund_note = ( isset( $refund_data['refund_reason'] ) && ! empty( $refund_data['refund_reason'] ) ) ? sprintf( __( '<br/>Reason: %s', 'woofunnels-upstroke-one-click-upsell' ), $refund_data['refund_reason'] ) : '';
					$gateway->wfocu_add_order_note( $order, $amount, $refund_txn_id, $offer_id, $refund_note );
					$refund_reason = empty( $refund_note ) ? '' : sprintf( __( '%s', 'woofunnels-upstroke-one-click-upsell' ), $refund_data['refund_reason'] );
					$refund        = wc_create_refund( array(
						'amount'         => $amount,
						'reason'         => $refund_reason,
						'order_id'       => $order_id,
						'refund_payment' => false,
						'line_items'     => $line_items,
						'restock_items'  => true,
					) );

					if ( is_wp_error( $refund ) ) {
						WFOCU_Core()->log->log( 'Refund Offer attempt failed' . print_r( $refund, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					}

					do_action( 'wfocu_offer_refunded_event', $order_id, $funnel_id, $offer_id, $refund_txn_id, $txn_id, $amount, $event_id );

					$result['success'] = true;
					$result['msg']     = __( 'Refund Successful', 'woofunnels-upstroke-one-click-upsell' );
				}
			}
			wp_send_json( $result );
		}

		public static function clear_template() {
			check_ajax_referer( 'wfocu_clear_template', '_nonce' );
			$offer     = ( isset( $_POST['offer_id'] ) && wc_clean( $_POST['offer_id'] ) ) ? wc_clean( $_POST['offer_id'] ) : 0;
			$funnel_id = ( isset( $_POST['id'] ) && wc_clean( $_POST['id'] ) ) ? wc_clean( $_POST['id'] ) : 0;
			$resp      = [];
			$meta      = get_post_meta( $offer, '_wfocu_setting', true );

			if ( is_object( $meta ) ) {
				$meta->template       = '';
				$meta->template_group = '';
				update_post_meta( $offer, '_wfocu_setting', $meta );
				do_action( 'wfocu_template_removed', $offer );
			}
			if ( '' !== $funnel_id ) {
				WFOCU_Common::update_funnel_time( $funnel_id );
			}

			$resp['status'] = true;
			$resp['msg']    = __( 'Product saved to funnel', 'woofunnels-upstroke-one-click-upsell' );
			$resp['data']   = '';
			wp_send_json( $resp );
		}


		/**
		 * Ajax action to activate plugin
		 */
		public static function activate_plugins() {
			$resp = [];
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_activate_plugins', '_nonce' );
			$plugin_init = isset( $_POST['plugin_init'] ) ? sanitize_text_field( $_POST['plugin_init'] ) : '';

			$activate        = activate_plugin( $plugin_init, '', false, true );
			$resp            = array( 'success' => true );
			$resp['message'] = __( 'Plugin Successfully Activated', 'woofunnels-upstroke-one-click-upsell' );
			$resp['init']    = $plugin_init;
			if ( is_wp_error( $activate ) ) {
				$resp['message'] = $activate->get_error_message();
				$resp['init']    = $plugin_init;
				$resp['success'] = true;
			}

			wp_send_json( $resp );
		}

		/**
		 * Duplicate WPML funnel
		 */
		public static function make_wpml_duplicate() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_make_wpml_duplicate', '_nonce' );

			$posted_data = ( isset( $_POST ) && isset( $_POST['href'] ) ) ? wc_clean( $_POST['href'] ) : [];
			if ( isset( $posted_data['trid'] ) && $posted_data['trid'] > 0 && class_exists( 'SitePress' ) && method_exists( 'SitePress', 'get_original_element_id_by_trid' ) ) {
				$trid           = absint( $posted_data['trid'] );
				$lang           = isset( $posted_data['lang'] ) ? trim( $posted_data['lang'] ) : '';
				$language_code  = isset( $posted_data['language_code'] ) ? trim( $posted_data['language_code'] ) : '';
				$lang           = empty( $lang ) ? $language_code : $lang;
				$master_post_id = SitePress::get_original_element_id_by_trid( $trid );
				if ( false !== $master_post_id ) {
					global $sitepress;
					$duplicate_id = $sitepress->make_duplicate( $master_post_id, $lang );
					if ( is_int( $duplicate_id ) && $duplicate_id > 0 ) {
						$new_post = get_post( $duplicate_id );
						if ( ! is_null( $new_post ) ) {
							$args               = array();
							$args['post_title'] = $new_post->post_title . ' - ' . __( 'Copy - ' . $lang, 'woofunnels-upstroke-one-click-upsell' );

							$args['ID'] = $duplicate_id;
							wp_update_post( $args );
							$resp = self::duplicate_offers( $master_post_id, $duplicate_id, $resp );
						}
						$resp['redirect_url'] = add_query_arg( [
							'section' => 'offers',
							'edit'    => $duplicate_id,
							'lang'    => $lang
						], admin_url( 'admin.php?page=upstroke' ) );
						$resp['duplicate_id'] = $duplicate_id;
						$resp['status']       = true;
					}
				}
			}
			wp_send_json( $resp );
		}

		public static function get_wpml_edit_url() {
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_get_wpml_edit_url', '_nonce' );

			$posted_data = ( isset( $_POST ) && isset( $_POST['href'] ) ) ? wc_clean( $_POST['href'] ) : [];
			if ( isset( $posted_data['post'] ) && $posted_data['post'] > 0 ) {
				$edit = absint( $posted_data['post'] );
				$lang = isset( $posted_data['lang'] ) ? trim( $posted_data['lang'] ) : '';

				$resp['redirect_url'] = add_query_arg( [
					'section' => 'offers',
					'edit'    => $edit,
					'lang'    => $lang
				], admin_url( 'admin.php?page=upstroke' ) );
				$resp['status']       = true;

			}
			wp_send_json( $resp );
		}

		public static function fire_conv_api_events() {
			check_ajax_referer( 'wfocu_fire_conv_api_event' );

			try {
				$events = isset( $_POST['data'] ) ? wc_clean( $_POST['data'] ) : '';
				if ( ! empty( $events ) ) {

					$events = json_decode( wp_kses_stripslashes( $events ), true );
					WFOCU_Core()->ecom_tracking->maybe_render_conv_api( $events, true );
				}
			} catch ( Exception|error $e ) {
				WFOCU_Core()->log->log( $e->getMessage() );
			}
		}

		/**
		 *
		 * add ct_inner in oxygen url
		 */
		public static function update_edit_url() {
			$resp = [];
			if ( false === WFOCU_Core()->role->user_access( 'funnel', 'write' ) ) {
				wp_send_json( $resp );
			}
			check_admin_referer( 'wfocu_update_edit_url', '_nonce' );

			$id             = isset( $_POST['id'] ) ? wc_clean( $_POST['id'] ) : 0;
			$edit_link_href = '';

			$edit_url = '';

			if ( function_exists( 'oxygen_add_posts_quick_action_link' ) ) {
				$post = get_post( $id );
				if ( ! is_null( $post ) ) {
					$actions = oxygen_add_posts_quick_action_link( array(), $post, "array" );
					if ( is_array( $actions ) && isset( $actions['url'] ) ) {
						$get_query_string = explode( '?', $actions['url'] );
						$edit_url         = isset( $get_query_string[1] ) ? $get_query_string[1] : '';
					}
				}
			}

			if ( empty( $edit_url ) && absint( $id ) > 0 ) {

				$post_template = intval( get_post_meta( $id, 'ct_other_template', true ) );
				// Check if we should edit the post or it's template
				$post_editable  = false;
				$template_inner = false;
				if ( $post_template === 0 ) { // default template
					// Get default template
					$default_template = null;
					if ( get_option( 'page_for_posts' ) == $id || get_option( 'page_on_front' ) == $id ) { //phpcs:ignore
						$default_template = ct_get_archives_template( $id );
					}
					if ( empty( $default_template ) ) {
						$default_template = ct_get_posts_template( $id );
					}
					if ( $default_template ) {
						$shortcodes = get_post_meta( $default_template->ID, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ), true );
						if ( $shortcodes && strpos( $shortcodes, '[ct_inner_content' ) !== false ) {
							$post_editable  = true;
							$template_inner = true;
						}
					} else {
						$post_editable = true;
					}
				} else if ( $post_template == - 1 ) { //phpcs:ignore 
					$post_editable = true;
				} else { // Custom template
					$shortcodes = get_post_meta( $post_template, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ), true );
					if ( $shortcodes && strpos( $shortcodes, '[ct_inner_content' ) !== false ) {
						$post_editable  = true;
						$template_inner = true;
					}
				}
				// Generate edit link
				if ( $post_editable ) {
					if ( $template_inner ) {
						$edit_link_href = '&ct_inner=true';
					}
				}

			}

			$resp = [
				'status' => true,
				'url'    => ! empty( $edit_url ) ? $edit_url : $edit_link_href,
			];
			wp_send_json( $resp );
		}

		/**
		 * handle normalize call from wc order list table
		 * @return void
		 */
		public static function normalize_order_from_wc_list() {
			try {
				if ( ! empty( WFOCU_Core()->orders ) ) {
					WFOCU_Core()->orders->maybe_handle_cron_normalize_stasuses();
					WFOCU_Core()->orders->maybe_execute_thankyou_hook();
				}
			} catch ( Exception|Error $e ) {
				WFOCU_Core()->log->log( "Error in normalizing order from wc list table: " . $e->getMessage(), 'error' );
			}
		}

	}

	WFOCU_AJAX_Controller::init();
}