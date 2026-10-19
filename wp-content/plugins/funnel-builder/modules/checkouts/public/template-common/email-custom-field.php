<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
/**
 * Created by PhpStorm.
 * User: sandeep
 * Date: 3/22/19
 * Time: 12:03 PM
 */
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $order WC_Order;
 */


$wfacp_id = $order->get_meta( '_wfacp_post_id' );
if ( empty( $wfacp_id ) || $wfacp_id == 0 ) {
	return;
}
$wfacp_custom_field = WFACP_Common::get_checkout_fields( $wfacp_id );
if ( empty( $wfacp_custom_field ) || ! isset( $wfacp_custom_field['advanced'] ) || empty( $wfacp_custom_field['advanced'] ) ) {
	return;
}
$wfacp_html = '';
foreach ( $wfacp_custom_field['advanced'] as $wfacp_key => $wfacp_field ) {
	if ( ! ( isset( $wfacp_field['show_custom_field_at_email'] ) && wc_string_to_bool( $wfacp_field['show_custom_field_at_email'] ) ) ) {
		continue;
	}
	$wfacp_meta_value = WFACP_Common::map_meta_value_for_custom_fields( $order->get_meta( $wfacp_key ), $wfacp_field );
	if ( '' !== $wfacp_meta_value || apply_filters( 'wfacp_print_blank_custom_field', false, $order, $wfacp_id ) ) {
		$wfacp_meta_value = apply_filters( 'wfacp_email_custom_field_value', $wfacp_meta_value, $wfacp_key, $wfacp_field );
		$wfacp_html       .= sprintf( '<tr class="woocommerce-table__line-item order_item"><td class="product-name"><b>%s</b></td><td class="product-total">%s</td></tr>', ( $wfacp_field['label'] ), ( $wfacp_meta_value ) );
	}
}

if ( empty( $wfacp_html ) ) {
	return;
}
?>
<div style="margin-bottom: 40px;">
    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details wfacp_email_custom_field" style="color: #69696a;border: 1px solid #e5e5e5;vertical-align: middle;text-align: left;padding: 0px;width: 100%;">
		<?php echo wp_kses_post( $wfacp_html ); ?>
    </table>
</div>
