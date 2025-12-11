<?php
if ( ! class_exists( 'WFTY_Rule_Base' ) ) {
	/**
	 * Base class for a Conditional_Content rule.
	 */
	class WFTY_Rule_Base {

		public $supports = array( 'order' );

		public function __construct( $name ) {

		}

		protected function get_rule_instance() {
			return WFTY_Rules::get_instance();
		}

		/**
		 * Get's the list of possibile values for the rule.
		 *
		 * Override to return the correct list of possibile values for your rule object.
		 * @return array
		 */
		public function get_possible_rule_values() {
			return array();
		}

		/**
		 * Gets the list of possibile rule operators available for this rule object.
		 *
		 * Override to return your own list of operators.
		 *
		 * @return array
		 */
		public function get_possible_rule_operators() {
			return array(
				'==' => __( "is equal to", 'funnel-builder-powerpack' ),
				'!=' => __( "is not equal to", 'funnel-builder-powerpack' ),
			);
		}

		/*
		 * Gets the input object type slug for this rule object.
		 *
		 * @return string
		 */

		public function get_condition_input_type() {
			return 'Select';
		}

		/**
		 * Checks if the conditions defined for this rule object have been met.
		 *
		 * @return boolean
		 */
		public function is_match( $rule_data, $env = 'cart' ) {
			return false;
		}

		/**
		 * Helper function to wrap the return value from is_match and apply filters or other modifications in sub classes.
		 *
		 * @param boolean $result The result that should be returned.
		 * @param array $rule_data The array config object for the current rule.
		 *
		 * @return boolean
		 */
		public function return_is_match( $result, $rule_data ) {
			return apply_filters( 'wfty_rules_is_match', $result, $rule_data );
		}


		public function supports( $env ) {

			return in_array( $env, $this->supports );
		}

		public function get_nice_string( $rule ) {
			return json_encode( $rule );
		}

		public function get_terms_title( $terms ) {
			$string = [];
			foreach ( $terms as $term ) {
				$term     = get_term_by( 'id', $term, 'product_tag' );
				$string[] = $term->name;
			}

			return implode( ',', $string );
		}

		public function get_category_title( $terms ) {
			$string = [];
			foreach ( $terms as $term ) {
				$term     = get_term_by( 'id', $term, 'product_cat' );
				$string[] = $term->name;
			}

			return implode( ',', $string );
		}

		public function get_product_type( $terms ) {
			$string = [];
			foreach ( $terms as $term ) {
				$term     = get_term_by( 'id', $term, 'product_type' );
				$string[] = $term->name;
			}

			return implode( ',', $string );
		}

		public function get_coupons_title( $coupons ) {
			$string = [];
			foreach ( $coupons as $coupon ) {
				$string[] = $coupon;
			}

			return implode( ',', $string );
		}

		public function get_gateways_title( $gateways ) {
			$result = [];

			foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
				foreach ( $gateways as $gate ) {

					if ( $gate === $gateway->id ) {
						$result[] = ! empty( $gateway->get_title() ) ? $gateway->get_title() : $gateway->get_method_title();
					}

				}
			}

			return implode( ',', $result );
		}

		public function get_countries_title( $countries ) {
			$result = [];

			foreach ( WC()->countries->get_allowed_countries() as $country => $country_title ) {
				if ( in_array( $country, $countries, true ) ) {
					$result[] = $country_title;
				}
			}

			return implode( ',', $result );
		}

		public function get_states_title( $countries ) {
			$result = [];

			foreach ( WC()->countries->get_allowed_countries() as $country => $country_title ) {
				if ( in_array( $country, $countries, true ) ) {
					$result[] = $country_title;
				}
			}

			return implode( ',', $result );
		}

		public function get_users_name( $names ) {
			$result = [];

			foreach ( $names as $user ) {

				$result[] = get_user_by( 'id', $user )->display_name;
			}

			return implode( ',', $result );
		}

		public function get_user_role_title( $names ) {
			$result         = [];
			$editable_roles = get_editable_roles();
			foreach ( $names as $user ) {

				$result[] = translate_user_role( $editable_roles[ $user ]['name'] );
			}

			return implode( ',', $result );
		}

		public function get_shipping_method_title( $method_ids ) {
			$result = [];

			foreach ( WC()->shipping()->get_shipping_methods() as $country => $country_title ) {
				if ( in_array( $country, $method_ids, true ) ) {
					$result[] = is_callable( array( $country_title, 'get_method_title' ) ) ? $country_title->get_method_title() : $country_title->get_title();
				}
			}

			return implode( ',', $result );
		}

		public function get_product_title( $items ) {
			$result = [];

			foreach ( $items as $item ) {
				$object   = wc_get_product( $item );
				$result[] = is_callable( array( $object, 'get_title' ) ) ? $object->get_title() : '';

			}

			return implode( ',', $result );
		}

		public function get_day_title( $items ) {
			$options = array(
				'0' => __( 'Sunday', 'funnel-builder-powerpack' ),
				'1' => __( 'Monday', 'funnel-builder-powerpack' ),
				'2' => __( 'Tuesday', 'funnel-builder-powerpack' ),
				'3' => __( 'Wednesday', 'funnel-builder-powerpack' ),
				'4' => __( 'Thursday', 'funnel-builder-powerpack' ),
				'5' => __( 'Friday', 'funnel-builder-powerpack' ),
				'6' => __( 'Saturday', 'funnel-builder-powerpack' ),

			);
			$result  = [];

			foreach ( $items as $item ) {

				$result[] = $options[ $item ];

			}

			return implode( ',', $result );
		}

		public function get_operators_string( $operator ) {
			switch ( $operator ) {
				case 'any':
					return __( 'matches any of', 'woofunnels-order-bump' );
					break;
				case 'none':
					return __( 'matches none of', 'woofunnels-order-bump' );
					break;
				case 'all':
					return __( 'matches all of', 'woofunnels-order-bump' );
					break;
				case 'exist':
					return __( 'contains', 'woofunnels-order-bump' );
					break;
				case 'not_exist':
					return __( 'doesn\'t contain ', 'woofunnels-order-bump' );
					break;
				case '!=':
					return __( 'is not', 'woofunnels-order-bump' );
					break;
				case '==':
					return __( 'is', 'woofunnels-order-bump' );
					break;
				case 'in':
					return __( 'is', 'woofunnels-order-bump' );
					break;
				case 'notin':
					return __( 'is not', 'woofunnels-order-bump' );
					break;
				case '>':
					return __( 'is greater than', 'funnel-builder-powerpack' );
					break;
				case '<':
					return __( 'is less than', 'funnel-builder-powerpack' );
					break;
				case '>=':
					return __( 'is greater or equal to', 'funnel-builder-powerpack' );
					break;
				case '<=':
					return __( 'is less or equal to', 'funnel-builder-powerpack' );
					break;
				case 'is':
					return __( 'has', 'funnel-builder-powerpack' );
					break;
				case 'is_not':
					return __( 'does not have', 'funnel-builder-powerpack' );
					break;
			}
		}

	}
}