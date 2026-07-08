<?php
/**
 * Condition Group Model Class
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Models
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Condition_Group class - Groups multiple conditions with AND logic.
 *
 * @since 2.0.0
 */
#[\AllowDynamicProperties]
class Condition_Group {

	/**
	 * Array of Condition objects.
	 *
	 * @var array
	 */
	private $conditions = array();

	/**
	 * Constructor.
	 *
	 * @param array $conditions Array of condition data.
	 */
	public function __construct( $conditions = array() ) {
		foreach ( $conditions as $condition_data ) {
			$this->add_condition( $condition_data );
		}
	}

	/**
	 * Add a condition to the group.
	 *
	 * @param array|Condition $condition_data Condition data or Condition object.
	 * @return bool True if added, false if invalid.
	 */
	public function add_condition( $condition_data ) {
		if ( $condition_data instanceof Condition ) {
			$condition = $condition_data;
		} else {
			$condition = Condition::from_array( $condition_data );
		}

		if ( $condition && $condition->is_valid() ) {
			$this->conditions[] = $condition;
			return true;
		}

		return false;
	}

	/**
	 * Get all conditions.
	 *
	 * @return array Array of Condition objects.
	 */
	public function get_conditions() {
		return $this->conditions;
	}

	/**
	 * Check if group has any conditions.
	 *
	 * @return bool
	 */
	public function has_conditions() {
		return ! empty( $this->conditions );
	}

	/**
	 * Convert group to array.
	 *
	 * @return array Group data.
	 */
	public function to_array() {
		return array_map(
			function ( $condition ) {
				return $condition->to_array();
			},
			$this->conditions
		);
	}

	/**
	 * Create group from array.
	 *
	 * @param array $data Group data.
	 * @return Condition_Group
	 */
	public static function from_array( $data ) {
		return new self( $data );
	}
}
