<?php
namespace ACFWF\Models\Store_Credits\Traits;

use ACFWF\Helpers\Plugin_Constants;
use ACFWF\Models\Objects\Store_Credit_Entry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait that houses the logic of admin apply store credits.
 *
 * @since 4.5.9
 */
trait Admin_Apply_Store_Credits {
    /*
    |--------------------------------------------------------------------------
    | Apply Store Credits in pending order
    |--------------------------------------------------------------------------
     */

    /**
     * Display the apply store credits discount in the edit order page.
     *
     * @since 4.5.4
     * @access public
     *
     * @param \WC_Order $order Order object.
     */
    public function display_apply_store_credits_discount_in_edit_order_page( $order ) {

        if ( ! apply_filters( 'acfw_enable_apply_store_credits_order', true, $order ) ) {
            return;
        }

        $allowed_statuses = apply_filters( 'acfwf_apply_store_credits_order_allowed_statuses', array( 'pending', 'on-hold', 'checkout-draft' ) );

        if ( ! in_array( $order->get_status(), $allowed_statuses, true ) || // Skip if order status is not allowed.
            ! $order->get_customer_id() ) { // Skip if order has no customer.
            return;
        }

        $btn_text = $this->order_has_store_credits_applied( $order )
            ? __( 'Update Store Credits', 'advanced-coupons-for-woocommerce-free' )
            : __( 'Apply Store Credits', 'advanced-coupons-for-woocommerce-free' );

        echo wp_kses_post(
            sprintf(
                '<button type="button" class="button acfw-apply-store-credits">%s</button>',
                $btn_text
            )
        );
    }

    /**
     * Check whether an order still carries a store credits discount.
     *
     * True when the order holds the after-tax payment meta or the store credit coupon item, which
     * is what the order screen renders. A cancellation strips both, so a cancelled order reads
     * false even though its ledger entry survives.
     *
     * @since 4.7.7
     * @access public
     *
     * @param \WC_Order $order Order object.
     * @return bool True when a store credits discount is still on the order.
     */
    public function order_has_store_credits_applied( $order ) {
        return (bool) $order->get_meta( Plugin_Constants::STORE_CREDITS_ORDER_PAID, true )
            || $this->_helper_functions->get_order_applied_coupon_item_by_code( \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code(), $order ) instanceof \WC_Order_Item_Coupon;
    }

