<?php

$icon = $this->el('span', [

    'class' => [
        'uk-text-{image_svg_color}',
    ],

    'uk-icon' => [
        'icon: {0};' => $props['icon'],
        'width: {image_width};',
        'height: {image_width};',
    ],

]);

return $icon;
