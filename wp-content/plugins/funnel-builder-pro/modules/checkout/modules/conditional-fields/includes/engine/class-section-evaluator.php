<?php
/**
 * Section_Evaluator
 *
 * Evaluates section-level conditional rules to determine section visibility.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Engine
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Engine;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Section_Rule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section_Evaluator class.
 *
 * @since 2.3.0
 */
class Section_Evaluator {

	/**
	 * Determine if a section should be visible based on its rule.
	 *
	 * @param Section_Rule $section_rule Section rule to evaluate.
	 * @param array        $context Evaluation context (cart_info, user_info, field_values).
	 * @return bool True if section should be visible, false otherwise.
	 */
	public static function should_show_section( $section_rule, $context = array() ) {
		if ( ! $section_rule instanceof Section_Rule ) {
			// No rule = show by default.
			return true;
		}

		// If rule is disabled, show section by default.
		if ( ! $section_rule->is_enabled() ) {
			return true;
		}

		// Get rule groups.
		$groups = $section_rule->get_rule_groups();
		if ( empty( $groups ) ) {
			// No conditions = show by default.
			return true;
		}

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		// Evaluate rule groups with logic.
		$group_logic    = $section_rule->get_group_logic();
		$rule_satisfied = Condition_Evaluator::evaluate_groups( $groups, $group_logic, $context );

		// Apply action logic.
		$action = $section_rule->get_action();

		// If action is 'show', return rule result.
		// If action is 'hide', return inverse of rule result.
		return ( 'show' === $action ) ? $rule_satisfied : ! $rule_satisfied;
	}

	/**
	 * Evaluate section rule and return result.
	 *
	 * @param Section_Rule $section_rule Section rule to evaluate.
	 * @param array        $context Evaluation context.
	 * @return bool True if rule conditions are met, false otherwise.
	 */
	public static function evaluate_section_rule( $section_rule, $context = array() ) {
		if ( ! $section_rule instanceof Section_Rule ) {
			return false;
		}

		// If rule is disabled, return false (don't apply).
		if ( ! $section_rule->is_enabled() ) {
			return false;
		}

		// If rule has no groups, return false.
		$groups = $section_rule->get_rule_groups();
		if ( empty( $groups ) ) {
			return false;
		}

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		// Evaluate rule groups with logic.
		$group_logic = $section_rule->get_group_logic();
		return Condition_Evaluator::evaluate_groups( $groups, $group_logic, $context );
	}
}
