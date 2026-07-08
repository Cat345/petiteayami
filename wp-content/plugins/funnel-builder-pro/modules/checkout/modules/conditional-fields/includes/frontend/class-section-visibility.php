<?php
/**
 * Section_Visibility
 *
 * Handles section-level conditional visibility via wfacp_section_wrapper_classes filter.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Frontend
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Frontend;

use FunnelKit\Checkout\Modules\Conditional_Fields\Engine\Section_Evaluator;
use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;
use FunnelKit\Checkout\Modules\Conditional_Fields\Field_Conditions_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section_Visibility class.
 *
 * @since 2.4.0
 */
#[\AllowDynamicProperties]
class Section_Visibility {

	/**
	 * Singleton instance.
	 *
	 * @var Section_Visibility
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.4.0
	 * @return Section_Visibility
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.4.0
	 */
	private function init_hooks() {
		add_filter( 'wfacp_section_wrapper_classes', array( $this, 'add_visibility_class' ), 10, 4 );
		add_filter( 'wfacp_form_section', array( $this, 'add_section_identifier_class' ), 5, 3 );
	}

	/**
	 * Add fkcf-section-{id} class so frontend JS can target sections for visibility.
	 *
	 * Uses section['id'] when available (e.g. contact-information-0), otherwise
	 * falls back to step_fieldset_index for compatibility.
	 *
	 * @since 2.4.0
	 * @param array  $section       Section data.
	 * @param int    $section_index Section index within the step.
	 * @param string $step          Step key.
	 * @return array Modified section.
	 */
	public function add_section_identifier_class( $section, $section_index, $step ) {
		if ( ! is_array( $section ) ) {
			return $section;
		}

		$section_id = isset( $section['id'] ) && is_string( $section['id'] ) && '' !== trim( $section['id'] )
			? sanitize_html_class( $section['id'] )
			: sanitize_html_class( $step . '_fieldset_' . $section_index );
		$class      = 'fkcf-section-' . $section_id;

		if ( ! isset( $section['class'] ) || ! is_string( $section['class'] ) ) {
			$section['class'] = '';
		}
		$section['class'] = trim( $section['class'] . ' ' . $class );

		return $section;
	}

	/**
	 * Add fkcf-section-hidden class when conditional rules say section should be hidden.
	 * Uses wfacp_section_wrapper_classes filter (applied at form.php section wrapper).
	 *
	 * @since 2.4.0
	 * @param string $classes       Current section wrapper classes.
	 * @param string $step          Step key (e.g. single_step, first_step).
	 * @param int    $section_index Section index within the step.
	 * @param array  $section       Section data.
	 * @return string Modified classes.
	 */
	public function add_visibility_class( $classes, $step, $section_index, $section ) {
		$checkout_id    = $this->get_current_checkout_id();
		$section_name   = isset( $section['name'] ) ? $section['name'] : '';
		$dom_section_id = isset( $section['id'] ) && is_string( $section['id'] ) && '' !== trim( $section['id'] )
			? sanitize_html_class( $section['id'] )
			: sanitize_text_field( $step . '_fieldset_' . $section_index );

		if ( ! $checkout_id ) {
			return $classes;
		}

		// Rules may be stored by FKCF key, admin section ID (single_step_fieldset_N), or custom IDs (rule-country-us-company--ssn).
		$fkcf_key     = Field_Conditions_Handler::get_fkcf_key_from_section_name( $section_name );
		$section_rule = null;

		if ( '' !== $fkcf_key ) {
			$section_rule = Rule_Storage::get_section_rule( $checkout_id, $fkcf_key );
			if ( ! $section_rule ) {
				Field_Conditions_Handler::sync_section_rule_from_rest_meta( $checkout_id, $fkcf_key );
				$section_rule = Rule_Storage::get_section_rule( $checkout_id, $fkcf_key );
			}
		}

		// Fallback: rules stored under admin section ID (e.g. single_step_fieldset_1).
		if ( ! $section_rule && '' !== $dom_section_id ) {
			$section_rule = Rule_Storage::get_section_rule( $checkout_id, $dom_section_id );
		}

		// Fallback: rules stored under custom IDs (e.g. rule-country-us-company--ssn) - try matching keys.
		if ( ! $section_rule && '' !== $section_name ) {
			$section_rule = Rule_Storage::get_section_rule_by_section_match( $checkout_id, $section_name, $dom_section_id );
		}

		if ( ! $section_rule ) {
			return $classes;
		}

		// Evaluate: should_show_section returns true if visible, false if hidden.
		$visible = Section_Evaluator::should_show_section( $section_rule );

		if ( ! $visible ) {
			$classes .= ' fkcf-section-hidden';
		}

		return $classes;
	}

	/**
	 * Get current checkout ID.
	 *
	 * @since 2.4.0
	 * @return int Checkout ID or 0.
	 */
	private function get_current_checkout_id() {
		if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'get_id' ) ) {
			$id = absint( \WFACP_Common::get_id() );
			if ( $id > 0 ) {
				return $id;
			}
		}

		// Fallback: during AJAX (update_order_review), checkout ID may be in POST.
		if ( wp_doing_ajax() && isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$post_data = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsing form query string; _wfacp_post_id is absint below.
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data );
			if ( ! empty( $post_data['_wfacp_post_id'] ) ) {
				return absint( $post_data['_wfacp_post_id'] );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reading checkout ID for visibility; value is absint.
		if ( wp_doing_ajax() && ! empty( $_POST['_wfacp_post_id'] ) ) {
			return absint( wp_unslash( $_POST['_wfacp_post_id'] ) );
		}

		global $post;
		if ( $post && 'wfacp_checkout' === get_post_type( $post ) ) {
			return absint( $post->ID );
		}

		return 0;
	}
}
