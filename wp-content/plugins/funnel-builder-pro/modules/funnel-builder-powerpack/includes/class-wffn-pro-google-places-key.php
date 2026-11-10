<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WFFN_Pro_Google_Places_Key' ) ) {
	/**
	 * Tells from the server whether the Google Maps key supports Places API (New).
	 *
	 * A Google key has no type of its own: what it can call depends on its Google Cloud project. One autocomplete
	 * request to Places API (New) answers it. Only a key Google accepts gets the new API on the checkout. A key
	 * whose project has Places API (New) disabled or blocked is an old key: the checkout keeps the legacy API and
	 * the admin is asked to create a new key. A key the check cannot classify (restrictions, billing, network)
	 * also keeps the legacy API.
	 */
	class WFFN_Pro_Google_Places_Key {

		const OPTION = 'wffn_google_places_key_check';
		const CRON_HOOK = 'wffn_google_places_key_check';
		const ENDPOINT = 'https://places.googleapis.com/v1/places:autocomplete';

		/**
		 * The key the checkout loads the Maps JS with: Settings > Google Maps, else the older checkout setting.
		 *
		 * @return string
		 */
		public static function get_key() {
			$key = '';
			if ( class_exists( 'BWF_Admin_General_Settings' ) ) {
				$key = BWF_Admin_General_Settings::get_instance()->get_option( 'funnelkit_google_map_key' );
			}
			if ( empty( $key ) && class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'global_settings' ) ) {
				$settings = WFACP_Common::global_settings( true );
				$key      = isset( $settings['wfacp_google_address_key'] ) ? $settings['wfacp_google_address_key'] : '';
			}

			return is_string( $key ) ? trim( $key ) : '';
		}

		/**
		 * @param string $key Google Maps API key.
		 *
		 * @return string
		 */
		private static function fingerprint( $key ) {
			return hash( 'sha256', (string) $key );
		}

		/**
		 * The stored check, only when it was made for this key.
		 *
		 * @param string|null $key Google Maps API key, the saved one when null.
		 *
		 * @return array|null type (new|legacy|unknown), reason, message, checked.
		 */
		public static function get_result( $key = null ) {
			$key = null === $key ? self::get_key() : trim( (string) $key );
			if ( '' === $key ) {
				return null;
			}
			$result = get_option( self::OPTION, array() );
			if ( ! is_array( $result ) || empty( $result['hash'] ) || ! hash_equals( $result['hash'], self::fingerprint( $key ) ) ) {
				return null;
			}

			return $result;
		}

		/**
		 * Check the key and store the answer for it.
		 *
		 * @param string $key Google Maps API key.
		 *
		 * @return array|null The stored result, null for an empty key.
		 */
		public static function check( $key ) {
			$key = trim( (string) $key );
			if ( '' === $key ) {
				delete_option( self::OPTION );

				return null;
			}

			$result = self::probe( $key );
			update_option( self::OPTION, $result, true );

			return $result;
		}

		/**
		 * Ask Places API (New) with the key.
		 *
		 * The store's own URL goes as the Referer, as on the checkout, so a key restricted to this website is
		 * judged as the checkout would be.
		 *
		 * @param string $key Google Maps API key, not empty.
		 *
		 * @return array hash, type (new|legacy|unknown), reason, message, checked.
		 */
		private static function probe( $key ) {
			$response = wp_remote_post( self::ENDPOINT, array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-Goog-Api-Key' => $key,
					'Referer'        => home_url( '/' ),
				),
				'body'    => wp_json_encode( array( 'input' => '1600 Amphitheatre Parkway' ) ),
			) );

			$result = array(
				'hash'    => self::fingerprint( $key ),
				'type'    => 'unknown',
				'reason'  => 'request',
				'message' => '',
				'checked' => time(),
			);

			if ( is_wp_error( $response ) ) {
				$result['message'] = $response->get_error_message();
			} elseif ( 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$result['type']   = 'new';
				$result['reason'] = '';
			} else {
				$body  = json_decode( wp_remote_retrieve_body( $response ), true );
				$error = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();

				$result['reason']  = 'other';
				$result['message'] = isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : '';
				if ( isset( $error['details'] ) && is_array( $error['details'] ) ) {
					foreach ( $error['details'] as $detail ) {
						if ( is_array( $detail ) && ! empty( $detail['reason'] ) ) {
							$result['reason'] = sanitize_key( $detail['reason'] );
							break;
						}
					}
				}

				// The project has Places API (New) disabled, or the key's API restrictions leave it out: a key made for the legacy API.
				if ( in_array( $result['reason'], array( 'service_disabled', 'api_key_service_blocked' ), true ) ) {
					$result['type'] = 'legacy';
				}
			}

			return $result;
		}

		/**
		 * Answers wfacp_google_places_use_new_api for the checkout.
		 *
		 * @param bool   $use_new Current answer.
		 * @param string $key     The key the checkout loads.
		 *
		 * @return bool
		 */
		public static function use_new_api( $use_new, $key = '' ) {
			$result = self::get_result( '' !== (string) $key ? $key : null );

			return ( null !== $result && 'new' === $result['type'] ) ? true : (bool) $use_new;
		}

		/**
		 * A changed key on save is checked straight away, so Settings shows the answer for the key just entered.
		 *
		 * @param mixed $old_value Previous bwf_gen_config.
		 * @param mixed $value     New bwf_gen_config.
		 *
		 * @return void
		 */
		public static function on_settings_saved( $old_value, $value ) {
			$old_key = is_array( $old_value ) && isset( $old_value['funnelkit_google_map_key'] ) ? trim( (string) $old_value['funnelkit_google_map_key'] ) : '';
			$new_key = is_array( $value ) && isset( $value['funnelkit_google_map_key'] ) ? trim( (string) $value['funnelkit_google_map_key'] ) : '';
			if ( $old_key === $new_key ) {
				return;
			}

			self::check( '' !== $new_key ? $new_key : self::get_key() );
		}

		/**
		 * The first save of the settings adds the option rather than updating it.
		 *
		 * @param string $option Option name.
		 * @param mixed  $value  New bwf_gen_config.
		 *
		 * @return void
		 */
		public static function on_settings_added( $option, $value ) {
			self::on_settings_saved( array(), $value );
		}

		/**
		 * After an update, or when the key changed some other way, check it in the background. An answer other
		 * than "new" is asked again after a day, in case the project or its restrictions were fixed.
		 *
		 * @return void
		 */
		public static function maybe_schedule() {
			if ( wp_next_scheduled( self::CRON_HOOK ) ) {
				return;
			}
			$key = self::get_key();
			if ( '' === $key ) {
				update_option( self::OPTION, array(
					'hash'    => '',
					'type'    => 'none',
					'reason'  => '',
					'message' => '',
					'checked' => time(),
				), true );

				return;
			}
			$result = self::get_result( $key );
			if ( null !== $result && ( 'new' === $result['type'] || ( time() - (int) $result['checked'] ) < DAY_IN_SECONDS ) ) {
				return;
			}

			wp_schedule_single_event( time(), self::CRON_HOOK );
		}

		/**
		 * @return void
		 */
		public static function run_scheduled() {
			self::check( self::get_key() );
		}

		/**
		 * What the admin is told about a result.
		 *
		 * @param array|null $result A check result, null when the key was not checked yet.
		 * @param bool       $saved  Whether the result is for the saved key rather than one only typed.
		 *
		 * @return array status (success|warning|error|info) and message (HTML).
		 */
		public static function describe( $result, $saved = true ) {
			$legacy_note = __( 'Until Google confirms a key for Places API (New), address autocomplete on the checkout keeps using the legacy Places API.', 'funnel-builder-pro' );

			if ( null === $result ) {
				return array(
					'status'  => 'info',
					'message' => esc_html__( 'This key has not been checked with Google yet. It is checked in the background after an update and whenever a new key is saved.', 'funnel-builder-pro' ) . ' ' . esc_html( $legacy_note ),
				);
			}

			if ( 'new' === $result['type'] ) {
				return array(
					'status'  => 'success',
					'message' => $saved ? esc_html__( 'This key supports Places API (New). Address autocomplete on the checkout uses it.', 'funnel-builder-pro' ) : esc_html__( 'This key supports Places API (New). Once it is saved, address autocomplete on the checkout uses it.', 'funnel-builder-pro' ),
				);
			}

			if ( 'legacy' === $result['type'] ) {
				return array(
					'status'  => 'warning',
					'message' => esc_html__( 'Your Google Address Autocomplete API is deprecated. Please create a new API key with Places API (New) enabled and enter it above.', 'funnel-builder-pro' ) . ' ' . esc_html( $legacy_note ) . ' <a href="' . esc_url( self::docs_url() ) . '" target="_blank">' . esc_html__( 'How to create a key', 'funnel-builder-pro' ) . '</a>',
				);
			}

			switch ( $result['reason'] ) {
				case 'api_key_invalid':
					$message = __( 'Google says this key is not valid. Check that it was copied in full.', 'funnel-builder-pro' );
					break;
				case 'api_key_http_referrer_blocked':
					$message = __( 'Google refused this key for this website. Add this site to the website restrictions of the key.', 'funnel-builder-pro' );
					break;
				case 'api_key_ip_address_blocked':
					$message = __( 'Google refused this key because of its IP address restrictions, so it could not be checked from this server.', 'funnel-builder-pro' );
					break;
				case 'billing_disabled':
					$message = __( 'Billing is not enabled on the Google Cloud project of this key.', 'funnel-builder-pro' );
					break;
				case 'request':
					$message = __( 'Google could not be reached to check this key. It is checked again later.', 'funnel-builder-pro' );
					break;
				default:
					$message = sprintf( /* translators: %s: error message from Google */
						__( 'Google could not confirm this key for Places API (New): %s', 'funnel-builder-pro' ), $result['message'] );
			}

			return array(
				'status'  => 'error',
				'message' => esc_html( $message ) . ' ' . esc_html( $legacy_note ),
			);
		}

		/**
		 * @return string
		 */
		private static function docs_url() {
			return 'https://developers.google.com/maps/documentation/places/web-service/get-api-key';
		}

		/**
		 * Same gate as saving the global settings.
		 *
		 * @return bool
		 */
		public static function rest_permission() {
			if ( function_exists( 'wffn_rest_api_helpers' ) ) {
				return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
			}

			return current_user_can( 'manage_options' );
		}

		/**
		 * Check the key typed on Settings > Google Maps. Only the saved key's answer is stored: a key that is
		 * typed but not saved must not change what the checkout uses.
		 *
		 * @param WP_REST_Request $request Request.
		 *
		 * @return WP_REST_Response
		 */
		public static function rest_check( $request ) {
			$key = trim( (string) $request->get_param( 'key' ) );
			if ( '' === $key ) {
				return rest_ensure_response( array( 'success' => false ) );
			}

			$saved  = hash_equals( self::fingerprint( self::get_key() ), self::fingerprint( $key ) );
			$result = $saved ? self::check( $key ) : self::probe( $key );

			return rest_ensure_response( array_merge( array( 'success' => true ), self::describe( $result, $saved ) ) );
		}

		/**
		 * The in-app notification announcing Places API (New), shown while the saved key is still a legacy one.
		 *
		 * Lives in FunnelKit's own notification list rather than a store-wide admin notice, so it only
		 * appears on our screens. Dismissible: the store owner may not be the person who manages the
		 * Google Cloud project, so the announcement is theirs to put away. The warning under the key
		 * field stays either way, and the notification stops being registered on its own once a key is
		 * confirmed for Places API (New).
		 *
		 * @param array $notifs Registered notifications.
		 *
		 * @return array
		 */
		public static function add_notification( $notifs ) {
			if ( ! is_array( $notifs ) ) {
				return $notifs;
			}
			$result = self::get_result();
			if ( null === $result || 'legacy' !== $result['type'] ) {
				return $notifs;
			}

			$notifs[] = array(
				/**
				 * What a dismissal is recorded against, so the notification stays gone for whoever
				 * closed it. Changing the string shows it again to everyone.
				 */
				'key'             => 'google_places_legacy_key',
				'content'         => '<div class="bwf-notifications-message current">
					<h3 class="bwf-notifications-title">' . esc_html__( 'FunnelKit now supports Google Places API (New)', 'funnel-builder-pro' ) . '</h3>
					<p class="bwf-notifications-content">' . esc_html__( 'Address autocomplete on your checkout now works with Google Places API (New). Your current API key only supports the legacy Places API, which Google has deprecated. Add a key with Places API (New) enabled to switch over.', 'funnel-builder-pro' ) . '</p>
				</div>',
				'customButtons'   => array(
					array(
						'label'     => __( 'Update API Key', 'funnel-builder-pro' ),
						/** The Settings screen itself: deeper paths to the Google Maps tab do not open it. */
						'href'      => admin_url( 'admin.php?page=bwf&path=/settings' ),
						'className' => 'is-primary',
					),
					array(
						'label'  => __( 'Read Documentation', 'funnel-builder-pro' ),
						'href'   => 'https://funnelkit.com/docs/checkout-pages/how-to/switch-to-google-places-api-new/',
						'target' => '__blank',
					),
				),
				'not_dismissible' => false,
				'index'           => 3,
			);

			return $notifs;
		}
	}
}
