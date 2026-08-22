<?php

namespace FSVendor;

if (!\defined('ABSPATH')) {
    exit;
}
if (!\class_exists('FSVendor\WPDesk_Tracker_Sender_Exception_WpError')) {
    class WPDesk_Tracker_Sender_Exception_WpError extends \RuntimeException
    {
        public function __construct($message, \WP_Error $wp_error)
        {
            $message = $message . ' WP_Error: ' . $wp_error->get_error_message();
            $code = $wp_error->get_error_code();
            parent::__construct($message, \is_int($code) ? $code : 0);
        }
    }
}
