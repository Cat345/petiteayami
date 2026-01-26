<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

class RemoveStyles
{
    /**
     * @param array<string, mixed> $styles
     *
     * @return array<string, mixed>
     */
    public static function handle(array $styles): array
    {
        unset(
            $styles['woocommerce-general'],
            $styles['woocommerce-layout'],
            $styles['woocommerce-smallscreen'],
        );

        return $styles;
    }
}
