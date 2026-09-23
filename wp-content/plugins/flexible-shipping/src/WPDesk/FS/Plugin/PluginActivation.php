<?php
/**
 * Class PluginActivation
 *
 * @package WPDesk\FS\Plugin
 */

namespace WPDesk\FS\Plugin;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Can redirect to FS Info tab on first plugin activation.
 */
class PluginActivation implements Hookable {

	const OPTION_NAME        = 'flexible-shipping-activation-redirected';
	const SHIPPING_METHOD_ID = 'flexible_shipping_info';
	const REDIRECT_MARKER    = 'flexible-shipping-activation-redirect';

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'admin_init', [ $this, 'redirect_on_first_activation_or_do_nothing' ] );
	}

	/**
	 * Redirects to the FS Info page after the first activation.
	 */
	public function redirect_on_first_activation_or_do_nothing() {
		if ( 0 !== (int) get_option( self::OPTION_NAME, 1 ) ) {
			return;
		}

		if ( $this->is_redirect_destination() ) {
			update_option( self::OPTION_NAME, 1 );

			return;
		}

		if ( ! $this->are_headers_sent() && wp_safe_redirect( $this->get_redirect_url() ) ) {
			$this->terminate();

			return;
		}

		add_action( 'admin_footer', [ $this, 'redirect_with_javascript' ] );
	}

	/**
	 * Redirects with JavaScript when an HTTP redirect cannot be sent.
	 */
	public function redirect_with_javascript() {
		wp_print_inline_script_tag(
			'window.location.replace(' . wp_json_encode( $this->get_redirect_url() ) . ');'
		);
	}

	/**
	 * Checks whether the current request is the redirect destination.
	 */
	private function is_redirect_destination(): bool {
		if (
			! isset( $_GET['page'], $_GET['tab'], $_GET['section'], $_GET[ self::REDIRECT_MARKER ] )
		) {
			return false;
		}

		return 'wc-settings' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'shipping' === sanitize_key( wp_unslash( $_GET['tab'] ) )
			&& self::SHIPPING_METHOD_ID === sanitize_key( wp_unslash( $_GET['section'] ) )
			&& '1' === sanitize_key( wp_unslash( $_GET[ self::REDIRECT_MARKER ] ) );
	}

	/**
	 * Returns the marked redirect destination URL.
	 */
	private function get_redirect_url(): string {
		return add_query_arg(
			self::REDIRECT_MARKER,
			'1',
			admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . self::SHIPPING_METHOD_ID )
		);
	}

	/**
	 * Checks whether response headers have already been sent.
	 */
	protected function are_headers_sent(): bool {
		return headers_sent();
	}

	/**
	 * .
	 *
	 * @codeCoverageIgnore
	 */
	protected function terminate() {
		die();
	}

	/**
	 * .
	 */
	public function add_activation_option_if_not_present() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, 0 );
		}
	}
}
