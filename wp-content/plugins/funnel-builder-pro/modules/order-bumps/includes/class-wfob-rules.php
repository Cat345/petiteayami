<?php
if ( ! class_exists( 'WFOB_Rules' ) ) {
	/**
	 * @author XLPlugins
	 */
	class WFOB_Rules {
		private static $ins = null;
		public $is_executing_rule = false;
		public $environments = array();
		public $excluded_rules = array();
		public $excluded_rules_categories = array();
		public $processed = array();
		public $record = array();
		public $skipped = array();

		public function __construct() {

			/**
			 * page and action check, for backend
			 */
			$page   = filter_input( INPUT_GET, 'page' );
			$action = filter_input( INPUT_POST, 'action' );

			if ( 'wfob' === $page || $action === 'wfob_change_rule_type' ) {
				add_action( 'admin_init', array( $this, 'load_rules_classes' ) );
			}


			add_filter( 'wfob_wfob_rule_get_rule_types', array( $this, 'default_rule_types' ), 1 );
			add_action( 'init', array( $this, 'maybe_save_rules' ) );
			add_action( 'wfob_before_rules', array( $this, 'reset_skipped' ) );
			add_filter( 'wfob_builder_menu', array( $this, 'add_rule_tab' ) );
			add_action( 'wfob_dashboard_page_rules', array( $this, 'render_rules' ) );

		}

		public static function get_instance() {
			if ( null == self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		/**
		 * Match the rules groups based on the environment its called on
		 * Iterate over the setof rules set against each offer and validates for the rules set
		 * Now this function also powered in a way that it can hold some rule for the next environment to run on
		 *
		 * @param $content_id Integer of the bump
		 * @param string $environment environment this function called on
		 *
		 * @return bool|mixed
		 */
		public function match_groups( $content_id, $rematch = false ) {

			$display = false;

			$cache_key = 'wfob_match_groups_' . $content_id;

			$wfob_cache_obj = WooFunnels_Cache::get_instance();
			$results        = $wfob_cache_obj->get_cache( $cache_key, WFOB_SLUG );

			if ( $results === false || true == $rematch ) {
				$this->is_executing_rule = true;

				//allowing rules to get manipulated using external logic
				$external_rules = apply_filters( 'wfob_before_rules', true, $content_id );
				if ( ! $external_rules ) {
					$this->is_executing_rule = false;

					return false;
				}
				/**
				 * @var $rule_object wfob_Rule_Base
				 */
				$groups = WFOB_Common::get_bump_rules( $content_id );

				if ( $groups && is_array( $groups ) && count( $groups ) ) {
					foreach ( $groups as $group_id => $group ) {
						$result = null;
						foreach ( $group as $rule_id => $rule ) {
							$rule_object = WFOB_Common::woocommerce_wfob_rule_get_rule_object( $rule['rule_type'] );

							if ( is_object( $rule_object ) ) {
								$match = $rule_object->is_match( $rule );

								if ( $match == false ) {
									$result = false;
									break;
								}
								$result = ( $result !== null ? ( $result & $match ) : $match );
							}
						}
						if ( $result ) {
							$display = true;
							break;
						}
					}
				} else {
					$display = true; //Always display the content if no rules have been configured.
				}

				$display = apply_filters( 'wfob_after_rules', $display, $content_id );
				$wfob_cache_obj->set_cache( $cache_key, ( $display ) ? 'yes' : 'no', WFOB_SLUG );
			} else {
				$display = ( $results == 'yes' ) ? true : false;
			}
			$this->is_executing_rule = false;

			return $display;
		}

		public function load_rules_classes() {

			//Include our default rule classes
			//Include the compatibility class
			include_once WFOB_PLUGIN_DIR . '/rules/class-wfob-compatibility.php';
			//Include our default rule classes
			include_once WFOB_PLUGIN_DIR . '/rules/rules/base.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/general.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/page.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/users.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/date-time.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/geo.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/cart.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/customer.php';
			include_once WFOB_PLUGIN_DIR . '/rules/rules/wfacp.php';
			if ( is_admin() || defined( 'DOING_AJAX' ) ) {
				//Include the admin interface builder
				include_once WFOB_PLUGIN_DIR . '/rules/class-wfob-input-builder.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/html-always.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/text.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/product-select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/chosen-select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/cart-category-select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/cart-tag-select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/cart-product-select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/user-select.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/date.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/time.php';
				include_once WFOB_PLUGIN_DIR . '/rules/inputs/coupon-text-match.php';
			}
			do_action( 'wfob_after_rules_classes' ); 
		}

		public function default_rule_types( $types ) {
			$types = array(
				__( 'Default', 'woofunnels-order-bump' )   => array(
					'general_always' => __( 'No Rules', 'woofunnels-order-bump' ),
				),
				__( 'Cart', 'woofunnels-order-bump' )      => array(
					'cart_total_full'         => __( 'Cart Total', 'woofunnels-order-bump' ),
					'cart_total'              => __( 'Cart Total (Subtotal)', 'woofunnels-order-bump' ),
					'cart_item'               => __( 'Cart Item(s)', 'woofunnels-order-bump' ),
					'cart_category'           => __( 'Cart Category(s)', 'woofunnels-order-bump' ),
					'cart_tags'               => __( 'Cart Product Tags', 'woofunnels-order-bump' ),
					'cart_item_count'         => __( 'Cart Item Count', 'woofunnels-order-bump' ),
					'cart_items_count'        => __( 'Cart Items Quantity Count', 'woofunnels-order-bump' ),
					'cart_item_type'          => __( 'Cart Item Type', 'woofunnels-order-bump' ),
					'cart_coupons'            => __( 'Cart Coupons', 'woofunnels-order-bump' ),
					'order_coupon_text_match' => __( 'Coupons - Text Match', 'woofunnels-order-bump' ),
					'cart_shipping_method'    => __( 'Cart Shipping Method', 'woofunnels-order-bump' ),
				),
				__( 'Customer', 'woofunnels-order-bump' )  => array(
					'customer_user'               => __( 'Customer', 'woofunnels-order-bump' ),
					'customer_role'               => __( 'Customer User Role', 'woofunnels-order-bump' ),
					'customer_purchased_products' => __( 'Customer Product Purchased', 'woofunnels-order-bump' ),
				),
				__( 'Geography', 'woofunnels-order-bump' ) => array(
					'cart_shipping_country' => __( 'Shipping Country', 'woofunnels-order-bump' ),
					'cart_billing_country'  => __( 'Billing Country', 'woofunnels-order-bump' ),
				),
				__( 'Date/Time', 'woofunnels-order-bump' ) => array(
					'day'  => __( 'Day', 'woofunnels-order-bump' ),
					'date' => __( 'Date', 'woofunnels-order-bump' ),
					'time' => __( 'Time', 'woofunnels-order-bump' ),
				),
			);
			if ( class_exists( 'WFACP_Core' ) ) {

				$types[ __( 'Checkout', 'woofunnels-order-bump' ) ] = [
					'wfacp_page' => __( 'FunnelKit Checkout pages', 'woofunnels-order-bump' ),

				];
			}

			return apply_filters( 'funnelkit_order_bump_rule_types', $types );

		}

		public function maybe_save_rules() {

			if ( null !== filter_input( INPUT_POST, 'wfob_rule' ) ) {
				$bump_id = filter_input( INPUT_POST, 'wfob_id' );
				update_post_meta( $bump_id, '_wfob_rules', $_POST['wfob_rule'] );
			}
		}

		public function set_environment_var( $key = 'order', $value = '' ) {
			if ( '' === $value ) {
				return;
			}
			$this->environments[ $key ] = $value;
		}

		public function reset_skipped( $result ) {
			$this->skipped = array();

			return $result;
		}

		public function get_environment_var( $key = 'order' ) {
			return isset( $this->environments[ $key ] ) ? $this->environments[ $key ] : false;
		}

		public function render_rules() {
			$bump_id    = filter_input( INPUT_GET, 'wfob_id', FILTER_SANITIZE_NUMBER_INT );
			$control_id = get_post_meta( $bump_id, '_bwf_ab_variation_of', true );
			if ( $control_id > 0 ) {
				include_once( $this->rule_views_path() . '/rules-blocked.php' );

				return;
			}

			if ( $bump_id > 0 ) {
				global $wfob_is_rules_saved;
				$wfob_is_rules_saved = get_post_meta( $bump_id, '_wfob_is_rules_saved', true );

			}
			include_once( $this->rule_views_path() . '/rules-head.php' );
			include_once( $this->rule_views_path() . '/rules-basic.php' );
			include_once( $this->rule_views_path() . '/rules-footer.php' );
			include_once( $this->rule_views_path() . '/rules-create.php' );
		}

		public function rule_views_path() {
			return WFOB_PLUGIN_DIR . '/rules/views';
		}

		public function add_rule_tab( $menu ) {
			$menu[35] = array(
				'icon' => 'dashicons dashicons-networking',
				'name' => __( 'Rules', 'woofunnels-order-bump' ),
				'key'  => 'rules',
			);

			return $menu;
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

					if ( in_array( $type, $this->excluded_rules_categories ) ) {
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

		protected function _validate_rule_block( $groups_category, $type, $environment ) {
			$iteration_results = array();
			if ( $groups_category && is_array( $groups_category ) && count( $groups_category ) ) {

				foreach ( $groups_category as $group_id => $group ) {
					$result        = null;
					$group_skipped = array();
					foreach ( $group as $rule_id => $rule ) {

						//just skipping the rule if excluded, so that it wont play any role in final judgement
						if ( in_array( $rule['rule_type'], $this->excluded_rules ) ) {

							continue;
						}
						$rule_object = $this->woocommerce_wfob_rule_get_rule_object( $rule['rule_type'] );

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
		 * @param string $rule_type The slug of the rule type to load.
		 *
		 * @return wfob_Rule_Base or superclass of wfob_Rule_Base
		 * @global array $woocommerce_wfob_rule_rules
		 *
		 */
		public function woocommerce_wfob_rule_get_rule_object( $rule_type ) {
			global $woocommerce_wfob_rule_rules;
			if ( isset( $woocommerce_wfob_rule_rules[ $rule_type ] ) ) {
				return $woocommerce_wfob_rule_rules[ $rule_type ];
			}
			$class = 'wfob_rule_' . $rule_type;
			if ( class_exists( $class ) ) {
				$woocommerce_wfob_rule_rules[ $rule_type ] = new $class;

				return $woocommerce_wfob_rule_rules[ $rule_type ];
			} else {
				return null;
			}
		}

		protected function _push_to_skipped( $rule ) {
			array_push( $this->skipped, $rule );
		}


	}

	if ( class_exists( 'WFOB_Rules' ) ) {
		WFOB_Core::register( 'rules', 'WFOB_Rules' );
	}
}