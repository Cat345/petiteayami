<?php

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-rating',
    ],

]);

echo $el($props, $attrs, $props['content']);
