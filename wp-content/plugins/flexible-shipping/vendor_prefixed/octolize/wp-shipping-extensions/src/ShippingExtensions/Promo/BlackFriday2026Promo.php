<?php

declare (strict_types=1);
namespace FSVendor\Octolize\ShippingExtensions\Promo;

use FSVendor\Octolize\ShippingExtensions\DateRange;
use FSVendor\Octolize\ShippingExtensions\TimedUpdate;
use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
/**
 * Black Friday campaign in Shipping Extensions.
 */
final class BlackFriday2026Promo implements Hookable
{
    private const PROMO_CODE = 'BLACKFRIDAY30WP';
    private const START_DATE = '2026-11-02 00:00:00';
    private const END_DATE = '2026-12-01 00:00:00';
    public function hooks(): void
    {
        add_filter('octolize/shipping-extensions/header-promo', [$this, 'add_promo']);
    }
    /**
     * @internal
     */
    public function add_promo(array $promo): array
    {
        if (!self::get_timed_update()->is_active()) {
            return $promo;
        }
        $promo[self::PROMO_CODE] = [
            'type' => 'black-friday-2026',
            'code' => self::PROMO_CODE,
            // Use the shop's clock for expiry even when the browser has another timezone.
            'remaining_ms' => (strtotime(self::END_DATE) - current_time('timestamp')) * 1000,
            'title' => __('Black Friday: ', 'flexible-shipping'),
            'title_highlight' => __('30% off plugins & bundles', 'flexible-shipping'),
            'description' => __('Get your shipping ready for the holiday rush · Until Nov 30', 'flexible-shipping'),
            'terms' => __('Applies to the first year of new licences. Renewals at regular price.', 'flexible-shipping'),
            'copy_label' => __('Copy code', 'flexible-shipping'),
            'copied_label' => __('Copied ✓', 'flexible-shipping'),
            'copy_error' => __('Could not copy. Select and copy the code manually.', 'flexible-shipping'),
        ];
        return $promo;
    }
    public static function get_timed_update(): TimedUpdate
    {
        return new TimedUpdate(self::PROMO_CODE, new DateRange(self::START_DATE, self::END_DATE));
    }
}
