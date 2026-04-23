<?php
/**
 * Checkout Field Rules REST API
 *
 * Provides rule definitions and suggestions for the conditional fields UI.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Api
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Api;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout_Field_Rules class.
 *
 * @since 2.0.0
 */
class Checkout_Field_Rules extends WP_REST_Controller {

	private static $ins  = null;
	protected $namespace = 'funnelkit-app';
	protected $rest_base = 'funnel-checkout/field-rules';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function register_routes() {
		// Get checkout field rules.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args' => array(
					'step_id' => array(
						'description' => __( 'Checkout step ID for custom fields.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rules' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
			)
		);

		// Get suggestions for search-type rules.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<rule_slug>[a-zA-Z0-9_-]+)/suggestions',
			array(
				'args' => array(
					'rule_slug' => array(
						'description' => __( 'Rule slug.', 'woofunnels-aero-checkout' ),
						'type'        => 'string',
						'required'    => true,
					),
					'search'    => array(
						'description' => __( 'Search term.', 'woofunnels-aero-checkout' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_suggestions' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
			)
		);

		// Save conditional field conditions for a checkout step.
		register_rest_route(
			$this->namespace,
			'/funnel-checkout/(?P<step_id>[\d]+)/field-conditions',
			array(
				'args' => array(
					'step_id' => array(
						'description' => __( 'Checkout step ID.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_field_conditions' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			)
		);
	}

	public function get_read_api_permission_check() {
		return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'read' );
	}

	public function get_write_api_permission_check() {
		return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
	}

	/**
	 * Save conditional field conditions for a checkout step.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_field_conditions( WP_REST_Request $request ) {
		$step_id    = absint( $request->get_param( 'step_id' ) );
		$conditions = $request->get_json_params();

		if ( empty( $step_id ) ) {
			return new WP_Error( 'invalid_step', __( 'Invalid step ID.', 'woofunnels-aero-checkout' ), array( 'status' => 400 ) );
		}

		// Validate step_id is an actual wfacp_checkout post.
		if ( 'wfacp_checkout' !== get_post_type( $step_id ) ) {
			return new WP_Error( 'invalid_step', __( 'Invalid checkout step.', 'woofunnels-aero-checkout' ), array( 'status' => 400 ) );
		}

		// Validate conditions data structure before dispatching.
		if ( ! is_array( $conditions ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid conditions data.', 'woofunnels-aero-checkout' ), array( 'status' => 400 ) );
		}

		do_action( 'wfacp_save_field_conditions', $step_id, $conditions );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Conditions saved successfully.', 'woofunnels-aero-checkout' ),
			)
		);
	}

	/**
	 * Get checkout field conditional rules.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_rules( WP_REST_Request $request ) {
		$step_id = absint( $request->get_param( 'step_id' ) );
		$rules   = array();
		$rules[] = $this->get_user_rules();
		$rules[] = $this->get_cart_rules();

		$field_group = $this->get_field_rules( $step_id );
		if ( ! empty( $field_group['rules'] ) ) {
			$rules[] = $field_group;
		}

		return rest_ensure_response(
			array(
				'code'   => 200,
				'result' => $rules,
			)
		);
	}

	// ─── Rule groups ──────────────────────────────────────────────

	/**
	 * User group: Role and Status rules.
	 *
	 * @return array
	 */
	private function get_user_rules() {
		$rules = array();

		$rules[] = array(
			'slug'          => 'customer_role',
			'type'          => 'Select',
			'options'       => $this->get_user_roles(),
			'name'          => __( 'User Role', 'woofunnels-aero-checkout' ),
			'multiple'      => true,
			'operators'     => array(
				'in'    => __( 'is', 'woofunnels-aero-checkout' ),
				'notin' => __( 'is not', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 1,
		);

		$rules[] = array(
			'slug'          => 'customer_status',
			'type'          => 'Select',
			'options'       => array(
				'logged_in'  => __( 'Logged In', 'woofunnels-aero-checkout' ),
				'logged_out' => __( 'Logged Out', 'woofunnels-aero-checkout' ),
			),
			'name'          => __( 'User Status', 'woofunnels-aero-checkout' ),
			'multiple'      => false,
			'operators'     => array(
				'==' => __( 'is', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 2,
		);

		return array(
			'title' => __( 'User', 'woofunnels-aero-checkout' ),
			'rules' => $rules,
		);
	}

	/**
	 * Cart group: all cart-related rules.
	 *
	 * @return array
	 */
	private function get_cart_rules() {
		$rules = array();

		$rules[] = array(
			'slug'          => 'cart_total',
			'type'          => 'Text',
			'options'       => array(),
			'name'          => __( 'Cart Total', 'woofunnels-aero-checkout' ),
			'multiple'      => false,
			'operators'     => array(
				'==' => __( 'is equal to', 'woofunnels-aero-checkout' ),
				'!=' => __( 'is not equal to', 'woofunnels-aero-checkout' ),
				'>'  => __( 'is greater than', 'woofunnels-aero-checkout' ),
				'<'  => __( 'is less than', 'woofunnels-aero-checkout' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-aero-checkout' ),
				'<=' => __( 'is less or equal to', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 1,
		);

		$rules[] = array(
			'slug'          => 'cart_items',
			'type'          => 'Search',
			'options'       => array(),
			'name'          => __( 'Cart Items', 'woofunnels-aero-checkout' ),
			'multiple'      => true,
			'operators'     => array(
				'any'  => __( 'matches any of', 'woofunnels-aero-checkout' ),
				'all'  => __( 'matches all of', 'woofunnels-aero-checkout' ),
				'none' => __( 'matches none of', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 2,
		);

		$rules[] = array(
			'slug'          => 'cart_category',
			'type'          => 'Search',
			'options'       => array(),
			'name'          => __( 'Cart Category', 'woofunnels-aero-checkout' ),
			'multiple'      => true,
			'operators'     => array(
				'any'  => __( 'matches any of', 'woofunnels-aero-checkout' ),
				'all'  => __( 'matches all of', 'woofunnels-aero-checkout' ),
				'none' => __( 'matches none of', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 3,
		);

		$rules[] = array(
			'slug'          => 'cart_tag',
			'type'          => 'Search',
			'options'       => array(),
			'name'          => __( 'Cart Tag', 'woofunnels-aero-checkout' ),
			'multiple'      => true,
			'operators'     => array(
				'any'  => __( 'matches any of', 'woofunnels-aero-checkout' ),
				'all'  => __( 'matches all of', 'woofunnels-aero-checkout' ),
				'none' => __( 'matches none of', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 4,
		);

		$rules[] = array(
			'slug'          => 'cart_item_count',
			'type'          => 'Number',
			'options'       => array(),
			'name'          => __( 'Cart Item Count', 'woofunnels-aero-checkout' ),
			'multiple'      => false,
			'operators'     => array(
				'==' => __( 'is equal to', 'woofunnels-aero-checkout' ),
				'!=' => __( 'is not equal to', 'woofunnels-aero-checkout' ),
				'>'  => __( 'is greater than', 'woofunnels-aero-checkout' ),
				'<'  => __( 'is less than', 'woofunnels-aero-checkout' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-aero-checkout' ),
				'<=' => __( 'is less or equal to', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '0',
			'priority'      => 5,
			'min'           => 0,
			'step'          => 1,
		);

		$rules[] = array(
			'slug'          => 'cart_coupons',
			'type'          => 'Search',
			'options'       => array(),
			'name'          => __( 'Cart Coupons', 'woofunnels-aero-checkout' ),
			'multiple'      => true,
			'operators'     => array(
				'any'  => __( 'matches any of', 'woofunnels-aero-checkout' ),
				'none' => __( 'matches none of', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 6,
		);

		$rules[] = array(
			'slug'          => 'cart_is_virtual',
			'type'          => 'Select',
			'options'       => array(
				'yes' => __( 'Yes', 'woofunnels-aero-checkout' ),
				'no'  => __( 'No', 'woofunnels-aero-checkout' ),
			),
			'name'          => __( 'Cart is Virtual', 'woofunnels-aero-checkout' ),
			'multiple'      => false,
			'operators'     => array(
				'==' => __( 'is', 'woofunnels-aero-checkout' ),
				'!=' => __( 'is not', 'woofunnels-aero-checkout' ),
			),
			'readable_text' => '{{key /}} {{rule /}} {{value /}}',
			'value_label'   => '',
			'default'       => '',
			'priority'      => 7,
		);

		return array(
			'title' => __( 'Cart', 'woofunnels-aero-checkout' ),
			'rules' => $rules,
		);
	}

	/**
	 * Fields group: dynamically generated from all available checkout fields.
	 *
	 * Returns a generic schema not bound to any specific step. The JS side
	 * handles filtering which fields to show based on what is on the checkout.
	 *
	 * @return array
	 */
	private function get_field_rules( $step_id = 0 ) {
		$shipping_rules = array();
		$billing_rules  = array();
		$generic_rules  = array();
		$custom_rules   = array();

		if ( class_exists( 'WFACP_Common' ) ) {
			// Get shipping address fields — only keep shipping_ prefixed ones.
			$shipping_fields = \WFACP_Common::get_address_fields( 'shipping_', true );
			if ( is_array( $shipping_fields ) ) {
				foreach ( $shipping_fields as $field_id => $field ) {
					if ( ! is_array( $field ) || isset( $field['fields_options'] ) ) {
						continue;
					}
					if ( 0 !== strpos( $field_id, 'shipping_' ) ) {
						continue;
					}
					$field['id']                 = $field_id;
					$field['_subgroup']          = __( 'Shipping Address', 'woofunnels-aero-checkout' );
					$shipping_rules[ $field_id ] = $field;
				}
			}

			// Get billing address fields — only keep billing_ prefixed ones.
			$billing_fields = \WFACP_Common::get_address_fields( 'billing_', true );
			if ( is_array( $billing_fields ) ) {
				foreach ( $billing_fields as $field_id => $field ) {
					if ( ! is_array( $field ) || isset( $field['fields_options'] ) ) {
						continue;
					}
					if ( 0 !== strpos( $field_id, 'billing_' ) ) {
						continue;
					}
					$field['id']                = $field_id;
					$field['_subgroup']         = __( 'Billing Address', 'woofunnels-aero-checkout' );
					$billing_rules[ $field_id ] = $field;
				}
			}

			// Get default advanced fields (generic, not step-specific).
			$advanced_fields = \WFACP_Common::get_advanced_fields();
			if ( is_array( $advanced_fields ) ) {
				foreach ( $advanced_fields as $field_id => $field ) {
					if ( is_array( $field ) ) {
						$field['id']                = $field_id;
						$generic_rules[ $field_id ] = $field;
					}
				}
			}

			// Get step-specific custom fields.
			if ( ! empty( $step_id ) ) {
				$page_fields = \WFACP_Common::get_page_custom_fields( $step_id );
				if ( is_array( $page_fields ) ) {
					// Custom fields are in the 'advanced' key, mixed with standard advanced fields.
					$advanced_keys = array_keys( $advanced_fields );
					foreach ( $page_fields as $section_fields ) {
						if ( ! is_array( $section_fields ) ) {
							continue;
						}
						foreach ( $section_fields as $field_id => $field ) {
							// Skip standard advanced fields (already in generic_rules).
							if ( in_array( $field_id, $advanced_keys, true ) ) {
								continue;
							}
							if ( is_array( $field ) ) {
								$field['id']               = $field_id;
								$field['_subgroup']        = __( 'Custom Fields', 'woofunnels-aero-checkout' );
								$custom_rules[ $field_id ] = $field;
							}
						}
					}
				}
			}
		}

		// Combine: shipping, billing, generic, then custom.
		$all_fields  = array_merge( $shipping_rules, $billing_rules, $generic_rules, $custom_rules );
		$field_rules = array();
		$priority    = 1;

		foreach ( $all_fields as $field_id => $field ) {
			$type = ! empty( $field['type'] ) ? $field['type'] : 'text';

			// Skip display-only, special, file upload, and toggle fields.
			$allowed_html_keys = array( 'shipping_calculator', 'order_total', 'order_summary', 'order_coupon', 'bwfan_birthday_date' );
			if ( 'wfacp_html' === $type && ! in_array( $field_id, $allowed_html_keys, true ) ) {
				continue;
			}
			if ( in_array( $type, array( 'wfacp_wysiwyg', 'product', 'hidden', 'wfacp_file_upload' ), true ) ) {
				continue;
			}
			// Skip same-as toggle checkboxes.
			if ( false !== strpos( $field_id, 'same_as_' ) ) {
				continue;
			}

			$rule = $this->build_field_rule( $field_id, $field, $priority );
			if ( $rule ) {
				if ( ! empty( $field['_subgroup'] ) ) {
					$rule['subgroup'] = $field['_subgroup'];
				}
				$field_rules[] = $rule;
				++$priority;
			}
		}

		return array(
			'title' => __( 'Fields', 'woofunnels-aero-checkout' ),
			'rules' => $field_rules,
		);
	}

	/**
	 * Build a rule schema for a single checkout field.
	 *
	 * @param string $field_id Field ID.
	 * @param array  $field    Field data.
	 * @param int    $priority Rule priority.
	 *
	 * @return array|null
	 */
	private function build_field_rule( $field_id, $field, $priority ) {
		$label = ! empty( $field['data_label'] ) ? $field['data_label'] : ( ! empty( $field['label'] ) ? $field['label'] : $field_id );
		$type  = ! empty( $field['type'] ) ? $field['type'] : 'text';
		$slug  = 'field_' . $field_id;

		switch ( $type ) {
			case 'select':
			case 'wfacp_radio':
			case 'dropdown':
			case 'select2':
				$options = $this->get_field_options( $field );

				return array(
					'slug'          => $slug,
					'type'          => 'Select',
					'options'       => $options,
					'name'          => $label,
					'multiple'      => false,
					'operators'     => array(
						'==' => __( 'is', 'woofunnels-aero-checkout' ),
						'!=' => __( 'is not', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);

			case 'multiselect':
				$options = $this->get_field_options( $field );

				return array(
					'slug'          => $slug,
					'type'          => 'Search',
					'options'       => $options,
					'name'          => $label,
					'multiple'      => true,
					'operators'     => array(
						'any'  => __( 'matches any of', 'woofunnels-aero-checkout' ),
						'all'  => __( 'matches all of', 'woofunnels-aero-checkout' ),
						'none' => __( 'matches none of', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);

			case 'country':
				return array(
					'slug'          => $slug,
					'type'          => 'Search',
					'options'       => array(),
					'name'          => $label,
					'multiple'      => true,
					'operators'     => array(
						'any'  => __( 'matches any of', 'woofunnels-aero-checkout' ),
						'all'  => __( 'matches all of', 'woofunnels-aero-checkout' ),
						'none' => __( 'matches none of', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);

			case 'state':
				return array(
					'slug'          => $slug,
					'type'          => 'Text',
					'options'       => array(),
					'name'          => $label,
					'multiple'      => false,
					'operators'     => array(
						'=='           => __( 'is', 'woofunnels-aero-checkout' ),
						'!='           => __( 'is not', 'woofunnels-aero-checkout' ),
						'is_blank'     => __( 'is blank', 'woofunnels-aero-checkout' ),
						'is_not_blank' => __( 'is not blank', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);

			case 'checkbox':
				return array(
					'slug'          => $slug,
					'type'          => 'Select',
					'options'       => array(
						'1' => __( 'Checked', 'woofunnels-aero-checkout' ),
						'0' => __( 'Unchecked', 'woofunnels-aero-checkout' ),
					),
					'name'          => $label,
					'multiple'      => false,
					'operators'     => array(
						'==' => __( 'is', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);

			case 'number':
				return array(
					'slug'          => $slug,
					'type'          => 'Text',
					'options'       => array(),
					'name'          => $label,
					'multiple'      => false,
					'operators'     => array(
						'==' => __( 'is equal to', 'woofunnels-aero-checkout' ),
						'!=' => __( 'is not equal to', 'woofunnels-aero-checkout' ),
						'>'  => __( 'is greater than', 'woofunnels-aero-checkout' ),
						'<'  => __( 'is less than', 'woofunnels-aero-checkout' ),
						'>=' => __( 'is greater or equal to', 'woofunnels-aero-checkout' ),
						'<=' => __( 'is less or equal to', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);

			default:
				// text, email, tel, url, textarea, password, etc.
				return array(
					'slug'          => $slug,
					'type'          => 'Text',
					'options'       => array(),
					'name'          => $label,
					'multiple'      => false,
					'operators'     => array(
						'=='               => __( 'is', 'woofunnels-aero-checkout' ),
						'!='               => __( 'is not', 'woofunnels-aero-checkout' ),
						'contains'         => __( 'contains', 'woofunnels-aero-checkout' ),
						'does_not_contain' => __( 'does not contain', 'woofunnels-aero-checkout' ),
						'is_blank'         => __( 'is blank', 'woofunnels-aero-checkout' ),
						'is_not_blank'     => __( 'is not blank', 'woofunnels-aero-checkout' ),
					),
					'readable_text' => '{{key /}} {{rule /}} {{value /}}',
					'value_label'   => '',
					'default'       => '',
					'priority'      => $priority,
				);
		}
	}

	/**
	 * Extract options from a field definition.
	 *
	 * @param array $field Field data.
	 *
	 * @return array
	 */
	private function get_field_options( $field ) {
		if ( empty( $field['options'] ) ) {
			return array();
		}

		if ( is_array( $field['options'] ) ) {
			return $field['options'];
		}

		// Handle pipe-separated options string.
		if ( is_string( $field['options'] ) ) {
			$options = array();
			$parts   = explode( '|', $field['options'] );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$options[ $part ] = $part;
				}
			}

			return $options;
		}

		return array();
	}

	/**
	 * Get suggestions for search-type rules.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_suggestions( WP_REST_Request $request ) {
		$rule_slug = $request->get_param( 'rule_slug' );
		$search    = $request->get_param( 'search' );
		$search    = ! empty( $search ) ? sanitize_text_field( $search ) : '';
		$result    = array();

		switch ( $rule_slug ) {
			case 'cart_items':
			case 'cart_item':
				$result = $this->search_products( $search );
				break;

			case 'cart_category':
				$result = $this->search_product_categories( $search );
				break;

			case 'cart_tag':
				$result = $this->search_product_tags( $search );
				break;

			case 'cart_coupons':
				$result = $this->search_coupons( $search );
				break;

			case 'customer_user':
				$result = $this->search_users( $search );
				break;

			default:
				// Handle dynamic field-based country rules (field_billing_country, field_shipping_country, etc.).
				if ( 0 === strpos( $rule_slug, 'field_' ) && false !== strpos( $rule_slug, '_country' ) ) {
					$result = $this->search_countries( $search );
				}
				break;
		}

		return rest_ensure_response(
			array(
				'code'   => 200,
				'result' => $result,
			)
		);
	}

	// ─── Helper: Get WooCommerce data ──────────────────────────────

	private function get_shipping_methods() {
		$methods = array();

		if ( ! class_exists( 'WooCommerce' ) ) {
			return $methods;
		}

		$shipping_zones = \WC_Shipping_Zones::get_zones();

		foreach ( $shipping_zones as $zone ) {
			if ( ! empty( $zone['shipping_methods'] ) ) {
				foreach ( $zone['shipping_methods'] as $method ) {
					$methods[ $method->get_rate_id() ] = $zone['zone_name'] . ': ' . $method->get_title();
				}
			}
		}

		// Also include zone 0 (rest of the world).
		$zone_0 = new \WC_Shipping_Zone( 0 );
		foreach ( $zone_0->get_shipping_methods() as $method ) {
			$methods[ $method->get_rate_id() ] = $zone_0->get_zone_name() . ': ' . $method->get_title();
		}

		return $methods;
	}

	private function get_payment_gateways() {
		$gateways = array();

		if ( ! class_exists( 'WooCommerce' ) || ! \WC()->payment_gateways() ) {
			return $gateways;
		}

		$available = \WC()->payment_gateways()->payment_gateways();

		foreach ( $available as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				$gateways[ $gateway->id ] = $gateway->get_title();
			}
		}

		return $gateways;
	}

	private function get_user_roles() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$roles  = array();
		$result = get_editable_roles();

		if ( ! empty( $result ) ) {
			foreach ( $result as $role => $details ) {
				$roles[ $role ] = translate_user_role( $details['name'] );
			}
		}

		return $roles;
	}

	// ─── Suggestion search helpers ─────────────────────────────────

	private function search_products( $search ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$product_ids = get_posts( $args );
		$results     = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$results[ $product_id ] = $product->get_name();
			}
		}

		return $results;
	}

	private function search_product_categories( $search ) {
		$terms   = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'search'     => $search,
				'hide_empty' => false,
				'number'     => 20,
			)
		);
		$results = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[ $term->term_id ] = $term->name;
			}
		}

		return $results;
	}

	private function search_product_tags( $search ) {
		$terms   = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'search'     => $search,
				'hide_empty' => false,
				'number'     => 20,
			)
		);
		$results = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[ $term->term_id ] = $term->name;
			}
		}

		return $results;
	}

	private function search_coupons( $search ) {
		$args = array(
			'post_type'      => 'shop_coupon',
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
		);

		$coupons = get_posts( $args );
		$results = array();

		foreach ( $coupons as $coupon ) {
			$results[ $coupon->post_title ] = $coupon->post_title;
		}

		return $results;
	}

	private function search_users( $search ) {
		$args = array(
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'number'         => 20,
		);

		$users   = get_users( $args );
		$results = array();

		foreach ( $users as $user ) {
			$results[ $user->ID ] = $user->display_name . ' (' . $user->user_email . ')';
		}

		return $results;
	}

	private function search_countries( $search ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$countries = \WC()->countries->get_countries();
		$results   = array();
		$search    = strtolower( $search );

		foreach ( $countries as $code => $name ) {
			if ( empty( $search ) || false !== strpos( strtolower( $name ), $search ) || false !== strpos( strtolower( $code ), $search ) ) {
				$results[ $code ] = $name;
			}
		}

		return $results;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private function debug_log( $message ) {
		// Debug logging disabled.
	}
}
