<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use WooCommerce;

class LoadBreadcrumbs
{
    public static function handle(array $items): array
    {
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
            return $items;
        }

        $breadcrumbs = new class extends \WC_Breadcrumb {
            // Remove paged trail
            protected function paged_trail() {}
        };
        $breadcrumbs->generate();

        WooCommerce::instance()->structured_data->generate_breadcrumblist_data($breadcrumbs);

        return array_map(
            fn($item) => ['name' => $item[0], 'link' => $item[1]],
            $breadcrumbs->get_breadcrumb(),
        );
    }
}
