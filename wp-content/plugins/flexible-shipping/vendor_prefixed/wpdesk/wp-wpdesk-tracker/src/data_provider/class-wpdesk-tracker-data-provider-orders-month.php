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
if (!\class_exists('FSVendor\WPDesk_Tracker_Data_Provider_Orders_Month')) {
    /**
     * Class WPDesk_Tracker_Data_Provider_Orders_Month
     */
    class WPDesk_Tracker_Data_Provider_Orders_Month implements \WPDesk_Tracker_Data_Provider
    {
        /**
         * Info about orders per month.
         *
         * @return array Data provided to tracker.
         */
        public function get_data()
        {
            global $wpdb;
            if (\class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $query = $wpdb->get_results("\nSELECT min(date_created_gmt) min, max(date_created_gmt) max, TIMESTAMPDIFF(MONTH, min(date_created_gmt), max(date_created_gmt) )+1 months\nFROM {$wpdb->prefix}wc_orders\nWHERE type = 'shop_order'\nAND status = 'wc-completed'\n");
            } else {
                $query = $wpdb->get_results("\nSELECT min(post_date_gmt) min, max(post_date_gmt) max, TIMESTAMPDIFF(MONTH, min(post_date_gmt), max(post_date_gmt) )+1 months\nFROM {$wpdb->posts} p\nWHERE p.post_type = 'shop_order'\nAND p.post_status = 'wc-completed'\n");
            }
            $data = ['orders_per_month' => []];
            if ($query) {
                foreach ($query as $row) {
                    if (null === $row->min || null === $row->max) {
                        continue;
                    }
                    $data['orders_per_month']['first'] = \mysql2date('Y-m-d\TH:i:s\Z', $row->min, \false);
                    $data['orders_per_month']['last'] = \mysql2date('Y-m-d\TH:i:s\Z', $row->max, \false);
                    $data['orders_per_month']['months'] = $row->months;
                }
            }
            return $data;
        }
    }
}
