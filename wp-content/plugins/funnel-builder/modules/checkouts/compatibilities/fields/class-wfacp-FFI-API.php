<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFACP_Compatibility_FFI_API' ) ) {
	/**
	 * FFl API By Optimum7
	 * Plugin URI: https://wordpress.org/plugins/ffl-api/
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_FFI_API {

		public $is_field = false;

		public function __construct() {

			/* Register Add field */
			add_filter( 'wfacp_advanced_fields', array( $this, 'add_field' ), 20 );
			add_filter( 'wfacp_html_fields_wfacp_ffl_api', '__return_false' );
			add_action( 'process_wfacp_html', array( $this, 'display_ffl_field' ), 999, 3 );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );

			/* prevent third party fields and wrapper*/

			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );
		}

		public function add_field( $fields ) {
			if ( false === $this->is_enabled() ) {
				return $fields;
			}

			$fields['wfacp_ffl_api'] = array(
				'type'       => 'wfacp_html',
				'class'      => array( 'wfacp-col-full', 'wfacp-form-control-wrapper', 'wfacp_ffl_api' ),
				'id'         => 'wfacp_ffl_api',
				'field_type' => 'wfacp_ffl_api',
				'label'      => __( 'FFL API', 'funnel-builder' ),

			);

			return $fields;
		}

		public function is_enabled() {
			return class_exists( 'Ffl_Api_Public' );
		}

		public function display_ffl_field( $field, $key, $args ) {
			if ( ! empty( $key ) && 'wfacp_ffl_api' === $key && $this->is_enabled() ) {
				$location = get_option( 'ffl_init_map_location', 'woocommerce_checkout_order_review' );
				$object   = WFACP_Common::remove_actions( $location, 'Ffl_Api_Public', 'ffl_init_map' );
				if ( $object instanceof Ffl_Api_Public ) {
					$this->is_field = true;
					echo '<div id="ffl_container"></div>';
				}
			}
		}

		public function internal_css() {

			if ( false === $this->is_enabled() ) {
				return;
			}

			$aKey        = wp_json_encode( get_option( 'ffl_api_key_option', '' ) );
			$gKey        = wp_json_encode( get_option( 'ffl_api_gmaps_option', '' ) );
			$default_msg = 'You have chosen your address as a shipping address for a firearm product. Unfortunately, we are not able to ship the firearms directly to your address. Please select the closest FFL from the map using your zip code.';
			$wMes        = wp_json_encode( get_option( 'ffl_api_warning_message' ) !== '' ? get_option( 'ffl_api_warning_message' ) : $default_msg );
			$hok         = wp_json_encode( get_option( 'ffl_init_map_location', '' ) );

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() emits a complete, JS-escaped literal.
			echo '
<script type="text/javascript">

  var aKey = ' . $aKey . '
    var gKey = ' . $gKey . '
    var wMes = ' . $wMes . '
    var hok = ' . $hok . '

</script>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			?>

			<script>

				window.addEventListener('load', function () {
					(function ($) {

						$(document.body).on('updated_checkout', function () {

							add_hide_animate();
						});


						function add_hide_animate() {

							var addresses = ['billing', 'shipping'];
							for (var i in addresses) {
								var key = addresses[i];


								$(".wfacp_divider_" + key + " .form-row").each(function () {
									let field_id = $(this).attr("id");
									if (field_id != '') {
										let field_val_id1 = field_id.replace('_field', '');
										let field_val = $('#' + field_val_id1).val();
										if (field_val != '' && field_val != null && !$(this).hasClass('wfacp-anim-wrap')) {

											$(this).addClass("wfacp-anim-wrap");
										}
									}
								});
							}


						}

						localStorage.removeItem("selectedFFL");

						if (!document.getElementById("ffl-zip-code")) {
							initFFLJs(aKey, gKey, wMes, hok);
						}
						jQuery("#shipping_country")?.val('US').trigger('change');

					})(jQuery);
				});
			</script>

			<style>
				#ffl_container .columns {
					display: block;
					margin-top: 15px;
				}

				#ffl_container .columns .column {
					display: inline-block;
					float: left;
					width: 31.33%;
					margin: 0;
					margin-right: 2%;
				}

				#ffl_container .columns .column:nth-child(2n) {
				}

				#ffl_container .columns .column:last-child {
					margin: 0;
					position: relative;
					margin-top: 32px;
				}

				#ffl_container .columns .column #ffl-search {
					border: 1px solid #d9d9d9;
				}

				#ffl_container .columns .column select {
					padding-top: 14px;
					padding-bottom: 14px;
					margin-bottom: 0;
					-webkit-appearance: menulist;
					-moz-appearance: menulist;
				}

				#ffl_container .columns:after, #ffl_container .columns:before {
					content: '';
					display: block;
				}

				#ffl_container .columns:after {
					clear: both;
				}
			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_FFI_API(), 'wfacp-ffi-api' );
}
