<?php
/**
 * Field_Evaluator
 *
 * Evaluates field-based conditions.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Engine
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Engine;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Condition;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field_Evaluator class.
 *
 * @since 2.0.0
 */
#[\AllowDynamicProperties]
class Field_Evaluator {

	/**
	 * Evaluate a field-based condition.
	 *
	 * @since 2.0.0
	 * @param Condition $condition Condition to evaluate.
	 * @param array     $field_values Field values from checkout form.
	 * @return bool True if condition is met, false otherwise.
	 */
	public static function evaluate( $condition, $field_values = null ) {
		if ( ! $condition instanceof Condition || 'field' !== $condition->get_type() ) {
			return false;
		}

		$field_id = $condition->get_field_id();
		$operator = $condition->get_operator();
		$value    = $condition->get_value();

		// Get field value.
		$field_value = self::get_field_value( $field_id, $field_values );

		// Evaluate based on operator.
		switch ( $operator ) {
			case 'value_eq':
				return self::evaluate_value_eq( $field_value, $value );

			case 'value_ne':
				return self::evaluate_value_ne( $field_value, $value );

			case 'value_gt':
				return self::evaluate_value_gt( $field_value, $value );

			case 'value_lt':
				return self::evaluate_value_lt( $field_value, $value );

			case 'value_empty':
				return self::evaluate_value_empty( $field_value );

			case 'value_not_empty':
				return self::evaluate_value_not_empty( $field_value );

			case 'value_contains':
				return self::evaluate_value_contains( $field_value, $value );

			case 'value_in':
				return self::evaluate_value_in( $field_value, $value );

			case 'value_none':
				return self::evaluate_value_none( $field_value, $value );

			default:
				return false;
		}
	}

	/**
	 * Get field value from checkout form.
	 *
	 * @since 2.0.0
	 * @param string $field_id Field ID.
	 * @param array  $field_values Field values array.
	 * @return mixed Field value.
	 */
	private static function get_field_value( $field_id, $field_values = null ) {
		// If field values provided, use them.
		if ( is_array( $field_values ) && isset( $field_values[ $field_id ] ) ) {
			return $field_values[ $field_id ];
		}

		// Try to get from POST data.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $field_id ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = wp_unslash( $_POST[ $field_id ] );
			return is_array( $value ) ? array_map( 'wc_clean', $value ) : wc_clean( $value );
		}

		// Try to get from REQUEST (covers both POST and GET).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST[ $field_id ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$value = wp_unslash( $_REQUEST[ $field_id ] );
			return is_array( $value ) ? array_map( 'wc_clean', $value ) : wc_clean( $value );
		}

		return '';
	}

	/**
	 * Evaluate value equals condition.
	 *
	 * @since 2.0.0
	 * @param mixed $field_value Field value.
	 * @param mixed $comparison_value Value to compare.
	 * @return bool
	 */
	private static function evaluate_value_eq( $field_value, $comparison_value ) {
		// Handle checkbox special case (use string cast for consistent comparison).
		if ( 'checked' === $comparison_value || '1' === (string) $comparison_value ) {
			return ! empty( $field_value ) && '1' === (string) $field_value;
		}

		// Handle array values (multiselect, checkbox group).
		if ( is_array( $field_value ) ) {
			return in_array( $comparison_value, $field_value, true );
		}

		// Normal comparison.
		return (string) $field_value === (string) $comparison_value;
	}

	/**
	 * Evaluate value not equals condition.
	 *
	 * @since 2.0.0
	 * @param mixed $field_value Field value.
	 * @param mixed $comparison_value Value to compare.
	 * @return bool
	 */
	private static function evaluate_value_ne( $field_value, $comparison_value ) {
		return ! self::evaluate_value_eq( $field_value, $comparison_value );
	}

	/**
	 * Evaluate value greater than condition.
	 *
	 * @since 2.0.0
	 * @param mixed $field_value Field value.
	 * @param mixed $comparison_value Value to compare.
	 * @return bool
	 */
	private static function evaluate_value_gt( $field_value, $comparison_value ) {
		if ( ! is_numeric( $field_value ) || ! is_numeric( $comparison_value ) ) {
			return false;
		}

		return floatval( $field_value ) > floatval( $comparison_value );
	}

	/**
	 * Evaluate value less than condition.
	 *
	 * @since 2.0.0
	 * @param mixed $field_value Field value.
	 * @param mixed $comparison_value Value to compare.
	 * @return bool
	 */
	private static function evaluate_value_lt( $field_value, $comparison_value ) {
		if ( ! is_numeric( $field_value ) || ! is_numeric( $comparison_value ) ) {
			return false;
		}

		return floatval( $field_value ) < floatval( $comparison_value );
	}

	/**
	 * Evaluate value empty condition.
	 *
	 * @since 2.0.0
	 * @param mixed $field_value Field value.
	 * @return bool
	 */
	private static function evaluate_value_empty( $field_value ) {
		if ( is_array( $field_value ) ) {
			return empty( $field_value );
		}

		return empty( trim( (string) $field_value ) );
	}

	/**
	 * Evaluate value not empty condition.
	 *
	 * @since 2.0.0
	 * @param mixed $field_value Field value.
	 * @return bool
	 */
	private static function evaluate_value_not_empty( $field_value ) {
		return ! self::evaluate_value_empty( $field_value );
	}

	/**
	 * Evaluate value contains condition.
	 *
	 * @since 2.0.0
	 * @param mixed  $field_value Field value.
	 * @param string $comparison_value Value to search for.
	 * @return bool
	 */
	private static function evaluate_value_contains( $field_value, $comparison_value ) {
		// Handle array values.
		if ( is_array( $field_value ) ) {
			return in_array( $comparison_value, $field_value, true );
		}

		// String contains check.
		return false !== strpos( (string) $field_value, (string) $comparison_value );
	}

	/**
	 * Evaluate value in list condition.
	 *
	 * @since 2.4.0
	 * @param mixed $field_value Field value.
	 * @param mixed $allowed_values Array of allowed values.
	 * @return bool
	 */
	private static function evaluate_value_in( $field_value, $allowed_values ) {
		if ( ! is_array( $allowed_values ) ) {
			$allowed_values = array( $allowed_values );
		}

		return in_array( (string) $field_value, array_map( 'strval', $allowed_values ), true );
	}

	/**
	 * Evaluate value NOT in list condition (matches none of).
	 *
	 * @since 2.6.0
	 * @param mixed $field_value Field value.
	 * @param mixed $excluded_values Array of excluded values.
	 * @return bool True if field value is NOT in the excluded list.
	 */
	private static function evaluate_value_none( $field_value, $excluded_values ) {
		if ( ! is_array( $excluded_values ) ) {
			$excluded_values = array( $excluded_values );
		}

		return ! in_array( (string) $field_value, array_map( 'strval', $excluded_values ), true );
	}
}
