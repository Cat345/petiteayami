<?php
/**
 * Shipping method logo assets for checkout blocks.
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

namespace WPDesk\FS\TableRate\ShippingMethod;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use WPDesk\FS\TableRate\ShippingMethodSingle;
use WPDesk_Flexible_Shipping;

/**
 * Enqueue logo assets for WooCommerce checkout blocks.
 */
class MethodLogoCheckoutBlocksAssets implements Hookable {
	const SCRIPT_HANDLE = 'flexible-shipping-shipping-method-logo';

	/**
	 * @var string
	 */
	private $plugin_url;

	/**
	 * @var string
	 */
	private $scripts_version;

	/**
	 * @param string $plugin_url      .
	 * @param string $scripts_version .
	 */
	public function __construct( $plugin_url, $scripts_version ) {
		$this->plugin_url      = rtrim( (string) $plugin_url, '/' ) . '/';
		$this->scripts_version = $scripts_version;
	}

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->should_enqueue_assets() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->plugin_url . 'assets/js/shipping-method-block-checkout' . $suffix . '.js',
			[],
			$this->scripts_version,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'__fsShippingMethodLogoBlocks',
			[
				'method_ids'              => [
					WPDesk_Flexible_Shipping::METHOD_ID,
					ShippingMethodSingle::SHIPPING_METHOD_ID,
				],
				'logo_url_key'            => RateCalculator::METHOD_LOGO_URL,
				'logo_alt_key'            => RateCalculator::METHOD_LOGO_ALT,
				'description_key'         => RateCalculator::DESCRIPTION,
				'description_encoded_key' => RateCalculator::DESCRIPTION_BASE64ENCODED,
				'description_class'       => 'shipping-method-description flexible-shipping-method-description-block',
				'wrapper_class'           => 'flexible-shipping-method-logo-block',
			]
		);
	}

	/**
	 * @return bool
	 */
	private function should_enqueue_assets(): bool {
		if ( ! is_checkout() ) {
			return false;
		}

		if ( function_exists( 'has_block' ) ) {
			return has_block( 'woocommerce/checkout' );
		}

		return true;
	}
}
