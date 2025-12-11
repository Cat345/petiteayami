<?php
/* Offer refund functionality */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Admin_Refund' ) ) {
	class WFOCU_Admin_Refund {

		/**
		 * @var $ins
		 */
		public static $ins;

		public $view_transaction_url;

		public $get_integration;

		public $order_id;

		public $transaction_id;
		public $localized_data;

		public function __construct() {
			//Adding refund offer metabox
			add_action( 'add_meta_boxes', array( $this, 'wfocu_register_admin_offer_refund_meta_boxes' ) );

			//Moving offer refund metabox below order items
			add_filter( 'get_user_option_meta-box-order_shop_order', array( $this, 'wfocu_move_offer_refund_metabox' ), 10, 1 );
		}

		/**
		 * @return WFOCU_Admin_Refund
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function get_total_shipping_and_tax( $event ) {

			$total = 0;

			if ( isset( $event['_total_tax'] ) && ! empty( $event['_total_tax'] ) ) {
				$total += $event['_total_tax'];
			}

			if ( isset( $event['_total_shipping'] ) && ! empty( $event['_total_shipping'] ) ) {
				$get_total_shipping = json_decode( $event['_total_shipping'], true );

				$total += $get_total_shipping['cost'];
			}

			return $total;
		}

		/**
		 * Metabox callback function
		 * Adding metabox to show refund table below order items table on order edit page
		 */
		public function wfocu_register_admin_offer_refund_meta_boxes() {
			global $post, $wpdb;
			if ( 'shop_order' !== get_current_screen()->id && 'woocommerce_page_wc-orders' !== get_current_screen()->id ) {
				return;
			}

			if ( isset( $_GET['id'] ) ) {
				$this->order_id = $_GET['id'];
			}

			if ( empty( $this->order_id ) && ! is_null( $post ) ) {
				$this->order_id = $post->ID;
			}

			if ( empty( $this->order_id ) ) {
				return;
			}

			$wc_get_order = wc_get_order( $this->order_id );

		$funnel_id = WFOCU_Common::get_order_meta( $wc_get_order, '_wfocu_funnel_id', false );
		if ( absint( $funnel_id ) < 1 ) {
			return;
		}

			if ( ! $this->wfocu_should_show_refund_metabox( $wc_get_order ) ) {
				return;
			}

			/**
			 * This query finds the rows of successful order accepted events where batching was not enable (new order is not created)
			 */
			$new_order_query = 'SELECT COUNT(event.id) FROM `' . $wpdb->prefix . 'wfocu_event` as event  LEFT JOIN ' . $wpdb->prefix . "wfocu_event_meta as metatable ON ( event.id = metatable.event_id AND metatable.meta_key = '_new_order') INNER JOIN " . $wpdb->prefix . "wfocu_session as session ON (event.sess_id = session.id) WHERE session.order_id = '$this->order_id' AND event.action_type_id = 4  AND (metatable.event_id IS NULL)";  //db call ok; no-cache ok; WPCS: unprepared SQL ok.

			$new_order_count = (int) $wpdb->get_var( $new_order_query );  //db call ok; no-cache ok; WPCS: unprepared SQL ok.

			if ( 0 < $new_order_count ) {

				add_action( 'admin_print_footer_scripts', array( $this, 'add_refund_js' ) );
				add_meta_box( 'wfocu-offer-refund-metabox', __( 'One Click Upsells Refund', 'woofunnels-upstroke-one-click-upsell' ), array(
					$this,
					'wfocu_offer_refund_metabox_callback',
				), function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order', 'normal' );
			}

		}

		/**
		 * Displaying offer refund table on order edit page below item list
		 */
		public function wfocu_offer_refund_metabox_callback( $post ) {


			$order = wc_get_order( $this->order_id );

			$funnel_id = $order->get_meta( '_wfocu_funnel_id', true );

			include plugin_dir_path( WFOCU_PLUGIN_FILE ) . '/admin/view/html-offer-refund.php';
		}

		/**
		 * @return array
		 */
		public function wfocu_move_offer_refund_metabox( $already ) {
			$already = is_array( $already ) ? $already : [];
			if ( ! isset( $already['normal'] ) ) {
				$normal = [ 'wfocu-offer-refund-metabox' ];
			} else {
				$normal         = explode( ',', $already['normal'] );
				$order_item_key = array_search( 'woocommerce-order-items', $normal );

				if ( $order_item_key !== false ) {
					/**
					 * Unset previously set metabox key
					 */
					$refund_key = array_search( 'wfocu-offer-refund-metabox', $normal );
					if ( $refund_key !== false ) {
						unset( $normal[ $refund_key ] );
					}

					/**
					 * find order item key and add refund metabox next
					 */
					array_splice( $normal, $order_item_key + 1, 0, 'wfocu-offer-refund-metabox' );
				}
			}

			$already['normal'] = join( ',', $normal );

			return $already;
		}


		/**
		 * Returns the merchant account transaction URL for the given transaction id
		 * Some gateway don't provide transaction URL in that case a simple text
		 *
		 * @param $transaction_id
		 *
		 * @return string transaction URL/text
		 * @see WC_Payment_Gateway::get_transaction_url()
		 *
		 */
		public function get_transaction_link( $transaction_id ) {
			$wc_order   = wc_get_order( $this->order_id );
			$return_url = $this->get_integration->get_transaction_link( $transaction_id, $wc_order );

			return $return_url;
		}

		/**
		 * Get Refund button HTML from gateway integration
		 *
		 * @param $funnel_id
		 * @param $offer_id
		 * @param $total_charge
		 * @param $transaction_id
		 * @param $refunded
		 *
		 * @return mixed
		 */
		public function get_refund_button_html( $funnel_id, $offer_id, $total_charge, $transaction_id, $refunded, $event_id ) {
			$button_html = $this->get_integration->get_refund_button_html( $funnel_id, $offer_id, $total_charge, $transaction_id, $refunded, $event_id );

			return $button_html;
		}

		/**
		 *
		 */
		public function wfocu_should_show_refund_metabox( $wc_get_order ) {
			$result                = false;
			$get_payment_gateway   = WFOCU_WC_Compatibility::get_payment_gateway_from_order( $wc_get_order );
			$this->get_integration = WFOCU_Core()->gateways->get_integration( $get_payment_gateway );

			if ( is_object( $this->get_integration ) ) {
				$available_gateways = WC()->payment_gateways->payment_gateways();

				$refund_supported = $this->get_integration->is_refund_supported( $wc_get_order );

				$result = $refund_supported;
			}

			return apply_filters( 'wfocu_should_show_refund_metabox', $result, $wc_get_order );
		}

		public function add_refund_js() {
			?>
            <script type="text/javascript">
                jQuery(document).ready(function () {

                    var wfocu_local = <?php echo wp_json_encode( $this->localized_data ); ?>;
                    if (typeof wfocu_local.offer_items !== "undefined") {
                        for (var key in wfocu_local.offer_items) {
                            jQuery("tr.item[data-order_item_id='" + wfocu_local.offer_items[key] + "']").each(function () {
                                jQuery(this).find(".quantity .refund input").prop('readonly', true);
                                jQuery(this).find(".line_cost .refund input").prop('readonly', true);
                                jQuery(this).find(".line_tax .refund input").prop('readonly', true);
                            });
                        }
                        jQuery(".refund-actions .button:first-child").before('<p class="wfocu_refund_notice">' + wfocu_local.refund_notice + '</p>');
                    }

                    jQuery('.wfocu-admin-offers-refund .wfocu-refund').on('click', function () {
                        var refund_reason = prompt(woocommerce_admin_meta_boxes.i18n_do_refund + '\n\n' + wfocu_local.refund_reason, "");
                        if (refund_reason !== null) {
                            jQuery('#wfocu-offer-refund-metabox').block({
                                message: null,
                                overlayCSS: {
                                    background: '#fff',
                                    opacity: 0.6
                                }
                            });
                            let txn_id = jQuery(this).attr('data-txn');
                            let order_id = jQuery('input[name="wfocu_order_id"]').val();
                            let amt = jQuery(this).attr('data-amount');
                            let offer_id = jQuery(this).attr('data-offer_id');
                            let funnel_id = jQuery(this).attr('data-funnel_id');
                            let event_id = jQuery(this).attr('data-event_id');
                            let nonce = jQuery('input[name="wfocu_admin_refund_offer"]').val();
                            let data = {
                                'action': 'wfocu_admin_refund_offer',
                                'order_id': order_id,
                                'offer_id': offer_id,
                                'funnel_id': funnel_id,
                                'event_id': event_id,
                                'txn_id': txn_id,
                                'amt': amt,
                                'refund_reason': refund_reason,
                                'nonce': nonce
                            };
                            jQuery.post(ajaxurl, data, function (response) {

                                alert(response.msg);
                                if (response.success) {

                                    location.reload();
                                    return;
                                }
                                jQuery('#wfocu-offer-refund-metabox').unblock();

                            });
                        }
                    });
                });
            </script>
			<?php
		}

		public function set_localized_data( $key, $value ) {
			$this->localized_data[ $key ] = $value;
		}


		public function print_metabox_css() {

			?>
            <style>


                #wfocu-offer-refund-metabox .ref_note {
                    padding: 10px;
                    color: #a00;
                }

                #wfocu-offer-refund-metabox .no-refund-offer {
                    padding: 0 20px;
                }

                #wfocu-offer-refund-metabox .inside {
                    margin: 0;
                    padding: 0;
                    background: #fefefe;
                }

                .wfocu_hide {
                    display: none;
                }

                #wfocu-offer-refund-metabox .wc-order-data-row {
                    border-bottom: 1px solid #dfdfdf;
                    padding: 1.5em 2em;
                    background: #f8f8f8;
                    line-height: 2em;
                }

                #wfocu-offer-refund-metabox .wc-order-data-row::after, #wfocu-offer-refund-metabox .wc-order-data-row::before {
                    content: ' ';
                    display: table
                }

                #wfocu-offer-refund-metabox .wc-order-data-row::after {
                    clear: both
                }

                #wfocu-offer-refund-metabox .wc-order-data-row p {
                    margin: 0;
                    line-height: 2em
                }

                #wfocu-offer-refund-metabox .wc-order-data-row .wc-used-coupons {
                    text-align: left
                }

                #wfocu-offer-refund-metabox .wc-order-data-row .wc-used-coupons .tips {
                    display: inline-block
                }

                #wfocu-offer-refund-metabox .wc-used-coupons {
                    float: left;
                    width: 50%
                }

                #wfocu-offer-refund-metabox .wc-order-totals {
                    float: right;
                    width: 50%;
                    margin: 0;
                    padding: 0;
                }

                #wfocu-offer-refund-metabox .wc-order-totals .amount {
                    font-weight: 700
                }

                #wfocu-offer-refund-metabox .wc-order-totals .label {
                    vertical-align: top
                }

                #wfocu-offer-refund-metabox .wc-order-totals .total {
                    font-size: 1em !important;
                    width: 10em;
                    margin: 0 0 0 .5em;
                    box-sizing: border-box
                }

                #wfocu-offer-refund-metabox .wc-order-totals .total input[type=text] {
                    width: 96%;
                    float: right
                }

                #wfocu-offer-refund-metabox .wc-order-totals .refunded-total {
                    color: #a00
                }

                #wfocu-offer-refund-metabox .refund-actions {
                    margin-top: 5px;
                    padding-top: 12px;
                    border-top: 1px solid #dfdfdf
                }

                #wfocu-offer-refund-metabox .refund-actions .button {
                    float: right;
                    margin-left: 4px
                }

                #wfocu-offer-refund-metabox .refund-actions .cancel-action {
                    float: left;
                    margin-left: 0
                }

                #wfocu-offer-refund-metabox .add_meta {
                    margin-left: 0 !important
                }

                #wfocu-offer-refund-metabox h3 small {
                    color: #999
                }

                #wfocu-offer-refund-metabox .amount {
                    white-space: nowrap
                }

                #wfocu-offer-refund-metabox .add-items .description {
                    margin-right: 10px
                }

                #wfocu-offer-refund-metabox .add-items .button {
                    float: left;
                    margin-right: .25em
                }

                #wfocu-offer-refund-metabox .add-items .button-primary {
                    float: none;
                    margin-right: 0
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper {
                    margin: 0;
                    overflow-x: auto
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items {
                    width: 100%;
                    background: #fff
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items thead th {
                    text-align: left;
                    padding: 1em;
                    font-weight: 400;
                    color: #999;
                    background: #f8f8f8;
                    -webkit-touch-callout: none;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                    user-select: none
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items thead th:last-child {
                    padding-right: 2em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items thead th:first-child {
                    padding-left: 2em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items thead th .wc-arrow {
                    float: right;
                    position: relative;
                    margin-right: -1em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td {
                    padding: 1.5em 1em 1em;
                    text-align: left;
                    line-height: 1.5em;
                    vertical-align: top;
                    border-bottom: 1px solid #f8f8f8
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th textarea, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td textarea {
                    width: 100%
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td select {
                    width: 50%
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th textarea, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td textarea {
                    font-size: 14px;
                    padding: 4px;
                    color: #555
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th:last-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td:last-child {
                    padding-right: 2em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody th:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td:first-child {
                    padding-left: 2em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody tr:last-child td {
                    border-bottom: 1px solid #dfdfdf
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody tr:first-child td {
                    border-top: 8px solid #f8f8f8
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody#order_line_items tr:first-child td {
                    border-top: none
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.thumb {
                    text-align: left;
                    width: 38px;
                    padding-bottom: 1.5em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.thumb .wc-order-item-thumbnail {
                    width: 38px;
                    height: 38px;
                    border: 2px solid #e8e8e8;
                    background: #f8f8f8;
                    color: #ccc;
                    position: relative;
                    font-size: 21px;
                    display: block;
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.thumb .wc-order-item-thumbnail::before {
                    font-family: Dashicons;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    -webkit-font-smoothing: antialiased;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    content: "";
                    width: 38px;
                    line-height: 38px;
                    display: block
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.thumb .wc-order-item-thumbnail img {
                    width: 100%;
                    height: 100%;
                    margin: 0;
                    padding: 0;
                    position: relative
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.name .wc-order-item-sku, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.name .wc-order-item-variation {
                    display: block;
                    margin-top: .5em;
                    font-size: .92em !important;
                    color: #888
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item {
                    min-width: 180px
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class label {
                    white-space: nowrap;
                    color: #999;
                    font-size: .833em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost label input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost label input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost label input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax label input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity label input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax label input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class label input {
                    display: inline
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class input {
                    width: 70px;
                    vertical-align: middle;
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax select, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class select {
                    width: 85px;
                    height: 26px;
                    vertical-align: middle;
                    font-size: 1em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input {
                    display: inline-block;
                    background: #fff;
                    border: 1px solid #ddd;
                    box-shadow: inset 0 1px 2px rgba(0, 0, 0, .07);
                    margin: 1px 0;
                    min-width: 80px;
                    overflow: hidden;
                    line-height: 1em;
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input {
                    width: 100%;
                    box-sizing: border-box
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input label {
                    font-size: .75em;
                    padding: 4px 6px 0;
                    color: #555;
                    display: block
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input input {
                    width: 100%;
                    box-sizing: border-box;
                    border: 0;
                    box-shadow: none;
                    margin: 0;
                    padding: 0 6px 4px;
                    color: #555;
                    background: 0 0
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input input::-webkit-input-placeholder, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input input::-webkit-input-placeholder, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input input::-webkit-input-placeholder, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input input::-webkit-input-placeholder, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input input::-webkit-input-placeholder, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input input::-webkit-input-placeholder, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input input::-webkit-input-placeholder {
                    color: #ddd
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input:first-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input:first-child {
                    border-bottom: 1px dashed #ddd;
                    background: #fff
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input:first-child label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input:first-child label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input:first-child label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input:first-child label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input:first-child label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input:first-child label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input:first-child label {
                    color: #ccc
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .split-input div.input:first-child input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .split-input div.input:first-child input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .split-input div.input:first-child input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .split-input div.input:first-child input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .split-input div.input:first-child input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .split-input div.input:first-child input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .split-input div.input:first-child input {
                    color: #ccc
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .view, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .view, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .view, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .view, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .view, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .view, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .view {
                    white-space: nowrap
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .edit, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .edit, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .edit, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .edit, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .edit, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .edit, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .edit {
                    text-align: left
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class del, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class small.times {
                    font-size: .92em !important;
                    color: #888
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-taxes, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-refund-fields, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-taxes {
                    margin: 0
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-taxes label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-taxes label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-taxes label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-taxes label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-taxes label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-taxes label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-refund-fields label, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-taxes label {
                    display: block
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax .wc-order-item-discount, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class .wc-order-item-discount {
                    display: block;
                    margin-top: .5em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .cost small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .item_cost small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_cost small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .line_tax small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax small.times, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .tax_class small.times {
                    margin-right: .25em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity input {

                    width: 50px
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items thead tr th, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tbody tr td {
                    text-align: left;
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr th, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr th {
                    border: 0;
                    padding: 0 4px .5em 0;
                    line-height: 1.5em;
                    width: 20%
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr td, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr td {
                    padding: 0 4px .5em 0;
                    border: 0;
                    line-height: 1.5em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr td input, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr td input {
                    width: 100%;
                    margin: 0;
                    position: relative;
                    border-bottom: 0;
                    box-shadow: none
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr td textarea, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr td textarea {
                    width: 100%;
                    height: 4em;
                    margin: 0;
                    box-shadow: none
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr td input:focus + textarea, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr td input:focus + textarea {
                    border-top-color: #999
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr td p, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr td p {
                    margin: 0 0 .5em;
                    line-height: 1.5em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.display_meta tr td p:last-child, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items table.meta tr td p:last-child {
                    margin: 0
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .refund_by {
                    border-bottom: 1px dotted #999
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.fee .thumb div {
                    display: block;
                    text-indent: -9999px;
                    position: relative;
                    height: 1em;
                    width: 1em;
                    font-size: 1.5em;
                    line-height: 1em;
                    vertical-align: middle;
                    margin: 0 auto
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.fee .thumb div::before {
                    font-family: WooCommerce;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    content: "";
                    color: #ccc
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.refund .thumb div {
                    display: block;
                    text-indent: -9999px;
                    position: relative;
                    height: 1em;
                    width: 1em;
                    font-size: 1.5em;
                    line-height: 1em;
                    vertical-align: middle;
                    margin: 0 auto
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.refund .thumb div::before {
                    font-family: WooCommerce;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    content: "";
                    color: #ccc
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.shipping .thumb div {
                    display: block;
                    text-indent: -9999px;
                    position: relative;
                    height: 1em;
                    width: 1em;
                    font-size: 1.5em;
                    line-height: 1em;
                    vertical-align: middle;
                    margin: 0 auto
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.shipping .thumb div::before {
                    font-family: WooCommerce;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    content: "";
                    color: #ccc
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.shipping .shipping_method, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items tr.shipping .shipping_method_name {
                    width: 100%;
                    margin: 0 0 .5em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items th.line_tax {
                    white-space: nowrap
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.line_tax .delete-order-tax, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items th.line_tax .delete-order-tax {
                    display: block;
                    text-indent: -9999px;
                    position: relative;
                    height: 1em;
                    width: 1em;
                    float: right;
                    font-size: 14px;
                    visibility: hidden;
                    margin: 3px -18px 0 0
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.line_tax .delete-order-tax::before, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items th.line_tax .delete-order-tax::before {
                    font-family: Dashicons;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    -webkit-font-smoothing: antialiased;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    content: "";
                    color: #999
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.line_tax .delete-order-tax:hover::before, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items th.line_tax .delete-order-tax:hover::before {
                    color: #a00
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items td.line_tax:hover .delete-order-tax, #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items th.line_tax:hover .delete-order-tax {
                    visibility: visible
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items small.refunded {
                    display: block;
                    color: #a00;
                    white-space: nowrap;
                    margin-top: .5em
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items small.refunded::before {
                    font-family: Dashicons;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    -webkit-font-smoothing: antialiased;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    content: "";
                    position: relative;
                    top: auto;
                    left: auto;
                    margin: -1px 4px 0 0;
                    vertical-align: middle;
                    line-height: 1em
                }

                #wfocu-offer-refund-metabox .wc-order-edit-line-item-actions a {
                    color: #ccc;
                    display: inline-block;
                    cursor: pointer;
                    padding: 0 0 .5em;
                    margin: 0 0 0 12px;
                    vertical-align: middle;
                    text-decoration: none;
                    line-height: 16px;
                    width: 16px;
                    overflow: hidden
                }

                #wfocu-offer-refund-metabox .wc-order-edit-line-item-actions a::before {
                    margin: 0;
                    padding: 0;
                    font-size: 16px;
                    width: 16px;
                    height: 16px
                }

                #wfocu-offer-refund-metabox .wc-order-edit-line-item-actions a:hover::before {
                    color: #999
                }

                #wfocu-offer-refund-metabox .wc-order-edit-line-item-actions a:first-child {
                    margin-left: 0
                }

                #wfocu-offer-refund-metabox .wc-order-totals .wc-order-edit-line-item-actions a {
                    padding: 0
                }

                #wfocu-offer-refund-metabox .inside {
                    margin: 0 !important;
                }

                #tiptip_content .t_left {
                    float: left;
                }

                #tiptip_content .t_right {
                    float: right;
                }

                #tiptip_content .refund_t {
                    height: 60px;
                    width: 120px;
                }

                .refund-actions .wfocu_refund_notice {
                    font-style: italic;
                    color: gray;
                    text-align: left;
                    padding-bottom: 1%;
                }

                #wfocu-offer-refund-metabox .woocommerce_order_items_wrapper table.woocommerce_order_items .quantity .view {
                    white-space: normal
                }

                #wfocu-offer-refund-metabox .postbox-header {
                    pointer-events: none !important
                }

                #wfocu-offer-refund-metabox .postbox-header .handle-actions {
                    display: none !important
                }

            </style>


			<?php
		}
	}

	WFOCU_Admin_Refund::get_instance();
}
