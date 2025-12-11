<?php

// If link is not set use image/video field for the lightbox
if (!$props['link'] && $element['lightbox']) {
    $props['link'] = $props['video'] ?: $props['image'];
}

// Override default settings
$element['text_color'] = $props['text_color'] ?: $element['text_color'];

if ($element['grid_masonry']) {
    $element['image_expand'] = '';
}

// New logic shortcuts
$element['has_transition'] = $element['overlay_hover'] || $element['image_transition'] || $element['image_transition_border'] || $props['hover_image'] || $props['hover_video'];
$element['text_color_inverse'] = $element['text_color'] === 'light' ? 'dark' : 'light';

$el = $this->el($props['item_element'] ?: 'div', [

    'class' => [
        'el-item',
        'uk-margin-auto uk-width-{item_maxwidth}',
        'uk-flex uk-flex-column {@image_expand} {@overlay_link}' => $props['link'],

        // Needs to be parent of `uk-link-toggle`
        'uk-{0}' => !$element['overlay_style'] || $element['overlay_cover'] ? $element['text_color'] : false,
        // Only for transparent navbar
        'uk-inverse-{0}' => $element['overlay_style'] && !$element['overlay_cover'] ? $element['text_color'] : false,

    ],

]);

// Link Container
$link_container = $props['link'] && $element['overlay_link'] ? $this->el('a', [

    'class' => [
        'uk-flex-1',
    ],

]) : null;

($link_container ?: $el)->attr([

    'class' => [
        'uk-box-shadow-{image_box_shadow}',
        'uk-box-shadow-hover-{image_hover_box_shadow}',

        'uk-border-{image_border} {@!image_box_decoration} {@!image_transition_border}',
        'uk-box-shadow-bottom {@image_box_decoration: shadow}',
        'tm-mask-default {@image_box_decoration: mask}',
        'tm-box-decoration-{image_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@image_box_decoration_inverse} {@image_box_decoration: default|primary|secondary}',
        'tm-transition-border {@image_transition_border}',
        'uk-inline' => $element['image_box_decoration'] || $element['image_transition_border'],

        'uk-transition-toggle {@has_transition}',

        'uk-flex uk-flex-column {@image_expand}',
    ],

    'style' => [
        "background-color: {$props['media_background']};" => $props['media_background'],
    ],

    'tabindex' => $element['has_transition'] && !($props['link'] && $element['overlay_link']) ? 0 : null,

    // Needs to be on anchor to have just one focusable toggle when using keyboard navigation
    'uk-toggle' => ($props['text_color_hover'] || $element['text_color_hover']) && ((!$element['overlay_style'] && ($props['hover_image'] || $props['hover_video'])) || ($element['overlay_cover'] && $element['overlay_hover'] && $element['overlay_transition_background'])) ? [
        'cls: uk-{text_color_inverse} [uk-{text_color}];',
        'mode: hover;',
        'target: !*; {@overlay_link}' => $props['link'],
    ] : false,

]);

// Box Decoration / Transition Border
$image_container = $element['image_box_decoration'] || $element['image_transition_border'] ? $this->el('div', [

    'class' => [
        'uk-flex-1 uk-flex uk-flex-column {@image_expand}',
    ],

]) : null;

($image_container ?: $link_container ?: $el)->attr('class', [
    'uk-inline-clip',
]);

$overlay = $this->el('div', [

    'class' => [
        'uk-{overlay_style}',
        'uk-transition-{overlay_transition} {@overlay_hover} {@overlay_cover}',

        'uk-position-cover {@overlay_cover}',
        'uk-position-{overlay_margin} {@overlay_cover}',
    ],

]);

$position = $this->el('div', [

    'class' => [
        'uk-position-{overlay_position} [uk-position-{overlay_margin} {@overlay_style}]',
        'uk-transition-{overlay_transition} {@overlay_hover}' => !$element['overlay_transition_background'] || !$element['overlay_cover'],
        'uk-blend-difference {@text_blend} {@!overlay_style}',
        'uk-flex {@content_expand}'
    ],

]);

$content = $this->el('div', [

    'class' => [
        $element['overlay_style'] ? 'uk-overlay' : 'uk-panel',
        'uk-padding {@!overlay_padding} {@!overlay_style}',
        'uk-padding-{!overlay_padding: |none}',
        'uk-padding-remove {@overlay_padding: none} {@overlay_style}',
        'uk-width-{overlay_maxwidth} {@!overlay_position: top|bottom}',
        'uk-margin-remove-first-child',
        'uk-flex-1 uk-flex uk-flex-column {@content_expand}'
    ],

]);

if (!$element['overlay_cover']) {
    $position->attr($overlay->attrs);
}

// Link
$link = include "{$__dir}/template-link.php";

?>

<?= $el($element, $attrs) ?>

    <?php if ($link_container) : ?>
    <?= $link_container($element) ?>
    <?php endif ?>

        <?php if ($image_container) : ?>
        <?= $image_container($element) ?>
        <?php endif ?>

            <?= $this->render("{$__dir}/template-media", compact('props', 'element')) ?>

            <?php if ($props['media_overlay']) : ?>
            <div class="uk-position-cover" style="background-color:<?= $props['media_overlay'] ?>"></div>
            <?php endif ?>

            <?php if ($element['overlay_cover']) : ?>
            <?= $overlay($element, '') ?>
            <?php endif ?>

            <?php if ($props['title'] != '' || $props['meta'] != '' || $props['content'] != '' || ($props['link'] && ($props['link_text'] || $element['link_text']))) : ?>
            <?= $position($element, $content($element, $this->render("{$__dir}/template-content", compact('props', 'element', 'link')))) ?>
            <?php endif ?>

        <?php if ($image_container) : ?>
        <?= $image_container->end() ?>
        <?php endif ?>

    <?php if ($link_container) : ?>
    <?= $link_container->end() ?>
    <?php endif ?>

<?= $el->end() ?>
