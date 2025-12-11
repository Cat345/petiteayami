<?php
add_action( 'wfacp_after_checkout_page_found', 'attach_wfacp_footer' );
function attach_wfacp_footer() {
	add_action( 'wp_footer', 'attach_wfacp_footer_js' );
}

function attach_wfacp_footer_js() {
	?>
    <script>
        window.addEventListener('load', function () {
            (function ($) {
                $('#shipping_country').on('change', function () {
                    var ct = $(this).val();
                    if (ct == 'NL' || ct == 'BE') {
                        $('#shipping_city_field').insertBefore('#shipping_street_name_field');
                        $('#shipping_postcode_field').insertAfter('#shipping_city_field');
                        $('#shipping_country_field').insertAfter('#shipping_postcode_field');
                    } else {
                        $('#shipping_city_field').insertAfter('#shipping_house_number_suffix_field');
                        $('#shipping_postcode_field').insertAfter('#shipping_city_field');
                        $('#shipping_country_field').insertAfter('#shipping_postcode_field');
                    }
                });
                $('#billing_country').on('change', function () {
                    var ct = $(this).val();
                    if (ct == 'NL' || ct == 'BE') {
                        $('#billing_city_field').insertBefore('#billing_street_name_field');
                        $('#billing_postcode_field').insertAfter('#billing_city_field');
                        $('#billing_country_field').insertAfter('#billing_postcode_field');
                    } else {
                        $('#billing_city_field').insertAfter('#billing_house_number_suffix_field');
                        $('#billing_postcode_field').insertAfter('#billing_city_field');
                        $('#billing_country_field').insertAfter('#billing_postcode_field');
                    }
                });
            })(jQuery)
        });
    </script>
	<?php
}

?>