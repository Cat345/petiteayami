<?php

/**
 * WordPress Multilingual Plugin
 * https://wpml.org/
 */
if ( ! class_exists( 'BWFAN_Compatibility_With_WPML' ) ) {
	class BWFAN_Compatibility_With_WPML {

		private $original_wfacp_post_id = null;

		public function __construct() {
			add_action( 'bwfan_email_setup_locale', [ $this, 'translate_email_body' ] );
			add_filter( 'wffn_wfty_filter_page_ids', [ $this, 'translate_checkout_post_id_for_funnel_lookup' ], 5, 2 );
			add_filter( 'wffn_wfty_filter_page_ids', [ $this, 'restore_checkout_post_id_after_funnel_lookup' ], 11, 2 );
		}

		/**
		 * Translate WPML checkout page ID to default language before funnel thank-you page lookup.
		 *
		 * When an order is placed on a translated checkout page, _wfacp_post_id stores the translated
		 * post ID. The funnel builder's maybe_filter_thankyou() then fails to find _bwf_in_funnel meta
		 * because it only exists on the original (default language) post. This translates the ID before
		 * the lookup runs at priority 10.
		 *
		 * @param array $thankyou_page_ids
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		public function translate_checkout_post_id_for_funnel_lookup( $thankyou_page_ids, $order ) {
			if ( ! $order instanceof WC_Order ) {
				return $thankyou_page_ids;
			}

			$aero_id = $order->get_meta( '_wfacp_post_id' );
			if ( empty( $aero_id ) || 0 === absint( $aero_id ) ) {
				return $thankyou_page_ids;
			}

			$default_lang = apply_filters( 'wpml_default_language', null );
			if ( empty( $default_lang ) ) {
				return $thankyou_page_ids;
			}

			$post_type = get_post_type( absint( $aero_id ) );
			if ( empty( $post_type ) ) {
				return $thankyou_page_ids;
			}

			$original_id = apply_filters( 'wpml_object_id', absint( $aero_id ), $post_type, true, $default_lang );

			if ( ! empty( $original_id ) && absint( $original_id ) !== absint( $aero_id ) ) {
				$this->original_wfacp_post_id = $aero_id;
				$order->update_meta_data( '_wfacp_post_id', $original_id );
			}

			return $thankyou_page_ids;
		}

		/**
		 * Restore original checkout page ID after funnel lookup completes.
		 *
		 * @param array $thankyou_page_ids
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		public function restore_checkout_post_id_after_funnel_lookup( $thankyou_page_ids, $order ) {
			if ( null !== $this->original_wfacp_post_id && $order instanceof WC_Order ) {
				$order->update_meta_data( '_wfacp_post_id', $this->original_wfacp_post_id );
				$this->original_wfacp_post_id = null;
			}

			return $thankyou_page_ids;
		}

		/**
		 * setup locale for email with translation plugins
		 *
		 * @param $lang
		 */
		public function translate_email_body( $lang ) {
			if ( empty( $lang ) ) {
				return;
			}

			global $woocommerce_wpml;
			if ( ! class_exists( 'woocommerce_wpml' ) || ! $woocommerce_wpml instanceof woocommerce_wpml ) {
				return;
			}
			$woocommerce_wpml->emails->change_email_language( $lang );
		}

		/**
		 * Get translated term IDs dynamically based on the context
		 *
		 * @param array $term_ids The original term IDs
		 * @param string $taxonomy_name The taxonomy name
		 * @param array $automation_data Automation context data
		 *
		 * @return array An array of translated term IDs
		 */
		public static function get_translated_term_ids( $term_ids, $taxonomy_name, $automation_data = [] ) {
			if ( empty( $term_ids ) ) {
				return $term_ids;
			}

			$language_code = self::detect_language_from_sources( $automation_data );
			if ( empty( $language_code ) ) {
				return $term_ids;
			}

			$all_translated_ids = [];
			foreach ( $term_ids as $term_id ) {
				$all_translated_ids[] = $term_id;

				$translated_id = apply_filters( 'wpml_object_id', $term_id, $taxonomy_name, false, $language_code );

				if ( $translated_id ) {
					$all_translated_ids[] = $translated_id;
				}
			}

			$ids = array_unique( $all_translated_ids );
			sort( $ids );

			return $ids;
		}

		/**
		 * Detect language from various possible sources
		 *
		 * @param $automation_data
		 *
		 * @return array|mixed|string
		 */
		private static function detect_language_from_sources( $automation_data ) {
			if ( isset( $automation_data['global']['language'] ) ) {
				return $automation_data['global']['language'];
			}

			global $sitepress;
			$default_language = $sitepress->get_default_language();
			$sitepress->get_current_language();
			if ( ! isset( $automation_data['global']['order_id'] ) ) {
				return $default_language;
			}
			$order = $automation_data['global']['order_id'];
			$order = wc_get_order( $order );
			if ( ! $order instanceof WC_Order ) {
				return $default_language;
			}
			$order_language = $order->get_meta( 'wpml_language' );

			return ! empty( $order_language ) ? $order_language : $default_language;
		}
	}

	new BWFAN_Compatibility_With_WPML();
}