    /**
     * Apply store credits to order.
     *
     * @since 4.5.4
     * @since 4.7.7 Create the ledger entry before discounting the order, and refuse a store credit
     *              coupon the order already carries.
     * @access public
     *
     * @param \WC_Order $order Order object.
     * @param float     $amount Store credits amount.
     * @throws \Exception When store credits amount is invalid.
     */
    public function apply_store_credits_to_order( $order, $amount ) {

        $balance = \ACFWF()->Store_Credits_Calculate->get_customer_balance( $order->get_customer_id(), true );

        // skip when either of the following is true: amount is zero, order total is zero or when customer has insufficient store credits balance.
        if ( 0 >= $amount || 0 >= $order->get_total() || $amount > $balance ) {
            throw new \Exception( wp_kses_post( __( 'Store credits amount is invalid.', 'advanced-coupons-for-woocommerce-free' ) ) );
        }

        $amount  = min( $amount, $order->get_total( 'edit' ) );
        $sc_data = array(
            'amount'     => $amount, // user currency based amount.
            'raw_amount' => $this->_get_amount_in_site_currency( $amount, $order ), // site currency based amount.
            'cart_total' => $order->get_total( 'edit' ),
            'currency'   => $order->get_currency(),
        );

        // Charge the customer before discounting the order. Without a transaction one of the two
        // has to happen first, and an order discounted for free is the costlier way round: that is
        // the very fault this guards against. The entry is removed again below if the discount
        // cannot then be put on the order.
        //
        // The ledger is kept in the site currency, as every other writer does, so that a net
        // calculation never subtracts one currency from another.
        $store_credit_entry = \ACFWF()->Store_Credits_Checkout->create_discount_store_credit_entry( (float) $sc_data['raw_amount'], $order );

        if ( ! $store_credit_entry->get_id() ) {
            throw new \Exception( wp_kses_post( __( 'Store credits amount is invalid.', 'advanced-coupons-for-woocommerce-free' ) ) );
        }

        // Only a discount this call put on the order may be taken off again in the catch. A coupon
        // the order already carried is refused below without being touched, and stripping it then
        // would take a discount the order was entitled to keep.
        $discount_written = false;

        try {

            if ( 'coupon' === get_option( Plugin_Constants::STORE_CREDIT_APPLY_TYPE, 'coupon' ) ) { // Apply store credit discount as coupon.

                // set the store credit discount data to our override function.
                \ACFWF()->Store_Credits_Checkout->set_coupon_override_data( $sc_data );

                $coupon_code = \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code();

                // The coupon object instance created here will be overrided so its data will now have the amount set
                // in the window prompt. This is done via the `woocommerce_get_shop_coupon_data` filter.
                //
                // For more info, see the function `\ACFWF\Models\Store_Credits\Checkout::override_store_credit_coupon_data`.
                $coupon = new \WC_Coupon( $coupon_code );

                // apply the store credit coupon to the order.
                $applied = $order->apply_coupon( $coupon );

                // WooCommerce refuses a code the order already carries. Give the credit back rather
                // than charge for a discount the order will not show.
                if ( is_wp_error( $applied ) ) {
                    throw new \Exception( wp_kses_post( $applied->get_error_message() ) );
                }

                $discount_written = true;

            } else { // Apply store credit discount after tax.

                // save store credit metadata to order.
                $order->update_meta_data( Plugin_Constants::STORE_CREDITS_ORDER_PAID, $sc_data );
                $order->save_meta_data();

                $discount_written = true;
            }

            $order->save();

            // recalculate order totals so the store credit payment is deducted from the order total.
            $order->calculate_totals( true );

        } catch ( \Throwable $error ) {

            // The discount did not make it onto the order in full, so the charge has to go back.
            // Anything thrown here - a refused coupon, a failing save, a filter on
            // calculate_totals - leaves the customer paying for a discount they cannot see unless
            // the entry is removed.
            //
            // The coupon item and the after-tax meta are both written before the save and the
            // recalculation that can throw, so take whichever of them this call put on the order
            // off again; otherwise the order keeps a discount with no ledger entry behind it.
            if ( $discount_written ) {
                $this->_strip_store_credits_discount_from_order( $order );
            }

            $deleted = $store_credit_entry->delete();

            if ( is_wp_error( $deleted ) ) {
                $this->_log_store_credits_rollback_failure( 'remove the store credit entry', $order, $deleted->get_error_message() );
            }

            throw new \Exception( wp_kses_post( $error->getMessage() ) );
        }

        // update users cached balance value.
        $new_balance = \ACFWF()->Store_Credits_Calculate->get_customer_balance( $order->get_customer_id(), true );

        do_action( 'acfw_after_apply_store_credits_to_order', $amount, $new_balance, $order, $store_credit_entry );
    }

