<?php

/**
 * @author XLPlugins
 */
if ( ! class_exists( 'WFTY_Rules' ) ) {
	class WFTY_Rules {
		private static $ins = null;
		public $is_executing_rule = false;
		public $environments = array();
		public $excluded_rules = array();
		public $excluded_rules_categories = array();
		public $processed = array();
		public $record = array();
		public $skipped = array();

		public function __construct() {

			add_filter( 'wfty_wfty_rule_get_rule_types', array( $this, 'default_rule_types' ), 1 );
			add_action( 'wfty_builder_menu', array( $this, 'add_rule_tab' ) );
			add_action( 'wfty_dashboard_page_rules', array( $this, 'render_rules' ) );
			add_action( 'wp_ajax_wfty_change_rule_type', array( $this, 'ajax_render_rule_choice' ) );
			add_action( 'wp_ajax_wfty_save_rules_settings', array( $this, 'update_rules' ) );
			add_filter( 'wffn_wfty_filter_page_ids', array( $this, 'maybe_parse_rule' ), 12, 2 );
		}

		/**
		 * @return WFTY_Rules|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}


		/**
		 * Match the rules groups based on the environment its called on
		 * Iterate over the setof rules set against each offer and validates for the rules set
		 * Now this function also powered in a way that it can hold some rule for the next environment to run on
		 *
		 * @param $content_id integer of the funnel
		 * @param string $environment environment this function called on
		 *
		 * @return bool|mixed|void
		 */
		public function match_groups( $content_id, $environment = 'order' ) {

			$this->is_executing_rule = true;
			$this->set_environment_var( 'thankyou_id', $content_id );

			//allowing rules to get manipulated using external logic
			$external_rules = apply_filters( 'wfty_before_rules', true, $content_id, $environment );
			if ( ! $external_rules ) {

				$this->is_executing_rule = false;

				return false;
			}

			/**
			 * Getting all the rules progress till now
			 */

			$groups = get_post_meta( $content_id, '_wfty_rules', true );
			$result = $this->_validate( $groups, $environment );

			$get_skipped_rules = $this->skipped;

			if ( $get_skipped_rules && count( $get_skipped_rules ) > 0 ) {

				/**
				 * If we have any rules that skipped because they belong to next upcoming environment.
				 * We got to save these rules and process them in correct environment
				 * Assigning sustained rules
				 * returning as false, to prevent any success further
				 */
				$display                        = false;
				$this->processed[ $content_id ] = $get_skipped_rules;
			} else {
				$display                        = apply_filters( 'wfty_after_rules', $result, $content_id, $environment, $this );
				$this->processed[ $content_id ] = $display;
			}


			$this->is_executing_rule = false;

			return $display;
		}

		public function set_environment_var( $key = 'order', $value = '' ) {

			if ( '' === $value ) {
				return;
			}
			$this->environments[ $key ] = $value;

		}

		protected function _validate_rule_block( $groups_category, $type, $environment ) {
			$iteration_results = array();

			if ( $groups_category && is_array( $groups_category ) && count( $groups_category ) ) {

				foreach ( $groups_category as $group_id => $group ) {

					$group_skipped = array();
					foreach ( $group as $rule ) {

						//just skipping the rule if excluded, so that it wont play any role in final judgement
						if ( in_array( $rule['rule_type'], $this->excluded_rules, true ) ) {

							continue;
						}
						$rule_object = $this->woocommerce_wfty_rule_get_rule_object( $rule['rule_type'] );

						if ( is_object( $rule_object ) ) {

							if ( $rule_object->supports( $environment ) ) {

								$match = $rule_object->is_match( $rule, $environment );

								//assigning values to the array.
								//on false, as this is single group (bind by AND), one false would be enough to declare whole result as false so breaking on that point
								if ( false === $match ) {
									$iteration_results[ $group_id ] = 0;
									break;
								} else {
									$iteration_results[ $group_id ] = 1;
								}
							} else {
								$iteration_results[ $group_id ] = 1;
								array_push( $group_skipped, $rule );
							}
						}
					}

					//checking if current group iteration combine returns true, if its true, no need to iterate other groups
					if ( isset( $iteration_results[ $group_id ] ) && $iteration_results[ $group_id ] === 1 ) {

						/**
						 * Making sure the skipped rule is only taken into account when we have status TRUE by executing rest of the rules.
						 */
						if ( $group_skipped && count( $group_skipped ) > 0 ) {
							$this->skipped = array_merge( $this->skipped, $group_skipped );
						}
						break;
					}
				}

				//checking count of all the groups iteration
				if ( count( $iteration_results ) > 0 ) {

					//checking for the any true in the groups
					if ( array_sum( $iteration_results ) > 0 ) {
						$display = true;
					} else {
						$display = false;
					}
				} else {

					//handling the case where all the rules got skipped
					$display = true;
				}
			} else {
				$display = true; //Always display the content if no rules have been configured.
			}

			return $display;
		}

		/**
		 * Creates an instance of a rule object
		 *
		 * @param type $rule_type The slug of the rule type to load.
		 *
		 * @return wfty_Rule_Base or superclass of wfty_Rule_Base
		 * @global array $woocommerce_wfty_rule_rules
		 *
		 */
		public function woocommerce_wfty_rule_get_rule_object( $rule_type ) {
			global $woocommerce_wfty_rule_rules;
			if ( isset( $woocommerce_wfty_rule_rules[ $rule_type ] ) ) {
				return $woocommerce_wfty_rule_rules[ $rule_type ];
			}
			$class = 'wfty_rule_' . $rule_type;

			if ( class_exists( $class ) ) {
				$woocommerce_wfty_rule_rules[ $rule_type ] = new $class;

				return $woocommerce_wfty_rule_rules[ $rule_type ];
			} else {
				return null;
			}
		}

		/**
		 * Validates and group whole block
		 *
		 * @param $groups
		 * @param $environment
		 *
		 * @return bool
		 */
		protected function _validate( $groups, $environment ) {

			if ( $groups && is_array( $groups ) && count( $groups ) ) {
				foreach ( $groups as $type => $groups_category ) {

					if ( in_array( $type, $this->excluded_rules_categories, true ) ) {
						continue;
					}
					$result = $this->_validate_rule_block( $groups_category, $type, $environment );

					if ( false === $result ) {
						return false;
					}
				}
			}

			return true;
		}

		public function find_match() {
			$get_processed = $this->get_processed_rules();
			foreach ( $get_processed as $id => $results ) {
				if ( false === is_bool( $results ) ) {
					return false;
				}
				if ( true === $results ) {
					return array( $id );
				}
			}

			return [];
		}

		public function get_processed_rules() {
			return $this->processed;
		}


		public function load_rules_classes() {


			//Include our default rule classes
			include_once $this->path() . '/rules/base.php';
			include_once $this->path() . '/rules/general.php';
			include_once $this->path() . '/rules/date-time.php';
			include_once $this->path() . '/rules/order.php';
			include_once $this->path() . '/rules/customer.php';
			include_once $this->path() . '/rules/bwf-customer.php';
			if ( is_admin() || defined( 'DOING_AJAX' ) ) {
				//Include the admin interface builder
				include_once $this->path() . '/class-wfocu-input-builder.php';
				include_once $this->path() . '/inputs/html-funnel-products.php';
				include_once $this->path() . '/inputs/html-funnel-onetime.php';
				include_once $this->path() . '/inputs/html-always.php';
				include_once $this->path() . '/inputs/text.php';
				include_once $this->path() . '/inputs/select.php';
				include_once $this->path() . '/inputs/product-select.php';
				include_once $this->path() . '/inputs/chosen-select.php';
				include_once $this->path() . '/inputs/cart-category-select.php';
				include_once $this->path() . '/inputs/cart-product-select.php';
				include_once $this->path() . '/inputs/html-rule-is-renewal.php';
				include_once $this->path() . '/inputs/html-rule-is-first-order.php';
				include_once $this->path() . '/inputs/html-rule-is-guest.php';
				include_once $this->path() . '/inputs/date.php';
				include_once $this->path() . '/inputs/time.php';
				include_once $this->path() . '/inputs/html-rule-is-upgrade.php';
				include_once $this->path() . '/inputs/html-rule-is-downgrade.php';
				include_once $this->path() . '/inputs/user-select.php';
				include_once $this->path() . '/inputs/coupon-select.php';
				include_once $this->path() . '/inputs/coupon-exist.php';
				include_once $this->path() . '/inputs/coupon-text-match.php';
				include_once $this->path() . '/inputs/html-custome-rule-unavailable.php';
				include_once $this->path() . '/inputs/custom-meta.php';
			}

		}


		public function default_rule_types( $types ) {
			$types = array(
				__( 'Default', 'funnel-builder-powerpack' )   => array(
					'general_always' => __( 'No Rules', 'funnel-builder-powerpack' ),
				),
				__( 'Order', 'funnel-builder-powerpack' )     => array(
					'order_item'     => __( 'Products', 'funnel-builder-powerpack' ),
					'order_category' => __( 'Product Category', 'funnel-builder-powerpack' ),
					'order_term'     => __( 'Product Tag', 'funnel-builder-powerpack' ),
					'order_total'    => __( 'Total', 'funnel-builder-powerpack' ),

					'order_item_count'        => __( 'Item Count', 'funnel-builder-powerpack' ),
					'order_item_type'         => __( 'Item Type', 'funnel-builder-powerpack' ),
					'order_coupons'           => __( 'Coupons', 'funnel-builder-powerpack' ),
					'order_coupon_exist'      => __( 'If Coupon(s)', 'funnel-builder-powerpack' ),
					'order_coupon_text_match' => __( 'Coupons - Text Match', 'funnel-builder-powerpack' ),
					'order_payment_gateway'   => __( 'Payment Gateway', 'funnel-builder-powerpack' ),
					'order_shipping_method'   => __( 'Shipping Method', 'funnel-builder-powerpack' ),
					'order_custom_meta'       => __( 'Order Custom Field', 'funnel-builder-powerpack' ),
				),
				__( 'Customer', 'funnel-builder-powerpack' )  => array(
					'is_first_order' => __( 'Customer - Is First Order', 'funnel-builder-powerpack' ),
					'is_guest'       => __( 'Customer - Is Guest', 'funnel-builder-powerpack' ),

					'customer_user'               => __( 'Customer - User Name', 'funnel-builder-powerpack' ),
					'customer_role'               => __( 'Customer - User Role', 'funnel-builder-powerpack' ),
					'customer_purchased_products' => __( 'Customer - Purchased Product: All Time', 'funnel-builder-powerpack' ),
					'customer_purchased_cat'      => __( 'Customer - Purchased Category: All Time', 'funnel-builder-powerpack' ),
				),
				__( 'Geography', 'funnel-builder-powerpack' ) => array(
					'order_shipping_country' => __( 'Shipping Country', 'funnel-builder-powerpack' ),
					'order_billing_country'  => __( '   Billing Country', 'funnel-builder-powerpack' ),

				),
				__( 'Date/Time', 'funnel-builder-powerpack' ) => array(
					'day'  => __( 'Day', 'funnel-builder-powerpack' ),
					'date' => __( 'Date', 'funnel-builder-powerpack' ),
					'time' => __( 'Time', 'funnel-builder-powerpack' ),
				),
			);


			return $types;
		}

		public function get_environment_var( $key = 'order' ) {
			return isset( $this->environments[ $key ] ) ? $this->environments[ $key ] : false;
		}

		public function render_rules() {
			$this->load_rules_classes();
			$thankyou_id = $this->get_thankyou_id();

			if ( $thankyou_id > 0 ) {
				global $wfty_is_rules_saved; //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
				$wfty_is_rules_saved = get_post_meta( $thankyou_id, '_wfty_is_rules_saved', true ); //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable

			}
			include_once( $this->path_views() . '/rules-head.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->path_views() . '/rules-basic.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->path_views() . '/rules-footer.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->path_views() . '/rules-create.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		}

		public function path_views() {
			return WFTP_PRO_Core()->pro_wc_thankyou->get_path() . '/rules/views';
		}

		public function path() {

			return WFTP_PRO_Core()->pro_wc_thankyou->get_path() . '/rules';
		}

		public function add_rule_tab( $menu ) {
			$menu[10] = array(
				'icon' => 'dashicons dashicons-networking',
				'name' => __( 'Rules', 'funnel-builder-powerpack' ),
				'key'  => 'rules',
			);

			return $menu;
		}

		protected function _push_to_skipped( $rule ) {
			array_push( $this->skipped, $rule );
		}

		public function update_rules() {

			check_admin_referer( 'wffn_tp_save_rules', '_nonce' );
			$resp = array(
				'msg'    => '',
				'status' => false,
			);
			$data = array();
			if ( isset( $_POST['data'] ) ) {
				wp_parse_str( $_POST['data'], $data );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( isset( $data['ty_id'] ) && $data['ty_id'] > 0 && isset( $data['wfty_rule'] ) && ! empty( $data['wfty_rule'] ) > 0 ) {
					$funnel_id = $data['ty_id'];
					$rules     = $data['wfty_rule'];
					$post      = get_post( $funnel_id );
					if ( ! is_wp_error( $post ) ) {
						$this->update_rules_data( $funnel_id, $rules );
						$this->update_rule_time( $funnel_id );
						$resp = array(
							'msg'    => __( 'Rules Updated successfully', 'funnel-builder-powerpack' ),
							'status' => true,
						);
					}
				}
			}

			wp_send_json( $resp );
		}

		public function ajax_render_rule_choice( $options = [] ) {

			$this->load_rules_classes();

			$defaults = array(
				'group_id'  => 0,
				'rule_id'   => 0,
				'rule_type' => null,
				'condition' => null,
				'operator'  => null,
			);
			$is_ajax  = false;

			if ( defined( 'DOING_AJAX' ) && true === DOING_AJAX && isset( $_POST['action'] ) && $_POST['action'] === 'wfty_change_rule_type' ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$is_ajax = true;
			}
			if ( $is_ajax ) {
				$options = array_merge( $defaults, $_POST );
			} else {
				$options = array_merge( $defaults, $options );
			}

			$rule_object = self::woocommerce_wfty_rule_get_rule_object( $options['rule_type'] );
			if ( ! empty( $rule_object ) ) {
				$values               = $rule_object->get_possible_rule_values();
				$operators            = $rule_object->get_possible_rule_operators();
				$condition_input_type = $rule_object->get_condition_input_type();
				// create operators field
				$operator_args = array(
					'input'   => 'select',
					'name'    => 'wfty_rule[' . $options['rule_category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][operator]',
					'choices' => $operators,
				);

				echo '<td class="operator">';
				if ( ! empty( $operators ) ) {
					wfty_Input_Builder::create_input_field( $operator_args, $options['operator'] );
				} else { ?>
                    <input type="hidden" name="<?php echo esc_attr( $operator_args['name'] ); ?>" value="=="/>
					<?php
				}
				echo '</td>';
				// create values field
				$value_args = array(
					'input'   => $condition_input_type,
					'name'    => 'wfty_rule[' . $options['rule_category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][condition]',
					'choices' => $values,
				);
				echo '<td class="condition">';
				wfty_Input_Builder::create_input_field( $value_args, $options['condition'] );
				echo '</td>';
			}
			if ( $is_ajax ) {
				die();
			}
		}


		public function woocommerce_wfty_rule_get_input_object( $input_type ) {
			global $woocommerce_wfty_rule_inputs;
			if ( isset( $woocommerce_wfty_rule_inputs[ $input_type ] ) ) {
				return $woocommerce_wfty_rule_inputs[ $input_type ];
			}
			$class = 'wfty_Input_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $input_type ) ) );
			if ( class_exists( $class ) ) {
				$woocommerce_wfty_rule_inputs[ $input_type ] = new $class;
			} else {
				$woocommerce_wfty_rule_inputs[ $input_type ] = apply_filters( 'woocommerce_wfty_rule_get_input_object', $input_type );
			}

			return $woocommerce_wfty_rule_inputs[ $input_type ];
		}

		public function get_funnel_rules( $funnel_id, $rule_type = 'basic' ) {
			$data = get_post_meta( $funnel_id, '_wfty_rules', true );

			return apply_filters( 'get_funnel_wfty_rules', ( isset( $data[ $rule_type ] ) ) ? $data[ $rule_type ] : array(), $funnel_id, $rule_type );
		}

		public function render_rule_choice_template( $options ) {
			// defaults
			$defaults              = array(
				'group_id'  => 0,
				'rule_id'   => 0,
				'rule_type' => null,
				'condition' => null,
				'operator'  => null,
				'category'  => 'basic',
			);
			$options               = array_merge( $defaults, $options );
			$rule_object           = $this->woocommerce_wfty_rule_get_rule_object( $options['rule_type'] );
			$values                = $rule_object->get_possible_rule_values();
			$operators             = $rule_object->get_possible_rule_operators();
			$condition_input_type  = $rule_object->get_condition_input_type();
			$operator_rules_output = '[<%= groupId %>][<%= ruleId %>][operator]'; //phpcs:ignore WordPressVIPMinimum.Security.Underscorejs.OutputNotation,WordPress.Security.EscapeOutput.OutputNotEscaped

			// create operators field
			$operator_args = array(
				'input'   => 'select',
				'name'    => 'wfty_rule[' . $options['category'] . ']' . $operator_rules_output,
				'choices' => $operators,
			);
			echo '<td class="operator">';
			if ( ! empty( $operators ) ) {
				wfty_Input_Builder::create_input_field( $operator_args, $options['operator'] );
			} else {
				echo '<input type="hidden" name="' . $operator_args['name'] . '" value="==" />'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</td>';
			// create values field
			$value_args = array(
				'input'   => $condition_input_type,
				'name'    => 'wfty_rule[basic][<%= groupId %>][<%= ruleId %>][condition]',  //phpcs:ignore WordPressVIPMinimum.Security.Underscorejs.OutputNotation
				'choices' => $values,
			);
			echo '<td class="condition">';
			wfty_Input_Builder::create_input_field( $value_args, $options['condition'] );
			echo '</td>';
		}

		public function update_rule_time( $funnel_id ) {
			$my_post = array(
				'ID' => $funnel_id,
			);

			wp_update_post( $my_post );
		}

		public function update_rules_data( $funnel_id, $data ) {
			$data = apply_filters( 'update_funnel_wfty_rule', $data, $funnel_id );

			update_post_meta( $funnel_id, '_wfty_rules', $data );
			update_post_meta( $funnel_id, '_wfty_is_rules_saved', 'yes' );
		}

		public function maybe_parse_rule( $contents, $order ) {
			try {
				$this->load_rules_classes();

				$rules = WFTY_Rules::get_instance();

				$rules->set_environment_var( 'order', $order->get_id() );

				if ( ! is_array( $contents ) || count( $contents ) === 0 ) {
					return [];
				}
				foreach ( $contents as $content ) {
					$rules->match_groups( $content );
				}

				/**
				 * Get the decided funnel
				 */
				return $rules->find_match();
			} catch ( Exception|Error $e ) {
				return [];
			}

		}

		public function get_thankyou_id() {

			$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
			$edit = filter_input( INPUT_GET, 'edit', FILTER_UNSAFE_RAW );
			if ( ! is_null( $page ) && 'wf-ty' === $page && ! is_null( $edit ) && $edit > 0 ) {
				return $edit;
			}

			return 0;
		}


	}

	WFTY_Rules::get_instance();
}