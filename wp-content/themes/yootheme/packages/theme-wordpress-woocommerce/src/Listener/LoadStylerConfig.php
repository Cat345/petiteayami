<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Config;
use YOOtheme\File;
use YOOtheme\Theme\Styler\StylerConfig;

class LoadStylerConfig
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param StylerConfig $config
     */
    public function handle($config): void
    {
        $file = File::find("~theme/css/theme{.{$this->config->get('theme.id')},}.css");
        $compiled = str_contains(File::getContents($file), '.woocommerce');
        $isWoocommerce = class_exists('WooCommerce', false);

        // check if theme css needs to be updated
        if ($isWoocommerce xor $compiled) {
            $config['update'] = true;
        }

        if (!$isWoocommerce) {
            return;
        }

        if (($key = array_search('@woocommerce-*', $config['ignore'] ?? [])) !== false) {
            unset($config['ignore'][$key]);
        }

        $config->unshift(
            'types',
            [
                'type' => 'checkbox',
                'vars' => ['*woocommerce-myaccount-page-grid-divider'],
                'attrs' => ['true-value' => 'true'],
            ],
            [
                'type' => 'checkbox',
                'vars' => [
                    '*woocommerce-product-page-add-to-cart-large',
                    '*woocommerce-product-page-add-to-cart-expand',
                    '*woocommerce-product-page-add-to-cart-reset-button',
                ],
                'attrs' => ['false-value' => 'false'],
            ],
            [
                'type' => 'select-custom',
                'vars' => ['*woocommerce-cart-page-layout', '*woocommerce-order-page-layout'],
                'attrs' => ['class' => 'yo-style-form'],
                'options' => ['Classic' => 'classic', '2 Columns' => 'columns'],
            ],
        );
    }
}