    /**
     * Update the store credits amount applied in the order.
     *
     * @since 4.5.5
     * @since 4.7.7 Target the order's newest store credit entry and check a raised amount against
     *              the customer's balance before the order is touched. The $sc_discount parameter
     *              was dropped: both the cap and the balance check now come from that entry.
     * @access public
     *
     * @param \WC_Order $order  Order object.
     * @param float     $amount Store credits amount.
     * @throws \Exception When store credits amount is invalid.
     */
    public function update_store_credits_in_order( $order, $amount ) {

        // An order can hold more than one discount entry once its credit has been returned and
        // applied again, so pick the newest by ID. Entry dates only go down to the second, which
        // leaves two entries made in the same second in no particular order.
        $store_credit_entry = $this->_queries->query_single_store_credit_entry(
            array(
                'type'       => 'decrease',
                'action'     => 'discount',
                'object_id'  => $order->get_id(),
                'sort_by'    => 'id',
                'sort_order' => 'desc',
            )
        );

        if ( ! $store_credit_entry instanceof Store_Credit_Entry ) {
            throw new \Exception( wp_kses_post( __( "The order doesn't have a valid store credit discount applied.", 'advanced-coupons-for-woocommerce-free' ) ) );
        }

        if ( 0 >= $amount ) {
            $this->_delete_store_credits_from_order( $order, $store_credit_entry );
            return;
        }

        $balance = \ACFWF()->Store_Credits_Calculate->get_customer_balance( $order->get_customer_id(), true );

        // Measure the room to grow against the entry being updated. The order total already has
        // this entry's amount taken off it, and lowering the entry is what hands the customer that
        // much of their balance back, so the entry is what both the cap and the balance check are
        // relative to.
        $entry_amount = (float) $store_credit_entry->get_prop( 'amount', 'edit' );

        // skip when either of the following is true: amount is zero, order total is zero or when customer has insufficient store credits balance.
        //
        // Compare in integer cents. A sum of two floats that cannot be stored exactly lands a
        // fraction of a cent low - 0.01 + 10.2 is 10.209999999999999 - which would refuse the
        // exact credit the customer has available.
        if ( 0 >= wc_add_number_precision( (float) $order->get_total( 'edit' ) + $entry_amount )
            || wc_add_number_precision( $amount ) > wc_add_number_precision( $balance + $entry_amount ) ) {
            throw new \Exception( wp_kses_post( __( 'Store credits amount is invalid.', 'advanced-coupons-for-woocommerce-free' ) ) );
        }

        $amount  = min( $amount, $order->get_total( 'edit' ) + $entry_amount );
        $sc_data = array(
            'amount'     => $amount, // user currency based amount.
            'raw_amount' => $this->_get_amount_in_site_currency( $amount, $order ), // site currency based amount.
            'cart_total' => $order->get_total( 'edit' ),
            'currency'   => $order->get_currency(),
        );

        // The site currency figure that is about to be written has to fit the balance as well. This
        // is the same check the entry's own save() would run, so doing it here lets that save skip
        // it and the balance is computed once per click instead of on every write.
        if ( wc_add_number_precision( (float) $sc_data['raw_amount'] ) > wc_add_number_precision( $balance + $entry_amount ) ) {
            throw new \Exception( wp_kses_post( __( 'Store credits amount is invalid.', 'advanced-coupons-for-woocommerce-free' ) ) );
        }

        // Write the entry before the order. A balance check that fails then leaves the order
        // exactly as it was, instead of showing a discount the customer was never charged for.
        $store_credit_entry->set_prop( 'amount', (float) $sc_data['raw_amount'] );
        $check = $store_credit_entry->save( true );

        if ( is_wp_error( $check ) ) {
            throw new \Exception( wp_kses_post( $check->get_error_message() ) );
        }

        // What the order shows right now, read before anything is written, so a failure halfway
        // through can put it back exactly as it was.
        $old_sc_data = $order->get_meta( Plugin_Constants::STORE_CREDITS_ORDER_PAID, true );
        $old_coupon  = $this->_helper_functions->get_order_applied_coupon_item_by_code( \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code(), $order );

        // Only the coupon type uses this figure, and what it needs is the coupon's *amount*, read
        // from the item that holds it. On a store that enters prices with tax that amount is the
        // discount plus the tax waived along with it - the rule WooCommerce itself applies when it
        // rebuilds the coupon in recalculate_coupons(). The discount on its own would put the
        // coupon back lower than the entry this rollback restores, leaving the order taking off
        // less than the ledger charges, which is the very mismatch this handler exists to prevent.
        $old_amount = $old_coupon instanceof \WC_Order_Item_Coupon
            ? (float) $old_coupon->get_discount() + ( $order->get_prices_include_tax() ? (float) $old_coupon->get_discount_tax() : 0.0 )
            : \ACFWF()->Store_Credits_Checkout->get_store_credits_discount_amount_on_order( $order );

        try {
            $this->_write_store_credits_discount_to_order( $order, $amount, $sc_data );
        } catch ( \Throwable $error ) {

            // Put both halves back. The coupon item and the after-tax meta are written before the
            // recalculation that can throw, so the order may already show the new figure; giving
            // the entry its old amount back on its own would leave the ledger and the order apart
            // again, which is the very fault this handler guards against.
            $this->_rollback_store_credits_discount_update( $order, $store_credit_entry, $entry_amount, $old_amount, $old_sc_data );

            throw new \Exception( wp_kses_post( $error->getMessage() ) );
        }

        // update users cached balance value.
        $new_balance = \ACFWF()->Store_Credits_Calculate->get_customer_balance( $order->get_customer_id(), true );

        do_action( 'acfw_after_update_store_credits_to_order', $amount, $new_balance, $order, $store_credit_entry );
    }

