<?php

namespace FSVendor;

/**
 * WP Desk Tracker
 *
 * @class        WPDESK_Tracker
 * @version        1.3.2
 * @package        WPDESK/Helper
 * @category    Class
 * @author        WP Desk
 */
if (!\defined('ABSPATH')) {
    exit;
}
if (!\class_exists('FSVendor\WPDesk_Tracker_Data_Provider_Orders')) {
    /**
     * Class WPDesk_Tracker_Data_Provider_Orders
     */
    class WPDesk_Tracker_Data_Provider_Orders implements \WPDesk_Tracker_Data_Provider
    {
        /**
         * Get order counts based on order status.
         *
         * @return array Data provided to tracker.
         */
        public function get_data()
        {
            $order_count = [];
            $order_count_data = [];
            $uses_hpos = \class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            if ($uses_hpos) {
                global $wpdb;
                $order_count_data = $wpdb->get_results("SELECT status, COUNT(id) AS orders FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' GROUP BY status", \OBJECT_K);
            } else {
                $order_count_data = \wp_count_posts('shop_order');
            }
            foreach (\wc_get_order_statuses() as $status_slug => $status_name) {
                if ($uses_hpos && \is_array($order_count_data) && isset($order_count_data[$status_slug]) && \is_object($order_count_data[$status_slug])) {
                    $hpos_order_data = \get_object_vars($order_count_data[$status_slug]);
                    $order_count[$status_slug] = $hpos_order_data['orders'] ?? 0;
                } elseif (\is_object($order_count_data)) {
                    $legacy_order_data = \get_object_vars($order_count_data);
                    $order_count[$status_slug] = $legacy_order_data[$status_slug] ?? 0;
                } else {
                    $order_count[$status_slug] = 0;
                }
            }
            return ['orders' => $order_count];
        }
    }
}
