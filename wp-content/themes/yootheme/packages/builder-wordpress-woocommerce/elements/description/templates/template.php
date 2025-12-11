<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-description',
    ],

]);

$name = 'woocommerce_product_description_heading';
$heading = add_filter($name, $filter = function () {});

echo $el($props, $attrs);

if ($props['description'] === 'short_description') {

    /**
     * Hook: woocommerce_single_product_summary.
     *
     * @hooked woocommerce_template_single_excerpt - 20
     */
    Hook::doAction('woocommerce_single_product_summary', ['start' => 20, 'end' => 29]);

} else {
    woocommerce_product_description_tab();
}

echo $el->end();

remove_filter($name, $filter);
