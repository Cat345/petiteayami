<?php
/**
 * Offer items HTML for meta box.
 */

$order_id = $order->get_id();
$wc_order = $order;

defined( 'ABSPATH' ) || exit;

$wfocu_session_ids = WFOCU_Core()->track->query_results( array(
	'data'          => array(
		'id' => array(
			'type'     => 'col',
			'function' => '',
			'name'     => 'session_id',
		),
	),
	'where'         => array(
		array(
			'key'      => 'events.order_id',
			'value'    => $order_id,
			'operator' => '=',
		),
	),
	'query_type'    => 'get_results',
	'session_table' => true,
	'nocache'       => true,
) );

$wfocu_session_id = '';

if ( is_array( $wfocu_session_ids ) && count( $wfocu_session_ids ) > 0 ) {
	$wfocu_session_ids = end( $wfocu_session_ids );
	if ( isset( $wfocu_session_ids->session_id ) ) {
		$wfocu_session_id = $wfocu_session_ids->session_id;
	}
}

$eventsdb = WFOCU_Core()->track->query_results( array(
	'where'      => array(
		array(
			'key'      => 'events.sess_id',
			'value'    => $wfocu_session_id,
			'operator' => '=',
		),

	),
	'query_type' => 'get_results',
	'order_by'   => 'events.timestamp',
	'order'      => 'ASC',
	'nocache'    => true,

) );

$event_ids = wc_list_pluck( $eventsdb, 'id' );

$events_meta = WFOCU_Core()->track->get_meta( $event_ids );

$transactions = array();


$events = [];
foreach ( is_array( $events_meta ) ? $events_meta : array() as $key => $meta ) {

	if ( ! isset( $events[ $meta['event_id'] ] ) ) {
		$events[ $meta['event_id'] ] = [];
	}

	$events[ $meta['event_id'] ][ $meta['meta_key'] ] = $meta['meta_value'];

}

$products           = [];
$all_upstroke_items = [];

if( ! is_array( $events ) || count( $events ) === 0 ) {
   return;
}

foreach ( $events as $id => $event ) {

	if ( isset( $event['_new_order'] ) ) {
		continue;
	}
	$event_row = $eventsdb[ array_search( $id, $event_ids ) ];

	if ( '5' === $event_row->action_type_id && isset( $event['_value'] ) ) {
		if ( ! isset( $products[ $event['_offer_id'] ] ) ) {
			$products[ $event['_offer_id'] ] = [];
		}
		$products[ $event['_offer_id'] ][] = array(
			'product_id'   => $event_row->object_id,
			'product_name' => get_the_title( $event_row->object_id ),
			'value'        => isset( $event['_value'] ) ? $event['_value'] : '',
		);
	}

	/**
	 * setup transaction id and charges
	 */
	if ( '4' === $event_row->action_type_id && isset( $event['_total_charged'] ) && isset( $event['_transaction_id'] ) && ! empty( $event['_transaction_id'] ) ) {

		$transactions[ $event_row->object_id ] = array(
			'transaction_id' => $event['_transaction_id'],
			'offer_id'       => $event_row->object_id,
			'offer_name'     => get_the_title( $event_row->object_id ),
			'value'          => isset( $event['_total_charged'] ) ? $event['_total_charged'] : '',
			'taxes_n_ship'   => ( 0 !== $this->get_total_shipping_and_tax( $event ) ) ? wc_price( $this->get_total_shipping_and_tax( $event ) ) : '-',
			'items_total'    => isset( $event['_total_items'] ) ? $event['_total_items'] : '',
			'event_id'       => $event_row->id,
		);

		if ( isset( $event['_items_added'] ) ) {
			$all_upstroke_items = array_merge( $all_upstroke_items, json_decode( $event['_items_added'], true ) );
		}
	}
}

