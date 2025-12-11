<?php

// Resets
if ($props['overlay_link']) { $props['title_link'] = ''; }
if ($props['content_expand']) {
    if (in_array($props['overlay_position'], ['top', 'bottom'])) {
        $props['overlay_position'] = 'cover';
    }
    if (in_array($props['overlay_position'], ['top-center', 'center', 'bottom-center'])) {
        $props['overlay_position'] = 'center-horizontal';
    }
    if (in_array($props['overlay_position'], ['top-left', 'center-left', 'bottom-left'])) {
        $props['overlay_position'] = 'left';
    }
    if (in_array($props['overlay_position'], ['top-right', 'center-right', 'bottom-right'])) {
        $props['overlay_position'] = 'right';
    }
}

// New logic shortcuts
$props['image_expand'] = $props['height_expand']; // Keep code same as other overlay elements
$props['overlay_cover'] = $props['overlay_style'] && $props['overlay_mode'] == 'cover';
$props['has_transition'] = $props['overlay_hover'] || $props['image_transition'] || $props['image_transition_border'] || $props['hover_image'] || $props['hover_video'];
$props['text_color_inverse'] = $props['text_color'] === 'light' ? 'dark' : 'light';

$props['flex_column_align'] = '';
$props['flex_column_align_fallback'] = '';
if ($props['image_expand']) {
    $horizontal = ['left', 'center', 'right'];
    $vertical = ['top', 'middle', 'bottom'];
    $props['flex_column_align'] = str_replace($horizontal, $vertical, $props['text_align'] ?? '');
    $props['flex_column_align_fallback'] = str_replace($horizontal, $vertical, $props['text_align_fallback'] ?? '');
}

$el = $this->el($props['html_element'] ?: 'div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 {@height_expand}',
        'uk-flex uk-flex-column {@image_expand}',
        'uk-flex-{flex_column_align}[@{text_align_breakpoint} [uk-flex-{flex_column_align_fallback}]] {@image_expand}',

        // Needs to be parent of `uk-link-toggle`
        'uk-{text_color}' => !$props['overlay_style'] || $props['overlay_cover'],
        // Only for transparent navbar
        'uk-inverse-{text_color}' => $props['overlay_style'] && !$props['overlay_cover'],
    ],

]);

// Container (Needed for link and to make text alignment work set on parent)
$container = $this->el($props['link'] && $props['overlay_link'] ? 'a' : 'div', [

    'class' => [
        'uk-box-shadow-{image_box_shadow}',
        'uk-box-shadow-hover-{image_hover_box_shadow}',

        'uk-border-{image_border} {@!image_box_decoration} {@!image_transition_border}',
        'uk-box-shadow-bottom {@image_box_decoration: shadow}',
        'tm-mask-default {@image_box_decoration: mask}',
        'tm-box-decoration-{image_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@image_box_decoration_inverse} {@image_box_decoration: default|primary|secondary}',
        'tm-transition-border {@image_transition_border}',
        'uk-inline' => $props['image_box_decoration'] || $props['image_transition_border'],

        'uk-transition-toggle {@has_transition}',

        'uk-flex-1 uk-flex uk-flex-column {@image_expand}',
    ],

    'style' => [
        "background-color: {$props['media_background']};" => $props['media_background'],
    ],

    'tabindex' => $props['has_transition'] && !($props['link'] && $props['overlay_link']) ? 0 : null,

    // Needs to be on anchor to have just one focusable toggle when using keyboard navigation
    'uk-toggle' => $props['text_color_hover'] && ((!$props['overlay_style'] && ($props['hover_image'] || $props['hover_video'])) || ($props['overlay_cover'] && $props['overlay_hover'] && $props['overlay_transition_background'])) ? [
        'cls: uk-{text_color_inverse} [uk-{text_color}];',
        'mode: hover;',
        'target: !*;',
    ] : false,

]);

// Box Decoration / Transition Border
$image_container = $props['image_box_decoration'] || $props['image_transition_border'] ? $this->el('div', [

    'class' => [
        'uk-flex-1 uk-flex uk-flex-column {@image_expand}',
    ],

]) : null;

($image_container ?: $container)->attr('class', [
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
        'uk-transition-{overlay_transition} {@overlay_hover}' => !($props['overlay_transition_background'] && $props['overlay_cover']),
        'uk-blend-difference {@text_blend} {@!overlay_style}',
        'uk-flex {@content_expand}'
    ],

]);

$content = $this->el('div', [

    'class' => [
        $props['overlay_style'] ? 'uk-overlay' : 'uk-panel',
        'uk-padding {@!overlay_padding} {@!overlay_style}',
        'uk-padding-{!overlay_padding: |none}',
        'uk-padding-remove {@overlay_padding: none} {@overlay_style}',
        'uk-width-{overlay_maxwidth} {@!overlay_position: top|bottom}',
        'uk-margin-remove-first-child',
        'uk-flex-1 uk-flex uk-flex-column {@content_expand}'
    ],

]);

if (!$props['overlay_cover']) {
    $position->attr($overlay->attrs);
}

// Link
$link = include "{$__dir}/template-link.php";

?>

<?= $el($props, $attrs) ?>

    <?= $container($props) ?>

        <?php if ($image_container) : ?>
        <?= $image_container($props) ?>
        <?php endif ?>

            <?= $this->render("{$__dir}/template-media", compact('props')) ?>

            <?php if ($props['media_overlay']) : ?>
            <div class="uk-position-cover" style="background-color:<?= $props['media_overlay'] ?>"></div>
            <?php endif ?>

            <?php if ($props['overlay_cover']) : ?>
            <?= $overlay($props, '') ?>
            <?php endif ?>

            <?php if ($props['title'] != '' || $props['meta'] != '' || $props['content'] != '' || ($props['link'] && $props['link_text'])) : ?>
            <?= $position($props, $content($props, $this->render("{$__dir}/template-content", compact('props', 'link')))) ?>
            <?php endif ?>

        <?php if ($image_container) : ?>
        <?= $image_container->end() ?>
        <?php endif ?>

    <?= $container->end() ?>

<?= $el->end() ?>
