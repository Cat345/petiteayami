<?php
if ( ! class_exists( 'WFFN_REST_THANKYOU_API_EndPoint' ) ) {
	class WFFN_REST_THANKYOU_API_EndPoint extends WFFN_REST_Controller {

		private static $ins = null;
		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'funnel-thankyou';

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
			// Get Rules for Thank You page.
			register_rest_route( $this->namespace, '/' . 'funnel-thankyou' . '/(?P<step_id>[\d]+)' . '/rules', array(
				'args'   => array(
					'step_id' => array(
						'description' => __( 'Current step id.', 'funnel-builder-powerpack' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_wcty_rules' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wcty_rules' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
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

		// Get rules for Thank You Step.
		public function get_wcty_rules( WP_REST_Request $request ) {
			$resp                  = array();
			$resp['success']       = false;
			$resp['msg']           = __( 'Failed', 'funnel-builder-powerpack' );
			$resp['data']['rules'] = array();

			$step_id   = $request->get_param( 'step_id' );
			$funnel_id = $request->get_param( 'funnel_id' );

			wffn_rest_api_helpers()->maybe_step_not_exits( $step_id );

			$step_post = wffn_rest_api_helpers()->get_step_post( $step_id );

			if ( 0 === absint( $funnel_id ) ) {
				$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );

			}
			$resp['data']['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );
			$resp['data']['step_data']   = $step_post;

			if ( class_exists( 'WFTY_Rules' ) && absint( $step_id ) > 0 ) {

				$wfty_rules = WFTY_Rules::get_instance()->get_funnel_rules( $step_id );
				$list_rules = $this->get_ty_rules( $wfty_rules );

				$formatted_rules = $this->strip_group_rule_keys( $wfty_rules );
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

				$resp['success']            = true;
				$resp['data']['rules_list'] = ! empty( $list_rules ) ? $list_rules : [];
				$resp['msg']                = __( 'Rules list loaded', 'funnel-builder-powerpack' );

				if ( ! empty( $formatted_rules ) ) {
					$resp['data']['rules'] = $formatted_rules;
				}
			}

			return rest_ensure_response( $resp );
		}

		// Update Rules for Thank You Step.
		public function update_wcty_rules( WP_REST_Request $request ) {
			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'funnel-builder-powerpack' );

			$step_id   = $request->get_param( 'step_id' );
			$wfty_rule = $request->get_body();

			if ( ! empty( $wfty_rule ) && absint( $step_id ) && class_exists( 'WFTY_Rules' ) ) {

				$posted_data = $this->sanitize_custom( $wfty_rule );
				$posted_data = $this->rectify_posted_rules( $posted_data );

				WFTY_Rules::get_instance()->update_rules_data( $step_id, $posted_data );
				WFTY_Rules::get_instance()->update_rule_time( $step_id );

				$resp['success'] = true;
				$resp['msg']     = __( 'Rules Updated', 'funnel-builder-powerpack' );

                $all_data = wffn_rest_api_helpers()->get_step_post( $step_id, true );
                $resp['step_data'] = is_array( $all_data ) && isset( $all_data['step_data'] ) ? $all_data['step_data'] : false;
                $resp['step_list'] = is_array( $all_data ) && isset( $all_data['step_list'] ) ? $all_data['step_list'] : false;

            }

			return rest_ensure_response( $resp );
		}

		public function get_ty_rules( $saved_rules ) {

			$rule_list = [];
			if ( class_exists( 'WFTY_Rules' ) ) {
				$rule_obj = WFTY_Rules::get_instance();
				$rule_obj->load_rules_classes();
				$wfty_rules =$rule_obj->default_rule_types( 'all' );

				$rule_set = $this->format_rules_select( $wfty_rules, 1 );

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
						$rule_object          = WFTY_Rules::get_instance()->woocommerce_wfty_rule_get_rule_object( $rule['key'] );
						$rule_type            = $rule_object->get_condition_input_type();
						$values               = $rule_object->get_possible_rule_values();
						$options['rule_type'] = $rule_type;
						$options              = array_merge( $defaults, $options );
						$operators            = $rule_object->get_possible_rule_operators();
						$operators            = ! empty( $operators ) && is_array( $operators ) ? wffn_rest_api_helpers()->array_to_nvp( array_flip( $operators ), "label", "value", "value", "key" ) : array();
						$rule['operators']    = $operators;
						$condition_input_type = $rule_object->get_condition_input_type();

						if ( in_array( $rule_type, [ 'Cart_Product_Select', 'Product_Select' ], true ) ) {
							$products = ( ! empty( $saved_rules ) && is_array( $saved_rules ) ) ? $this->get_product_from_conditions( $saved_rules ) : [];
							$values   = $products;
						}
						if ( in_array( $rule_type, [ 'Coupon_Select' ], true ) ) {
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

						$data_args['condition_input_type'] = $condition_input_type;
						$data_args['value_args']           = array(
							'input'   => $condition_input_type,
							'name'    => 'wfty_rule[' . $options['category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][condition]',
							'choices' => $values
						);

						$rule['fields'] = $this->render_input_fields( $data_args );
						$rule_list[]    = $rule;

					}

				}
			}

			return $rule_list;

		}

	}

	WFFN_REST_THANKYOU_API_EndPoint::get_instance();
}