<?php
/**
 * User_Evaluator
 *
 * Evaluates user-based conditions.
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
 * User_Evaluator class.
 *
 * @since 2.0.0
 */
class User_Evaluator {

	/**
	 * Evaluate a user-based condition.
	 *
	 * @since 2.0.0
	 * @param Condition $condition Condition to evaluate.
	 * @return bool True if condition is met, false otherwise.
	 */
	public static function evaluate( $condition ) {
		if ( ! $condition instanceof Condition || 'user' !== $condition->get_type() ) {
			return false;
		}

		$operator = $condition->get_operator();
		$value    = $condition->get_value();

		switch ( $operator ) {
			case 'user_role_eq':
				return self::evaluate_user_role_eq( $value );

			case 'user_role_ne':
				return self::evaluate_user_role_ne( $value );

			case 'user_logged_in_eq':
				return self::evaluate_user_logged_in( '==', $value );

			case 'user_logged_in_ne':
				return self::evaluate_user_logged_in( '!=', $value );

			default:
				return false;
		}
	}

	/**
	 * Evaluate user role equals condition.
	 *
	 * @since 2.0.0
	 * @param string|array $roles Role(s) to check.
	 * @return bool
	 */
	private static function evaluate_user_role_eq( $roles ) {
		$user_roles = self::get_current_user_roles();

		if ( ! is_array( $roles ) ) {
			$roles = array( $roles );
		}

		// Check if user has any of the specified roles.
		$intersection = array_intersect( $user_roles, $roles );

		return ! empty( $intersection );
	}

	/**
	 * Evaluate user role not equals condition.
	 *
	 * @since 2.0.0
	 * @param string|array $roles Role(s) to check.
	 * @return bool
	 */
	private static function evaluate_user_role_ne( $roles ) {
		return ! self::evaluate_user_role_eq( $roles );
	}

	/**
	 * Evaluate user logged in condition.
	 *
	 * @since 2.0.0
	 * @param string $comparison Comparison operator (==, !=).
	 * @param string $value Value to compare ('yes' or 'no').
	 * @return bool
	 */
	private static function evaluate_user_logged_in( $comparison, $value ) {
		$is_logged_in = is_user_logged_in();
		// Normalize value: expect 'yes' or 'no'; handle arrays/objects from storage.
		$value_str  = is_array( $value ) ? ( isset( $value['key'] ) ? $value['key'] : (string) reset( $value ) ) : (string) $value;
		$expect_yes = in_array( strtolower( $value_str ), array( 'yes', 'logged_in', '1', 'true' ), true );

		if ( '==' === $comparison ) {
			return $is_logged_in === $expect_yes;
		}

		if ( '!=' === $comparison ) {
			return $is_logged_in !== $expect_yes;
		}

		return false;
	}

	/**
	 * Get current user roles.
	 *
	 * @since 2.0.0
	 * @return array User roles.
	 */
	private static function get_current_user_roles() {
		if ( ! is_user_logged_in() ) {
			return array( 'guest' );
		}

		$user = wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return array( 'guest' );
		}

		$roles = (array) $user->roles;

		return ! empty( $roles ) ? $roles : array( 'guest' );
	}

	/**
	 * Check if user is logged in.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public static function is_user_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Get available user roles for admin UI.
	 *
	 * @since 2.0.0
	 * @return array Array of role name => label.
	 */
	public static function get_available_roles() {
		global $wp_roles;

		$roles_obj = isset( $wp_roles ) ? $wp_roles : new \WP_Roles();

		$roles = $roles_obj->get_names();

		// Add guest role.
		$roles['guest'] = __( 'Guest (Not Logged In)', 'woofunnels-aero-checkout' );

		return $roles;
	}
}
