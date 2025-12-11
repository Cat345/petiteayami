<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

class RemoveStyles
{
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
