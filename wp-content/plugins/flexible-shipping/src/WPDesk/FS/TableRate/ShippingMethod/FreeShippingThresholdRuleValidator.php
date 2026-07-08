<?php
/**
 * Class FreeShippingThresholdRuleValidator
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

declare( strict_types=1 );

namespace WPDesk\FS\TableRate\ShippingMethod;

use WPDesk\FS\TableRate\Rule\Condition\Price;
use WPDesk\FS\TableRate\Rule\Condition\None;

/**
 * Validates if free shipping threshold is covered by price rules.
 */
final class FreeShippingThresholdRuleValidator {

	/**
	 * @param mixed $threshold Free shipping threshold after WooCommerce formatting.
	 * @param array $rules     Normalized method rules.
	 *
	 * @return bool
	 */
	public function is_valid( $threshold, array $rules ): bool {
		if ( '' === $threshold || ! is_numeric( $threshold ) ) {
			return true;
		}

		$threshold = (float) $threshold;

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || $this->is_deleted( $rule ) ) {
				continue;
			}

			if ( $this->rule_covers_threshold( $threshold, $rule ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param float $threshold Free shipping threshold.
	 * @param array $rule      Rule settings.
	 *
	 * @return bool
	 */
	private function rule_covers_threshold( float $threshold, array $rule ): bool {
		if ( ! isset( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
			return false;
		}

		foreach ( $rule['conditions'] as $condition ) {
			if ( ! is_array( $condition ) || $this->is_deleted( $condition ) ) {
				continue;
			}

			if ( ( $condition['condition_id'] ?? '' ) === None::CONDITION_ID ) {
				return true;
			}

			if ( ( $condition['condition_id'] ?? '' ) !== Price::CONDITION_ID ) {
				continue;
			}

			if ( $this->condition_covers_threshold( $threshold, $condition ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param float $threshold Free shipping threshold.
	 * @param array $condition Price condition settings.
	 *
	 * @return bool
	 */
	private function condition_covers_threshold( float $threshold, array $condition ): bool {
		$min = $condition[ Price::MIN ] ?? '';
		$max = $condition[ Price::MAX ] ?? '';

		if ( '' !== $min && ! is_numeric( $min ) ) {
			return false;
		}

		if ( '' !== $max && ! is_numeric( $max ) ) {
			return false;
		}

		$min = '' === $min ? 0.0 : (float) $min;

		return $min <= $threshold && ( '' === $max || (float) $max >= $threshold );
	}

	/**
	 * @param array $settings Rule or condition settings.
	 *
	 * @return bool
	 */
	private function is_deleted( array $settings ): bool {
		return filter_var( $settings['deleted'] ?? false, FILTER_VALIDATE_BOOLEAN );
	}
}
