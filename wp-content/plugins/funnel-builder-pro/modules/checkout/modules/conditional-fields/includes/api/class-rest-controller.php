<?php
/**
 * REST Controller
 *
 * REST API endpoints for conditional fields admin.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Api
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Api;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Rule;
use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST_Controller class.
 *
 * @since 2.4.0
 */
#[\AllowDynamicProperties]
class Rest_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'fkcf/v1';

	/**
	 * Singleton instance.
	 *
	 * @var Rest_Controller
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.4.0
	 * @return Rest_Controller
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @since 2.4.0
	 */
	public function register_routes() {
		$permission = array( $this, 'check_admin_permission' );

		// Editor data (fields + section rules).
		register_rest_route(
			self::NAMESPACE,
			'/checkout/(?P<checkout_id>\d+)/editor-data',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_editor_data' ),
				'permission_callback' => $permission,
				'args'                => array(
					'checkout_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return $param > 0;
						},
					),
				),
			)
		);

		// Load checkout fields.
		register_rest_route(
			self::NAMESPACE,
			'/checkout/(?P<checkout_id>\d+)/fields',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fields' ),
				'permission_callback' => $permission,
				'args'                => array(
					'checkout_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return $param > 0;
						},
					),
				),
			)
		);

		// Field rules: get, save, delete.
		register_rest_route(
			self::NAMESPACE,
			'/checkout/(?P<checkout_id>\d+)/field/(?P<field_id>[^/]+)/rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_field_rules' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_field_rules' ),
					'permission_callback' => $permission,
					'args'                => array(
						'rule_data' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_field_rules' ),
					'permission_callback' => $permission,
				),
			)
		);

		// Section rule: get, save, delete.
		register_rest_route(
			self::NAMESPACE,
			'/checkout/(?P<checkout_id>\d+)/section/(?P<section_id>[^/]+)/rule',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_section_rule' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_section_rule' ),
					'permission_callback' => $permission,
					'args'                => array(
						'rule_data' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_section_rule' ),
					'permission_callback' => $permission,
				),
			)
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_admin_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Post-level authorization: verify checkout_id is a valid wfacp_checkout post the user can edit.
		$checkout_id = $request->get_param( 'checkout_id' );
		if ( $checkout_id ) {
			$checkout_id = absint( $checkout_id );
			if ( 'wfacp_checkout' !== get_post_type( $checkout_id ) ) {
				return false;
			}
			if ( ! current_user_can( 'edit_post', $checkout_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get editor data (fields + section rules).
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_editor_data( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );

		$sections      = $this->get_checkout_sections( $checkout_id );
		$all_rules_v2  = Rule_Storage::get_all_rules_v2( $checkout_id );
		$section_rules = array();

		foreach ( array_keys( $sections ) as $section_id ) {
			$section_rule = null;
			if ( isset( $all_rules_v2['sections'][ $section_id ]['section_rule'] ) ) {
				$section_rule = $all_rules_v2['sections'][ $section_id ]['section_rule'];
			}
			$section_rules[ $section_id ] = $section_rule ? $section_rule->to_array() : null;
		}

		$fields            = $this->get_checkout_fields( $checkout_id );
		$rules             = Rule_Storage::get_rules( $checkout_id );
		$fields_with_rules = array();

		foreach ( $fields as $field ) {
			$field_id = $field['id'];
			$rule     = isset( $rules[ $field_id ] ) ? $rules[ $field_id ] : null;
			$has_rule = $rule instanceof Rule;

			$fields_with_rules[] = array(
				'id'        => $field_id,
				'label'     => $field['label'],
				'section'   => $field['section'],
				'type'      => $field['type'],
				'has_rules' => $has_rule,
				'enabled'   => $has_rule && $rule->is_enabled(),
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'fields'        => $fields_with_rules,
					'section_rules' => $section_rules,
				),
			),
			200
		);
	}

	/**
	 * Get checkout fields.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_fields( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );

		$fields            = $this->get_checkout_fields( $checkout_id );
		$rules             = Rule_Storage::get_rules( $checkout_id );
		$fields_with_rules = array();

		foreach ( $fields as $field ) {
			$field_id = $field['id'];
			$rule     = isset( $rules[ $field_id ] ) ? $rules[ $field_id ] : null;
			$has_rule = $rule instanceof Rule;

			$fields_with_rules[] = array(
				'id'        => $field_id,
				'label'     => $field['label'],
				'section'   => $field['section'],
				'type'      => $field['type'],
				'has_rules' => $has_rule,
				'enabled'   => $has_rule && $rule->is_enabled(),
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'fields' => $fields_with_rules ),
			),
			200
		);
	}

	/**
	 * Get field rules.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_field_rules( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );
		$field_id    = sanitize_text_field( $request->get_param( 'field_id' ) );

		if ( ! $field_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		$rule       = Rule_Storage::get_field_rule( $checkout_id, $field_id );
		$rule_array = $rule ? $rule->to_array() : null;

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'rules' => $rule_array ),
			),
			200
		);
	}

	/**
	 * Save field rules.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_field_rules( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );
		$field_id    = sanitize_text_field( $request->get_param( 'field_id' ) );
		$params      = $request->get_json_params();
		$rule_data   = isset( $params['rule_data'] ) ? $params['rule_data'] : $request->get_param( 'rule_data' );

		if ( ! $field_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		if ( empty( $rule_data ) || ! is_array( $rule_data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid rule data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		// Validate rule data structure: must have 'action' and 'rule_groups'.
		if ( ! isset( $rule_data['action'] ) || ! in_array( $rule_data['action'], array( 'show', 'hide' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid rule action. Must be "show" or "hide".', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		if ( ! isset( $rule_data['rule_groups'] ) || ! is_array( $rule_data['rule_groups'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Rule groups are required.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		$result = Rule_Storage::save_field_rule( $checkout_id, $field_id, $rule_data );

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'message' => __( 'Rules saved successfully.', 'woofunnels-aero-checkout' ) ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'data'    => array( 'message' => __( 'Failed to save rules.', 'woofunnels-aero-checkout' ) ),
			),
			500
		);
	}

	/**
	 * Delete field rules.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_field_rules( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );
		$field_id    = sanitize_text_field( $request->get_param( 'field_id' ) );

		if ( ! $field_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		$result = Rule_Storage::delete_field_rule( $checkout_id, $field_id );

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'message' => __( 'Rules deleted successfully.', 'woofunnels-aero-checkout' ) ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'data'    => array( 'message' => __( 'Failed to delete rules.', 'woofunnels-aero-checkout' ) ),
			),
			500
		);
	}

	/**
	 * Get section rule.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_section_rule( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );
		$section_id  = sanitize_text_field( $request->get_param( 'section_id' ) );

		if ( ! $section_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		$section_rule = Rule_Storage::get_section_rule( $checkout_id, $section_id );
		$rule_array   = $section_rule ? $section_rule->to_array() : null;

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'rule' => $rule_array ),
			),
			200
		);
	}

	/**
	 * Save section rule.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_section_rule( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );
		$section_id  = sanitize_text_field( $request->get_param( 'section_id' ) );
		$params      = $request->get_json_params();
		$rule_data   = isset( $params['rule_data'] ) ? $params['rule_data'] : $request->get_param( 'rule_data' );

		if ( ! $section_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		if ( empty( $rule_data ) || ! is_array( $rule_data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid rule data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		// Validate rule data structure: must have 'action' and 'rule_groups'.
		if ( ! isset( $rule_data['action'] ) || ! in_array( $rule_data['action'], array( 'show', 'hide' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid rule action. Must be "show" or "hide".', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		if ( ! isset( $rule_data['rule_groups'] ) || ! is_array( $rule_data['rule_groups'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Rule groups are required.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		$result = Rule_Storage::save_section_rule( $checkout_id, $section_id, $rule_data );

		if ( $result ) {
			$saved_rule = Rule_Storage::get_section_rule( $checkout_id, $section_id );
			$rule_array = $saved_rule ? $saved_rule->to_array() : null;

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'message' => __( 'Section rule saved successfully.', 'woofunnels-aero-checkout' ),
						'rule'    => $rule_array,
					),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'data'    => array( 'message' => __( 'Failed to save section rule.', 'woofunnels-aero-checkout' ) ),
			),
			500
		);
	}

	/**
	 * Delete section rule.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_section_rule( $request ) {
		$checkout_id = $request->get_param( 'checkout_id' );
		$section_id  = sanitize_text_field( $request->get_param( 'section_id' ) );

		if ( ! $section_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ),
				),
				400
			);
		}

		$result = Rule_Storage::delete_section_rule( $checkout_id, $section_id );

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'message' => __( 'Section rule deleted successfully.', 'woofunnels-aero-checkout' ) ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'data'    => array( 'message' => __( 'Failed to delete section rule.', 'woofunnels-aero-checkout' ) ),
			),
			500
		);
	}

	/**
	 * Get sections for a checkout page.
	 *
	 * @since 2.3.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array of sections with IDs and labels.
	 */
	private function get_checkout_sections( $checkout_id ) {
		if ( ! $checkout_id ) {
			return array();
		}

		$page_layout = get_post_meta( $checkout_id, '_wfacp_page_layout', true );

		if ( empty( $page_layout ) || ! is_array( $page_layout ) || ! isset( $page_layout['fieldsets'] ) ) {
			return array(
				'billing'  => __( 'Billing Information', 'woofunnels-aero-checkout' ),
				'shipping' => __( 'Shipping Address', 'woofunnels-aero-checkout' ),
				'account'  => __( 'Account Details', 'woofunnels-aero-checkout' ),
				'order'    => __( 'Order Notes', 'woofunnels-aero-checkout' ),
				'advanced' => __( 'Additional Fields', 'woofunnels-aero-checkout' ),
			);
		}

		$sections = array();

		foreach ( $page_layout['fieldsets'] as $step_key => $fieldsets ) {
			if ( ! is_array( $fieldsets ) ) {
				continue;
			}

			foreach ( $fieldsets as $index => $fieldset ) {
				if ( ! is_array( $fieldset ) ) {
					continue;
				}

				$section_name = isset( $fieldset['name'] ) ? $fieldset['name'] : 'Section ' . ( $index + 1 );
				$section_key  = $step_key . '_fieldset_' . $index;

				$sections[ $section_key ] = $section_name;
			}
		}

		return $sections;
	}

	/**
	 * Get checkout fields for a checkout page.
	 *
	 * @since 2.0.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array of fields.
	 */
	private function get_checkout_fields( $checkout_id ) {
		if ( ! $checkout_id ) {
			return array();
		}

		$page_layout = get_post_meta( $checkout_id, '_wfacp_page_layout', true );

		if ( empty( $page_layout ) || ! is_array( $page_layout ) || ! isset( $page_layout['fieldsets'] ) ) {
			return $this->get_default_checkout_fields();
		}

		$all_fields = array();

		foreach ( $page_layout['fieldsets'] as $step_key => $fieldsets ) {
			if ( ! is_array( $fieldsets ) ) {
				continue;
			}

			foreach ( $fieldsets as $index => $fieldset ) {
				if ( ! is_array( $fieldset ) || ! isset( $fieldset['fields'] ) || ! is_array( $fieldset['fields'] ) ) {
					continue;
				}

				$section_key = $step_key . '_fieldset_' . $index;

				foreach ( $fieldset['fields'] as $field ) {
					if ( ! is_array( $field ) || ! isset( $field['id'] ) ) {
						continue;
					}

					$field_id = $field['id'];
					$label    = $this->get_field_label( $field_id, $field );
					$type     = isset( $field['type'] ) ? $field['type'] : 'text';

					$all_fields[] = array(
						'id'      => $field_id,
						'label'   => $label,
						'section' => $section_key,
						'type'    => $type,
					);
				}
			}
		}

		return $all_fields;
	}

	/**
	 * Get label for a field.
	 *
	 * @since 2.0.5
	 * @param string $field_id Field ID.
	 * @param array  $field_data Field data.
	 * @return string Field label.
	 */
	private function get_field_label( $field_id, $field_data ) {
		if ( isset( $field_data['label'] ) && ! empty( $field_data['label'] ) ) {
			return $field_data['label'];
		}
		if ( isset( $field_data['field_label'] ) && ! empty( $field_data['field_label'] ) ) {
			return $field_data['field_label'];
		}
		if ( isset( $field_data['placeholder'] ) && ! empty( $field_data['placeholder'] ) ) {
			return $field_data['placeholder'];
		}

		return ucwords( str_replace( array( '_', '-' ), ' ', $field_id ) );
	}

	/**
	 * Get default checkout fields.
	 *
	 * @since 2.0.0
	 * @return array Array of default fields.
	 */
	private function get_default_checkout_fields() {
		return array(
			array(
				'id'      => 'billing_first_name',
				'label'   => __( 'First Name', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_last_name',
				'label'   => __( 'Last Name', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_company',
				'label'   => __( 'Company Name', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_email',
				'label'   => __( 'Email Address', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'email',
			),
			array(
				'id'      => 'billing_phone',
				'label'   => __( 'Phone', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'tel',
			),
			array(
				'id'      => 'billing_country',
				'label'   => __( 'Country', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'select',
			),
			array(
				'id'      => 'billing_address_1',
				'label'   => __( 'Street Address', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_city',
				'label'   => __( 'Town / City', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_state',
				'label'   => __( 'State', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'select',
			),
			array(
				'id'      => 'billing_postcode',
				'label'   => __( 'ZIP / Postcode', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
		);
	}
}
