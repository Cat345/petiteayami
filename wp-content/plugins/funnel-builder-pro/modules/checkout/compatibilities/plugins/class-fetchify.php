<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: Fetchify (ClickToAddress Auto-Complete) by ClearCourse Business Services Limited t/a Fetchify
 * Plugin URI: https://wordpress.org/plugins/clicktoaddress-auto-complete/
 */

if ( ! class_exists( 'WFACP_Compatibility_With_Fetchify' ) ) {

	/**
	 * Fetchify only enqueues its checkout script on `woocommerce_checkout_billing`, which FunnelKit
	 * checkouts never fire, and its search-first layout looks for WooCommerce's billing/shipping
	 * wrappers. This loads Fetchify's own script and runs that layout inside the FunnelKit form.
	 */
	#[\AllowDynamicProperties]
	class WFACP_Compatibility_With_Fetchify {

		private $integration = null;

		public function __construct() {
			add_action( 'wfacp_after_template_found', array( $this, 'setup' ) );
		}

		public function setup() {
			try {
				if ( ! function_exists( 'WC' ) || ! WC()->integrations ) {
					return;
				}
				$integrations = WC()->integrations->get_integrations();
				if ( ! isset( $integrations['clicktoaddress_autocomplete'] ) || ! is_callable( array( $integrations['clicktoaddress_autocomplete'], 'addCheckoutJs' ) ) ) {
					return;
				}
				$this->integration = $integrations['clicktoaddress_autocomplete'];

				// Same gate Fetchify uses before hooking its checkout script.
				$enabled = false;
				foreach ( array( 'enabled_checkout', 'enabled_postcode', 'enabled_phone', 'enabled_email' ) as $key ) {
					$enabled = $enabled || 1 === absint( $this->integration->get_option( $key ) );
				}
				if ( ! $enabled || '' === (string) $this->integration->get_option( 'access_token' ) ) {
					return;
				}

				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
			} catch ( Throwable $e ) {
				$this->integration = null;
			}
		}

		public function enqueue_scripts() {
			try {
				$this->integration->addCheckoutJs();
				if ( ! wp_script_is( 'clicktoaddress-autocomplete-js-checkout', 'enqueued' ) || ! $this->is_search_first() ) {
					return;
				}

				wp_register_style( 'wfacp-fetchify', false, array(), WFACP_VERSION );
				wp_enqueue_style( 'wfacp-fetchify' );
				wp_add_inline_style( 'wfacp-fetchify', $this->loading_css() );

				wp_register_script( 'wfacp-fetchify-loading', false, array(), WFACP_VERSION, false );
				wp_enqueue_script( 'wfacp-fetchify-loading' );
				wp_add_inline_script( 'wfacp-fetchify-loading', 'document.documentElement.classList.add("wfacp-fetchify-loading");setTimeout(function(){document.documentElement.classList.remove("wfacp-fetchify-loading")},5000);' );

				wp_add_inline_script( 'clicktoaddress-autocomplete-js-checkout', $this->layout_js() );
			} catch ( Throwable $e ) {
				return;
			}
		}

		/**
		 * Search-first layout inside the FunnelKit form. Runs from Fetchify's own calls: add_autocomplete_form()
		 * sets a section up, show_fields() reveals it after a pick.
		 */
		private function layout_js() {
			ob_start();
			?>
			// Fetchify waits for jQuery the same way before it builds the search, so the wrappers below are always in place first.
			(function wfacpFetchify() {
				if (typeof jQuery === 'undefined') {
					setTimeout(wfacpFetchify, 50);
					return;
				}
				var $ = jQuery;
				var cfg = window.cc_c2a_config && window.cc_c2a_config.autocomplete;
				var fn = window.cc_c2a_functions && window.cc_c2a_functions.autocomplete;
				var root = document.documentElement;
				if (!cfg || !fn || !cfg.enabled || cfg.search_line_1) {
					root.classList.remove('wfacp-fetchify-loading');
					return;
				}
				var addForm = fn.add_autocomplete_form;
				var showFields = fn.show_fields;

				fn.add_autocomplete_form = function (prefix) {
					var result = addForm.apply(this, arguments);
					setup(prefix);
					if (!$('#billing_address_1_field, #shipping_address_1_field').not('.wfacp-fetchify-ready').length) {
						root.classList.remove('wfacp-fetchify-loading');
					}
					return result;
				};

				fn.show_fields = function (form) {
					var result = showFields.apply(this, arguments);
					form.find('.cc_c2a_search_input').each(function () {
						var section = $(this).data('wfacpFetchify');
						if (section) {
							section.picked();
						}
					});
					return result;
				};

				function setup(prefix) {
					var street = $('#' + prefix + '_address_1_field').addClass('wfacp-fetchify-ready');
					var input = $('#' + prefix + '_cc_c2a_search_input');
					if (!input.length || !street.length) {
						return;
					}
					// Fetchify looks for WooCommerce's div.woocommerce-<prefix>-fields; FunnelKit wraps each address in .wfacp_divider_<prefix>.
					var box = street.closest('.wfacp_divider_' + prefix);
					box = box.length ? box : street.parent();
					var row = input.closest('p');
					var manual = row.find('.cc_c2a_manual');
					var search = box.find('.cc_c2a_hide');
					var rows = box.find('[cc-c2a-hide="1"]').add('#' + prefix + '_address_2_collapse_label');
					var searching = false;

					row.insertBefore(street).addClass('wfacp-form-control-wrapper wfacp-col-full ' + (street.attr('class').match(/wfacp_\w+_field(s|_hide)/g) || []).join(' '));
					input.addClass('wfacp-form-control');
					row.find('label').addClass('wfacp-form-control-label');
					street.prepend(search);

					function showSearch() {
						searching = true;
						rows.hide();
						row.show();
						search.hide();
						manual.show();
					}

					function showManual() {
						searching = false;
						rows.css('display', '');
						row.hide();
						manual.hide();
						search.show();
					}

					// As on the WooCommerce checkout, the search and "Enter Address Manually" stay after a pick.
					input.data('wfacpFetchify', {
						picked: function () {
							searching = false;
							rows.css('display', '');
							row.show();
							search.stop(true, true).hide();
							manual.stop(true, true).show();
						}
					});

					if (!cfg.hide_fields) {
						return;
					}
					if ($('#' + prefix + '_address_1').val()) {
						showManual();
					} else {
						showSearch();
					}
					manual.on('click', showManual);
					search.on('click', showSearch);

					// WooCommerce's address-i18n re-shows city, postcode and state; these run in the same task, before paint.
					$(document.body).on('country_to_state_changing country_to_state_changed updated_checkout', function () {
						if (searching) {
							rows.hide();
						}
					});

					// After an error Fetchify turns hide_fields off and only reveals WooCommerce's wrappers; it may also fill without show_fields().
					input.on('keyup', function () {
						setTimeout(function () {
							if (searching && !cfg.hide_fields) {
								showManual();
							}
						}, 1500);
					});
					$('#' + prefix + '_address_1').on('change', function () {
						if (searching && this.value) {
							input.data('wfacpFetchify').picked();
						}
					});
				}
			})();
			<?php
			return ob_get_clean();
		}

		/**
		 * Fetchify hides the address fields behind a "Find Address" search only with these settings.
		 */
		private function is_search_first() {
			return 1 === absint( $this->integration->get_option( 'enabled_checkout' ) ) && 1 === absint( $this->integration->get_option( 'hide_fields' ) ) && 1 !== absint( $this->integration->get_option( 'search_first_line' ) );
		}

		/**
		 * Keeps the address rows from painting before the search is in place; the repeated id outranks
		 * the templates' layout rules (e.g. #wfacp-e-form p.form-row.wfacp-col-left-half).
		 */
		private function loading_css() {
			$selectors = array();
			foreach ( array( 'billing', 'shipping' ) as $prefix ) {
				foreach ( array( 'address_1_field', 'address_2_field', 'address_2_collapse_label', 'city_field', 'postcode_field', 'state_field', 'country_field' ) as $row ) {
					$id          = '#' . $prefix . '_' . $row;
					$selectors[] = 'html.wfacp-fetchify-loading ' . $id . $id . $id;
				}
			}

			return implode( ',', $selectors ) . '{display:none}';
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Fetchify(), 'fetchify' );
}
