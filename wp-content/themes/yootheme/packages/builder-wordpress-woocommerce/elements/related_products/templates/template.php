<?php

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-products',
    ],

]);

echo $el($props, $attrs, $props['content']);
