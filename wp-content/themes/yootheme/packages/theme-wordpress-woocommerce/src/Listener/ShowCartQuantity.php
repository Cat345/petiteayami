<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Config;
use function WC;

class ShowCartQuantity
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Add fragment with cart item count.
     *
     * @param array<string> $fragments
     *
     * @return array<string>
     */
    public static function addToCartFragments(array $fragments): array
    {
        return $fragments + static::getCartQuantity();
    }

    /**
     * Load the cart fragments script to update the cart item count in the menu.
     *
     * @return void
     */
    public static function loadCartFragmentsScript(): void
    {
        wp_enqueue_script('wc-cart-fragments');
    }

    /**
     * Filters the navigation menu items being returned.
     *
     * @param list<object> $items
     *
     * @return list<object>
     */
    public function navMenuObjects(array $items): array
    {
        // @phpstan-ignore booleanNot.alwaysFalse
        if (!WC()->cart) {
            return $items;
        }

        foreach ($items as $item) {
            if ($item->object === 'page' && ((int) $item->object_id) === wc_get_page_id('cart')) {
                $key = "~theme.menu.items.{$item->ID}";
                $style = $this->config->get("{$key}.woocommerce_cart_quantity") ?: 'parenthesis';
                $suffix = static::getCartQuantity()["[data-cart-{$style}]"];

                $this->config->set("{$key}.title-suffix", $suffix);
            }
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    protected static function getCartQuantity(): array
    {
        $quantity = WC()->cart->get_cart_contents_count();

        $types = [
            ['text', '%d'],
            ['parenthesis', '(%d)'],
            ['superscript', '<sup>%d</sup>'],
            ['badge', '%d', ' class="uk-badge"'],
        ];

        foreach ($types as $args) {
            $type = "data-cart-{$args[0]}";
            $args = $quantity ? [sprintf($args[1], $quantity), $args[2] ?? ''] : ['', ''];
            $fragments["[{$type}]"] = sprintf('<span%3$s %s>%s</span>', $type, ...$args);
        }

        return $fragments;
    }
}