    /**
     * Write a store credits discount amount onto the order.
     *
     * Updates the store credit coupon item (before tax type) or the after-tax payment meta, then
     * recalculates the order. Shared by the update path and by the rollback that puts a failed
     * update back, so both write the discount exactly the same way.
     *
     * @since 4.7.7
     * @access private
     *
     * @param \WC_Order $order   Order object.
     * @param float     $amount  Store credits amount in the order currency.
     * @param array     $sc_data Store credits data of this write: saved as the order meta for the
     *                           after-tax type, and read for its site currency amount by the
     *                           coupon type. May be empty, which the coupon type works around.
     */
    private function _write_store_credits_discount_to_order( $order, $amount, $sc_data ) {

        // check if order has coupon applied called 'store credits'.
        $store_credit_coupon = $this->_helper_functions->get_order_applied_coupon_item_by_code( \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code(), $order );

        // update store credit coupon amount (before tax type).
        if ( $store_credit_coupon instanceof \WC_Order_Item_Coupon ) {

            $this->_write_amount_to_store_credit_coupon_item( $store_credit_coupon, $amount, $order, $sc_data );

            $store_credit_coupon->set_discount( $amount );
            $store_credit_coupon->save();

            $order->add_item( $store_credit_coupon );
            $order->save();

            // recalculate discounts for the order.
            $order->recalculate_coupons();

        } else { // update store credits payment amount (after tax type).

            // save store credit metadata to order.
            $order->update_meta_data( Plugin_Constants::STORE_CREDITS_ORDER_PAID, $sc_data );
            $order->save_meta_data();

            // recalculate order totals so the store credit payment is deducted from the order total.
            $order->calculate_totals( true );
        }
    }

    /**
     * Write a new store credits amount onto the store credit coupon item.
     *
     * The coupon is virtual, so `recalculate_coupons()` cannot load it by ID and rebuilds it from
     * the item's own metadata instead. Three copies of the amount can live there, and whichever
     * ones the item carries have to move together:
     *
     * - `coupon_info`, the succinct JSON WooCommerce has written since 8.7 and reads first;
     * - `coupon_data`, the verbose array it wrote before that, still present on older orders;
     * - the plugin's own copy, which is what removing the discount reads to decide how much credit
     *   to give the customer back.
     *
     * Writing only the one the item does not have leaves the recalculation to put the old amount
     * straight back, and leaving the plugin's copy behind makes a later removal return the amount
     * the order carried before this change.
     *
     * @since 4.7.7
     * @access private
     *
     * @param \WC_Order_Item_Coupon $store_credit_coupon Store credit coupon item.
     * @param float                 $amount              Store credits amount in the order currency.
     * @param \WC_Order             $order               Order object.
     * @param array                 $sc_data             Store credits data of this write, when the caller has it.
     */
    private function _write_amount_to_store_credit_coupon_item( $store_credit_coupon, $amount, $order, $sc_data ) {

        $coupon_info = $store_credit_coupon->get_meta( 'coupon_info', true );

        // The item only carries `coupon_info` because a WooCommerce that writes it put it there.
        // An order kept across a downgrade can still hold it on a WooCommerce that no longer has
        // the methods to read it, so fall through to `coupon_data` instead of fatally erroring.
        if ( is_string( $coupon_info ) && '' !== $coupon_info && method_exists( '\WC_Coupon', 'set_short_info' ) ) {

            // Re-encode through WC_Coupon so the entry keeps the shape WooCommerce expects, rather
            // than this plugin's idea of it.
            $info_coupon = new \WC_Coupon();
            $info_coupon->set_short_info( $coupon_info );
            $info_coupon->set_amount( $amount );

            $store_credit_coupon->update_meta_data( 'coupon_info', $info_coupon->get_short_info() );

        } else {

            $coupon_data = $store_credit_coupon->get_meta( 'coupon_data', true );

            if ( is_array( $coupon_data ) && ! empty( $coupon_data ) ) {
                $coupon_data['amount'] = $amount;
                $store_credit_coupon->update_meta_data( 'coupon_data', $coupon_data );
            }
        }

        $sc_coupon_data = $store_credit_coupon->get_meta( Plugin_Constants::STORE_CREDITS_ORDER_COUPON_META, true );

        if ( is_array( $sc_coupon_data ) && ! empty( $sc_coupon_data ) ) {

            $sc_coupon_data['amount']     = $amount;
            $sc_coupon_data['raw_amount'] = isset( $sc_data['raw_amount'] )
                ? (float) $sc_data['raw_amount']
                : $this->_get_amount_in_site_currency( $amount, $order );

            $store_credit_coupon->update_meta_data( Plugin_Constants::STORE_CREDITS_ORDER_COUPON_META, $sc_coupon_data );
        }
    }

