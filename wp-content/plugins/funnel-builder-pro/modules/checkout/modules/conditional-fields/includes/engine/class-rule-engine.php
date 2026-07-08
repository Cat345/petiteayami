<?php
/**
 * Rule_Engine
 *
 * Main engine for evaluating complete rules and determining field visibility.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Engine
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Engine;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Rule;
use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Section_Rule;
use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Condition;
use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Condition_Group;
use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;
use FunnelKit\Checkout\Modules\Conditional_Fields\Field_Conditions_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule_Engine class.
 *
 * @since 2.0.0
 */
#[\AllowDynamicProperties]
class Rule_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var Rule_Engine
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Rule_Engine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Evaluate a rule.
	 *
	 * @since 2.0.0
	 * @param Rule  $rule Rule to evaluate.
	 * @param array $context Evaluation context.
	 * @return bool True if rule conditions are met, false otherwise.
	 */
	public function evaluate_rule( $rule, $context = array() ) {
		if ( ! $rule instanceof Rule ) {
			$this->debug_log( 'evaluate_rule: Not a valid Rule instance' );
			return false;
		}

		// If rule is disabled, return false (don't apply).
		if ( ! $rule->is_enabled() ) {
			$this->debug_log( 'evaluate_rule: Rule is disabled' );
			return false;
		}

		// If rule has no groups, return false.
		$groups = $rule->get_rule_groups();
		if ( empty( $groups ) ) {
			$this->debug_log( 'evaluate_rule: Rule has no groups' );
			return false;
		}

		$this->debug_log( 'evaluate_rule: Evaluating ' . count( $groups ) . ' group(s)' );

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		// Evaluate rule groups with logic.
		$group_logic = $rule->get_group_logic();
		$this->debug_log( 'evaluate_rule: Group logic = ' . $group_logic );

		$result = Condition_Evaluator::evaluate_groups( $groups, $group_logic, $context );
		$this->debug_log( 'evaluate_rule: Result = ' . ( $result ? 'TRUE' : 'FALSE' ) );

		return $result;
	}

	/**
	 * Determine if a field should be visible.
	 *
	 * @since 2.0.0
	 * @param Rule  $rule Rule for the field.
	 * @param array $context Evaluation context.
	 * @return bool True if field should be visible, false otherwise.
	 */
	public function should_show_field( $rule, $context = array() ) {
		if ( ! $rule instanceof Rule ) {
			// No rule = show by default.
			return true;
		}

		$rule_satisfied = $this->evaluate_rule( $rule, $context );
		$action         = $rule->get_action();

		// If action is 'show', return rule result.
		// If action is 'hide', return inverse of rule result.
		return ( 'show' === $action ) ? $rule_satisfied : ! $rule_satisfied;
	}

	/**
	 * Get all hidden fields for a checkout.
	 *
	 * @since 2.0.0
	 * @param int   $checkout_id Checkout page ID.
	 * @param array $context Optional evaluation context.
	 * @return array Array of hidden field IDs.
	 */
	public function get_hidden_fields( $checkout_id, $context = array() ) {
		$rules = Rule_Storage::get_rules( $checkout_id );

		if ( empty( $rules ) ) {
			return array();
		}

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		$hidden_fields = array();

		foreach ( $rules as $field_id => $rule ) {
			if ( ! $this->should_show_field( $rule, $context ) ) {
				$hidden_fields[] = $field_id;
			}
		}

		return $hidden_fields;
	}

	/**
	 * Get all visible fields for a checkout.
	 *
	 * @since 2.0.0
	 * @param int   $checkout_id Checkout page ID.
	 * @param array $context Optional evaluation context.
	 * @return array Array of visible field IDs.
	 */
	public function get_visible_fields( $checkout_id, $context = array() ) {
		$rules = Rule_Storage::get_rules( $checkout_id );

		if ( empty( $rules ) ) {
			// No rules = all fields visible.
			return array();
		}

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		$visible_fields = array();

		foreach ( $rules as $field_id => $rule ) {
			if ( $this->should_show_field( $rule, $context ) ) {
				$visible_fields[] = $field_id;
			}
		}

		return $visible_fields;
	}

	/**
	 * Check if a specific field should be visible.
	 *
	 * @since 2.0.0
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $field_id Field ID.
	 * @param array  $context Optional evaluation context.
	 * @return bool True if field should be visible, false otherwise.
	 */
	public function is_field_visible( $checkout_id, $field_id, $context = array() ) {
		$rule = Rule_Storage::get_field_rule( $checkout_id, $field_id );

		// No rule = visible by default.
		if ( ! $rule ) {
			return true;
		}

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		return $this->should_show_field( $rule, $context );
	}

	/**
	 * Check if a field should be visible using hierarchical evaluation.
	 * Section rules take precedence over field rules.
	 *
	 * @since 2.3.0
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $field_id Field ID.
	 * @param array  $context Optional evaluation context.
	 * @return bool True if field should be visible, false otherwise.
	 */
	public function is_field_visible_hierarchical( $checkout_id, $field_id, $context = array() ) {
		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		// Determine which section this field belongs to.
		$section_id = $this->get_section_for_field( $field_id );

		// Check if there's a section rule.
		$section_rule = Rule_Storage::get_section_rule( $checkout_id, $section_id );

		if ( $section_rule ) {
			// Evaluate section visibility.
			$section_visible = Section_Evaluator::should_show_section( $section_rule, $context );

			// If section is hidden, all fields in it are hidden.
			if ( ! $section_visible ) {
				return false;
			}
		}

		// Section is visible (or no section rule), check field rule.
		$field_rule = Rule_Storage::get_field_rule( $checkout_id, $field_id );

		// No field rule = visible by default.
		if ( ! $field_rule ) {
			return true;
		}

		// Evaluate field visibility.
		return $this->should_show_field( $field_rule, $context );
	}

	/**
	 * Get section ID for a field.
	 *
	 * @since 2.3.0
	 * @param string $field_id Field ID.
	 * @return string Section ID.
	 */
	private function get_section_for_field( $field_id ) {
		if ( strpos( $field_id, 'billing_' ) === 0 ) {
			return 'billing';
		}
		if ( strpos( $field_id, 'shipping_' ) === 0 ) {
			return 'shipping';
		}
		if ( strpos( $field_id, 'account_' ) === 0 ) {
			return 'account';
		}
		if ( strpos( $field_id, 'order_' ) === 0 ) {
			return 'order';
		}
		return 'advanced';
	}

	/**
	 * Evaluate all rules for a checkout and return visibility map.
	 *
	 * @since 2.0.0
	 * @param int   $checkout_id Checkout page ID.
	 * @param array $context Optional evaluation context.
	 * @return array Array mapping field IDs to visibility boolean.
	 */
	public function get_visibility_map( $checkout_id, $context = array() ) {
		$rules = Rule_Storage::get_rules( $checkout_id );

		$this->debug_log( '=== get_visibility_map called for checkout ' . $checkout_id . ' ===' );

		if ( empty( $rules ) ) {
			$this->debug_log( 'No rules found for this checkout' );
			return array();
		}

		$this->debug_log( 'Found ' . count( $rules ) . ' rule(s)' );

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		// Log cart info from context.
		if ( isset( $context['cart_info'] ) ) {
			$cart_total = isset( $context['cart_info']['total'] ) ? $context['cart_info']['total'] : 0;
			$this->debug_log( 'Cart total in context: ' . $cart_total );
		}

		$visibility_map = array();

		foreach ( $rules as $field_id => $rule ) {
			$should_show                 = $this->should_show_field( $rule, $context );
			$visibility_map[ $field_id ] = $should_show;

			$action = $rule->get_action();
			$this->debug_log( "Field: $field_id | Action: $action | Visibility: " . ( $should_show ? 'SHOW' : 'HIDE' ) );
		}

		$this->debug_log( '=== Visibility map complete ===' );

		return $visibility_map;
	}

	/**
	 * Get hierarchical visibility map including sections and fields.
	 *
	 * @since 2.3.0
	 * @param int   $checkout_id Checkout page ID.
	 * @param array $context Optional evaluation context.
	 * @return array Array with 'sections' and 'fields' keys.
	 */
	public function get_hierarchical_visibility_map( $checkout_id, $context = array() ) {
		$this->debug_log( '=== get_hierarchical_visibility_map called for checkout ' . $checkout_id . ' ===' );

		// Build context if not provided.
		if ( empty( $context ) ) {
			$context = Condition_Evaluator::build_context();
		}

		$all_rules = Rule_Storage::get_all_rules_v2( $checkout_id );

		$section_visibility = array();
		$field_visibility   = array();

		if ( empty( $all_rules ) || ! isset( $all_rules['sections'] ) ) {
			$this->debug_log( 'No rules found, returning empty map' );
			return array(
				'sections' => $section_visibility,
				'fields'   => $field_visibility,
			);
		}

		// Build FKCF key -> visibility map from rules.
		$fkcf_visibility = array();
		foreach ( $all_rules['sections'] as $fkcf_key => $section_data ) {
			$section_is_visible = true;
			if ( ! empty( $section_data['section_rule'] ) ) {
				$section_rule       = $section_data['section_rule'];
				$section_is_visible = Section_Evaluator::should_show_section( $section_rule, $context );
				$this->debug_log( "FKCF Section: $fkcf_key | Visibility: " . ( $section_is_visible ? 'SHOW' : 'HIDE' ) );
			}
			$fkcf_visibility[ $fkcf_key ] = array(
				'visible' => $section_is_visible,
				'data'    => $section_data,
			);
		}

		// Build section visibility using section ID to match add_section_identifier_class (fkcf-section-{id}).
		$layout           = class_exists( '\WFACP_Common' ) ? \WFACP_Common::get_page_layout( $checkout_id ) : null;
		$rules_key_to_dom = $this->build_rules_key_to_dom_map( $layout, array_keys( $fkcf_visibility ) );

		if ( ! empty( $layout['fieldsets'] ) && is_array( $layout['fieldsets'] ) ) {
			foreach ( $layout['fieldsets'] as $step => $sections ) {
				if ( ! is_array( $sections ) ) {
					continue;
				}
				foreach ( $sections as $section_index => $section ) {
					if ( ! is_array( $section ) ) {
						continue;
					}
					$dom_section_id = isset( $section['id'] ) && is_string( $section['id'] ) && '' !== trim( $section['id'] )
						? sanitize_html_class( $section['id'] )
						: $step . '_fieldset_' . $section_index;
					$section_name   = isset( $section['name'] ) ? $section['name'] : '';
					$fkcf_key       = Field_Conditions_Handler::get_fkcf_key_from_section_name( $section_name );

					// Check fkcf_key first, then dom_section_id, then rules_key_to_dom (rules may use custom IDs like rule-country-us-company--ssn).
					$visibility_key = ( '' !== $fkcf_key && isset( $fkcf_visibility[ $fkcf_key ] ) ) ? $fkcf_key : $dom_section_id;
					if ( ! isset( $fkcf_visibility[ $visibility_key ] ) ) {
						$visibility_key = $dom_section_id;
					}
					if ( isset( $fkcf_visibility[ $visibility_key ] ) ) {
						$section_visibility[ $dom_section_id ] = $fkcf_visibility[ $visibility_key ]['visible'];
						$this->debug_log( "DOM Section: $dom_section_id (key=$visibility_key) | Visibility: " . ( $fkcf_visibility[ $visibility_key ]['visible'] ? 'SHOW' : 'HIDE' ) );
					} else {
						$section_visibility[ $dom_section_id ] = true;
					}
				}
			}
		}

		// Add visibility for rules sections that match layout by rules_key_to_dom (custom IDs like rule-country-us-company--ssn).
		foreach ( $rules_key_to_dom as $rules_key => $dom_id ) {
			if ( isset( $fkcf_visibility[ $rules_key ] ) ) {
				$section_visibility[ $dom_id ] = $fkcf_visibility[ $rules_key ]['visible'];
				$this->debug_log( "Rules key $rules_key -> DOM $dom_id | Visibility: " . ( $fkcf_visibility[ $rules_key ]['visible'] ? 'SHOW' : 'HIDE' ) );
			}
		}

		// Add visibility for rules keys used directly as DOM ids (section has id = rules_key in layout).
		foreach ( array_keys( $fkcf_visibility ) as $rules_key ) {
			if ( ! empty( $all_rules['sections'][ $rules_key ]['section_rule'] ) && ! isset( $section_visibility[ $rules_key ] ) ) {
				$section_visibility[ $rules_key ] = $fkcf_visibility[ $rules_key ]['visible'];
				$this->debug_log( "Rules key (direct): $rules_key | Visibility: " . ( $fkcf_visibility[ $rules_key ]['visible'] ? 'SHOW' : 'HIDE' ) );
			}
		}

		// Field visibility: always evaluate from rules (independent of layout).
		foreach ( $fkcf_visibility as $fkcf_key => $data ) {
			$section_data = $data['data'];
			if ( ! $data['visible'] ) {
				// Section hidden: hide all fields in this section.
				if ( ! empty( $section_data['field_rules'] ) ) {
					foreach ( array_keys( $section_data['field_rules'] ) as $field_id ) {
						$field_visibility[ $field_id ] = false;
						$this->debug_log( "Field: $field_id | Hidden by section rule" );
					}
				}
			} elseif ( ! empty( $section_data['field_rules'] ) ) {
				// Section visible: evaluate each field rule.
				foreach ( $section_data['field_rules'] as $field_id => $field_rule_data ) {
					$rule_obj = is_array( $field_rule_data )
						? Rule::from_array( array_merge( $field_rule_data, array( 'field_id' => $field_id ) ) )
						: $field_rule_data;
					if ( ! $rule_obj instanceof Rule ) {
						continue;
					}
					$field_is_visible              = $this->should_show_field( $rule_obj, $context );
					$field_visibility[ $field_id ] = $field_is_visible;
					$this->debug_log( "Field: $field_id | Visibility: " . ( $field_is_visible ? 'SHOW' : 'HIDE' ) );
				}
			}
		}

		$result = array(
			'sections' => $section_visibility,
			'fields'   => $field_visibility,
		);

		$this->debug_log( '=== Hierarchical visibility map complete ===' );
		$this->debug_log( 'Result: ' . wp_json_encode( $result ) );

		return $result;
	}

	/**
	 * Build map from rules section keys to layout DOM section IDs.
	 * Rules may be stored under custom IDs (e.g. rule-country-us-company--ssn) while
	 * layout uses section['id'] or single_step_fieldset_N. Match by id or by section name.
	 *
	 * @since 2.6.0
	 * @param array $layout    Page layout with fieldsets.
	 * @param array $rules_keys Rules section keys to match.
	 * @return array Map of rules_key => dom_section_id.
	 */
	private function build_rules_key_to_dom_map( $layout, $rules_keys ) {
		$map = array();
		if ( empty( $layout['fieldsets'] ) || ! is_array( $layout['fieldsets'] ) || empty( $rules_keys ) ) {
			return $map;
		}

		$matched = array();
		foreach ( $layout['fieldsets'] as $step => $sections ) {
			if ( ! is_array( $sections ) ) {
				continue;
			}
			foreach ( $sections as $section_index => $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}
				$dom_section_id  = isset( $section['id'] ) && is_string( $section['id'] ) && '' !== trim( $section['id'] )
					? sanitize_html_class( $section['id'] )
					: $step . '_fieldset_' . $section_index;
				$section_name    = isset( $section['name'] ) ? $section['name'] : '';
				$name_key        = Field_Conditions_Handler::get_fkcf_key_from_section_name( $section_name );
				$normalized_name = str_replace( '--', '-', $name_key );

				$section_id = isset( $section['id'] ) && is_string( $section['id'] ) ? sanitize_html_class( $section['id'] ) : '';
				foreach ( $rules_keys as $rules_key ) {
					if ( isset( $matched[ $rules_key ] ) ) {
						continue;
					}
					$normalized_rules = str_replace( '--', '-', $rules_key );
					if ( ( '' !== $section_id && $section_id === $rules_key )
						|| ( '' !== $name_key && $name_key === $rules_key )
						|| ( '' !== $normalized_name && $normalized_name === $normalized_rules )
						|| $dom_section_id === $rules_key ) {
						$map[ $rules_key ]     = $dom_section_id;
						$matched[ $rules_key ] = true;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private function debug_log( $message ) {
		// Debug logging disabled.
	}

	/**
	 * Get compiled JavaScript rules for frontend.
	 *
	 * @since 2.0.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array JavaScript-friendly rules array.
	 */
	public function get_js_rules( $checkout_id ) {
		$rules = Rule_Storage::get_rules( $checkout_id );

		if ( empty( $rules ) ) {
			return array();
		}

		$js_rules = array();

		foreach ( $rules as $field_id => $rule ) {
			if ( ! $rule->is_enabled() ) {
				continue;
			}

			$js_rules[ $field_id ] = array(
				'action'      => $rule->get_action(),
				'group_logic' => $rule->get_group_logic(),
				'groups'      => $this->convert_groups_to_js( $rule->get_rule_groups() ),
			);
		}

		return $js_rules;
	}

	/**
	 * Get compiled JavaScript section rules for frontend.
	 *
	 * Section rules with field-based conditions need client-side evaluation
	 * so visibility updates immediately when user changes form fields.
	 *
	 * @since 2.5.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array JavaScript-friendly section rules array keyed by DOM section ID.
	 */
	public function get_js_section_rules( $checkout_id ) {
		$all_rules = Rule_Storage::get_all_rules_v2( $checkout_id );

		if ( empty( $all_rules['sections'] ) ) {
			return array();
		}

		$js_section_rules = array();

		// Get layout to map FKCF keys to DOM section IDs.
		$layout          = class_exists( '\WFACP_Common' ) ? \WFACP_Common::get_page_layout( $checkout_id ) : null;
		$fkcf_to_dom_map = array();

		if ( ! empty( $layout['fieldsets'] ) && is_array( $layout['fieldsets'] ) ) {
			foreach ( $layout['fieldsets'] as $step => $sections ) {
				if ( ! is_array( $sections ) ) {
					continue;
				}
				foreach ( $sections as $section_index => $section ) {
					if ( ! is_array( $section ) ) {
						continue;
					}
					$dom_section_id = isset( $section['id'] ) && is_string( $section['id'] ) && '' !== trim( $section['id'] )
						? sanitize_html_class( $section['id'] )
						: $step . '_fieldset_' . $section_index;
					$section_name   = isset( $section['name'] ) ? $section['name'] : '';
					$fkcf_key       = Field_Conditions_Handler::get_fkcf_key_from_section_name( $section_name );

					if ( '' !== $fkcf_key ) {
						$fkcf_to_dom_map[ $fkcf_key ] = $dom_section_id;
					}
					// Also map dom_section_id to itself so rules stored under admin section ID (e.g. single_step_fieldset_1) work.
					$fkcf_to_dom_map[ $dom_section_id ] = $dom_section_id;
				}
			}
		}

		// Add mapping for rules stored under custom IDs (e.g. rule-country-us-company--ssn).
		$rules_keys_with_section_rule = array();
		foreach ( $all_rules['sections'] as $key => $data ) {
			if ( ! empty( $data['section_rule'] ) ) {
				$rules_keys_with_section_rule[] = $key;
			}
		}
		$rules_key_to_dom = $this->build_rules_key_to_dom_map( $layout, $rules_keys_with_section_rule );
		$fkcf_to_dom_map  = array_merge( $fkcf_to_dom_map, $rules_key_to_dom );

		foreach ( $all_rules['sections'] as $fkcf_key => $section_data ) {
			if ( empty( $section_data['section_rule'] ) ) {
				continue;
			}

			$section_rule = $section_data['section_rule'];
			if ( ! $section_rule->is_enabled() ) {
				continue;
			}

			// Get DOM section ID for this FKCF key.
			$dom_section_id = isset( $fkcf_to_dom_map[ $fkcf_key ] ) ? $fkcf_to_dom_map[ $fkcf_key ] : $fkcf_key;

			$js_section_rules[ $dom_section_id ] = array(
				'action'      => $section_rule->get_action(),
				'group_logic' => $section_rule->get_group_logic(),
				'groups'      => $this->convert_groups_to_js( $section_rule->get_rule_groups() ),
				'fkcf_key'    => $fkcf_key,
			);
		}

		return $js_section_rules;
	}

	/**
	 * Convert rule groups to JavaScript-friendly format.
	 *
	 * @since  2.0.0
	 * @param  array $groups Condition groups.
	 * @return array JavaScript-friendly groups.
	 */
	private function convert_groups_to_js( $groups ) {
		$js_groups = array();

		foreach ( $groups as $group ) {
			if ( ! $group instanceof Condition_Group ) {
				continue;
			}

			$js_conditions = array();

			foreach ( $group->get_conditions() as $condition ) {
				if ( ! $condition instanceof Condition ) {
					continue;
				}

				$js_conditions[] = array(
					'type'         => $condition->get_type(),
					'operator'     => $condition->get_operator(),
					'operand_type' => $condition->get_operand_type(),
					'operand'      => $condition->get_operand(),
					'value'        => $condition->get_value(),
					'field_id'     => $condition->get_field_id(),
				);
			}

			// Condition groups use AND logic internally
			$js_groups[] = array(
				'logic'      => 'AND',
				'conditions' => $js_conditions,
			);
		}

		return $js_groups;
	}
}
