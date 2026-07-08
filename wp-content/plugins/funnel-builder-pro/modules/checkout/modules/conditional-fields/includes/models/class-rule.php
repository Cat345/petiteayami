<?php
/**
 * Rule Model Class
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Models
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule class - Represents a complete conditional visibility rule for a field.
 *
 * @since 2.0.0
 */
#[\AllowDynamicProperties]
class Rule {

	/**
	 * Field ID this rule applies to.
	 *
	 * @var string
	 */
	private $field_id;

	/**
	 * Whether the rule is enabled.
	 *
	 * @var bool
	 */
	private $enabled = true;

	/**
	 * Rule action (show or hide).
	 *
	 * @var string
	 */
	private $action = 'show';

	/**
	 * Array of Condition_Group objects.
	 *
	 * @var array
	 */
	private $rule_groups = array();

	/**
	 * Logic between groups (AND or OR).
	 *
	 * @var string
	 */
	private $group_logic = 'OR';

	/**
	 * Constructor.
	 *
	 * @param array $data Rule data.
	 */
	public function __construct( $data = array() ) {
		$this->field_id    = isset( $data['field_id'] ) ? sanitize_text_field( $data['field_id'] ) : '';
		$this->enabled     = isset( $data['enabled'] ) ? (bool) $data['enabled'] : true;
		$this->action      = isset( $data['action'] ) && in_array( $data['action'], array( 'show', 'hide' ), true ) ? $data['action'] : 'show';
		$this->group_logic = isset( $data['group_logic'] ) && in_array( $data['group_logic'], array( 'AND', 'OR' ), true ) ? $data['group_logic'] : 'OR';

		// Add rule groups.
		if ( isset( $data['rule_groups'] ) && is_array( $data['rule_groups'] ) ) {
			foreach ( $data['rule_groups'] as $group_data ) {
				$this->add_rule_group( $group_data );
			}
		}
	}

	/**
	 * Add a rule group.
	 *
	 * @param array|Condition_Group $group_data Group data or Condition_Group object.
	 * @return bool True if added, false if invalid.
	 */
	public function add_rule_group( $group_data ) {
		if ( $group_data instanceof Condition_Group ) {
			$group = $group_data;
		} else {
			$group = Condition_Group::from_array( $group_data );
		}

		if ( $group && $group->has_conditions() ) {
			$this->rule_groups[] = $group;
			return true;
		}

		return false;
	}

	/**
	 * Get field ID.
	 *
	 * @return string
	 */
	public function get_field_id() {
		return $this->field_id;
	}

	/**
	 * Check if rule is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get rule action.
	 *
	 * @return string
	 */
	public function get_action() {
		return $this->action;
	}

	/**
	 * Get rule groups.
	 *
	 * @return array Array of Condition_Group objects.
	 */
	public function get_rule_groups() {
		return $this->rule_groups;
	}

	/**
	 * Get group logic.
	 *
	 * @return string
	 */
	public function get_group_logic() {
		return $this->group_logic;
	}

	/**
	 * Set rule action.
	 *
	 * @param string $action Action (show or hide).
	 */
	public function set_action( $action ) {
		if ( in_array( $action, array( 'show', 'hide' ), true ) ) {
			$this->action = $action;
		}
	}

	/**
	 * Set group logic.
	 *
	 * @param string $logic Logic (AND or OR).
	 */
	public function set_group_logic( $logic ) {
		if ( in_array( $logic, array( 'AND', 'OR' ), true ) ) {
			$this->group_logic = $logic;
		}
	}

	/**
	 * Set enabled status.
	 *
	 * @param bool $enabled Whether rule is enabled.
	 */
	public function set_enabled( $enabled ) {
		$this->enabled = (bool) $enabled;
	}

	/**
	 * Validate the rule.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid() {
		if ( empty( $this->field_id ) ) {
			return false;
		}

		if ( empty( $this->rule_groups ) ) {
			return false;
		}

		// Validate action.
		if ( ! in_array( $this->action, array( 'show', 'hide' ), true ) ) {
			return false;
		}

		// Validate group logic.
		if ( ! in_array( $this->group_logic, array( 'AND', 'OR' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert rule to array.
	 *
	 * @return array Rule data.
	 */
	public function to_array() {
		return array(
			'field_id'    => $this->field_id,
			'enabled'     => $this->enabled,
			'action'      => $this->action,
			'group_logic' => $this->group_logic,
			'rule_groups' => array_map(
				function ( $group ) {
					return $group->to_array();
				},
				$this->rule_groups
			),
		);
	}

	/**
	 * Create rule from array.
	 *
	 * @param array $data Rule data.
	 * @return Rule|null Rule object or null if invalid.
	 */
	public static function from_array( $data ) {
		$rule = new self( $data );

		return $rule->is_valid() ? $rule : null;
	}
}