    /**
     * Convert an amount in the order's currency to the site currency.
     *
     * The ledger holds every figure in the site currency, so an order placed in another one has to
     * be converted before its amount is written to an entry or measured against a balance.
     *
     * @since 4.7.7
     * @access private
     *
     * @param float     $amount Amount in the order's currency.
     * @param \WC_Order $order  Order object.
     * @return float Amount in the site currency.
     */
    private function _get_amount_in_site_currency( $amount, $order ) {
        return (float) apply_filters( 'acfw_filter_amount', $amount, true, array( 'user_currency' => $order->get_currency() ) );
    }

    /**
     * Put an order and its store credit entry back to what they held before a failed update.
     *
     * Neither half is allowed to throw: the caller is already rethrowing the original failure, and
     * a rollback that fails is a money mismatch a shop manager has to know about, so it is logged
     * rather than swallowed.
     *
     * @since 4.7.7
     * @access private
     *
     * @param \WC_Order          $order              Order object.
     * @param Store_Credit_Entry $store_credit_entry Store credit entry object.
     * @param float              $entry_amount       Entry amount before the update, in site currency.
     * @param float              $old_amount         Coupon amount the order showed before the update: the
     *                                                discount, plus its tax where prices include tax.
     * @param mixed              $old_sc_data        After-tax payment meta before the update.
     */
    private function _rollback_store_credits_discount_update( $order, $store_credit_entry, $entry_amount, $old_amount, $old_sc_data ) {

        $store_credit_entry->set_prop( 'amount', $entry_amount );
        $check = $store_credit_entry->save( true );

        if ( is_wp_error( $check ) ) {
            $this->_log_store_credits_rollback_failure( 'restore the store credit entry amount', $order, $check->get_error_message() );
        }

        // The after-tax branch needs the meta it had; without it there is nothing to write back
        // and overwriting the order with an empty array would lose the discount altogether.
        $has_coupon = $this->_helper_functions->get_order_applied_coupon_item_by_code( \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code(), $order ) instanceof \WC_Order_Item_Coupon;

        if ( ! $has_coupon && ( ! is_array( $old_sc_data ) || empty( $old_sc_data ) ) ) {
            $this->_log_store_credits_rollback_failure( 'restore the order discount', $order, 'the order has no store credits payment meta to restore' );
            return;
        }

        try {
            // The coupon type has no payment meta to hand back, so give the writer the entry
            // amount outright: it is the site currency figure this rollback has just restored, and
            // deriving it from the order's discount instead would drop the discount's tax on a
            // store whose prices include tax, leaving the coupon holding less than the ledger.
            $this->_write_store_credits_discount_to_order(
                $order,
                $old_amount,
                is_array( $old_sc_data ) && ! empty( $old_sc_data )
                    ? $old_sc_data
                    : array( 'raw_amount' => $entry_amount )
            );
        } catch ( \Throwable $rollback_error ) {
            $this->_log_store_credits_rollback_failure( 'restore the order discount', $order, $rollback_error->getMessage() );
        }
    }

