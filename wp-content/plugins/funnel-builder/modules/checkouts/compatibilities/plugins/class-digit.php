<?php
/*
 * Compatability added with plugin DIGITS: WordPress Mobile Number Signup and Login by UnitedOver v.8.5
 *  Plugin URI: https://digits.unitedover.com
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_Digits_by_UnitedOver' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Digits_by_UnitedOver {
		public function __construct() {

			add_filter( 'wfacp_internal_css', [ $this, 'add_js' ], 50 );
		}


		public function add_js() {
			?>
            <style>
                body #wfacp-e-form .digcon {
                    position: relative;
                    display: block;
                }

                body #wfacp-e-form .digcon .dig_wc_logincountrycodecontainer {
                    position: absolute;
                    top: 0;
                    bottom: 0;
                    padding: 1px;
                    right: auto;
                    left: 0;
                    z-index: 999;
                }

                body #wfacp-e-form .digcon .dig_wc_logincountrycodecontainer .countrycode {
                    z-index: 1;
                    display: flex;
                    align-items: center;
                    height: 100%;
                    padding: 10px 12px;
                    width: auto;
                    margin-right: 0;
                    background: 0 0;
                    position: relative;
                    font-size: 14px;
                    font-weight: 400;
                    border: none;
                }

                body #wfacp-e-form .digcon .dig_wc_logincountrycodecontainer:after {
                    content: '';
                    display: block;
                    width: 1px;
                    background: #d9d9d9;
                    height: 18px;
                    position: absolute;
                    right: 0;
                    top: 50%;
                    margin-top: -9px;
                }

            </style>

            <script>
                window.addEventListener('load', function () {
                    (function ($) {

                        if($('.dig_wc_logincountrycodecontainer').length > 0){


                            setTimeout(function(){
                                digit_field_position('.dig_wc_logincountrycodecontainer:visible');
                            },500);


                            jQuery(document).on("focusout", 'focusin',".countrycode, .countrycode_search", function (e) {
                                setTimeout(function(){
                                    digit_field_position('.dig_wc_logincountrycodecontainer:visible');
                                },500);
                            });

                            jQuery(document).on('update_flag', '.country_code_flag', function (e) {
                                setTimeout(function(){
                                    digit_field_position('.dig_wc_logincountrycodecontainer:visible');
                                },500);
                            })

                            var elem = jQuery(".digit_cs-list");
                            elem.on('mousedown click', 'li', function (e) {
                                setTimeout(function(){
                                    digit_field_position('.dig_wc_logincountrycodecontainer:visible');
                                },500);
                            });

                            function digit_field_position(className) {

                                let flag_w = 0;

                                flag_w = $(className).innerWidth();
                                 if (typeof flag_w !== "undefined" && '' != flag_w) {
                                    flag_w = parseInt(flag_w) + 12;

                                    if ($('.wfacp-top').length == 0) {
                                        if (true === wfacp_frontend.is_rtl || "1" === wfacp_frontend.is_rtl) {
                                            $('.digcon #username').parents('.wfacp-form-control-wrapper').find('.wfacp-form-control-label').css('right', flag_w + 8);
                                        } else {
                                            $('.digcon #username').parents('.wfacp-form-control-wrapper').find('.wfacp-form-control-label').css('left', flag_w + 8);
                                        }
                                    }
                                    if (true === wfacp_frontend.is_rtl || "1" === wfacp_frontend.is_rtl) {
                                        $('.digcon #username').css('cssText', 'padding-right: ' + flag_w + 'px !important');
                                    } else {
                                        $('.digcon  #username').css('cssText', 'padding-left: ' + flag_w + 'px !important');
                                    }
                                }
                            }
                        }

                    })(jQuery);
                });
            </script>
			<?php

		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Digits_by_UnitedOver(), 'digits' );
}