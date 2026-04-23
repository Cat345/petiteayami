<?php
/**
 * Ajax_Handler
 *
 * Handles AJAX requests from admin interface.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Admin
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Admin;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Rule;
use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax_Handler class.
 *
 * @since 2.0.0
 */
class Ajax_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var Ajax_Handler
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Ajax_Handler
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
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 * AJAX handlers deprecated in favor of REST API; kept for backward compatibility if needed.
	 *
	 * @since 2.0.0
	 */
	private function init_hooks() {
		// REST API is primary. AJAX hooks removed - see Api\Rest_Controller.
	}

	/**
	 * Load checkout fields.
	 *
	 * @since 2.0.0
	 */
	public function load_checkout_fields() {
		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get checkout ID from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;

		if ( ! $checkout_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid checkout ID.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get fields.
		$admin_pages = Admin_Pages::get_instance();
		$fields      = $admin_pages->get_checkout_fields( $checkout_id );

		// Get rules for these fields.
		$rules = Rule_Storage::get_rules( $checkout_id );

		// Build fields with rule status.
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

		wp_send_json_success( array( 'fields' => $fields_with_rules ) );
	}

	/**
	 * Get all checkout editor data in one request (fields with rule status + all section rules).
	 * Used by the accordion UI to avoid one AJAX per section.
	 *
	 * @since 2.3.0
	 */
	public function get_checkout_editor_data() {
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;

		if ( ! $checkout_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid checkout ID.', 'woofunnels-aero-checkout' ) ) );
		}

		$admin_pages   = Admin_Pages::get_instance();
		$sections      = $admin_pages->get_checkout_sections( $checkout_id );
		$all_rules_v2  = Rule_Storage::get_all_rules_v2( $checkout_id );
		$section_rules = array();

		foreach ( array_keys( $sections ) as $section_id ) {
			$section_rule = null;
			if ( isset( $all_rules_v2['sections'][ $section_id ]['section_rule'] ) ) {
				$section_rule = $all_rules_v2['sections'][ $section_id ]['section_rule'];
			}
			$section_rules[ $section_id ] = $section_rule ? $section_rule->to_array() : null;
		}

		$fields            = $admin_pages->get_checkout_fields( $checkout_id );
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

		wp_send_json_success(
			array(
				'fields'        => $fields_with_rules,
				'section_rules' => $section_rules,
			)
		);
	}

	/**
	 * Log debug message to plugin debug file.
	 *
	 * @param string $message Message to log.
	 */
	private function debug_log( $message ) {
		// Debug logging disabled.
	}

	/**
	 * Save field rules.
	 *
	 * @since 2.0.0
	 */
	public function save_field_rules() {
		$this->debug_log( 'save_field_rules called' );

		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );
		$this->debug_log( 'Nonce check passed' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->debug_log( 'Permission denied' );
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get data from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$field_id = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$rule_data = isset( $_POST['rule_data'] ) ? json_decode( wp_unslash( $_POST['rule_data'] ), true, 10 ) : array();

		$this->debug_log( "Checkout ID: {$checkout_id}, Field ID: {$field_id}" );
		$this->debug_log( 'Raw rule_data from POST: ' . ( isset( $_POST['rule_data'] ) ? substr( wp_unslash( $_POST['rule_data'] ), 0, 200 ) : 'not set' ) );
		$this->debug_log( 'Decoded rule_data: ' . wp_json_encode( $rule_data ) );

		if ( ! $checkout_id || ! $field_id ) {
			$this->debug_log( 'ERROR: Invalid checkout_id or field_id' );
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ) );
		}

		// Validate rule data.
		if ( empty( $rule_data ) || ! is_array( $rule_data ) ) {
			$this->debug_log( 'ERROR: Invalid rule data - empty or not array' );
			wp_send_json_error( array( 'message' => __( 'Invalid rule data.', 'woofunnels-aero-checkout' ) ) );
		}

		$this->debug_log( 'Calling Rule_Storage::save_field_rule()' );

		// Save rules.
		$result = Rule_Storage::save_field_rule( $checkout_id, $field_id, $rule_data );

		$this->debug_log( 'Rule_Storage::save_field_rule() returned: ' . ( $result ? 'true' : 'false' ) );

		if ( $result ) {
			$this->debug_log( 'SUCCESS: Rules saved' );
			wp_send_json_success( array( 'message' => __( 'Rules saved successfully.', 'woofunnels-aero-checkout' ) ) );
		} else {
			$this->debug_log( 'ERROR: Failed to save rules' );
			wp_send_json_error( array( 'message' => __( 'Failed to save rules.', 'woofunnels-aero-checkout' ) ) );
		}
	}

	/**
	 * Get field rules.
	 *
	 * @since 2.0.0
	 */
	public function get_field_rules() {
		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get data from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$field_id = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';

		if ( ! $checkout_id || ! $field_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get rule.
		$rule = Rule_Storage::get_field_rule( $checkout_id, $field_id );

		if ( ! $rule ) {
			wp_send_json_success( array( 'rules' => null ) );
		}

		// Convert rule to array.
		$rule_array = $rule->to_array();

		wp_send_json_success( array( 'rules' => $rule_array ) );
	}

	/**
	 * Delete field rules.
	 *
	 * @since 2.0.0
	 */
	public function delete_field_rules() {
		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get data from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$field_id = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';

		if ( ! $checkout_id || ! $field_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ) );
		}

		// Delete rule.
		$result = Rule_Storage::delete_field_rule( $checkout_id, $field_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Rules deleted successfully.', 'woofunnels-aero-checkout' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete rules.', 'woofunnels-aero-checkout' ) ) );
		}
	}

	/**
	 * Save section rule.
	 *
	 * @since 2.3.0
	 */
	public function save_section_rule() {
		$this->debug_log( 'save_section_rule called' );

		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );
		$this->debug_log( 'Nonce check passed' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->debug_log( 'Permission denied' );
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get data from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$section_id = isset( $_POST['section_id'] ) ? sanitize_text_field( wp_unslash( $_POST['section_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$rule_data = isset( $_POST['rule_data'] ) ? json_decode( wp_unslash( $_POST['rule_data'] ), true, 10 ) : array();

		$this->debug_log( "Checkout ID: {$checkout_id}, Section ID: {$section_id}" );
		$this->debug_log( 'Decoded rule_data: ' . wp_json_encode( $rule_data ) );

		if ( ! $checkout_id || ! $section_id ) {
			$this->debug_log( 'ERROR: Invalid checkout_id or section_id' );
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ) );
		}

		// Validate rule data.
		if ( empty( $rule_data ) || ! is_array( $rule_data ) ) {
			$this->debug_log( 'ERROR: Invalid rule data - empty or not array' );
			wp_send_json_error( array( 'message' => __( 'Invalid rule data.', 'woofunnels-aero-checkout' ) ) );
		}

		$this->debug_log( 'Calling Rule_Storage::save_section_rule()' );

		// Save section rule.
		$result = Rule_Storage::save_section_rule( $checkout_id, $section_id, $rule_data );

		$this->debug_log( 'Rule_Storage::save_section_rule() returned: ' . ( $result ? 'true' : 'false' ) );

		if ( $result ) {
			$this->debug_log( 'SUCCESS: Section rule saved' );
			$saved_rule = Rule_Storage::get_section_rule( $checkout_id, $section_id );
			$rule_array = $saved_rule ? $saved_rule->to_array() : null;
			wp_send_json_success(
				array(
					'message' => __( 'Section rule saved successfully.', 'woofunnels-aero-checkout' ),
					'rule'    => $rule_array,
				)
			);
		} else {
			$this->debug_log( 'ERROR: Failed to save section rule' );
			wp_send_json_error( array( 'message' => __( 'Failed to save section rule.', 'woofunnels-aero-checkout' ) ) );
		}
	}

	/**
	 * Get section rule.
	 *
	 * @since 2.3.0
	 */
	public function get_section_rule() {
		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get data from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$section_id = isset( $_POST['section_id'] ) ? sanitize_text_field( wp_unslash( $_POST['section_id'] ) ) : '';

		if ( ! $checkout_id || ! $section_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get section rule.
		$section_rule = Rule_Storage::get_section_rule( $checkout_id, $section_id );

		if ( ! $section_rule ) {
			wp_send_json_success( array( 'rule' => null ) );
		}

		// Convert rule to array.
		$rule_array = $section_rule->to_array();

		wp_send_json_success( array( 'rule' => $rule_array ) );
	}

	/**
	 * Delete section rule.
	 *
	 * @since 2.3.0
	 */
	public function delete_section_rule() {
		// Check nonce.
		check_ajax_referer( 'fkcf_admin_nonce', 'nonce' );

		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get data from request.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( wp_unslash( $_POST['checkout_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$section_id = isset( $_POST['section_id'] ) ? sanitize_text_field( wp_unslash( $_POST['section_id'] ) ) : '';

		if ( ! $checkout_id || ! $section_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request data.', 'woofunnels-aero-checkout' ) ) );
		}

		// Delete section rule.
		$result = Rule_Storage::delete_section_rule( $checkout_id, $section_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Section rule deleted successfully.', 'woofunnels-aero-checkout' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete section rule.', 'woofunnels-aero-checkout' ) ) );
		}
	}
}
