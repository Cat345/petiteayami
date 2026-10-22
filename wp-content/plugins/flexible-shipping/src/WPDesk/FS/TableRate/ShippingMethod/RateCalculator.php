<?php
/**
 * Class RateCalculator
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

namespace WPDesk\FS\TableRate\ShippingMethod;

use FSVendor\WPDesk\Forms\Field;
use FSVendor\WPDesk\FS\TableRate\Settings\CartCalculationOptions;
use FSVendor\WPDesk\FS\TableRate\Settings\MethodSettings;
use FSVendor\WPDesk\FS\TableRate\Settings\MethodSettingsImplementation;
use FSVendor\Psr\Log\LoggerInterface;
use FSVendor\Psr\Log\NullLogger;
use FSVendor\WPDesk\FS\TableRate\Logger\ShippingMethodLogger;
use WC_Cart;
use WC_Shipping_Method;
use WPDesk\FS\TableRate\Rule\Condition\Condition;
use WPDesk\FS\TableRate\Rule\Cost\AdditionalCost;
use WPDesk\FS\TableRate\Rule\CostsCalculator;
use WPDesk\FS\TableRate\Rule\ShippingContents\DestinationAddressFactory;
use WPDesk\FS\TableRate\Rule\ShippingContents\ShippingContents;
use WPDesk\FS\TableRate\Rule\ShippingContents\ShippingContentsImplementation;
use WPDesk\FS\TableRate\Rule\SpecialAction\SpecialAction;
use WPDesk_Flexible_Shipping;

/**
 * Can calculate single shipping rate.
 */
class RateCalculator {

	const FS_INTEGRATION            = '_fs_integration';
	const FS_METHOD                 = '_fs_method';
	const DESCRIPTION               = 'description';
	const DESCRIPTION_BASE64ENCODED = 'description_base64encoded';
	const METHOD_LOGO_URL           = 'method_logo_url';
	const METHOD_LOGO_ALT           = 'method_logo_alt';

	/**
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * @var WC_Shipping_Method
	 */
	private $shipping_method;

	/**
	 * @var string
	 */
	private $shop_currency;

	/**
	 * @var string
	 */
	private $cart_currency;

	/**
	 * @var Condition[]
	 */
	private $available_conditions;

	/**
	 * @var Field[]
	 */
	private $cost_fields;

	/**
	 * @var AdditionalCost[]
	 */
	private array $available_additional_costs;

	/**
	 * @var SpecialAction[]
	 */
	private $available_special_actions;

	/**
	 * @var int
	 */
	private $cost_rounding_precision;

	/**
	 * @var bool
	 */
	private $prices_includes_tax;

	/**
	 * @var WC_Cart
	 */
	private $cart;

	/**
	 * @var ShippingContents
	 */
	private $cart_contents;

	/**
	 * @var array
	 */
	private $package;

	/**
	 * @var FreeShippingCalculator
	 */
	private $free_shipping_calculator;

	/**
	 * RateCalculator constructor.
	 *
	 * @param WC_Shipping_Method     $shipping_method            .
	 * @param string                 $shop_currency              .
	 * @param string                 $cart_currency              .
	 * @param Condition[]            $available_conditions       .
	 * @param Field[]                $cost_fields                .
	 * @param AdditionalCost[]       $available_additional_costs .
	 * @param SpecialAction[]        $available_special_actions  .
	 * @param int                    $cost_rounding_precision    .
	 * @param bool                   $prices_includes_tax        .
	 * @param WC_Cart                $cart                       .
	 * @param ShippingContents       $cart_contents              .
	 * @param array                  $package                    .
	 * @param FreeShippingCalculator $free_shipping_calculator   .
	 */
	public function __construct(
		WC_Shipping_Method $shipping_method,
		$shop_currency,
		$cart_currency,
		array $available_conditions,
		array $cost_fields,
		array $available_additional_costs,
		array $available_special_actions,
		$cost_rounding_precision,
		$prices_includes_tax,
		WC_Cart $cart,
		ShippingContents $cart_contents,
		array $package,
		FreeShippingCalculator $free_shipping_calculator
	) {
		$this->shipping_method            = $shipping_method;
		$this->shop_currency              = $shop_currency;
		$this->cart_currency              = $cart_currency;
		$this->available_conditions       = $available_conditions;
		$this->cost_fields                = $cost_fields;
		$this->available_additional_costs = $available_additional_costs;
		$this->available_special_actions  = $available_special_actions;
		$this->cost_rounding_precision    = $cost_rounding_precision;
		$this->prices_includes_tax        = $prices_includes_tax;
		$this->cart                       = $cart;
		$this->cart_contents              = $cart_contents;
		$this->package                    = $package;
		$this->free_shipping_calculator   = $free_shipping_calculator;
	}

