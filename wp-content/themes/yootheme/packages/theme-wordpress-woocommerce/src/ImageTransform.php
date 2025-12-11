<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce;

use YOOtheme\Url;
use YOOtheme\View\HtmlElement;

class ImageTransform
{
    public static function handle(HtmlElement $element)
    {
        global $product;

        if (
            is_product() &&
            $product->is_type('variable') &&
            static::getProductImage() === parse_url(Url::to($element->attrs['src']), PHP_URL_PATH)
        ) {
            $element->attrs['data-woo-product-image'] = true;
        }
    }

    protected static function getProductImage()
    {
        static $url;

        if (is_null($url)) {
            $url = parse_url(
                set_url_scheme(get_the_post_thumbnail_url(), 'relative'),
                PHP_URL_PATH,
            );
        }

        return $url;
    }
}
