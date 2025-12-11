<?php

global $product;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-stock [tm-element-woo-stock-disabled {@out_of_stock_style}]',
    ],

]);

echo $el($props, $attrs, wc_get_stock_html($product));
