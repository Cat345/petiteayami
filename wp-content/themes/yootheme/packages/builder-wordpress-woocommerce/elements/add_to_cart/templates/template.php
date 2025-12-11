<?php

use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-add-to-cart',
    ],

]);

echo $el($props, $attrs);

if (!$props['quantity']) {
    Helper::filterOnce('woocommerce_quantity_input_type', fn() => 'hidden');
}
/**
 * Hook: woocommerce_single_product_summary.
 *
 * @hooked woocommerce_template_single_add_to_cart - 30
 */
Hook::doAction('woocommerce_single_product_summary', ['start' => 30, 'end' => 39]);

echo $el->end();
