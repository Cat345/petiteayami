<?php

declare (strict_types=1);
namespace FSVendor\Octolize\ShippingExtensions;

use FSVendor\Octolize\ShippingExtensions\Tracker\ViewPageTracker;
use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
/**
 * Handles unread badge and view tracking for time-limited updates.
 */
class TimedUpdates implements Hookable
{
    /**
     * @var TimedUpdate[]
     */
    private $updates;
    /**
     * @param TimedUpdate[] $updates
     */
    public function __construct(array $updates)
    {
        $this->updates = $updates;
    }
    public function hooks(): void
    {
        add_filter('octolize/shipping-extensions/should-add-badge', [$this, 'should_add_badge'], 10, 2);
        add_action('octolize/shipping-extensions/view-tracking', [$this, 'view_tracking']);
    }
    /**
     * @param bool            $should_add_badge
     * @param ViewPageTracker $view_page_tracker
     *
     * @return bool
     */
    public function should_add_badge($should_add_badge, $view_page_tracker): bool
    {
        foreach ($this->updates as $update) {
            if ($update->is_active() && !$update->is_read($view_page_tracker->get_views($update->get_code()))) {
                return \true;
            }
        }
        return $should_add_badge;
    }
    public function view_tracking(ViewPageTracker $tracker): void
    {
        foreach ($this->updates as $update) {
            if ($update->is_active()) {
                $tracker->update_views($update->get_code());
            }
        }
    }
}
