<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-tabs',
    ],

]);

echo $el($props, $attrs);

/**
 * Hook: woocommerce_after_single_product_summary.
 *
 * @hooked woocommerce_output_product_data_tabs - 10
 */
Hook::doAction('woocommerce_after_single_product_summary', ['end' => 14]);

echo $el->end();
