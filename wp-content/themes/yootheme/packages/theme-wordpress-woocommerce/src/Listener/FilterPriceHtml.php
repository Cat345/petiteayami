<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Config;

class FilterPriceHtml
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Show sale price after regular price.
     */
    public function sale(string $price, $regular_price, $sale_price): string
    {
        if (!$this->config->get('~theme.woocommerce.price.sale_after_regular')) {
            return $price;
        }

        return sprintf(
            '<ins>%s</ins> <del aria-hidden="true">%s</del>',
            is_numeric($sale_price) ? wc_price($sale_price) : $sale_price,
            is_numeric($regular_price) ? wc_price($regular_price) : $regular_price,
        );
    }

    /**
     * Show the lowest price for grouped product.
     *
     * @param \WC_Product_Variable $product
     */
    public function grouped(string $price, $product, $childPrices): string
    {
        if (!$this->config->get('~theme.woocommerce.price.from')) {
            return $price;
        }

        $minPrice = min($childPrices);

        return $this->fromPrice(wc_price($minPrice), $minPrice, max($childPrices));
    }

    /**
     * Show the lowest price for variable product.
     *
     * @param \WC_Product_Variable $product
     */
    public function variable(string $price, $product): string
    {
        if (!$this->config->get('~theme.woocommerce.price.from')) {
            return $price;
        }

        $minPriceSale = $product->get_variation_sale_price('min', true);
        $minPriceRegular = $product->get_variation_regular_price('min', true);

        $price =
            $minPriceSale !== $minPriceRegular
                ? wc_format_sale_price($minPriceRegular, $minPriceSale)
                : wc_price((float) $minPriceRegular);

        return $this->fromPrice(
            $price,
            $product->get_variation_price('min', true),
            $product->get_variation_price('max', true),
        );
    }

    protected function fromPrice(string $price, $minPrice, $maxPrice): string
    {
        return $minPrice !== $maxPrice
            ? '<span class="tm-price-from">' .
                    _x('from', 'min_price', 'yootheme') .
                    '</span> ' .
                    $price
            : $price;
    }
}
