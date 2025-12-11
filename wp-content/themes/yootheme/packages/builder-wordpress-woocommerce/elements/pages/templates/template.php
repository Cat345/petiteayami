<?php

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-pages',
    ],

]);

echo $el($props, $attrs, "[woocommerce_{$props['page']}]");
