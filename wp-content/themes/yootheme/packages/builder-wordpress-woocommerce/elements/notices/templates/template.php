<?php

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-notices',
    ],

]);

echo $el($props, $attrs, $props['content']);
