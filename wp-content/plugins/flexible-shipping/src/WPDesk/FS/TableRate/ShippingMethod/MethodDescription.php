<?php
/**
 * Class MethodDescription
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

namespace WPDesk\FS\TableRate\ShippingMethod;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use FSVendor\WPDesk\View\Renderer\Renderer;
use WC_Shipping_Rate;
use WC_Shipping_Zones;
use WPDesk\FS\TableRate\ShippingMethodSingle;
use WPDesk_Flexible_Shipping;

/**
 * Can display method description.
 */
class MethodDescription implements Hookable {

	/**
	 * Renderer.
	 *
	 * @var Renderer;
	 */
	private $renderer;

	/**
	 * MethodDescription constructor.
	 *
	 * @param Renderer $renderer .
	 */
	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'woocommerce_after_shipping_rate', [ $this, 'display_description_if_present' ], 10, 2 );
		add_filter( 'woocommerce_package_rates', [ $this, 'add_description_to_rate_if_present' ] );
	}

	/**
	 * @param WC_Shipping_Rate[] $rates .
	 *
	 * @return WC_Shipping_Rate[]
	 */
	public function add_description_to_rate_if_present( $rates ) {
		if ( $this->is_store_api_request() ) {
			return $rates;
		}

		foreach ( $rates as $rate ) {
			if ( ! $rate instanceof WC_Shipping_Rate || ! $this->should_display_method_description( $rate ) ) {
				continue;
			}

			if ( ! $this->supports_native_rate_description( $rate ) ) {
				continue;
			}

			if ( '' !== $rate->get_description() ) {
				continue;
			}

			$description = $this->get_method_description_from_meta_data( $rate );

			if ( '' === $description ) {
				continue;
			}

			$rate->set_description( $this->sanitize_method_description_for_rate( $description ) );
		}

		return $rates;
	}

	/**
	 * @param WC_Shipping_Rate $method .
	 * @param int              $index  .
	 */
	public function display_description_if_present( $method, $index ) {
		if ( ! $method instanceof WC_Shipping_Rate || ! $this->should_display_method_description( $method ) ) {
			return;
		}

		$description = $this->get_method_description( $method );

		if ( '' !== $description ) {
			$method_logo_data = $this->get_method_logo_data( $method );

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->renderer->render(
				'cart/flexible-shipping/after-shipping-rate',
				[
					'method_description' => $description,
					'method_logo_url'    => $method_logo_data['url'],
					'method_logo_alt'    => $method_logo_data['alt'],
				]
			); // WPCS: XSS OK.
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * @param WC_Shipping_Rate $method .
	 *
	 * @return string
	 */
	private function get_method_description( $method ) {
		$description = $this->get_method_description_from_meta_data( $method );

		if ( '' !== $description ) {
			return $description;
		}

		if ( ! method_exists( $method, 'get_description' ) ) {
			return '';
		}

		return $method->get_description();
	}

	/**
	 * @param WC_Shipping_Rate $method .
	 *
	 * @return string
	 */
	private function get_method_description_from_meta_data( $method ) {
		$meta_data = $method->get_meta_data();

		if ( isset( $meta_data[ RateCalculator::DESCRIPTION_BASE64ENCODED ] ) && ! empty( $meta_data[ RateCalculator::DESCRIPTION_BASE64ENCODED ] ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$description = base64_decode( $meta_data[ RateCalculator::DESCRIPTION_BASE64ENCODED ] );

			if ( $description ) {
				return wp_kses_post( $description );
			}
		}

		if ( isset( $meta_data[ RateCalculator::DESCRIPTION ] ) ) {
			return wp_kses_post( $meta_data[ RateCalculator::DESCRIPTION ] );
		}

		return '';
	}

	/**
	 * @param WC_Shipping_Rate $method .
	 *
	 * @return array{url:string,alt:string}
	 */
	private function get_method_logo_data( $method ) {
		$meta_data = $method->get_meta_data();

		return [
			'url' => (string) ( $meta_data[ RateCalculator::METHOD_LOGO_URL ] ?? '' ),
			'alt' => (string) ( $meta_data[ RateCalculator::METHOD_LOGO_ALT ] ?? '' ),
		];
	}

	/**
	 * @param string $description .
	 *
	 * @return string
	 */
	private function sanitize_method_description_for_rate( $description ) {
		return trim( wp_strip_all_tags( html_entity_decode( $description, ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * @param object $rate .
	 *
	 * @return bool
	 */
	private function supports_native_rate_description( $rate ) {
		return method_exists( $rate, 'get_description' ) && method_exists( $rate, 'set_description' );
	}

	/**
	 * @return bool
	 */
	private function is_store_api_request() {
		if ( ! defined( 'REST_REQUEST' ) || REST_REQUEST !== true ) {
			return false;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		return false !== strpos( $request_uri, '/wc/store/' );
	}

	/**
	 * @param WC_Shipping_Rate $method .
	 *
	 * @return bool
	 */
	private function should_display_method_description( $method ) {
		return in_array(
			$method->get_method_id(),
			[
				WPDesk_Flexible_Shipping::METHOD_ID,
				ShippingMethodSingle::SHIPPING_METHOD_ID,
			],
			true
		);
	}
}
