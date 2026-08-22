<?php

namespace FSVendor\WPDesk\Tracker;

use FSVendor\Psr\Log\LoggerInterface;
final class Tracker
{
    private string $plugin_file;
    private string $plugin_basename;
    private string $plugin_slug;
    private string $shop_url;
    private string $plugin_name;
    private ?LoggerInterface $logger;
    private ?\WPDesk_Tracker_Interface $tracker = null;
    private ?string $bucket = null;
    private bool $tracker_started = \false;
    /** @var array<string, array<string, true>> */
    private static array $handled_consent_changes = [];
    /** @var array<string, true> */
    private static array $handled_scheduled_sends = [];
    /**
     * Registers a plugin with the tracker without constructing its payload providers.
     *
     * @param string $plugin_file Absolute path to the main plugin file.
     * @param string $plugin_slug Plugin slug.
     * @param string $shop_url Plugin shop URL.
     * @param string $plugin_name Plugin name.
     * @param LoggerInterface|null $logger Logger for the tracker sender.
     * @return void
     */
    public static function register_plugin($plugin_file, $plugin_slug, $shop_url, $plugin_name, ?LoggerInterface $logger = null)
    {
        $tracker = new self($plugin_file, plugin_basename($plugin_file), $plugin_slug, $shop_url, $plugin_name, $logger);
        $tracker->register_hooks();
    }
    private function __construct($plugin_file, $plugin_basename, $plugin_slug, $shop_url, $plugin_name, ?LoggerInterface $logger)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = $plugin_basename;
        $this->plugin_slug = $plugin_slug;
        $this->shop_url = $shop_url;
        $this->plugin_name = $plugin_name;
        $this->logger = $logger;
    }
    private function register_hooks()
    {
        $opt_in_opt_out = new OptInOptOut($this->plugin_basename, $this->plugin_slug, $this->shop_url, $this->plugin_name);
        $opt_in_opt_out->create_objects();
        $opt_in_opt_out->hooks();
        add_action('admin_init', [\FSVendor\WPDesk_Tracker::class, 'schedule']);
        add_action('wpdesk_tracker_send_event', [$this, 'initialize_tracker']);
        add_action('wpdesk_tracker_send_event', [$this, 'send_scheduled_tracking_data'], \PHP_INT_MAX);
        add_action('update_option_wpdesk_helper_options', [$this, 'initialize_tracker'], 10, 0);
        add_action('update_option_wpdesk_helper_options', [$this, 'handle_consent_change'], \PHP_INT_MAX, 3);
        add_filter('wpdesk_tracker_instance', [$this, 'provide_legacy_tracker']);
    }
    /**
     * Provides an instance to integrations using the deprecated WPDesk_Tracker_Factory.
     *
     * @param mixed $tracker
     * @return mixed
     */
    public function provide_legacy_tracker($tracker)
    {
        if ($tracker instanceof \WPDesk_Tracker_Interface) {
            return $tracker;
        }
        if (defined('WP_DEBUG') && \WP_DEBUG) {
            _doing_it_wrong(__METHOD__, 'Use a bucket-specific tracker integration instead of wpdesk_tracker_instance.', '3.12.0');
        }
        return $this->get_tracker();
    }
    /** @return void */
    public function initialize_tracker()
    {
        $this->get_tracker();
    }
    /** @return void */
    public function send_scheduled_tracking_data()
    {
        $tracker = $this->get_tracker();
        $bucket = $this->get_bucket();
        if (isset(self::$handled_scheduled_sends[$bucket])) {
            return;
        }
        self::$handled_scheduled_sends[$bucket] = \true;
        if ($tracker instanceof \FSVendor\WPDesk_Tracker) {
            $tracker->send_tracking_data();
        }
    }
    /**
     * @param mixed $old_value
     * @param mixed $value
     * @param string $option
     *
     * @return void
     */
    public function handle_consent_change($old_value, $value, $option)
    {
        $tracker = $this->get_tracker();
        $bucket = $this->get_bucket();
        $change_key = md5(serialize([$old_value, $value, $option]));
        if (isset(self::$handled_consent_changes[$bucket][$change_key])) {
            return;
        }
        self::$handled_consent_changes[$bucket][$change_key] = \true;
        if ($tracker instanceof \FSVendor\WPDesk_Tracker) {
            $tracker->update_option_wpdesk_helper_options($old_value, $value, $option);
        }
    }
    /** @return \WPDesk_Tracker_Interface|null */
    private function get_tracker()
    {
        if ($this->tracker instanceof \WPDesk_Tracker_Interface) {
            return $this->tracker;
        }
        $bucket = $this->get_bucket();
        $tracker = apply_filters('wpdesk/tracker/bucket/' . $bucket, null);
        if (!is_object($tracker)) {
            $factory = new \FSVendor\WPDesk_Tracker_Factory_Prefixed($this->logger);
            $tracker = $factory->create_tracker(basename($this->plugin_basename), $bucket, \false);
            $tracker->init_payload_hooks();
            add_filter('wpdesk/tracker/bucket/' . $bucket, static function ($instance) use ($tracker) {
                return is_object($instance) ? $instance : $tracker;
            });
        }
        $this->tracker = $tracker;
        if ('wpdesk' === $bucket) {
            $this->tracker = apply_filters('wpdesk_tracker_instance', $this->tracker);
        }
        $this->notify_tracker_started();
        return $this->tracker;
    }
    /** @return void */
    private function notify_tracker_started()
    {
        if ($this->tracker_started) {
            return;
        }
        $this->tracker_started = \true;
        do_action('wpdesk/tracker/plugin_started', $this->tracker, $this->plugin_basename, $this->plugin_slug, $this->get_bucket());
    }
    /** @return string */
    private function get_bucket()
    {
        if (null !== $this->bucket) {
            return $this->bucket;
        }
        $plugin_data = get_file_data($this->plugin_file, ['Author' => 'Author']);
        $bucket = sanitize_key($plugin_data['Author'] ?? '');
        $bucket = apply_filters('wpdesk/tracker/bucket/' . $this->plugin_slug, $bucket);
        $this->bucket = sanitize_key($bucket) ?: 'wpdesk';
        return $this->bucket;
    }
}