    /**
     * Take the store credits discount off an order without touching the ledger.
     *
     * Used to undo a half-written discount when the apply failed. Unlike a cancellation this
     * writes no `cancelled_order` entry: the charge it would return is being deleted outright.
     *
     * @since 4.7.7
     * @access private
     *
     * @param \WC_Order $order Order object.
     */
    private function _strip_store_credits_discount_from_order( $order ) {

        try {
            $coupon_code = \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code();

            if ( $this->_helper_functions->get_order_applied_coupon_item_by_code( $coupon_code, $order ) instanceof \WC_Order_Item_Coupon ) {
                $order->remove_coupon( $coupon_code );
            }

            if ( $order->get_meta( Plugin_Constants::STORE_CREDITS_ORDER_PAID, true ) ) {
                $order->delete_meta_data( Plugin_Constants::STORE_CREDITS_ORDER_PAID );
                $order->save_meta_data();
            }

            $order->save();
            $order->calculate_totals( true );

        } catch ( \Throwable $rollback_error ) {
            $this->_log_store_credits_rollback_failure( 'remove the half-applied order discount', $order, $rollback_error->getMessage() );
        }
    }

    /**
     * Log a store credits rollback that did not go through.
     *
     * @since 4.7.7
     * @access private
     *
     * @param string    $action  What the rollback was trying to do.
     * @param \WC_Order $order   Order object.
     * @param string    $message Reason the rollback failed.
     */
    private function _log_store_credits_rollback_failure( $action, $order, $message ) {
        wc_get_logger()->error(
            sprintf(
                'Store credits: failed to %1$s on order #%2$d. %3$s',
                $action,
                $order->get_id(),
                $message
            ),
            array( 'source' => 'acfwf-store-credits' )
        );
    }

