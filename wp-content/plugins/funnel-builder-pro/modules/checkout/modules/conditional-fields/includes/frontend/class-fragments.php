<?php
/**
 * Fragments
 *
 * Handles WooCommerce fragments for dynamic updates.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Frontend
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Frontend;

use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;
use FunnelKit\Checkout\Modules\Conditional_Fields\Engine\Rule_Engine;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fragments class.
 *
 * @since 2.0.0
 */
class Fragments {

	/**
	 * Singleton instance.
	 *
	 * @var Fragments
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Fragments
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
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	private function init_hooks() {
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_conditional_fragments' ) );
	}

	/**
	 * Add conditional field fragments to order review update.
	 *
	 * @since 2.0.0
	 * @param array $fragments Fragments array.
	 * @return array Modified fragments.
	 */
	public function add_conditional_fragments( $fragments ) {
		$this->debug_log( 'add_conditional_fragments called' );

		// Get current checkout ID.
		$checkout_id = $this->get_current_checkout_id();
		$this->debug_log( 'Checkout ID: ' . $checkout_id );

		if ( ! $checkout_id ) {
			$this->debug_log( 'No checkout ID found, returning fragments unchanged' );
			return $fragments;
		}

		// Check if checkout has rules.
		if ( ! Rule_Storage::has_rules( $checkout_id ) ) {
			$this->debug_log( 'Checkout has no rules, returning fragments unchanged' );
			return $fragments;
		}

		$this->debug_log( 'Checkout has rules, evaluating...' );

		// Clear cart cache to ensure fresh data for evaluation.
		// This is critical because cart may have changed since page load.
		\FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager::clear_all_cart_cache();

		// Ensure cart totals are recalculated before evaluation.
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}

		// Get cart total for logging (use 'edit' context for raw numeric value).
		$cart_total = WC()->cart ? WC()->cart->get_total( 'edit' ) : 0;
		$this->debug_log( 'Current cart total: ' . $cart_total );

		// Evaluate rules and get hierarchical visibility map (sections + fields).
		$engine         = Rule_Engine::get_instance();
		$visibility_map = $engine->get_hierarchical_visibility_map( $checkout_id );

		$this->debug_log( 'Hierarchical visibility map: ' . wp_json_encode( $visibility_map ) );

		// Get full cart info for client-side evaluation (including products, categories, tags for cart_contains rules).
		$cart_info = \FunnelKit\Checkout\Modules\Conditional_Fields\Engine\Cart_Evaluator::get_cart_info();

		// Add to fragments.
		$fragments['fkcf_visibility_map'] = wp_json_encode( $visibility_map );
		$fragments['fkcf_cart_info']      = wp_json_encode( $cart_info );
		$fragments['fkcf_checkout_id']    = $checkout_id;

		$this->debug_log( 'Fragments updated with visibility map and cart info' );

		return $fragments;
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
	 * Get current checkout ID.
	 * On update_order_review AJAX, WFACP sets id from posted_data; fallback to POST.
	 *
	 * @since 2.0.0
	 * @return int Checkout ID or 0.
	 */
	private function get_current_checkout_id() {
		if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'get_id' ) ) {
			$id = absint( \WFACP_Common::get_id() );
			if ( $id > 0 ) {
				return $id;
			}
		}

		// Fallback: during update_order_review AJAX, checkout ID may be in POST (WooCommerce sends serialized post_data).
		if ( wp_doing_ajax() && isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$post_data = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsing form query string; _wfacp_post_id is absint below.
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data );
			if ( ! empty( $post_data['_wfacp_post_id'] ) ) {
				return absint( $post_data['_wfacp_post_id'] );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reading checkout ID for fragment context; value is absint.
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
