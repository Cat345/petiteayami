<?php

namespace FSVendor;

if (!\defined('ABSPATH')) {
    exit;
}
if (!\class_exists('FSVendor\WPDesk_Tracker_Sender_Resolver')) {
    /**
     * Resolves the tracker sender when the payload is sent.
     */
    class WPDesk_Tracker_Sender_Resolver implements \WPDesk_Tracker_Sender
    {
        /**
         * @var string
         */
        private $basename;
        /**
         * @var WPDesk_Tracker_Sender|null
         */
        private $sender;
        /**
         * @param string $basename Plugin basename.
         */
        public function __construct($basename)
        {
            $this->basename = $basename;
        }
        /**
         * Sends payload to resolved sender.
         *
         * @param array<mixed> $payload Payload to send.
         *
         * @return array<mixed> If succeeded. Array containing 'headers', 'body', 'response', 'cookies', 'filename'.
         */
        public function send_payload(array $payload)
        {
            return $this->get_sender()->send_payload($payload);
        }
        private function get_sender(): \WPDesk_Tracker_Sender
        {
            if ($this->sender instanceof \WPDesk_Tracker_Sender) {
                return $this->sender;
            }
            $default_sender = new WPDesk_Tracker_Sender_Wordpress_To_WPDesk();
            $sender = \apply_filters('wpdesk/tracker/sender/' . $this->basename, $default_sender);
            if ($sender === $default_sender && $this->get_slug() !== $this->basename) {
                $sender = \apply_filters('wpdesk/tracker/sender/' . $this->get_slug(), $default_sender);
            }
            $this->sender = $sender instanceof \WPDesk_Tracker_Sender ? $sender : new WPDesk_Tracker_Sender_Wordpress_To_WPDesk();
            return $this->sender;
        }
        private function get_slug(): string
        {
            return \pathinfo($this->basename, \PATHINFO_FILENAME);
        }
    }
}
