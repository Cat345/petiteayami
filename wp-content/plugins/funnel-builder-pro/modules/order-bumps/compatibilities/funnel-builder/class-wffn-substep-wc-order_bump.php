<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
defined( 'ABSPATH' ) || exit; //Exit if accessed directly


/**
 * Class contains all the Order bump related funnel functionality
 * Class WFFN_Substep_WC_Order_Bump
 */
if ( ! class_exists( 'WFFN_Substep_WC_Order_Bump' ) ) {
	class WFFN_Substep_WC_Order_Bump extends WFFN_Substep {

		private static $ins = null;
		public $slug = 'wc_order_bump';

		/**
		 * WFFN_Substep_WC_Order_Bump constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'wfob_bumps_from_external_base', array( $this, 'filter_bumps' ), 10, 2 );
			add_filter( 'wfob_add_control_meta_query', array( $this, 'exclude_from_query' ), 10, 1 );
			add_filter( 'maybe_setup_funnel_for_breadcrumb', [ $this, 'maybe_funnel_breadcrumb' ] );
			add_filter( 'woocommerce_checkout_update_order_meta', [ $this, 'woocommerce_checkout_update_order_meta' ] );
		}

		/**
		 * @return WFFN_Substep_WC_Order_Bump|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		/**
		 * @param $substep
		 *
		 * @return array
		 */
		public function get_substep_data() {
			return array(
				'type'        => $this->slug,
				'legend'      => $this->get_title(),
				'name'        => __( 'Name', 'woofunnels-order-bump' ),
				'products'    => __( 'Products', 'woofunnels-order-bump' ),
				'drag'        => __( 'Drag to reorder', 'woofunnels-order-bump' ),
				'popup_title' => __( 'Add Bump', 'woofunnels-order-bump' ),
				'add_btn'     => __( '+ Add Bump', 'woofunnels-order-bump' ),
				'submit_btn'  => __( 'Add', 'woofunnels-order-bump' ),
				'no_step'     => __( 'No Bumps in the Checkout.', 'woofunnels-order-bump' ),
				'no_product'  => __( 'Currently no product is added in this bump.', 'woofunnels-order-bump' ),
			);
		}

		/**
		 * Return title of order_bump substep
		 */
		public function get_title() {
			return __( 'Order Bump', 'woofunnels-order-bump' );
		}

		/**
		 * @param $step
		 *
		 * @return array
		 */
		public function get_substep_designs( $term, $funnel_id = 0 ) {

			$args = array(
				'post_type'      => WFOB_Common::get_bump_post_type_slug(),
				'post_status'    => 'any',
				'posts_per_page' => WFOB_Common::posts_per_page(),

			);

			if ( ! empty( $term ) ) {
				if ( is_numeric( $term ) ) {
					$args['p'] = $term;
				} else {
					$args['s'] = $term;
				}
			}
			if ( absint( $funnel_id ) > 0 ) {
				$args['meta_query'] = [
					[
						'key'   => '_bwf_in_funnel',
						'value' => $funnel_id,
					]
				];
			}
			$q                    = new WP_Query( $args );
			$bump_inside_funnels  = [];
			$bump_outside_funnels = [];
			if ( $q->found_posts > 0 ) {
				foreach ( $q->posts as $bump_post ) {
					$data          = array(
						'id'   => $bump_post->ID,
						'name' => $bump_post->post_title,
					);
					$bwf_funnel_id = get_post_meta( $bump_post->ID, '_bwf_in_funnel', true );
					$funnel        = new WFFN_Funnel( $bwf_funnel_id );
					if ( ! empty( $bwf_funnel_id ) && ! empty( $funnel->get_title() ) ) {
						if ( ! isset( $bump_inside_funnels[ $bwf_funnel_id ] ) ) {
							$bump_inside_funnels[ $bwf_funnel_id ] = [ 'name' => $funnel->get_title(), 'id' => $bwf_funnel_id, "steps" => [] ];
						}
						$bump_inside_funnels[ $bwf_funnel_id ]['steps'][] = $data;
					} else {
						$bump_outside_funnels[] = $data;
					}
				}
			}

			if ( ! empty( $bump_outside_funnels ) ) {
				$bump_outside_funnels = [ [ 'name' => __( 'Others', 'woofunnels-order-bump' ), 'id' => '0', 'steps' => $bump_outside_funnels ] ];
			}

			return array_merge( $bump_inside_funnels, $bump_outside_funnels );
		}

		/**
		 * @param $decided_bumps
		 * @param $posted_data
		 *
		 * @return array
		 */
		public function filter_bumps( $decided_bumps, $posted_data ) {
			$current_step = [];

			/**
			 * Check if the respective method exists to go further
			 */
			if ( method_exists( WFFN_Core()->admin, 'get_license_config' ) ) {
				$License               = WooFunnels_licenses::get_instance();
				$License->plugins_list = null;
				$License->get_plugins_list();

				$state = $this->get_current_app_state();
				
				if ( in_array( $state, [ 'pro_without_license', 'license_expired' ], true ) ) {

					if ( empty( WC()->session->get( 'license_expired_bump_rejected' ) ) ) {
						WC()->session->set( 'license_expired_bump_rejected', 'yes' );
					}

					return [];
				}
			}

			if ( empty( $posted_data ) ) {

				/**
				 * Here check if global checkout is setup and enabled, now check if checkout has no checkout step
				 * then fetch all the substep of type
				 */

				/**
				 * Check if store checkout is configures
				 */
				if ( ! method_exists( 'WFFN_Common', 'get_store_checkout_id' ) || ! WFFN_Common::get_store_checkout_id() ) {
					return $decided_bumps;
				}

				/**
				 * Check if store checkout funnel is enabled
				 */

				if ( false === wffn_string_to_bool( WFFN_Core()->get_dB()->get_meta( WFFN_Common::get_store_checkout_id(), 'status' ) ) ) {
					return $decided_bumps;
				}

				/**
				 * Check if we do not have checkout in our funnel
				 */

				$funnel = new WFFN_Funnel( WFFN_Common::get_store_checkout_id() );

				/**
				 * Check if this is a valid funnel and has native checkout
				 * filter thankyou pages and serve the results
				 */
				if ( wffn_is_valid_funnel( $funnel ) && true === $funnel->is_funnel_has_native_checkout() ) {
					return array_map( 'get_post', $this->maybe_substeps_global( $funnel->get_id() ) );
				}


				return $decided_bumps;
			}

			/**
			 * If current page is native page && If store checkout is enabled from backend && Store checkout funnel Doesn't consist any checkout
			 * If all true the provide order bumps from funnel meta
			 */
			if ( empty( $posted_data['_wfacp_post_id'] ) || 0 === abs( $posted_data['_wfacp_post_id'] ) ) {

				return $decided_bumps;
			}

			$funnel_id = get_post_meta( $posted_data['_wfacp_post_id'], '_bwf_in_funnel', true );
			if ( empty( $funnel_id ) || abs( $funnel_id ) === 0 ) {
				return $decided_bumps;
			}

			$funnel = WFFN_Core()->admin->get_funnel( $funnel_id );
			if ( ! $funnel instanceof WFFN_Funnel ) {
				return $decided_bumps;
			}

			$current_step['id']   = $posted_data['_wfacp_post_id'];
			$current_step['type'] = 'wc_checkout';

			$current_step['id'] = apply_filters( 'wffn_maybe_get_ab_control', $current_step['id'] );

			return array_map( 'get_post', $this->maybe_get_substeps( $current_step, $funnel ) );
		}

		/**
		 * @param $funnel_id
		 * @param $step_id
		 * @param $substep
		 * @param $posted_data
		 *
		 * @return stdClass
		 */
		public function add_substep( $funnel_id, $step_id, $substep, $posted_data ) {

			$title        = isset( $posted_data['title'] ) ? $posted_data['title'] : '';
			$duplicate_id = isset( $posted_data['design_name']['id'] ) ? $posted_data['design_name']['id'] : 0;

			if ( $step_id > 1 ) {
				$post                = array();
				$post['post_title']  = $title;
				$post['post_type']   = WFOB_Common::get_bump_post_type_slug();
				$post['post_status'] = 'publish';

				$menu_order = WFOB_Common::get_highest_menu_order();

				$post['menu_order'] = $menu_order + 1;

				if ( $duplicate_id > 0 ) {

					$substep_id = WFOB_Common::make_duplicate( $duplicate_id );
					wp_update_post( array(
						'ID'          => $substep_id,
						'post_title'  => $title,
						'post_status' => 'publish',
					) );
				} else {
					$substep_id = wp_insert_post( $post );
				}
				if ( ! is_wp_error( $substep_id ) && $substep_id > 0 ) {
					update_post_meta( $substep_id, '_wfob_version', WFOB_VERSION );
					$get_design_data = get_post_meta( $substep_id, '_wfob_design_data', false );

					if ( empty( $get_design_data ) ) {
						$default_slug        = WFOB_Common::$design_default_layout;
						$default_design_data = WFOB_Common::get_default_model_data( $substep_id );

						if ( isset( $default_design_data[ $default_slug ] ) ) {
							WFOB_Common::update_design_data( $substep_id, $default_design_data[ $default_slug ] );
						}
					}

					$posted_data['id']              = $substep_id;
					$posted_data['_data']           = new stdClass();
					$posted_data['_data']->products = $this->get_products( $substep_id );
				}
			}

			return parent::add_substep( $funnel_id, $step_id, $substep, $posted_data );
		}

		/**
		 * @param $funnel_id
		 * @param $substep
		 * @param $posted_data
		 *
		 * @return stdClass
		 */
		public function add_native_store_substep( $funnel_id, $substep, $posted_data ) {
			$title               = isset( $posted_data['title'] ) ? $posted_data['title'] : '';
			$duplicate_id        = isset( $posted_data['design_name']['id'] ) ? $posted_data['design_name']['id'] : 0;
			$post                = array();
			$post['post_title']  = $title;
			$post['post_type']   = WFOB_Common::get_bump_post_type_slug();
			$post['post_status'] = 'publish';

			$menu_order = WFOB_Common::get_highest_menu_order();

			$post['menu_order'] = $menu_order + 1;

			if ( $duplicate_id > 0 ) {

				$substep_id = WFOB_Common::make_duplicate( $duplicate_id );
				wp_update_post( array(
					'ID'          => $substep_id,
					'post_title'  => $title,
					'post_status' => 'publish',
				) );
			} else {
				$substep_id = wp_insert_post( $post );
			}
			if ( ! is_wp_error( $substep_id ) && $substep_id > 0 ) {
				update_post_meta( $substep_id, '_wfob_version', WFOB_VERSION );

				$posted_data['id']              = $substep_id;
				$posted_data['_data']           = new stdClass();
				$posted_data['_data']->products = $this->get_products( $substep_id );
			}

			return parent::add_native_store_substep( $funnel_id, $substep, $posted_data );
		}

		/**
		 * @param $substep_id
		 *
		 * @return array
		 */
		public function get_products( $substep_id ) {
			$products      = get_post_meta( $substep_id, '_wfob_selected_products', true );
			$bump_products = array();
			$discount_html = WFFN_Common::get_discount_type_keys();

			foreach ( ( is_array( $products ) && count( $products ) > 0 ) ? $products : array() as $product ) {

				$bump_products[ $product['id'] ] = array(
					'title'           => $product['title'],
					'qty'             => $product['quantity'],
					'discount_html'   => $discount_html[ $product['discount_type'] ],
					'discount_amount' => $product['discount_amount'],
				);
			}

			return $bump_products;
		}

		/**
		 * @param $funnel_id
		 * @param $step_id
		 * @param $duplicate_step_id
		 * @param $subtype
		 * @param $substep_id
		 * @param $substep_key
		 * @param $duplicated_substeps
		 *
		 * @return mixed
		 */
		public function duplicate_single_substep( $funnel_id, $step_id, $duplicate_step_id, $subtype, $substep_id, $substep_key = 0, $duplicated_substeps = [] ) {
			$duplicate_substep_id = WFOB_Common::make_duplicate( $substep_id );

			if ( $duplicate_substep_id > 0 ) {
				$post_status = get_post_status( $substep_id );
				wp_update_post( [ 'ID' => $duplicate_substep_id, 'post_status' => $post_status ] );
			}

			$duplicated_substeps[ $subtype ][ $substep_key ] = array();

			$duplicated_substeps[ $subtype ][ $substep_key ]['id']    = $duplicate_substep_id;
			$duplicated_substeps[ $subtype ][ $substep_key ]['_data'] = new stdClass();

			$duplicated_substeps[ $subtype ][ $substep_key ]['_data']->products = $this->get_products( $duplicate_substep_id );

			return parent::duplicate_single_substep( $funnel_id, $step_id, $duplicate_step_id, $subtype, $substep_id, $substep_key, $duplicated_substeps );
		}

		/**
		 * @param $funnel_id
		 * @param $duplicate_step_id
		 * @param $subtype
		 * @param $substep_id
		 * @param $substep_key
		 * @param $duplicated_substeps
		 *
		 * @return mixed
		 */
		public function duplicate_store_checkout_substep( $funnel_id, $duplicate_substep_id, $subtype, $substep_id, $substep_key = 0, $duplicated_substeps = [] ) {
			$duplicate_substep_id = WFOB_Common::make_duplicate( $substep_id );

			if ( $duplicate_substep_id > 0 ) {
				$post_status = get_post_status( $substep_id );
				wp_update_post( [ 'ID' => $duplicate_substep_id, 'post_status' => $post_status ] );
			}

			$duplicated_substeps[ $subtype ][ $substep_key ] = array();

			$duplicated_substeps[ $subtype ][ $substep_key ]['id']    = $duplicate_substep_id;
			$duplicated_substeps[ $subtype ][ $substep_key ]['_data'] = new stdClass();

			$duplicated_substeps[ $subtype ][ $substep_key ]['_data']->products = $this->get_products( $duplicate_substep_id );

			return parent::duplicate_store_checkout_substep( $funnel_id, $duplicate_substep_id, $subtype, $substep_id, $substep_key, $duplicated_substeps );
		}

		/**
		 * @param $substep_arr
		 *
		 * @return array
		 */
		public function populate_substeps_data_properties( $substep_arr ) {
			$substeps = array();


			foreach ( is_array( $substep_arr ) ? $substep_arr : array() as $substep_id ) {
				$substep_data                         = array();
				$substep_data['id']                   = $substep_id;
				$substep_data['tags']                 = $this->get_substep_entity_tags( $substep_id );
				$substep_data['_data']                = new stdClass();
				$bump_products                        = class_exists( 'WFOB_Common' ) ? WFOB_Common::get_bump_products( $substep_id ) : [];
				$substep_data['_data']->title         = $this->get_entity_title( $substep_id );
				$substep_data['_data']->edit          = $this->get_entity_edit_link( $substep_id );
				$substep_data['_data']->view          = $this->get_entity_view_link( $substep_id );
				$substep_data['_data']->status        = $this->get_entity_status( $substep_id );
				$substep_data['_data']->product_count = is_array( $bump_products ) ? count( $bump_products ) : 0;
				$substeps[]                           = $substep_data;
			}

			return $substeps;
		}

		/**
		 * @param $step_id
		 *
		 * @return mixed
		 */
		public function get_entity_edit_link( $step_id ) {
			$link = parent::get_entity_edit_link( $step_id );
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$link = esc_url( BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
					'page'    => 'wfob',
					'section' => 'products',
					'wfob_id' => $step_id,
				), admin_url( 'admin.php' ) ) ) );
			}

			return $link;
		}


		public function _get_export_metadata( $substep ) {
			return WFOB_Core()->export->get_bump_array_for_json( $substep['id'] );
		}

		public function _process_import( $substep ) {
			$iport_id = WFOB_Core()->import->import_from_json_data( [ $substep ] );

			return $iport_id[0];
		}

		/**
		 * @param $substep_id
		 *
		 * @return array
		 */
		public function get_substep_entity_tags( $substep_id ) {
			$product_meta = get_post_meta( $substep_id, '_wfob_selected_products', true );

			$funnel_rules = get_post_meta( $substep_id, '_wfob_rules', true );
			$has_rules    = false;
			foreach ( is_array( $funnel_rules ) ? $funnel_rules : array() as $rule_groups ) {
				foreach ( is_array( $rule_groups ) ? $rule_groups : array() as $rules_data ) {
					foreach ( is_array( $rules_data ) ? $rules_data : array() as $rules_arr ) {
						if ( isset( $rules_arr['rule_type'] ) && ( 'general_always' !== $rules_arr['rule_type'] && 'general_always_2' !== $rules_arr['rule_type'] ) ) {
							$has_rules = true;
							break 3;
						}
					}
				}
			}

			$flags = array();
			if ( $has_rules ) {
				$flags['has_rules'] = array(
					'label'       => __( 'Has Rules', 'woofunnels-order-bump' ),
					'label_class' => 'bwf-st-c-badge-green',
					'edit'        => function_exists( 'wffn_rest_api_helpers' ) ? wffn_rest_api_helpers()->get_entity_url( 'bump', 'rules', $substep_id ) : ''
				);
			}

			if ( ! is_array( $product_meta ) || count( $product_meta ) < 1 ) {
				$flags['no_product'] = array(
					'label'       => __( 'No Products', 'woofunnels-order-bump' ),
					'label_class' => 'bwf-st-c-badge-red',
					'edit'        => function_exists( 'wffn_rest_api_helpers' ) ? wffn_rest_api_helpers()->get_entity_url( 'bump', 'product', $substep_id ) : ''
				);
			}

			return $flags;
		}

		/**
		 * @param $get_ref
		 *
		 * @return mixed
		 */
		public function maybe_funnel_breadcrumb( $get_ref ) {
			$step_id = filter_input( INPUT_GET, 'wfob_id', FILTER_UNSAFE_RAW );
			if ( empty( $get_ref ) && ! empty( $step_id ) ) {
				$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );
				if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
					return $funnel_id;
				}
			}

			return $get_ref;
		}

		public function maybe_substeps_global( $funnel ) {
			$substeps = WFFN_Common::get_store_checkout_global_substeps( $funnel );
			if ( empty( $substeps ) ) {
				return [];
			}

			return array_filter( $substeps[ $this->slug ], function ( $k ) {
				if ( $this->is_disabled( $this->get_entity_status( $k ) ) ) {   //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UndefinedVariable
					return false;
				}

				return $k;
			} );
		}


		public function get_current_app_state() {
			$license_config = WFFN_Core()->admin->get_license_config( true );


			if ( isset( $license_config['f']['ed'] ) && $license_config['f']['ed'] ) {
				$ed = $license_config['f']['ed'];

				if ( strtotime( 'now' ) > strtotime( $ed ) ) {
					if ( strtotime( 'now' ) - strtotime( $ed ) < $license_config['gp'][0] * DAY_IN_SECONDS ) {
						return 'license_expired_on_grace_period';
					}

					return 'license_expired';
				}
			}
			if ( defined( 'WFFN_VERSION' ) && version_compare( WFFN_VERSION, '3.9.1', '>' ) ) {
				$license_config = WFFN_Core()->admin->get_license_config( false, false );

			} else {

				$license_config = WFFN_Core()->admin->get_license_config();

			}

			if ( isset( $license_config['f']['la'] ) && $license_config['f']['la'] === true ) {
				return 'pro';
			}
			$license_config = WFFN_Core()->admin->get_license_config();

			if ( isset( $license_config['f']['ad'] ) && $license_config['f']['ad'] ) {
				$ad = $license_config['f']['ad'];

				if ( strtotime( 'now' ) - strtotime( $ad ) < $license_config['gp'][1] * DAY_IN_SECONDS ) {
					return 'pro_without_license_on_grace_period';
				}
			}

			return 'pro_without_license';
		}

		public function woocommerce_checkout_update_order_meta( $order_id ) {
			$license_expired_bump_rejected = WC()->session->get( 'license_expired_bump_rejected' );
			if ( $license_expired_bump_rejected == 'yes' ) {
				$orderbump_skipped = __( 'Order Bump Skipped', 'woofunnels-order-bump' );
				$svg_icon          = WFOB_PLUGIN_URL . '/admin/assets/img/icon_error.svg';
				$contact_support   = 'https://funnelkit.com/support/';
				$reason_base       = __( '<div style="display:flex;align-items:center;margin-bottom:4px;gap:4px;padding-left:20px !important;background: url(' . esc_url( $svg_icon ) . ') no-repeat left !important;">
    <strong style="font-size:13px;">%s</strong>
</div><strong>%s</strong>: %s
<div style="margin:8px 0px;">%s</div>
<div><a target="_blank" href="%s">%s</a></div>', 'woofunnels-order-bump' );

				$note = sprintf( $reason_base, $orderbump_skipped, __( 'Order Bump License Has Expired', 'woofunnels-order-bump' ), __( 'Please renew your license to continue using premium features without interruption.', 'woofunnels-order-bump' ), '', $contact_support, __( 'Go to Contact Support', 'woofunnels-order-bump' ) );

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->add_order_note( $note );
				}

				WC()->session->set( 'license_expired_bump_rejected', 'no' );
			}
		}
	}
}


WFFN_Core()->substeps->register( WFFN_Substep_WC_Order_Bump::get_instance() );
