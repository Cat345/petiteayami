<?php
if ( ! class_exists( 'WFOCU_Rules' ) ) {
	/**
	 * @author XLPlugins
	 */
	class WFOCU_Rules {
		private static $ins = null;
		public $is_executing_rule = false;
		public $environments = array();
		public $excluded_rules = array();
		public $excluded_rules_categories = array();
		public $processed = array();
		public $record = array();
		public $skipped = array();

		public function __construct() {

			add_filter( 'wfocu_wfocu_rule_get_rule_types', array( $this, 'default_rule_types' ), 1 );
			add_filter( 'wfocu_wfocu_rule_get_rule_types_product', array( $this, 'rule_types_product' ), 1 );
			add_action( 'wfocu_before_rules', array( $this, 'reset_skipped' ) );
			add_action( 'wfocu_builder_menu', array( $this, 'add_rule_tab' ) );
			add_action( 'wfocu_dashboard_page_rules', array( $this, 'render_rules' ) );

		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		/**
		 * Match the rules groups based on the environment its called on
		 * Iterate over the setof rules set against each offer and validates for the rules set
		 * Now this function also powered in a way that it can hold some rule for the next environment to run on
		 *
		 * @param int $content_id id of the funnel
		 * @param string $environment environment this function called on
		 *
		 * @return bool|mixed|void
		 */
		public function match_groups( $content_id, $environment = 'cart' ) {

			$this->is_executing_rule = true;
			$this->set_environment_var( 'funnel_id', $content_id );
			//allowing rules to get manipulated using external logic
			$external_rules = apply_filters( 'wfocu_before_rules', true, $content_id, $environment );
			if ( ! $external_rules ) {

				$this->is_executing_rule = false;

				return false;
			}

			/**
			 * Getting all the rules progress till now
			 */
			$sustained = WFOCU_Core()->data->get( 'processed', false, 'rules' );

			/**
			 * If we have a call for an environment & we have a setof rule sustained then process sustained rules
			 */
			if ( 'order' === $environment && false !== $sustained ) {

				/**
				 * Iterating over the saved rules and try to match them for the current environment
				 */
				if ( is_array( $sustained ) && count( $sustained ) > 0 && isset( $sustained[ $content_id ] ) ) {

					/**
					 * filtering out decided rules
					 */
					if ( false === is_bool( $sustained[ $content_id ] ) ) {
						$this->processed[ $content_id ] = $this->_validate_rule_block( $sustained[ $content_id ], 'basic', $environment );

					} else {
						$this->processed[ $content_id ] = $sustained[ $content_id ];
					}

					$this->is_executing_rule = false;

					return;
				}
			}
			$groups = get_post_meta( $content_id, '_wfocu_rules', true );
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
				$display                        = apply_filters( 'wfocu_after_rules', $result, $content_id, $environment, $this );
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
						$rule_object = $this->woocommerce_wfocu_rule_get_rule_object( $rule['rule_type'] );

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
							$this->skipped = array_merge( $this->skipped, $groups_category );
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
		 * @return wfocu_Rule_Base or superclass of wfocu_Rule_Base
		 * @global array $woocommerce_wfocu_rule_rules
		 *
		 */
		public function woocommerce_wfocu_rule_get_rule_object( $rule_type ) {
			global $woocommerce_wfocu_rule_rules;
			if ( isset( $woocommerce_wfocu_rule_rules[ $rule_type ] ) ) {
				return $woocommerce_wfocu_rule_rules[ $rule_type ];
			}
			$class = 'wfocu_rule_' . $rule_type;
			if ( class_exists( $class ) ) {
				$woocommerce_wfocu_rule_rules[ $rule_type ] = new $class;

				return $woocommerce_wfocu_rule_rules[ $rule_type ];
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
					return $id;
				}
			}

			return false;
		}

		public function get_processed_rules() {
			return $this->processed;
		}

		public function sustain_results() {
			$get_processed = $this->get_processed_rules();
			WFOCU_Core()->data->set( 'processed', $get_processed, 'rules' );
			WFOCU_Core()->data->save( 'rules' );
		}

		public function load_rules_classes() {

			//Include our default rule classes
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/base.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/general.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/date-time.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/order.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/customer.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/funnels.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/wfacp.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/bwf-customer.php';
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/rules/bwfcrm-tag.php';
			if ( is_admin() || defined( 'DOING_AJAX' ) ) {
				//Include the admin interface builder
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/class-wfocu-input-builder.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-funnel-products.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-funnel-onetime.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-always.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/text.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/product-select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/chosen-select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/cart-category-select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/cart-product-select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-rule-is-renewal.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-rule-is-first-order.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-rule-is-guest.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/date.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/time.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-rule-is-upgrade.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-rule-is-downgrade.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/user-select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/coupon-select.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/coupon-exist.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/coupon-text-match.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/html-custome-rule-unavailable.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/custom-meta.php';
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'rules/inputs/item-text-match.php';
			}
			do_action( 'wfocu_load_rule_files' );

		}


		public function default_rule_types( $types ) {
			$types = array(
				__( 'Default', 'woofunnels-upstroke-one-click-upsell' )       => array(
					'general_always' => __( 'No Rules', 'woofunnels-upstroke-one-click-upsell' ),
				),
				__( 'Order', 'woofunnels-upstroke-one-click-upsell' )         => array(
					'order_total' => __( 'Total', 'woofunnels-upstroke-one-click-upsell' ),

					'order_item_count'        => __( 'Item Count', 'woofunnels-upstroke-one-click-upsell' ),
					'order_item_type'         => __( 'Item Type', 'woofunnels-upstroke-one-click-upsell' ),
					'order_coupons'           => __( 'Coupons', 'woofunnels-upstroke-one-click-upsell' ),
					'order_coupon_exist'      => __( 'If Coupon(s)', 'woofunnels-upstroke-one-click-upsell' ),
					'order_coupon_text_match' => __( 'Coupons - Text Match', 'woofunnels-upstroke-one-click-upsell' ),
					'order_payment_gateway'   => __( 'Payment Gateway', 'woofunnels-upstroke-one-click-upsell' ),
					'order_shipping_method'   => __( 'Shipping Method', 'woofunnels-upstroke-one-click-upsell' ),
					'order_custom_meta'       => __( 'Order Custom Field', 'woofunnels-upstroke-one-click-upsell' ),
					'order_item_text_match'   => __( 'Order Item - Text Match', 'woofunnels-upstroke-one-click-upsell' ),
				),
				__( 'Customer', 'woofunnels-upstroke-one-click-upsell' )      => array(
					'is_first_order' => __( 'Customer - Is First Order', 'woofunnels-upstroke-one-click-upsell' ),
					'is_guest'       => __( 'Customer - Is Guest', 'woofunnels-upstroke-one-click-upsell' ),

					'customer_user'               => __( 'Customer - User Name', 'woofunnels-upstroke-one-click-upsell' ),
					'customer_role'               => __( 'Customer - User Role', 'woofunnels-upstroke-one-click-upsell' ),
					'customer_purchased_products' => __( 'Customer - Purchased Product: All Time', 'woofunnels-upstroke-one-click-upsell' ),
					'customer_purchased_cat'      => __( 'Customer - Purchased Category: All Time', 'woofunnels-upstroke-one-click-upsell' ),
				),
				__( 'Geography', 'woofunnels-upstroke-one-click-upsell' )     => array(
					'order_shipping_country' => __( 'Shipping Country', 'woofunnels-upstroke-one-click-upsell' ),
					'order_billing_country'  => __( 'Billing Country', 'woofunnels-upstroke-one-click-upsell' ),

				),
				__( 'Date/Time', 'woofunnels-upstroke-one-click-upsell' )     => array(
					'day'  => __( 'Day', 'woofunnels-upstroke-one-click-upsell' ),
					'date' => __( 'Date', 'woofunnels-upstroke-one-click-upsell' ),
					'time' => __( 'Time', 'woofunnels-upstroke-one-click-upsell' ),
				),
				__( 'Upsell Funnel', 'woofunnels-upstroke-one-click-upsell' ) => array(
					'funnel_skip' => __( 'Skip Upsell Funnel', 'woofunnels-upstroke-one-click-upsell' ),
				),
			);
			if ( class_exists( 'BWFCRM_Tag' ) ) {
				$types[ __( 'FunnelKit Automations', 'woofunnels-upstroke-one-click-upsell' ) ] = [
					'automation_tag' => __( 'Contact Tags', 'woofunnels-upstroke-one-click-upsell' ),

				];
			}
			if ( class_exists( 'WFACP_Core' ) ) {

				$types[ __( 'Checkout', 'woofunnels-order-bump' ) ] = [
					'wfacp_page' => __( 'FunnelKit Checkout pages', 'woofunnels-order-bump' ),

				];
			}

			return $types;
		}

		public function rule_types_product( $types ) {
			$types = apply_filters( 'wfocu_rule_type_product_args', array(
				__( 'Default', 'woofunnels-upstroke-one-click-upsell' ) => array(
					'general_always_2' => __( 'No Rules', 'woofunnels-upstroke-one-click-upsell' ),
				),
				__( 'Order', 'woofunnels-upstroke-one-click-upsell' )   => array(
					'order_item'     => __( 'Products   ', 'woofunnels-upstroke-one-click-upsell' ),
					'order_category' => __( 'Product Category', 'woofunnels-upstroke-one-click-upsell' ),
					'order_term'     => __( 'Product Tag', 'woofunnels-upstroke-one-click-upsell' ),
				),
			) );

			return $types;
		}

		public function reset_skipped( $result ) {
			$this->skipped = array();

			return $result;
		}

		public function get_environment_var( $key = 'order' ) {
			return isset( $this->environments[ $key ] ) ? $this->environments[ $key ] : false;
		}

		public function render_rules() {

			$this->load_rules_classes();

			$funnel_id = WFOCU_Core()->funnels->get_funnel_id();

			$control_id = get_post_meta( $funnel_id, '_bwf_ab_variation_of', true );
			if ( $control_id > 0 ) {
				include_once( $this->rule_views_path() . '/rules-blocked.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

				return;
			}
			if ( $funnel_id > 0 ) {
				global $wfocu_is_rules_saved; //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
				$wfocu_is_rules_saved = get_post_meta( $funnel_id, '_wfocu_is_rules_saved', true ); //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
			}
			include_once( $this->rule_views_path() . '/rules-head.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->rule_views_path() . '/rules-product.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->rule_views_path() . '/rules-basic.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->rule_views_path() . '/rules-footer.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once( $this->rule_views_path() . '/rules-create.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		}

		public function rule_views_path() {
			return WFOCU_PLUGIN_DIR . '/rules/views';
		}

		public function add_rule_tab( $menu ) {
			$menu[45] = array(
				'icon' => 'dashicons dashicons-networking',
				'name' => __( 'Rules', 'woofunnels-upstroke-one-click-upsell' ),
				'key'  => 'rules',
			);

			return $menu;
		}

		protected function _push_to_skipped( $rule ) {
			array_push( $this->skipped, $rule );
		}


	}

	if ( class_exists( 'WFOCU_Rules' ) ) {
		WFOCU_Core::register( 'rules', 'WFOCU_Rules' );
	}
}