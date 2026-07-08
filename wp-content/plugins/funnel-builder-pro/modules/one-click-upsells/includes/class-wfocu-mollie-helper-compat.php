<?php


/**
 * This class contains compatibility methods that will necessary for the version compatibility with mollie
 */

use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Mollie_Helper_Compat' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Mollie_Helper_Compat {

		public static function get_plugin_id( $container ) {

			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::PLUGIN_ID;
			} else {
				return $container->get( 'shared.plugin_id' );
			}
		}

		public static function get_settings_helper( $container ) {

			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::getSettingsHelper();
			} else {
				return $container->get( 'settings.settings_helper' );
			}
		}

		public static function get_payment_factory( $container ) {

			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::getPaymentFactoryHelper();
			} else {
				return $container->get( PaymentFactory::class );
			}
		}

		public static function get_data_helper( $container ) {

			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::getDataHelper();
			} else {
				return $container->get( 'settings.data_helper' );
			}
		}

		public static function get_api_helper( $container ) {

			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::getApiHelper();
			} else {
				return $container->get( 'SDK.api_helper' );
			}
		}

		public static function get_api_client( $container, $testmode ) {
			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::getApiHelper()->getApiClient( $testmode );
			} else {
				return self::get_api_helper( $container )->getApiClient( self::get_settings_helper( $container )->getApiKey() );
			}
		}

		public static function get_payment_object( $container ) {

			if ( is_null( $container ) ) {
				return Mollie_WC_Plugin::getPaymentObject();
			} else {
				return $container->get( MollieObject::class );
			}
		}


		public static function setHttpReponseCode( $container, $code ) {

			if ( is_null( $container ) ) {
				Mollie_WC_Plugin::setHttpResponseCode( $code );
			} else {
				$container->get( 'SDK.HttpResponse' )->setHttpResponseCode( $code );
			}
		}

		public static function onWebhookAction( $container, $gateway ) {

			if ( is_null( $container ) ) {
				$gateway->onWebhookAction();
			} else {
				$orderService = $container->get( MollieOrderService::class );
				$orderService->setGateway( $gateway );
				$orderService->onWebhookAction();
			}
		}

		/**
		 * Returns the WebhookHandler instance from the container (Mollie >= 8.1.4).
		 * Returns null for older Mollie versions that don't have this class.
		 *
		 * @param mixed $container
		 * @return \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler|null
		 */
		public static function get_webhook_handler( $container ) {
			if ( is_null( $container ) ) {
				return null;
			}

			if ( ! class_exists( 'Mollie\\WooCommerce\\Payment\\Webhooks\\WebhookHandler' ) ) {
				return null;
			}

			try {
				return $container->get( \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::class );
			} catch ( \Throwable $e ) {
				return null;
			}
		}
	}
}