$this->set_localized_data( 'offer_items', $all_upstroke_items );
$this->set_localized_data( 'refund_notice', __( '<strong>Note:</strong> Its recommended that refund the upsell offers first if you are refunding the complete order.', 'woofunnels-upstroke-one-click-upsell' ) );
$this->set_localized_data( 'refund_reason', __( 'Reason for refund(Optional):', 'woofunnels-upstroke-one-click-upsell' ) );
$refunded_offers = WFOCU_Common::get_order_meta( $order, '_wfocu_refunded_offers', false );
$refunded_offers = empty( $refunded_offers ) ? array() : $refunded_offers;
echo $this->print_metabox_css();
?>

<div class="woocommerce_order_items_wrapper wc-order-items-editable wfocu-admin-offers-refund">
	<?php if ( count( $transactions ) > 0 ) { ?>
        <div class="ref_note">
			<?php _e( 'Use WooCommerce refunding to refund amount <strong> less than or equal to </strong> primary order  <strong>OR</strong> Click \'Refund\' to refund upsell offers.', 'woofunnels-upstroke-one-click-upsell' ); ?>
            <br>
			<?php _e( 'Note: If you are refunding full order, it is advised to refund upsell offers first &amp; then refund the primary order.', 'woofunnels-upstroke-one-click-upsell' ); ?>
        </div>
        <table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
            <thead>
            <tr>
                <th class="quantity"><?php esc_html_e( 'Offer', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
                <th class="item_cost"><?php esc_html_e( 'Transaction', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
                <th class="item offer_products"><?php esc_html_e( 'Products', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
                <th class="line_cost"><?php esc_html_e( 'Total', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
                <th class="wc-order-edit-line-item"><?php esc_html_e( 'Action', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
            </tr>
            </thead>
            <tbody id="order_line_items">
			<?php
			foreach ( $transactions as $transaction ) {

				$refunded = in_array( $transaction['offer_id'], $refunded_offers );
				if ( isset( $products[ $transaction['offer_id'] ] ) ) { ?>
                    <tr class="item">

                        <td class="quantity">
                            <div class="view">
                                <span><?php echo $transaction['offer_name']; ?></span>
                            </div>

                        </td>
                        <td class="item_cost" width="1%">
                            <div class="view">
                                <span class="transaction"><?php echo $this->get_transaction_link( $transaction['transaction_id'] ) ?></span>
                            </div>
                        </td>
                        <td class="name off_pro_name">
							<?php foreach ( $products[ $transaction['offer_id'] ] as $prodct ) { ?>
                                <a data-product_id="<?php echo $prodct['product_id']; ?>" href="<?php echo get_edit_post_link( $prodct['product_id'] ); ?>" class="wc-order-item-name"><?php echo $prodct['product_name']; ?></a>
                                <div class="wc-order-item-sku">
                                    <strong></strong>
                                </div>
							<?php } ?>

                        </td>


                        <td class="item_cost" width="1%">
                            <div class="view">
                                <strong><?php echo wc_price( $transaction['value'] ); ?> </strong> <?php echo wc_help_tip( sprintf( __( '<div class="refund_t"><span class="t_left">Item Prices:</span><span class="t_right">%s</span> <br/> <span class="t_left">Tax & Shipping:</span> <span class="t_right">%s</span>  <span class="t_left">Total:</span> <span class="t_right">%s</span></div>', 'woocommerce' ), wc_price( $transaction['items_total'] ), $transaction['taxes_n_ship'], wc_price( $transaction['value'] ) ) ); ?>

                            </div>
                        </td>

                        <td class="wc-order-edit-line-item">
                            <div class="view">
								<?php echo $this->get_refund_button_html( $funnel_id, $transaction['offer_id'], $transaction['value'], $transaction['transaction_id'], $refunded, $transaction['event_id'] ); ?>
                            </div>
                        </td>

                    </tr>
				<?php } ?>

			<?php } ?>
            <input type="hidden" value="<?php echo $order_id; ?>" name="wfocu_order_id">
            <input type="hidden" value="<?php echo wp_create_nonce( 'wfocu_admin_refund_offer' ); ?>" name="wfocu_admin_refund_offer">
            </tbody>
        </table>
		<?php
	} else {
		echo "<p class='no-refund-offer'>" . __( 'Refunds are not available for any offer(s) against this order.', 'woofunnels-upstroke-one-click-upsell' ) . '</p>';

	} ?>
</div>
