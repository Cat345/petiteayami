<?php

namespace FSVendor\WPDesk\Plugin\Flow\Initialization\Simple;

use FSVendor\WPDesk\Tracker\Tracker;
/**
 * Trait helps with tracker initialization
 *
 * @package WPDesk\Plugin\Flow\Initialization\Simple\
 */
trait TrackerInstanceAsFilterTrait
{
    /**
     * Register tracker hooks without constructing its payload providers.
     *
     * @return void
     */
    private function prepare_tracker_action()
    {
        if (!apply_filters('wpdesk_can_start_tracker', \true, $this->plugin_info)) {
            return;
        }
        $shops = $this->plugin_info->get_plugin_shops();
        $shop_url = $shops[get_locale()] ?? $shops['default'] ?? 'https://wpdesk.net';
        $plugin_file = rtrim($this->plugin_info->get_plugin_dir(), '/\\') . '/' . basename($this->plugin_info->get_plugin_file_name());
        add_action('wpdesk/tracker/plugin_started', function ($tracker, $plugin_basename, $plugin_slug) {
            if ($plugin_basename === $this->plugin_info->get_plugin_file_name() && $plugin_slug === $this->plugin_info->get_plugin_slug()) {
                do_action('wpdesk_tracker_started', $tracker, $this->plugin_info);
            }
        }, 10, 3);
        Tracker::register_plugin($plugin_file, $this->plugin_info->get_plugin_slug(), $shop_url, $this->plugin_info->get_plugin_name());
    }
}
