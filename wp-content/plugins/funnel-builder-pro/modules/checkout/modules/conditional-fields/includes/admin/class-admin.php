<?php
/**
 * Admin
 *
 * Handles admin-specific functionality.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Admin
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 *
 * @since 2.0.0
 */
class Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Admin
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
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Admin
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since 2.0.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_styles( $hook_suffix ) {
		// Load on plugin settings page.
		if ( 'settings_page_fkcf-settings' === $hook_suffix ) {
			$this->enqueue_settings_styles();
			return;
		}
	}

	/**
	 * Enqueue styles for settings page.
	 *
	 * @since 2.4.0
	 */
	private function enqueue_settings_styles() {

		wp_enqueue_style(
			$this->plugin_name . '-admin',
			FKCF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue accordion styles (Phase 2).
		wp_enqueue_style(
			$this->plugin_name . '-admin-accordion',
			FKCF_PLUGIN_URL . 'assets/css/admin-accordion.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue select2 for better dropdowns (use WooCommerce's bundled selectWoo if available).
		if ( ! wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'selectWoo' );
		} else {
			wp_enqueue_style( 'select2' );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 2.0.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		// Load on plugin settings page.
		if ( 'settings_page_fkcf-settings' === $hook_suffix ) {
			$this->enqueue_settings_scripts();
			return;
		}
	}

	/**
	 * Enqueue scripts for settings page.
	 *
	 * @since 2.4.0
	 */
	private function enqueue_settings_scripts() {
		// Admin UI is now powered by React app in funnel-builder plugin.
		// Enqueue select2 for any legacy settings page usage (use WooCommerce's bundled selectWoo if available).
		if ( ! wp_script_is( 'select2', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
		} else {
			wp_enqueue_script( 'select2' );
		}
	}
}
