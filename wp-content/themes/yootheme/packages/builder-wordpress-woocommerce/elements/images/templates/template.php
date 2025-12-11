<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-images',
    ],

]);

echo $el($props, $attrs);

/**
 * Hook: woocommerce_before_single_product_summary.
 *
 * @hooked woocommerce_show_product_sale_flash - 10
 * @hooked woocommerce_show_product_images - 20
 */
Hook::doAction('woocommerce_before_single_product_summary');

echo $el->end();
