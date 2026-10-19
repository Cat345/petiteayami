<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_Departamentos_Ciudades_Colombia' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Departamentos_Ciudades_Colombia {
		public function __construct() {
			add_action( 'wfacp_internal_css', array( $this, 'execute_style_script' ) );
		}

		public function execute_style_script() {
			?>
			<style>
				/* Core's shimmer rules cover text and price nodes only, so carrier logos stay
				   visible on top of the placeholder. Hide them for the duration. */
				.wfacp_shipping_options.wfacp_anim_active img {
					visibility: hidden;
				}
			</style>
			<script>
				window.addEventListener( 'bwf_checkout_load', function () {
					( function ( $ ) {

						// wc_city_select_params is localized by "Departamentos y Ciudades de
						// Colombia para WooCommerce" itself; bail like its own script does if
						// it isn't there.
						if ( typeof wc_city_select_params === 'undefined' ) {
							return;
						}

						var cities;
						try {
							cities = $.parseJSON( wc_city_select_params.cities.replace( /&quot;/g, '"' ) );
						} catch ( e ) {
							return;
						}

						/**
						 * The Colombia plugin's own js/place-select.js rebuilds #billing_city /
						 * #shipping_city into a department-filtered <select> whenever the state
						 * changes, but it locates that city field via:
						 *     $(this).closest('div.woocommerce-billing-fields, div.woocommerce-shipping-fields,
						 *                      form.woocommerce-shipping-calculator, div.woocommerce-address-fields,
						 *                      form.mvx_shop_settings_form')
						 * Aero Checkout's markup never wraps address fields in those
						 * WooCommerce-default classes, so that lookup always comes back empty
						 * and the handler silently does nothing: the city field is stuck
						 * disabled the moment a department is picked. The functions below mirror
						 * the Colombia plugin's own cityToInput / disableCity / cityToSelect
						 * logic, but resolve the country/state/city trio by field id instead, so
						 * behavior matches native WooCommerce checkout.
						 */
						// Last country|state each prefix was built for, so a refresh that
						// changed neither leaves the customer's choice alone.
						var last_sync = {};

						function cityToInput( $citybox ) {
							if ( $citybox.is( 'input' ) ) {
								$citybox.prop( 'disabled', false );
								return;
							}

							var input_name = $citybox.attr( 'name' );
							var input_id = $citybox.attr( 'id' );
							var placeholder = $citybox.attr( 'placeholder' );

							$citybox.parent().find( '.select2-container' ).remove();
							$citybox.replaceWith( '<input type="text" class="input-text wfacp-form-control" name="' + input_name + '" id="' + input_id + '" placeholder="' + placeholder + '" />' );
						}

						/**
						 * Programmatic value changes use the select2 namespace so selectWoo
						 * redraws without WooCommerce seeing an address change. A plain
						 * 'change' here is read by WooCommerce as the customer editing the
						 * field, which schedules update_checkout, whose updated_checkout then
						 * re-enters this code - the field rebuilds on a loop and a selection
						 * can never be made.
						 */
						function disableCity( $citybox ) {
							$citybox.val( '' ).trigger( 'change.select2' );
							$citybox.prop( 'disabled', true );
						}

						function cityToSelect( $citybox, current_cities ) {
							var value = $citybox.val();

							if ( $citybox.is( 'input' ) ) {
								var input_name = $citybox.attr( 'name' );
								var input_id = $citybox.attr( 'id' );
								var placeholder = $citybox.attr( 'placeholder' );

								$citybox.replaceWith( '<select name="' + input_name + '" id="' + input_id + '" class="city_select wfacp-form-control" placeholder="' + placeholder + '"></select>' );
								$citybox = $( '#' + input_id );
							} else {
								$citybox.prop( 'disabled', false );
							}

							// Built through the DOM rather than concatenated markup: the city list
							// is filterable server side, and .text() escapes whatever it is given.
							$citybox.empty().append(
								$( '<option>' ).val( '' ).text( wc_city_select_params.i18n_select_city_text )
							);

							for ( var index in current_cities ) {
								if ( current_cities.hasOwnProperty( index ) ) {
									$citybox.append( $( '<option>' ).val( current_cities[ index ] ).text( current_cities[ index ] ) );
								}
							}

							if ( $( 'option[value="' + value + '"]', $citybox ).length ) {
								$citybox.val( value ).trigger( 'change.select2' );
							} else {
								$citybox.val( '' ).trigger( 'change.select2' );
							}

							applySelectWoo( $citybox );

							// Kept for parity with the Colombia plugin's own event contract, in case
							// another script on the page listens for it.
							$( document.body ).trigger( 'city_to_select' );
						}

						// The Colombia plugin's own selectWoo enhancement for the city select can
						// end up never bound if its script executes before selectWoo/select2 is
						// available, leaving the field visually unstyled. Apply it ourselves
						// whenever we (re)build the select so it matches the rest of the checkout.
						function applySelectWoo( $select ) {
							if ( typeof $.fn.selectWoo !== 'function' || ! $select.is( 'select' ) ) {
								return;
							}
							if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
								$select.selectWoo( 'destroy' );
							}
							$select.selectWoo( { width: '100%' } );
						}

						function resolvePrefix( fieldId ) {
							if ( 0 === fieldId.indexOf( 'shipping_' ) ) {
								return 'shipping';
							}
							if ( 0 === fieldId.indexOf( 'calc_shipping_' ) ) {
								return 'calc_shipping';
							}

							return 'billing';
						}

						function syncCityField( prefix ) {
							var $country = $( '#' + prefix + '_country' );
							var $city = $( '#' + prefix + '_city' );

							if ( ! $country.length || ! $city.length ) {
								return;
							}

							var country = $country.val();
							var $state = $( '#' + prefix + '_state' );
							var state = $state.length ? $state.val() : '';

							/*
							 * Rebuilding the select replaces the element and drops whatever the
							 * customer had chosen. updated_checkout fires on every recalculation,
							 * so without this guard each refresh wipes the selection. The field
							 * only needs rebuilding when the country or department moved.
							 */
							var signature = country + '|' + state;
							if ( last_sync[ prefix ] === signature && $city.is( ':visible' ) ) {
								return;
							}
							last_sync[ prefix ] = signature;

							if ( cities[ country ] ) {
								if ( cities[ country ] instanceof Array ) {
									cityToSelect( $city, cities[ country ] );
								} else if ( state ) {
									if ( cities[ country ][ state ] ) {
										cityToSelect( $city, cities[ country ][ state ] );
									} else {
										cityToInput( $city );
									}
								} else {
									disableCity( $city );
								}
							} else {
								cityToInput( $city );
							}
						}

						$( document.body ).on(
							'change',
							'#billing_country, #billing_state, #shipping_country, #shipping_state, #calc_shipping_country, #calc_shipping_state',
							function () {
								syncCityField( resolvePrefix( $( this ).attr( 'id' ) ) );
							}
						);

						// Re-sync after WooCommerce's own AJAX order-review refresh, and once now
						// for prefilled values (returning customer / saved address).
						$( document.body ).on( 'updated_checkout', function () {
							setTimeout( function () {
								syncCityField( 'billing' );
								syncCityField( 'shipping' );
							}, 500 );
						} );

						// A city change re-runs update_checkout and rebuilds the shipping methods.
						// Aero's shimmer class is only ever added to <body> by cart actions, so scope
						// it to the shipping block here - .wfacp_shipping_options is the same selector
						// core uses as the AJAX fragment key (class-wfacp-template.php:1106).
						// change.select2 from the rebuilds above does not reach this handler, so only
						// a real selection starts it.
						$( document.body ).on( 'change', '#billing_city, #shipping_city', function () {
							$( '.wfacp_shipping_options' ).addClass( 'wfacp_anim_active' );
						} ).on( 'updated_checkout', function () {
							$( '.wfacp_shipping_options' ).removeClass( 'wfacp_anim_active' );
						} );

						syncCityField( 'billing' );
						syncCityField( 'shipping' );
					} )( jQuery );
				} );
			</script>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Departamentos_Ciudades_Colombia(), 'dcco' );
}
