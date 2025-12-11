<?php

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-additional-information',
    ],

]);

if (empty($props['title'])) {
    $name = 'woocommerce_product_additional_information_heading';
    $heading = add_filter($name, $filter = function () {});
}

echo $el($props, $attrs);
woocommerce_product_additional_information_tab();
echo $el->end();

if (empty($props['title'])) {
    remove_filter($name, $filter);
}
