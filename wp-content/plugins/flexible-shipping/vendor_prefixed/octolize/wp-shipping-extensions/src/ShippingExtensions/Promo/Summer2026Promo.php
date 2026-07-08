<?php

namespace FSVendor\Octolize\ShippingExtensions\Promo;

use FSVendor\Octolize\ShippingExtensions\DateRange;
use FSVendor\Octolize\ShippingExtensions\TimedUpdate;
use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
class Summer2026Promo implements Hookable
{
    private const PROMO_CODE = 'SUMMER20';
    private const PROMO_START_DATE = '1970-01-01';
    private const PROMO_END_DATE = '2026-07-13';
    /**
     * @return void
     */
    public function hooks(): void
    {
        add_filter('octolize/shipping-extensions/header-promo', [$this, 'add_promo']);
    }
    /**
     * @param array $promo
     *
     * @return array
     */
    public function add_promo($promo): array
    {
        if ($this->is_active_promo()) {
            $promo[self::PROMO_CODE] = $this->get_promo_content();
        }
        return $promo;
    }
    private function is_active_promo(): bool
    {
        return self::get_timed_update()->is_active();
    }
    public static function get_timed_update(): TimedUpdate
    {
        return new TimedUpdate(self::PROMO_CODE, new DateRange(self::PROMO_START_DATE, self::PROMO_END_DATE));
    }
    private function get_promo_content(): string
    {
        // Translators: HTML tags
        return sprintf(__('%1$s%2$s%3$s☀️ Summer Sale: 20%% off all plugins - for new and returning customers!%4$s%5$sOne discount per account. Don\'t hesitate - offer ends July 13th.%6$s%7$sGet your shipping rules right once, enjoy the whole summer with peace of mind.%8$s%9$s%10$sSUMMER20%11$s%12$sCopy code%13$s%14$s'), '<span class="oct-promo-content">', '<span class="oct-promo-text">', '<span class="oct-promo-line"><strong>', '</strong></span>', '<span class="oct-promo-line">', '</span>', '<span class="oct-promo-line">', '</span></span>', '<span class="oct-code" tabindex="0" data-tooltip="Use this coupon during checkout at octolize.com.">', '<span class="oct-code-value">', '</span>', '<span class="oct-copy-to-clipboard" data-value="SUMMER20">', '</span>', '</span>');
    }
}
