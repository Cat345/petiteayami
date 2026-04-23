<?php
/**
 * Condition Model Class
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Models
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Condition class - Represents a single conditional rule condition.
 *
 * @since 2.0.0
 */
class Condition {

	/**
	 * Condition type (cart, user, field).
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Condition operator.
	 *
	 * @var string
	 */
	private $operator;

	/**
	 * Operand type (for cart conditions: product, category, tag, etc.).
	 *
	 * @var string
	 */
	private $operand_type;

	/**
	 * Operand value (ID or slug).
	 *
	 * @var mixed
	 */
	private $operand;

	/**
	 * Comparison value.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Field ID (for field-based conditions).
	 *
	 * @var string
	 */
	private $field_id;

	/**
	 * Constructor.
	 *
	 * @param array $data Condition data.
	 */
	public function __construct( $data = array() ) {
		$this->type         = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : '';
		$this->operator     = isset( $data['operator'] ) ? sanitize_text_field( $data['operator'] ) : '';
		$this->operand_type = isset( $data['operand_type'] ) ? sanitize_text_field( $data['operand_type'] ) : '';
		$this->operand      = isset( $data['operand'] ) ? $this->sanitize_operand( $data['operand'] ) : '';
		$this->value        = isset( $data['value'] ) ? $this->sanitize_value( $data['value'] ) : '';
		$this->field_id     = isset( $data['field_id'] ) ? sanitize_text_field( $data['field_id'] ) : '';
	}

	/**
	 * Sanitize operand value.
	 *
	 * @param mixed $operand Operand value.
	 * @return mixed Sanitized operand.
	 */
	private function sanitize_operand( $operand ) {
		if ( is_array( $operand ) ) {
			// Check if all values are numeric IDs or string slugs/types.
			$first_value = reset( $operand );
			if ( is_numeric( $first_value ) ) {
				// Numeric IDs (products, categories, tags, shipping classes).
				return array_map( 'absint', $operand );
			} else {
				// String values (product types, etc.).
				return array_map( 'sanitize_text_field', $operand );
			}
		}

		// Single value.
		if ( is_numeric( $operand ) ) {
			return absint( $operand );
		} else {
			return sanitize_text_field( $operand );
		}
	}

	/**
	 * Sanitize comparison value.
	 *
	 * @param mixed $value Comparison value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_value( $value ) {
		if ( is_numeric( $value ) ) {
			return floatval( $value );
		}

		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Validate the condition.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid() {
		if ( empty( $this->type ) || empty( $this->operator ) ) {
			return false;
		}

		// Type-specific validation.
		switch ( $this->type ) {
			case 'cart':
				// Operators that only need a value (no operand_type).
				$value_only_operators = array(
					'cart_is_virtual_eq',
					'cart_is_virtual_ne',
					'cart_total_eq',
					'cart_total_ne',
					'cart_total_gt',
					'cart_total_lt',
					'cart_total_gte',
					'cart_total_lte',
					'cart_subtotal_eq',
					'cart_subtotal_ne',
					'cart_subtotal_gt',
					'cart_subtotal_lt',
					'cart_subtotal_gte',
					'cart_subtotal_lte',
					'cart_item_count_eq',
					'cart_item_count_ne',
					'cart_item_count_gt',
					'cart_item_count_lt',
					'cart_item_count_gte',
					'cart_item_count_lte',
				);
				if ( in_array( $this->operator, $value_only_operators, true ) ) {
					return '' !== (string) $this->value;
				}

				// Operators that need operand_type (product, category, tag, coupon, etc.).
				$operand_operators = array(
					'cart_contains',
					'cart_not_contains',
					'cart_only_contains',
					'cart_coupon_contains',
					'cart_coupon_not_contains',
				);
				if ( in_array( $this->operator, $operand_operators, true ) ) {
					return ! empty( $this->operand_type ) || ! empty( $this->operand );
				}

				return ! empty( $this->operand_type );

			case 'user':
				// User role conditions can have array value.
				return ! empty( $this->value ) || ( is_array( $this->value ) && ! empty( $this->value ) );

			case 'field':
				return ! empty( $this->field_id );

			default:
				return false;
		}
	}

	/**
	 * Convert condition to array.
	 *
	 * @return array Condition data.
	 */
	public function to_array() {
		return array(
			'type'         => $this->type,
			'operator'     => $this->operator,
			'operand_type' => $this->operand_type,
			'operand'      => $this->operand,
			'value'        => $this->value,
			'field_id'     => $this->field_id,
		);
	}

	/**
	 * Create condition from array.
	 *
	 * @param array $data Condition data.
	 * @return Condition|null Condition object or null if invalid.
	 */
	public static function from_array( $data ) {
		$condition = new self( $data );

		if ( ! $condition->is_valid() ) {
			return null;
		}

		return $condition;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private static function debug_log( $message ) {
		// Debug logging disabled.
	}

	/**
	 * Get condition type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get condition operator.
	 *
	 * @return string
	 */
	public function get_operator() {
		return $this->operator;
	}

	/**
	 * Get operand type.
	 *
	 * @return string
	 */
	public function get_operand_type() {
		return $this->operand_type;
	}

	/**
	 * Get operand value.
	 *
	 * @return mixed
	 */
	public function get_operand() {
		return $this->operand;
	}

	/**
	 * Get comparison value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Get field ID.
	 *
	 * @return string
	 */
	public function get_field_id() {
		return $this->field_id;
	}
}