	/**
	 * @param LoggerInterface $logger .
	 */
	public function set_logger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return LoggerInterface
	 */
	public function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = new NullLogger();
		}

		return $this->logger;
	}

	/**
	 * @param MethodSettings $method_settings .
	 * @param string         $rate_id         .
	 * @param bool           $user_logged_in  .
	 *
	 * @return array
	 */
	public function calculate_rate( MethodSettings $method_settings, $rate_id, $user_logged_in ) {
		$logger = $this->get_logger();

		if ( $method_settings instanceof MethodSettingsImplementation ) {
			$logger->debug( $method_settings->format_for_log(), $this->get_logging_context( $logger, 'configuration' ) );
		}

		$add_method = true;
		if ( 'yes' === $method_settings->get_visibility() && ! $user_logged_in ) {
			$logger->debug( __( 'Method available only for logged in users, but user is not logged in.', 'flexible-shipping' ), $this->get_logging_context( $logger, 'results' ) );
			$add_method = false;
		}

		if ( $add_method ) {
			if ( $this->shop_currency !== $this->cart_currency ) {
				// Translators: shop currency.
				$logger->debug( sprintf( __( 'Shop currency: %1$s', 'flexible-shipping' ), $this->shop_currency ), $this->get_logging_context( $logger, 'input' ) );
				// Translators: cart currency.
				$logger->debug( sprintf( __( 'Cart currency: %1$s', 'flexible-shipping' ), $this->cart_currency ), $this->get_logging_context( $logger, 'input' ) );
			}

			// Older MethodSettings interfaces omit get_cart_calculation().
			$cart_calculation = $method_settings instanceof MethodSettingsImplementation
				? $method_settings->get_cart_calculation()
				: ( $method_settings->get_raw_settings()['cart_calculation'] ?? CartCalculationOptions::CART );
			if ( CartCalculationOptions::PACKAGE === $cart_calculation ) {
				$shipping_contents = new ShippingContentsImplementation(
					$this->package['contents'],
					$this->prices_includes_tax,
					$this->cost_rounding_precision,
					DestinationAddressFactory::create_from_package_destination( $this->package['destination'] ),
					get_woocommerce_currency()
				);
			} else {
				$shipping_contents = $this->cart_contents;
			}

			/**
			 * @return ShippingContents
			 */
			$shipping_contents = apply_filters( 'flexible_shipping_shipping_contents', $shipping_contents, $method_settings->get_raw_settings(), $this->cart, $this->package );

			if ( ! $shipping_contents instanceof ShippingContents || empty( $shipping_contents->get_contents() ) ) {
				$logger->debug( __( 'Empty contents', 'flexible-shipping' ) );

				return [];
			}

			do_action( 'flexible-shipping/rate-calculator/before', $shipping_contents, $method_settings, $logger );

			// Translators: contents value.
			$logger->debug( sprintf( __( 'Contents value: %1$s %2$s', 'flexible-shipping' ), $shipping_contents->get_contents_cost(), $shipping_contents->get_currency() ), $this->get_logging_context( $logger, 'input' ) );
			// Translators: contents weight.
			$logger->debug( sprintf( __( 'Contents weight: %1$s', 'flexible-shipping' ), wc_format_weight( $shipping_contents->get_contents_weight() ) ), $this->get_logging_context( $logger, 'input' ) );

			$rate = $this->calculate_rate_for_rates( $method_settings, $shipping_contents, $rate_id, $logger );

			do_action( 'flexible-shipping/rate-calculator/log', $logger );
		} else {
			$rate = [];
		}

		return $rate;
	}

	/**
	 * @param MethodSettings   $method_settings   .
	 * @param ShippingContents $shipping_contents .
	 * @param string           $rate_id           .
	 * @param LoggerInterface  $logger            .
	 *
	 * @return array
	 */
	private function calculate_rate_for_rates( MethodSettings $method_settings, ShippingContents $shipping_contents, $rate_id, LoggerInterface $logger ) {
		$cost_calculator = new CostsCalculator(
			$method_settings,
			$shipping_contents,
			$this->available_conditions,
			$this->cost_fields,
			$this->available_additional_costs,
			$this->available_special_actions,
			$this->cost_rounding_precision,
			$this->shop_currency,
			$logger
		);

		$cost_calculator->process_rules();
		$add_method = $cost_calculator->is_triggered();

		// Translators: add method.
		$logger->debug( sprintf( __( 'Used and displayed in the cart/checkout: %1$s', 'flexible-shipping' ), $add_method ? __( 'yes', 'flexible-shipping' ) : __( 'no', 'flexible-shipping' ) ), $this->get_logging_context( $logger, 'results' ) );
		$add_method_before_filters = $add_method;

		$add_method = apply_filters( 'flexible_shipping_add_method', $add_method, $method_settings->get_raw_settings(), $this->package, $this->shipping_method );
		if ( $add_method_before_filters !== $add_method ) {
			// Translators: add method.
			$logger->debug( sprintf( __( 'Used and displayed in the cart/checkout after filters: %1$s', 'flexible-shipping' ), $add_method ? __( 'yes', 'flexible-shipping' ) : __( 'no', 'flexible-shipping' ) ), $this->get_logging_context( $logger, 'results' ) );
		}

		if ( $add_method ) {
			$cost = $this->set_zero_cost_if_negative( $cost_calculator->get_calculated_cost() );

			// Translators: cost, currency.
			$logger->debug( sprintf( __( 'Calculated shipping cost: %1$s %2$s', 'flexible-shipping' ), $cost, $this->shop_currency ), $this->get_logging_context( $logger, 'results' ) );

			$is_free_shipping = $this->free_shipping_calculator->is_free_shipping( $method_settings, $this->cart_contents->get_contents_cost() );
			// Translators: free shipping.
			$logger->debug( sprintf( __( 'Free shipping: %1$s', 'flexible-shipping' ), $is_free_shipping ? __( 'yes', 'flexible-shipping' ) : __( 'no', 'flexible-shipping' ) ), $this->get_logging_context( $logger, 'results' ) );

			if ( $is_free_shipping ) {
				$cost = 0.0;
				// Translators: shipping cost after free shipping.
				$logger->debug( sprintf( __( 'Shipping cost after free shipping applied: %1$s', 'flexible-shipping' ), $cost ), $this->get_logging_context( $logger, 'results' ) );
			}

			// Translators: method id.
			$logger->debug( sprintf( __( 'Shipping method ID: %1$s', 'flexible-shipping' ), $rate_id ), $this->get_logging_context( $logger, 'results' ) );

			$method_title = $this->get_single_method_title( $method_settings->get_raw_settings(), $cost );
			// Translators: method title.
			$logger->debug( sprintf( __( 'Shipping method title: %1$s', 'flexible-shipping' ), $method_title ), $this->get_logging_context( $logger, 'results' ) );

			$rate = [
				'id'          => $rate_id,
				'label'       => $method_title,
				'cost'        => $cost,
				'package'     => $this->package,
				'meta_data'   => $this->prepare_meta_data( $method_settings ),
			];
		} else {
			$rate = [];
		}

		return $rate;
	}

	/**
	 * @param LoggerInterface $logger  Calculation logger.
	 * @param string          $section Log section.
	 *
	 * @return array
	 */
	private function get_logging_context( LoggerInterface $logger, string $section ): array {
		if ( ! $logger instanceof ShippingMethodLogger ) {
			return [];
		}
		if ( 'configuration' === $section ) {
			return $logger->get_configuration_section_context();
		}
		if ( 'input' === $section ) {
			return $logger->get_input_data_context();
		}

		return $logger->get_results_context();
	}

	/**
	 * @param float $cost .
	 *
	 * @return float
	 */
	private function set_zero_cost_if_negative( $cost ) {
		$allow_negative_costs = (bool) apply_filters( 'flexible-shipping/shipping-method/allow-negative-costs', false );

		if ( ! $allow_negative_costs && 0.0 > (float) $cost ) {
			$cost = 0.0;
		}

		return (float) $cost;
	}

	/**
	 * @param MethodSettings $method_settings .
	 *
	 * @return array
	 */
	private function prepare_meta_data( MethodSettings $method_settings ) {
		$description = $this->get_method_description( $method_settings );
		$logo_data   = MethodLogo::get_logo_data( $method_settings->get_raw_settings() );

		$meta_data = [
			WPDesk_Flexible_Shipping::META_DEFAULT => $method_settings->get_default(),
			self::FS_METHOD                        => $method_settings->get_raw_settings(),
			self::FS_INTEGRATION                   => $method_settings->get_integration(),
			self::DESCRIPTION                      => $description,
		];

		if ( ! apply_filters( 'flexible-shipping/order-meta-data/keep-method-rules', false, $meta_data ) ) {
			unset( $meta_data[ self::FS_METHOD ][ CommonMethodSettings::METHOD_RULES ] );
		}

		if ( esc_html( $description ) !== $description ) {
			$meta_data[ self::DESCRIPTION_BASE64ENCODED ] = base64_encode( $description );
		}

		if ( '' !== $logo_data['url'] ) {
			$meta_data[ self::METHOD_LOGO_URL ] = $logo_data['url'];
			$meta_data[ self::METHOD_LOGO_ALT ] = $logo_data['alt'];
		}

		/**
		 * Rate metadata.
		 *
		 * @param array $meta_data       .
		 * @param array $method_settings .
		 *
		 * @return array
		 */
		return apply_filters( 'flexible-shipping/rate/meta_data', $meta_data, $method_settings->get_raw_settings() );
	}

	/**
	 * @param MethodSettings $method_settings .
	 *
	 * @return string
	 */
	private function get_method_description( MethodSettings $method_settings ) {
		return wp_kses_post( wpdesk__( $method_settings->get_description(), 'flexible-shipping' ) );
	}

	/**
	 * @param array $shipping_method .
	 * @param float $cost            .
	 *
	 * @return mixed|string|void
	 */
	private function get_single_method_title( $shipping_method, $cost ) {
		$method_title = wpdesk__( $shipping_method['method_title'], 'flexible-shipping' );

		if ( 0.0 >= (float) $cost ) {
			if ( ! isset( $shipping_method['method_free_shipping_label'] ) ) {
				$shipping_method['method_free_shipping_label'] = __( 'Free', 'flexible-shipping' );
			}
			if ( '' !== $shipping_method['method_free_shipping_label'] ) {
				$method_title .= ' (' . wpdesk__( $shipping_method['method_free_shipping_label'], 'flexible-shipping' ) . ')';
			}
		}

		return $method_title;
	}
}
