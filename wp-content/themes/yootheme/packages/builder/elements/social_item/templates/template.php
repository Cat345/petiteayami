<?php

// Image
if ($props['image']) {

    $icon = $this->el('image', [
        'src' => $props['image'],
        'alt' => true,
        'loading' => $element['image_loading'] ? false : null,
        'width' => $element['image_width'],
        'height' => $element['image_height'],
        'uk-svg' => $element['image_svg_inline'],
        'thumbnail' => true,
    ]);

// Icon
} else {

    $icon = $this->el('span', [

        'uk-icon' => [
            'icon: {0};' => $props['icon'] ?: $this->e($props['link'], 'social'),
            'width: {icon_width};',
            'height: {icon_width};',
        ],

    ]);

}

// Link
$link = $this->el('a', [

    'class' => [
        'el-link',
        'uk-icon-link {@!link_style}',
        'uk-icon-button {@link_style: button}',
        'uk-link-{link_style: muted|text|reset}',
    ],

    'href' => $props['link'],
    'aria-label' => $props['link_aria_label'] ?: $element['link_aria_label'],
    'target' => ['_blank {@link_target}'],
    'rel' => 'noreferrer',

]);

?>

<?= $link($element, $icon($element, '')) ?>
