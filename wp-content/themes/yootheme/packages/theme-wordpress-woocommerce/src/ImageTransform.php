<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce;

use YOOtheme\Url;
use YOOtheme\Html\HtmlElement;

class ImageTransform
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        return $next(
            static::isProductImage($element->attr('src'))
                ? $element->withAttr('data-woo-product-image', true)
                : $element,
            $params,
        );
    }

    protected static function isProductImage(?string $src): bool
    {
        global $product;

        return !empty($src) &&
            is_product() &&
            $product instanceof \WC_Product &&
            $product->is_type('variable') &&
            static::getProductImage() === parse_url(Url::to($src), PHP_URL_PATH);
    }

    protected static function getProductImage(): string
    {
        static $url;

        $url ??= parse_url(set_url_scheme(get_the_post_thumbnail_url(), 'relative'), PHP_URL_PATH);

        return $url ?: '';
    }
}