    /**
     * Delete the store credits amount applied in the order.
     *
     * @since 4.5.5
     * @access private
     *
     * @param \WC_Order          $order              Order object.
     * @param Store_Credit_Entry $store_credit_entry Store credit entry object.
     */
    private function _delete_store_credits_from_order( $order, $store_credit_entry ) {

        // check if order has coupon applied called 'store credits'.
        $store_credit_coupon = $this->_helper_functions->get_order_applied_coupon_item_by_code( \ACFWF()->Store_Credits_Checkout->get_store_credit_coupon_code(), $order );

        // delete store credit discount data from order.
        if ( $store_credit_coupon instanceof \WC_Order_Item_Coupon ) {
            $order->remove_item( $store_credit_coupon->get_id() );
            $order->recalculate_coupons();
            $store_credit_coupon->delete();
        } else {
            $order->delete_meta_data( Plugin_Constants::STORE_CREDITS_ORDER_PAID );
        }

        $order->calculate_totals( true );

        // delete store credit entry.
        $store_credit_entry->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX Functions
    |--------------------------------------------------------------------------
     */

    /**
     * AJAX Apply store credits to order.
     *
     * @since 4.5.4
     * @since 4.7.7 Clear and re-charge a discount whose credit was refunded, instead of rewriting
     *              its ledger entry, and refuse an invalid amount before the order is touched.
     * @access public
     *
     * @throws \Exception When invalid request.
     */
    public function ajax_apply_store_credits_to_order() {
        check_ajax_referer( 'order-item', 'security' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( -1 );
        }

        $response = array();

        try {

            if ( ! isset( $_POST['order_id'] ) || ! isset( $_POST['amount'] ) ) {
                throw new \Exception( __( 'Invalid request.', 'advanced-coupons-for-woocommerce-free' ) );
            }

            $order_id = absint( $_POST['order_id'] );
            $amount   = (float) wc_format_decimal( wp_unslash( $_POST['amount'] ) );
            $order    = \wc_get_order( $order_id );

            if ( ! $order instanceof \WC_Order ) {
                throw new \Exception( __( 'Invalid request.', 'advanced-coupons-for-woocommerce-free' ) );
            }

            $calculate = \ACFWF()->Store_Credits_Calculate;
            $applied   = $this->order_has_store_credits_applied( $order );
            $refunded  = $calculate->get_total_refunded_store_credits_discount_for_order( $order );

            // Three ways an order can arrive here, and only one of them may rewrite the existing
            // ledger entry.
            //
            // A refund returns the credit but leaves the discount on the order, so the entry and
            // the order no longer describe the same money. Rewriting the entry then moves the
            // difference a second time: lowering it to the prompt's prefilled net hands the
            // refunded credit straight back. Clearing it out and charging afresh is the only way
            // to keep the ledger honest - the removal returns whatever the order still holds, and
            // the new amount is charged on its own merits. It also frees a coupon-mode order whose
            // credit was fully refunded, which otherwise kept its coupon and could be neither
            // updated nor cleared.
            //
            // A cancellation is the reverse: it strips the meta and the coupon but leaves the
            // ledger entry behind, so an order with no discount on it must charge afresh rather
            // than rewrite that dead entry for free.
            //
            // What is left - a discount on the order with nothing given back - is a true update.
            // Its first half is also what the button's label uses, so the label and this branch
            // agree.
            if ( $applied && 0 < $refunded ) {

                // Refuse an invalid amount before the removal, not after. The removal strips the
                // discount, saves the order and writes the `cancelled_order` entry, so a refusal
                // raised afterwards would report an error to the shop manager while the order has
                // already lost its discount and asks the customer for the full amount again.
                //
                // Both of the limits the re-charge applies are measured here against the room the
                // removal is about to free: the balance grows by the credit the removal gives
                // back, and the order total grows by the discount the order still shows.
                //
                // The balance has to be measured against both figures the re-charge will use. It
                // refuses on the order-currency amount, and the ledger entry it then writes
                // refuses on the site-currency amount; on a multi-currency shop those are not the
                // same number, so checking only one of them lets the other refuse after the
                // removal has already stripped the discount.
                if ( 0 < $amount ) {

                    $room  = $calculate->get_customer_balance( $order->get_customer_id(), true )
                        + \ACFWF()->Store_Credits_Checkout->get_store_credits_returned_on_removal( $order );
                    $total = \ACFWF()->Store_Credits_Checkout->get_order_total_without_store_credits_discount( $order );
                    $raw   = $this->_get_amount_in_site_currency( min( $amount, $total ), $order );

                    if ( wc_add_number_precision( $amount ) > wc_add_number_precision( $room )
                        || wc_add_number_precision( $raw ) > wc_add_number_precision( $room )
                        || 0 >= wc_add_number_precision( $total ) ) {
                        throw new \Exception( wp_kses_post( __( 'Store credits amount is invalid.', 'advanced-coupons-for-woocommerce-free' ) ) );
                    }
                }

                \ACFWF()->Store_Credits_Checkout->remove_store_credits_discount_from_order(
                    $order,
                    __( 'Store credits updated by an admin.', 'advanced-coupons-for-woocommerce-free' )
                );

                if ( 0 < $amount ) {
                    // Re-read the order: the removal saved it and recalculated its totals, and the
                    // apply below measures the new amount against the order total.
                    $order = \wc_get_order( $order_id );

                    if ( ! $order instanceof \WC_Order ) {
                        throw new \Exception( __( 'Invalid request.', 'advanced-coupons-for-woocommerce-free' ) );
                    }

                    $this->apply_store_credits_to_order( $order, $amount );
                }
            } elseif ( $applied && 0 < $calculate->get_net_store_credits_discount_for_order( $order ) ) {
                $this->update_store_credits_in_order( $order, $amount );
            } else {
                $this->apply_store_credits_to_order( $order, $amount );
            }

            ob_start();
            include WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-items.php';
            $response['html'] = ob_get_clean();

        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'error' => $e->getMessage() ) );
        }

        wp_send_json_success( $response );
    }
}
