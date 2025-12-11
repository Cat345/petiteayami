<?php

use WeglotWP\Services\Language_Service_Weglot;
if ( ! class_exists( 'WFOCU_Compatibility_With_Weglot' ) ) {
	class WFOCU_Compatibility_With_Weglot {
		public $glot_class_object;

		public function __construct() {
			/**
			 * Check if the class exist of weGlot and we have hooks setup for the woocommerce
			 * Then collect class object and hook in filter to further modify the URL to work with the language.
			 */
			add_action( 'wp_loaded', function () {

				/**
				 * Check if theme class exists
				 */
				if ( class_exists( 'WeglotWP\Third\Woocommerce\WC_Filter_Urls_Weglot' ) ) {

					global $wp_filter;
					foreach ( $wp_filter['woocommerce_payment_successful_result']->callbacks as $key => $val ) {

						if ( 10 !== $key ) {
							continue;
						}

						foreach ( $val as $innerval ) {
							if ( isset( $innerval['function'] ) && is_array( $innerval['function'] ) ) {
								if ( is_a( $innerval['function']['0'], 'WeglotWP\Third\Woocommerce\WC_Filter_Urls_Weglot' ) ) {
									$this->glot_class_object = $innerval['function']['0'];

									add_filter( 'wfocu_front_offer_url', array( $this, 'weglot_comptibility_function' ) );
									break;
								}
							}
						}
					}
				}


			}, 110 );
		}


		public function is_enable() {
			return false;
		}


		function weglot_comptibility_function( $urlRedirect ) {


			if ( function_exists( 'weglot_get_current_and_original_language' ) ) {
				if ( null === $this->glot_class_object ) {
					return $urlRedirect;
				}
				$current_and_original_language = weglot_get_current_and_original_language();
				$choose_current_language       = $current_and_original_language['current'];
				if ( $current_and_original_language['current'] !== $current_and_original_language['original'] ) { // Not ajax
					$url = $this->glot_class_object->request_url_services->create_url_object( $urlRedirect );
				} else {
					if ( isset( $_SERVER['HTTP_REFERER'] ) ) { //phpcs:ignore
						// Ajax
						$url                     = $this->glot_class_object->request_url_services->create_url_object( $_SERVER['HTTP_REFERER'] ); //phpcs:ignore
						$choose_current_language = $url->detectCurrentLanguage();
						$url                     = $this->glot_class_object->request_url_services->create_url_object( $urlRedirect );
					}
				}
				if ( $this->glot_class_object->replace_url_services->check_link( $urlRedirect ) ) { // We must not add language code if external link
					$urlRedirect = $url->getForLanguage( $choose_current_language );
				}

				return $urlRedirect;
			} else {

				/** @var  $language_service Language_Service_Weglot */
				$language_service        = weglot_get_service( 'Language_Service_Weglot' );
				$url_service             = weglot_get_service( 'Request_Url_Service_Weglot' );
				$replace_url_service     = weglot_get_service( 'Replace_Url_Service_Weglot' );
				$choose_current_language = $url_service->get_current_language();
				if ( $choose_current_language !== $language_service->get_original_language() ) { // Not ajax
					$url = $url_service->create_url_object( $urlRedirect );
				} else {
					if ( isset( $_SERVER['HTTP_REFERER'] ) ) { //phpcs:ignore
						// Ajax
						$url                     = $url_service->create_url_object( $_SERVER['HTTP_REFERER'] ); //phpcs:ignore
						$choose_current_language = $url->getCurrentLanguage();
						$url                     = $url_service->create_url_object( $urlRedirect );
					}
				}
				if ( $replace_url_service->check_link( $urlRedirect ) ) { // We must not add language code if external link
					if ( isset( $url ) && $url ) {
						$urlRedirect = $url->getForLanguage( $choose_current_language );
					}
				}

				return $urlRedirect;
			}

		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Weglot(), 'weglot' );


}