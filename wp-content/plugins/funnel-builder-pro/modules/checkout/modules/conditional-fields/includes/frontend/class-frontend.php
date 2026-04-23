<?php
/**
 * Frontend
 *
 * Handles frontend functionality and script enqueuing.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Frontend
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Frontend;

use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;
use FunnelKit\Checkout\Modules\Conditional_Fields\Engine\Rule_Engine;
use FunnelKit\Checkout\Modules\Conditional_Fields\Engine\Cart_Evaluator;
use FunnelKit\Checkout\Modules\Conditional_Fields\Field_Conditions_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class.
 *
 * @since 2.0.0
 */
class Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var Frontend
	 */
	private static $instance = null;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Current checkout ID.
	 *
	 * @var int
	 */
	private $checkout_id = 0;

	/**
	 * Cached visibility map (avoids duplicate computation).
	 *
	 * @var array|null
	 */
	private $visibility_map = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Frontend
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
		$this->plugin_name = 'wfacp-conditional-fields';
		$this->version     = defined( 'FKCF_VERSION' ) ? FKCF_VERSION : '2.0.0';

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	private function init_hooks() {
		add_action( 'wfacp_after_checkout_page_found', array( $this, 'init_conditional_logic' ) );
	}

	/**
	 * Initialize conditional logic for checkout page.
	 *
	 * @since 2.0.0
	 */
	public function init_conditional_logic() {
		// Get current checkout ID.
		$this->checkout_id = $this->get_current_checkout_id();

		if ( ! $this->checkout_id ) {
			return;
		}

		// Check if checkout has rules in FKCF format.
		$has_rules = Rule_Storage::has_rules( $this->checkout_id );

		// If no FKCF rules, try syncing from REST meta (handles desync scenario).
		if ( ! $has_rules ) {
			$synced = Field_Conditions_Handler::sync_all_rules_from_rest_meta( $this->checkout_id );
			if ( $synced ) {
				$has_rules = Rule_Storage::has_rules( $this->checkout_id );
			}
		}

		if ( ! $has_rules ) {
			return;
		}

		// Enqueue scripts and styles.
		add_action( 'wfacp_internal_css', array( $this, 'enqueue_scripts' ) );

		// Output initial hide CSS in <head> so hidden fields never flash on first paint.
		add_action( 'wfacp_header_print_in_head', array( $this, 'output_initial_hide_css' ) );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_scripts() {
		// Enqueue styles.
		wp_enqueue_style(
			$this->plugin_name . '-frontend',
			FKCF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue scripts.
		// wp_enqueue_script(
		// $this->plugin_name . '-frontend',
		// FKCF_PLUGIN_URL . 'assets/js/frontend.js',
		// array( 'jquery' ),
		// $this->version,
		// true
		// );

		// Get JavaScript rules.
		$engine   = Rule_Engine::get_instance();
		$js_rules = $engine->get_js_rules( $this->checkout_id );

		// Get section rules for client-side evaluation (needed for field-based conditions).
		$js_section_rules = $engine->get_js_section_rules( $this->checkout_id );

		// Evaluate server-side conditions (cart, user) and pass hierarchical visibility map (sections + fields).
		$visibility_map = $this->get_visibility_map();

		// Get cart info for client-side evaluation of cart conditions.
		$cart_info = Cart_Evaluator::get_cart_info();

		// Get user info for client-side evaluation of user conditions.
		$current_user = wp_get_current_user();
		$user_roles   = ( $current_user->exists() && ! empty( $current_user->roles ) ) ? $current_user->roles : array( 'guest' );
		$user_info    = array(
			'is_logged_in' => is_user_logged_in(),
			'user_id'      => get_current_user_id(),
			'roles'        => $user_roles,
		);

		// Localize script with rules and settings.
		wp_localize_script(
			'wfacp_checkout_js',
			'fkcfData',
			array(
				'rules'         => $js_rules,
				'sectionRules'  => $js_section_rules,
				'visibilityMap' => $visibility_map,
				'cartInfo'      => $cart_info,
				'userInfo'      => $user_info,
				'checkoutId'    => $this->checkout_id,
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'fkcf_frontend_nonce' ),
			)
		);
	}

	/**
	 * Output a <style> tag with display:none for initially hidden fields/sections.
	 *
	 * This runs during wfacp_internal_css (before JS loads) so the browser hides
	 * conditional fields via CSS immediately — no flash of content on first paint.
	 * The JS will take over this same <style id="fkcf-hide-css"> tag afterwards.
	 *
	 * @since 2.0.0
	 */
	public function output_initial_hide_css() {
		$visibility_map = $this->get_visibility_map();

		$selectors = array();

		// Hidden fields.
		if ( ! empty( $visibility_map['fields'] ) && is_array( $visibility_map['fields'] ) ) {
			foreach ( $visibility_map['fields'] as $field_id => $visible ) {
				if ( $visible ) {
					continue;
				}
				$selectors = array_merge( $selectors, $this->get_field_css_selectors( $field_id ) );
			}
		}

		// Hidden sections.
		if ( ! empty( $visibility_map['sections'] ) && is_array( $visibility_map['sections'] ) ) {
			foreach ( $visibility_map['sections'] as $section_id => $visible ) {
				if ( $visible ) {
					continue;
				}
				$selectors = array_merge( $selectors, $this->get_section_css_selectors( $section_id ) );
			}
		}

		if ( empty( $selectors ) ) {
			return;
		}

		$css = implode( ',', $selectors ) . '{display:none !important}';
		echo '<style id="fkcf-hide-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get CSS selectors for a field ID. Mirrors JS getFieldCssSelectors().
	 *
	 * @param string $field_id Field identifier.
	 * @return array CSS selector strings.
	 */
	private function get_field_css_selectors( $field_id ) {
		// Validate field_id for CSS context - only allow safe characters.
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $field_id ) ) {
			return array();
		}

		$s   = array();
		$esc = $field_id;

		$s[] = '#' . $esc . '_field';
		$s[] = '.' . $esc;
		$s[] = '#' . $esc . '_field_file_upload';
		$s[] = '.wfacp_' . $esc . '_field';
		$s[] = '[data-field-id="' . $esc . '"]';
		$s[] = '[data-field-key="' . $esc . '"]';

		if ( 'bwfan_birthday_date' === $field_id ) {
			$s[] = '.bwfan-birthday-wrap';
			$s[] = '.wfacp-dob-field-sec';
		}

		return $s;
	}

	/**
	 * Get CSS selectors for a section ID. Mirrors JS getSectionCssSelectors().
	 *
	 * @param string $section_id Section identifier.
	 * @return array CSS selector strings.
	 */
	private function get_section_css_selectors( $section_id ) {
		// Validate section_id for CSS context - only allow safe characters.
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $section_id ) ) {
			return array();
		}

		$esc = $section_id;

		return array(
			'.fkcf-section-' . $esc,
			'.wfacp_' . $esc . '_fields',
			'.woocommerce-' . $esc . '-fields',
			'#' . $esc . '_fields',
		);
	}

	/**
	 * Get the hierarchical visibility map, cached for the request.
	 *
	 * @return array
	 */
	private function get_visibility_map() {
		if ( null === $this->visibility_map ) {
			$engine               = Rule_Engine::get_instance();
			$this->visibility_map = $engine->get_hierarchical_visibility_map( $this->checkout_id );
		}

		return $this->visibility_map;
	}

	/**
	 * Get current checkout ID.
	 *
	 * @since 2.0.0
	 * @return int Checkout ID or 0 if not found.
	 */
	private function get_current_checkout_id() {
		// Try WFACP method first.
		if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'get_id' ) ) {
			$checkout_id = \WFACP_Common::get_id();
			if ( $checkout_id ) {
				return absint( $checkout_id );
			}
		}

		// Fallback: try to get from global.
		global $post;
		if ( $post && 'wfacp_checkout' === get_post_type( $post ) ) {
			return absint( $post->ID );
		}

		return 0;
	}

	/**
	 * Get current checkout ID (public method).
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_checkout_id() {
		return $this->checkout_id;
	}
}
