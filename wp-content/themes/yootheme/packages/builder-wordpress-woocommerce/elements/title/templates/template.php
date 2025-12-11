<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-title',
    ],

]);

echo $el($props, $attrs);

/**
 * Hook: woocommerce_single_product_summary.
 *
 * @hooked woocommerce_template_single_title - 5
 */
Hook::doAction('woocommerce_single_product_summary', ['end' => 9]);

echo $el->end();
