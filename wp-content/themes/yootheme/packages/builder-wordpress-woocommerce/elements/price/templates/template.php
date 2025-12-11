<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-price',
    ],

]);

echo $el($props, $attrs);

/**
 * Hook: woocommerce_single_product_summary.
 *
 * @hooked woocommerce_template_single_rating - 10
 * @hooked woocommerce_template_single_price - 10
 */
Hook::doAction('woocommerce_single_product_summary', [
    'start' => 10,
    'end' => 19,
    'skip' => ['woocommerce_template_single_rating'],
]);

echo $el->end();
