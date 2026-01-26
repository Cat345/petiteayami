<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use WooCommerce;
use WPML\Convert\Ids;
use YOOtheme\Config;

class LoadCustomizer
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        if (!class_exists(WooCommerce::class, false)) {
            return;
        }

        $cartId = wc_get_page_id('cart');

        if (class_exists(Ids::class, false) && class_exists('SitePress', false)) {
            $cartId = Ids::convert($cartId, 'page', true);
        }

        $this->config->set('woocommerce.cartPage', (int) $cartId);
        $this->config->addFile('customizer', __DIR__ . '/../../config/customizer.php');
    }
}
