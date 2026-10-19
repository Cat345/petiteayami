<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility for the Woodev SDEK / eDostavka (woocommerce-edostavka) delivery plugin on Aero.
 *
 * SDEK's edostavka_set_customer_location handler (the city dropdown) leaves
 * `$customer_location->save()` commented out, so a city change is never persisted. The stored
 * city_code stays on the PREVIOUS city, so the shipping method, the pickup button and the "Chosen
 * point" address all render one city behind, and a point chosen for the new city is looked up under
 * the wrong code and never appears.
 *
 * Fix — carry the exact city the customer picked all the way through, so the map, the render and the
 * pickup-point lookup all use one identical code:
 *   - Client: when SDEK's city request is sent, remember the selected city on the checkout form (hidden
 *     wfacp_sdek_city[] fields) and patch the pickup button's data-city_id so the map opens on that city.
 *     The fields are dropped again when the customer types a city of their own.
 *   - Server: at the start of each order-review refresh (before SDEK's own save at priority 10 and
 *     before shipping recalculates), apply that posted city to the customer handler with set_location(),
 *     so SDEK renders the method/button/address for exactly the chosen city — same request, no
 *     persistence lag and no ambiguous "look the city up by name" step.
 */
if ( ! class_exists( 'WFACP_Compatibility_With_SDEK_Edostavka_Pickup_Point' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_SDEK_Edostavka_Pickup_Point {

		public function __construct() {
			// Priority 1 so it runs before SDEK's own customer save (priority 10) and the shipping recalc.
			if ( $this->is_enabled() ) {
				add_action( 'woocommerce_checkout_update_order_review', array( $this, 'apply_selected_city' ), 1 );
			}
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'actions' ) );
		}

		public function is_enabled() {
			return function_exists( 'wc_edostavka_shipping' ) && class_exists( 'WC_Edostavka_Checkout' );
		}

		/**
		 * Apply the city the customer selected (sent on the checkout form) to the SDEK customer handler,
		 * so the order review renders for exactly that city instead of a stale or name-guessed one.
		 *
		 * @param string $post_data Serialized checkout form posted with the order-review refresh.
		 */
		public function apply_selected_city( $post_data ) {
			if ( ! function_exists( 'wc_edostavka_shipping' ) ) {
				return;
			}

			parse_str( (string) $post_data, $parsed );
			if ( empty( $parsed['wfacp_sdek_city'] ) || ! is_array( $parsed['wfacp_sdek_city'] ) ) {
				return;
			}

			$city      = $parsed['wfacp_sdek_city'];
			$city_code = isset( $city['code'] ) && is_scalar( $city['code'] ) ? absint( $city['code'] ) : 0;
			if ( ! $city_code ) {
				return;
			}

			// The city belongs to the country it was picked in; once the customer ships elsewhere it no longer applies.
			$country_code  = $this->get_posted_text( $city, 'country_code' );
			$country_field = empty( $parsed['ship_to_different_address'] ) ? 'billing_country' : 'shipping_country';
			$country       = isset( $parsed[ $country_field ] ) && is_scalar( $parsed[ $country_field ] ) ? (string) $parsed[ $country_field ] : '';
			if ( $country_code && '' !== $country && strtoupper( $country_code ) !== strtoupper( $country ) ) {
				return;
			}

			$handler = wc_edostavka_shipping()->get_customer_handler();
			if ( ! $handler ) {
				return;
			}

			$region_code = $this->get_posted_text( $city, 'region_code' );
			$handler->set_location(
				array(
					'country_code' => $country_code,
					'region_code'  => is_numeric( $region_code ) ? absint( $region_code ) : null,
					'region'       => $this->get_posted_text( $city, 'region' ),
					'city_code'    => $city_code,
					'city'         => $this->get_posted_text( $city, 'city' ),
				)
			);
		}

		/**
		 * @param array  $city Posted wfacp_sdek_city fields.
		 * @param string $key  Field key.
		 *
		 * @return string|null Cleaned value, or null when it was not posted or is empty.
		 */
		private function get_posted_text( $city, $key ) {
			if ( ! isset( $city[ $key ] ) || ! is_scalar( $city[ $key ] ) ) {
				return null;
			}
			$value = bwf_clean( (string) $city[ $key ] );

			return '' === $value ? null : $value;
		}

		public function actions() {
			if ( ! $this->is_enabled() ) {
				return;
			}
			add_action( 'wp_footer', array( $this, 'render_script' ), 20 );
			add_filter( 'wfacp_forms_field', array( $this, 'use_live_location_default' ), 10, 2 );
		}

		/**
		 * The saved Aero form keeps the city/state default SDEK returned at save time (the saving admin's
		 * own SDEK city, or the SDEK default-city setting of that moment), so it goes stale. Read it live
		 * from SDEK instead, the same way SDEK sets it on the native checkout. When SDEK has no value the
		 * saved default stays, so a merchant-set default still applies.
		 *
		 * @param array  $field Field args.
		 * @param string $key   Field key.
		 *
		 * @return array
		 */
		public function use_live_location_default( $field, $key ) {
			if ( ! is_array( $field ) || ! in_array( $key, array( 'billing_city', 'shipping_city', 'billing_state', 'shipping_state' ), true ) ) {
				return $field;
			}

			$handler = wc_edostavka_shipping()->get_customer_handler();
			if ( ! $handler ) {
				return $field;
			}

			if ( false !== strpos( $key, '_city' ) ) {
				$value = $handler->get_city();
			} else {
				$value = empty( $field['options'] ) ? $handler->get_region() : '';
			}

			if ( $value ) {
				$field['default'] = $value;
			}

			return $field;
		}

		public function render_script() {
			?>
			<script>
			(function ($) {
				var $form = $('form.checkout');
				var cityAction = 'edostavka_set_customer_location';
				var pointAction = 'edostavka_set_delivery_point';
				var reviewsSent = 0;

				function decode( value ) {
					try {
						return decodeURIComponent( value.replace(/\+/g, ' ') );
					} catch ( e ) {
						return value;
					}
				}

				function parseQuery( query, params ) {
					$.each( String( query ).split('&'), function ( i, pair ) {
						var at = pair.indexOf('=');
						if ( at > 0 ) {
							params[ decode( pair.slice(0, at) ) ] = decode( pair.slice(at + 1) );
						}
					});
				}

				// SDEK may post through wc-ajax or admin-ajax, as a string, a plain object or FormData.
				function readRequest( settings ) {
					var params = {};
					var url = ( settings && settings.url ) ? String(settings.url) : '';
					var data = settings ? settings.data : null;
					if ( url.indexOf('?') !== -1 ) {
						parseQuery( url.slice(url.indexOf('?') + 1), params );
					}
					if ( typeof data === 'string' ) {
						parseQuery( data, params );
					} else if ( $.isPlainObject( data ) ) {
						parseQuery( $.param( data ), params );
					} else if ( data && typeof data.forEach === 'function' && ! $.isArray( data ) ) {
						data.forEach(function (value, key) {
							if ( typeof value === 'string' ) {
								params[ key ] = value;
							}
						});
					}

					return {
						action: params['wc-ajax'] || params.action || '',
						location: function (key) {
							return params['location[' + key + ']'] || '';
						}
					};
				}

				function stashField( key, value ) {
					var name = 'wfacp_sdek_city[' + key + ']';
					var $field = $form.find('input[name="' + name + '"]');
					if ( ! $field.length ) {
						$field = $('<input type="hidden">').attr('name', name).appendTo($form);
					}
					$field.val( value == null ? '' : value );
				}

				function readStash() {
					var stash = {};
					$form.find('input[name^="wfacp_sdek_city["]').each(function () {
						stash[ this.name.slice(16, -1) ] = this.value;
					});
					return stash;
				}

				function writeStash( stash ) {
					$form.find('input[name^="wfacp_sdek_city["]').remove();
					$.each( stash, stashField );
				}

				function setButton( button, cityId, selectedId ) {
					$(button).data('city_id', cityId).attr('data-city_id', cityId)
							.data('selected_id', selectedId).attr('data-selected_id', selectedId);
				}

				function refresh() {
					// aero_block() no-ops unless refresh_page_data_load_trigger is on, so flip it first to
					// get the shimmer over the shipping calculator/total, then re-run Aero's refresh so the
					// method/price and the pickup-point address re-render for the new city/point.
					window.refresh_page_data_load_trigger = true;
					$(document.body).trigger('update_checkout');
				}

				// Remember the city as soon as SDEK's request leaves, so any refresh WooCommerce or SDEK
				// fires meanwhile already posts it and a second refresh is not needed.
				$(document).on('ajaxSend', function (event, xhr, settings) {
					var request = readRequest( settings );
					if ( request.action === 'update_order_review' ) {
						reviewsSent++;
						return;
					}

					var code = request.location('code');
					if ( request.action !== cityAction || ! /^\d+$/.test( code ) ) {
						return;
					}

					var city = request.location('city') || request.location('id');
					settings.wfacpSdek = {
						reviewsSent: reviewsSent,
						previous: readStash(),
						buttons: $('.wc-edostavka-choose-delivery-point').map(function () {
							return { el: this, cityId: $(this).attr('data-city_id'), selectedId: $(this).attr('data-selected_id') };
						}).get()
					};
					writeStash({
						code: code,
						country_code: request.location('country_code'),
						region_code: request.location('region_code'),
						region: request.location('region'),
						city: /^\d+$/.test( city ) ? '' : city
					});
					$('.wc-edostavka-choose-delivery-point').each(function () {
						setButton( this, code, '' );
					});
				});

				$(document).on('ajaxComplete', function (event, xhr, settings) {
					var action = readRequest( settings ).action;
					var failed = ! xhr || xhr.status < 200 || xhr.status >= 300 || ( xhr.responseJSON && false === xhr.responseJSON.success );
					if ( action === cityAction && settings.wfacpSdek ) {
						if ( failed ) {
							writeStash( settings.wfacpSdek.previous );
							$.each( settings.wfacpSdek.buttons, function (i, button) {
								setButton( button.el, button.cityId, button.selectedId );
							});
						} else if ( reviewsSent > settings.wfacpSdek.reviewsSent ) {
							return;
						}
						refresh();
					} else if ( action === pointAction && ! failed ) {
						refresh();
					}
				});

				// A city typed by hand replaces the one picked from SDEK's list.
				$form.on('input', 'input[name="billing_city"], input[name="shipping_city"]', function () {
					var picked = readStash().city;
					var inUse = $('input[name="ship_to_different_address"]').is(':checked') ? 'shipping_city' : 'billing_city';
					if ( picked !== undefined && this.name === inUse && $(this).val() !== picked ) {
						$form.find('input[name^="wfacp_sdek_city["]').remove();
					}
				});
			})(jQuery);
			</script>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_SDEK_Edostavka_Pickup_Point(), 'sdek-edostavka-pickup-point' );
}
