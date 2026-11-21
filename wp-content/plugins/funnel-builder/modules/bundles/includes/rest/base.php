<?php
/**
 * Base — shared plumbing for every admin REST controller.
 *
 * The namespace string, the `manage_options` check, body decoding, the money
 * formatter, and the `{ code, message, data }` envelope, which is always sent
 * as HTTP 200 because the SPA's apiClient reads `code` from the body.
 */
namespace WFFN\Bundles\Includes\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Admin\Navigation;
use WFFN\Bundles\Includes\Compat\Currency\Layer;

/**
 * Shared base class for the plugin's admin REST controllers.
 */
abstract class Base {

	/** @var string REST namespace, e.g. `wffn-bundles-admin/v1`. */
	protected $namespace;

	/**
	 * Resolve the REST namespace this controller registers under.
	 */
	public function __construct() {
		$this->namespace = WFFN_BUNDLES_ADMIN_NAMESPACE . '/' . WFFN_BUNDLES_API_VERSION;
	}

	/**
	 * Register this controller's routes. Called on `rest_api_init`.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Pin every response in this namespace to base currency.
	 *
	 * A REST request is not `is_admin()`, so a currency switcher would otherwise
	 * relabel these figures as the merchant's last storefront choice.
	 * `rest_pre_dispatch` is the first hook that knows the route.
	 *
	 * @return void
	 */
	public static function pin_currency_to_base() {
		add_filter(
			'rest_pre_dispatch',
			function ( $result, $server, $request ) {
				if ( 0 === strpos( ltrim( (string) $request->get_route(), '/' ), WFFN_BUNDLES_ADMIN_NAMESPACE . '/' ) ) {
					Layer::pin_to_base();
				}
				return $result;
			},
			10,
			3
		);
	}

	/**
	 * Admin routes: the same access rule as the admin screens.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_manage() {
		return Navigation::can_manage();
	}

	/**
	 * Admin write routes: Funnel Builder's write access, not only read.
	 *
	 * @return bool
	 */
	public function can_write() {
		return Navigation::can_write();
	}

	/**
	 * The `{id}` captured by the route itself.
	 *
	 * `$request['id']` answers from the JSON body and the query string before
	 * the URL, so `PUT /bundles/4?id=1` would act on bundle 1.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return string
	 */
	protected static function route_id( $request ) {
		$params = $request->get_url_params();
		return (string) ( $params['id'] ?? '' );
	}

	/**
	 * Decode the request body to an associative array.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return array
	 */
	protected function payload( $request ) {
		$json = $request->get_json_params();
		if ( is_array( $json ) ) {
			return $json;
		}
		$params = $request->get_body_params();
		return is_array( $params ) ? $params : array();
	}

	/**
	 * Money display string in the store's format; markup stripped for JSON.
	 *
	 * Through Layer, not wc_price(), so the base currency is named explicitly.
	 *
	 * @param float $amount The value to format.
	 * @return string
	 */
	protected static function money( $amount ) {
		return html_entity_decode(
			wp_strip_all_tags( Layer::format( (float) $amount ) ),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	/**
	 * The store's base currency code, or '' before WooCommerce is loaded.
	 *
	 * @return string
	 */
	private static function base_currency() {
		return function_exists( 'get_woocommerce_currency' ) ? (string) Layer::get_base_currency() : '';
	}

	/**
	 * Signature of every store setting money rendering depends on.
	 *
	 * Cache keys for payloads holding pre-formatted money must include this, so
	 * a currency change misses the cache instead of serving stale symbols.
	 *
	 * @return string
	 */
	protected static function price_format_signature() {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return 'no-wc';
		}
		return implode(
			'|',
			array(
				self::base_currency(),
				(string) get_option( 'woocommerce_currency_pos', 'left' ),
				wc_get_price_decimal_separator(),
				wc_get_price_thousand_separator(),
				(string) wc_get_price_decimals(),
			)
		);
	}

	/**
	 * Success envelope { code, message, data }, returned verbatim to the app.
	 *
	 * @param mixed  $data    Response payload.
	 * @param string $message Human-readable message.
	 * @param int    $code    Envelope status code.
	 * @return \WP_REST_Response
	 */
	protected static function ok( $data, $message = 'Success', $code = 200 ) {
		return new \WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Error envelope, still sent as HTTP 200.
	 *
	 * @param string                                             $message Human-readable message.
	 * @param int                                                $code    Envelope status code.
	 * @param string|null                                        $path    Form field the first issue belongs to, so the
	 *                                                                    editor can show it inline instead of only a
	 *                                                                    generic toast.
	 * @param array<int,array{path:string,message:string}>|null $issues  Every issue found, when there is more than
	 *                                                                    one, so the editor can flag all of them at
	 *                                                                    once instead of one save attempt per fix.
	 * @return \WP_REST_Response
	 */
	protected static function err( $message, $code = 400, $path = null, $issues = null ) {
		$data = null === $path ? null : array( 'path' => $path );
		if ( is_array( $issues ) && ! empty( $issues ) ) {
			$data           = is_array( $data ) ? $data : array();
			$data['issues'] = array_values( $issues );
		}
		return new \WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => $data,
			),
			200
		);
	}
}
