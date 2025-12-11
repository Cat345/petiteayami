<?php

wp_enqueue_script( 'wfocu-modal-script', WFOCU_PLUGIN_URL . '/admin/assets/js/wfocu-modal.js', array( 'jquery' ), WFOCU_VERSION );
wp_enqueue_style( 'wfocu-modal-style', WFOCU_PLUGIN_URL . '/admin/assets/css/wfocu-modal.css', null, WFOCU_VERSION );

if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
	wp_enqueue_style( 'wfocu-modal-common-style', WFOCU_PLUGIN_URL . '/admin/assets/css/wfocu-mb-common.css', null, WFOCU_VERSION );

}else{
	wp_enqueue_style( 'wfocu-modal-common-style', WFOCU_PLUGIN_URL . '/admin/assets/css/min/wfocu-mb-common.min.css', null, WFOCU_VERSION );

}


$maybe_offer_id = WFOCU_Core()->template_loader->get_offer_id();

$offer_data = WFOCU_Core()->offers->get_offer_meta( $maybe_offer_id );

$code_key      = ( ! empty( $offer_data ) && isset( $offer_data->products ) && count( get_object_vars( $offer_data->products ) ) < 2 ) ? 'single' : 'multi';
$product_count = 1;

$shortcodes = WFOCU_Core()->admin->get_shortcodes_list();
?>

    <div class='' id="wfocu_shortcode_help_box" style="display: none;">

        <h3><?php esc_html_e( 'Shortcodes', 'woofunnels-upstroke-one-click-upsell' ); ?></h3>
        <div style="font-size: 1.1em; margin: 5px;"><i><?php esc_html_e( 'Here are set of Shortcodes that can be used on this page.', 'woofunnels-upstroke-one-click-upsell' ); ?> </i> </div>
		<?php foreach ( ( ! empty( $offer_data ) && isset( $offer_data->products ) ) ? $offer_data->products : array() as $hash => $product_id ) {
            if(!wc_get_product( $product_id ) instanceof WC_Product) {
                continue;
            }
            ?>
            <h4><?php _e( sprintf( 'Product: %1$s', wc_get_product( $product_id )->get_title() ), 'woofunnels-upstroke-one-click-upsell' ); ?></h4>

            <table class="table widefat">
                <thead>
                <tr>
                    <td><?php esc_html_e( 'Title', 'woofunnels-upstroke-one-click-upsell' ); ?></td>
                    <td style="width: 70%;"><?php esc_html_e( 'Shortcodes', 'woofunnels-upstroke-one-click-upsell' ); ?></td>

                </tr>
                </thead>
                <tbody>

				<?php
				foreach ( $shortcodes as $shortcode ) { ?>
                    <tr>
                        <td>
							<?php echo esc_html( $shortcode['label'] ); ?>
                        </td>
                        <td>
                            <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                   value='<?php printf( $shortcode['code'][ $code_key ], $product_count ); ?>'/>
                        </td>
                    </tr>
				<?php }
				$product_count ++;
				?>
                </tbody>
            </table>
		<?php } ?>
        <br/>

        <h3><strong>Order Personalization Shortcodes</strong></h3>

        <table class="table widefat">
            <caption><p style="float: left;">To personalize upsell pages with different order attributes, use these merge tags-</p></caption>
            <thead>
            <tr>
                <td width="300">Name</td>
                <td>Shortcodes</td>
            </tr>
            </thead>
            <tbody>

            <tr>
                <td>Customer First Name</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="customer_first_name"]'/>
                </td>
            </tr>

            <tr>
                <td>Customer Last Name</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="customer_last_name"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Number</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_no"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Date</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_date"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Total</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_total"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Item Count</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_itemscount"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Shipping Method</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_shipping_method"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Billing Country</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_billing_country"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Shipping Country</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key="order_shipping_country"]'/>
                </td>
            </tr>

            <tr>
                <td>Order Custom Meta</td>
                <td><input type="text" style="width: 75%;" onClick="this.select()" readonly
                           value='[wfocu_order_data key=""]'/>
                </td>
            </tr>

            </tbody>
        </table>

    </div>
<?php
