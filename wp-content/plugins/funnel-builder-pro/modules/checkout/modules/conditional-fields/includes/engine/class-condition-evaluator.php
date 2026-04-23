<?php
/**
 * Condition_Evaluator
 *
 * Orchestrates evaluation of all condition types.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Engine
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Engine;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Condition;
use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Condition_Group;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Condition_Evaluator class.
 *
 * @since 2.0.0
 */
class Condition_Evaluator {

	/**
	 * Evaluate a single condition.
	 *
	 * @since 2.0.0
	 * @param Condition $condition Condition to evaluate.
	 * @param array     $context Evaluation context (cart_info, field_values).
	 * @return bool True if condition is met, false otherwise.
	 */
	public static function evaluate_condition( $condition, $context = array() ) {
		if ( ! $condition instanceof Condition || ! $condition->is_valid() ) {
			return false;
		}

		$type = $condition->get_type();

		switch ( $type ) {
			case 'cart':
				$cart_info = isset( $context['cart_info'] ) ? $context['cart_info'] : null;
				return Cart_Evaluator::evaluate( $condition, $cart_info );

			case 'user':
				return User_Evaluator::evaluate( $condition );

			case 'field':
				$field_values = isset( $context['field_values'] ) ? $context['field_values'] : null;
				return Field_Evaluator::evaluate( $condition, $field_values );

			default:
				return false;
		}
	}

	/**
	 * Evaluate a condition group.
	 *
	 * @since 2.0.0
	 * @param Condition_Group $group Condition group to evaluate.
	 * @param array           $context Evaluation context.
	 * @return bool True if group conditions are met, false otherwise.
	 */
	public static function evaluate_group( $group, $context = array() ) {
		if ( ! $group instanceof Condition_Group || ! $group->has_conditions() ) {
			return false;
		}

		$conditions = $group->get_conditions();

		// Condition groups ALWAYS use AND logic - all conditions must be true.
		// OR logic is applied between groups, not within a group.
		foreach ( $conditions as $condition ) {
			if ( ! self::evaluate_condition( $condition, $context ) ) {
				return false; // Early return on first false.
			}
		}

		return true;
	}

	/**
	 * Evaluate multiple condition groups.
	 *
	 * @since 2.0.0
	 * @param array  $groups Array of Condition_Group objects.
	 * @param string $group_logic Logic between groups (and/or).
	 * @param array  $context Evaluation context.
	 * @return bool True if groups evaluation is met, false otherwise.
	 */
	public static function evaluate_groups( $groups, $group_logic, $context = array() ) {
		if ( empty( $groups ) || ! is_array( $groups ) ) {
			return false;
		}

		// Normalize to uppercase to match Rule class validation.
		$group_logic = strtoupper( $group_logic );

		// AND logic - all groups must be true.
		if ( 'AND' === $group_logic ) {
			foreach ( $groups as $group ) {
				if ( ! self::evaluate_group( $group, $context ) ) {
					return false; // Early return on first false.
				}
			}
			return true;
		}

		// OR logic - at least one group must be true.
		if ( 'OR' === $group_logic ) {
			foreach ( $groups as $group ) {
				if ( self::evaluate_group( $group, $context ) ) {
					return true; // Early return on first true.
				}
			}
			return false;
		}

		return false;
	}

	/**
	 * Build evaluation context.
	 *
	 * When field_values is null, attempts to get from posted form data (AJAX) or
	 * WooCommerce checkout values (initial load) so field-based rules work.
	 *
	 * @since 2.0.0
	 * @param array|null $field_values Optional field values. If null, auto-fetched.
	 * @return array Evaluation context.
	 */
	public static function build_context( $field_values = null ) {
		if ( null === $field_values ) {
			$field_values = self::get_field_values_from_request();
		}

		return array(
			'cart_info'    => Cart_Evaluator::get_cart_info(),
			'field_values' => $field_values,
		);
	}

	/**
	 * Get checkout field values from current request.
	 *
	 * During update_order_review AJAX, form data is in post_data (serialized).
	 * On initial load, use WooCommerce checkout/customer values.
	 *
	 * @since 2.4.0
	 * @return array Field key => value.
	 */
	public static function get_field_values_from_request() {
		// WFACP stores parsed post_data during update_order_review (priority -1).
		if ( class_exists( '\WFACP_Common' ) && ! empty( \WFACP_Common::$post_data ) && is_array( \WFACP_Common::$post_data ) ) {
			return \WFACP_Common::$post_data;
		}

		// Fallback: parse post_data during AJAX when WFACP may not have run yet.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Reading checkout form data during update_order_review.
		if ( wp_doing_ajax() && isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$post_data = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Reading checkout form data during update_order_review.
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data );
			if ( ! empty( $post_data ) ) {
				return $post_data;
			}
		}

		// Initial load: use WooCommerce checkout values (customer/session defaults).
		if ( function_exists( 'WC' ) && WC()->checkout() ) {
			$values = array();
			$fields = WC()->checkout()->get_checkout_fields();
			foreach ( array( 'billing', 'shipping', 'account', 'order' ) as $fieldset ) {
				if ( isset( $fields[ $fieldset ] ) && is_array( $fields[ $fieldset ] ) ) {
					foreach ( array_keys( $fields[ $fieldset ] ) as $key ) {
						$val = WC()->checkout()->get_value( $key );
						if ( '' !== $val && null !== $val ) {
							$values[ $key ] = $val;
						}
					}
				}
			}
			if ( ! empty( $values ) ) {
				return $values;
			}
		}

		return array();
	}
}
