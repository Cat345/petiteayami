<?php
/**
 * Section Rule Model Class
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Models
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section_Rule class - Represents a conditional visibility rule for an entire section.
 *
 * @since 2.3.0
 */
class Section_Rule extends Rule {

	/**
	 * Section ID this rule applies to.
	 *
	 * @var string
	 */
	private $section_id;

	/**
	 * Section label (human-readable name).
	 *
	 * @var string
	 */
	private $section_label;

	/**
	 * Constructor.
	 *
	 * @param string $section_id Section ID (billing, shipping, etc.).
	 */
	public function __construct( $section_id = '' ) {
		$this->section_id    = sanitize_text_field( $section_id );
		$this->section_label = $this->get_section_label_for_id( $section_id );

		// Call parent constructor with section_id as field_id for compatibility.
		parent::__construct( array( 'field_id' => $section_id ) );
	}

	/**
	 * Get section ID.
	 *
	 * @return string
	 */
	public function get_section_id() {
		return $this->section_id;
	}

	/**
	 * Get section label.
	 *
	 * @return string
	 */
	public function get_section_label() {
		return $this->section_label;
	}

	/**
	 * Get section label for a given section ID.
	 *
	 * @param string $section_id Section ID.
	 * @return string Section label.
	 */
	private function get_section_label_for_id( $section_id ) {
		$labels = array(
			'billing'  => __( 'Billing Information', 'woofunnels-aero-checkout' ),
			'shipping' => __( 'Shipping Address', 'woofunnels-aero-checkout' ),
			'account'  => __( 'Account Details', 'woofunnels-aero-checkout' ),
			'order'    => __( 'Order Notes', 'woofunnels-aero-checkout' ),
			'advanced' => __( 'Additional Fields', 'woofunnels-aero-checkout' ),
		);

		return isset( $labels[ $section_id ] ) ? $labels[ $section_id ] : ucfirst( $section_id );
	}

	/**
	 * Convert section rule to array.
	 *
	 * @return array Section rule data.
	 */
	public function to_array() {
		$data = parent::to_array();

		// Replace field_id with section_id for clarity.
		unset( $data['field_id'] );
		$data['section_id']    = $this->section_id;
		$data['section_label'] = $this->section_label;

		return $data;
	}

	/**
	 * Create section rule from array.
	 *
	 * @param array $data Section rule data.
	 * @return Section_Rule Section rule object.
	 */
	public static function from_array( $data ) {
		$section_id = isset( $data['section_id'] ) ? sanitize_text_field( $data['section_id'] ) : '';
		$rule       = new self( $section_id );

		// Set properties.
		if ( isset( $data['action'] ) ) {
			$rule->set_action( $data['action'] );
		}

		if ( isset( $data['group_logic'] ) ) {
			$rule->set_group_logic( $data['group_logic'] );
		}

		if ( isset( $data['enabled'] ) ) {
			$rule->set_enabled( $data['enabled'] );
		}

		// Add rule groups.
		if ( isset( $data['rule_groups'] ) && is_array( $data['rule_groups'] ) ) {
			foreach ( $data['rule_groups'] as $group_data ) {
				$rule->add_rule_group( $group_data );
			}
		}

		return $rule;
	}

	/**
	 * Validate the section rule.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid() {
		// Section ID is required.
		if ( empty( $this->section_id ) ) {
			return false;
		}

		// Validate action.
		if ( ! in_array( $this->get_action(), array( 'show', 'hide' ), true ) ) {
			return false;
		}

		// Validate group logic.
		if ( ! in_array( $this->get_group_logic(), array( 'AND', 'OR' ), true ) ) {
			return false;
		}

		// Rule groups can be empty (section visible/hidden without conditions).
		return true;
	}
}
