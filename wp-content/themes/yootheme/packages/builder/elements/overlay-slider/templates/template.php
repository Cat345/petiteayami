<?php

// Resets
if ($props['overlay_link']) { $props['title_link'] = ''; }
if (!$props['slider_width']) {
    $props['slider_height'] = '';
    $props['height_expand'] = '';
    $props['image_expand'] = true;
}
if ($props['slider_width'] && $props['slider_height'] ) {
    $props['height_expand'] = '';
}
if ($props['height_expand'] || $props['slider_min_height'] || $props['slider_height']) {
    $props['image_expand'] = true;
}
if ($props['slider_height'] == 'viewport') {
    if ($props['slider_height_viewport'] > 100) {
        $props['slider_height_offset_top'] = false;
    } elseif (!$props['slider_height_viewport']) {
        $props['slider_height_viewport'] = 100;
    }
}
if ($props['slider_parallax']) {
    $props['slidenav'] = '';
}
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
$props['overlay_cover'] = $props['overlay_style'] && $props['overlay_mode'] == 'cover';

$el = $this->el('div', [

    'class' => [
        'uk-slider-container {@!slidenav: outside}',
        // Expand to column height
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

    'uk-slider' => $this->expr([
        'sets: {slider_sets}; {@!slider_parallax}',
        'center: {slider_center};',
        'finite: {slider_finite};',
        'velocity: {slider_velocity}; {@!slider_parallax}',
        'autoplay: {slider_autoplay}; [pauseOnHover: false; {@!slider_autoplay_pause}] [autoplayInterval: {slider_autoplay_interval}000;] {@!slider_parallax}',
        // Parallax
        'parallax: true; {@slider_parallax}',
        'parallax-easing: {slider_parallax_easing}; {@slider_parallax}',
        'parallax-target: {slider_parallax_target}; {@slider_parallax}',
        'parallax-start: {slider_parallax_start}; {@slider_parallax}',
        'parallax-end: {slider_parallax_end}; {@slider_parallax}',
        // Overlay active
        'clsActivated: uk-transition-active; [active: first; {@overlay_active_first}] {@overlay_display: active}',
    ], $props) ?: true,

]);

// Container
$container = $this->el('div', [

    'class' => [
        'uk-position-relative',
        'uk-visible-toggle' => ($props['slidenav'] && $props['slidenav_hover']) || ($props['nav'] && $props['nav_hover']),
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

    'tabindex' => ($props['slidenav'] && $props['slidenav_hover']) || ($props['nav'] && $props['nav_hover']) ? '-1' : null,

]);

// Slider Container
$slider_container = $props['slidenav'] == 'outside' ? $this->el('div', [

    'class' => [
        'uk-slider-container',
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

]) : null;

// Slider Items
$slider_items = $this->el('div', [

    'class' => [
        'uk-slider-items',
        'uk-grid [uk-grid-{!slider_gap: default}] {@slider_gap}',
        'uk-grid-divider {@slider_gap} {@slider_divider}',
        'uk-flex-1 {@height_expand}',
    ],

    'style' => [
        'min-height: {slider_min_height}px; {@!slider_height}',
        'height: max({0}px, {slider_height_viewport}vh); {@slider_height: viewport} {@!slider_height_offset_top}' => [$props['slider_min_height'] ?: '0'],
    ],

    // Height Viewport
    'uk-height-viewport' => ($props['slider_height'] == 'viewport' && $props['slider_height_offset_top']) || $props['slider_height'] == 'section' ? [
        'property: height;',
        'offset-top: true; {@slider_height_offset_top}',
        'min: {slider_min_height};',
        'offset-bottom: {0}; {@slider_height: viewport}' => $props['slider_height_viewport'] && $props['slider_height_viewport'] < 100 ? 100 - (int) $props['slider_height_viewport'] : false,
        'offset-bottom: !:is(.uk-section-default,.uk-section-muted,.uk-section-primary,.uk-section-secondary) +; {@slider_height: section}',
    ] : false,

]);

$slider_item = $this->el('div', [

    'class' => [
        'uk-width-{slider_width_default} {@slider_width}',
        'uk-width-{slider_width_small}@s {@slider_width}',
        'uk-width-{slider_width_medium}@m {@slider_width}',
        'uk-width-{slider_width_large}@l {@slider_width}',
        'uk-width-{slider_width_xlarge}@xl {@slider_width}',
        // Can't use `uk-grid-match` on the parent because `flex-wrap: warp` creates a multi-line flex container which doesn't shrink the child height if its content is larger
        'uk-flex' => $props['image_expand'],
        'uk-flex-{text_align}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]] {@image_expand}',
    ],

]);

?>

<?= $el($props, $attrs) ?>

    <?= $container($props) ?>

        <?php if ($slider_container) : ?>
        <?= $slider_container($props) ?>
        <?php endif ?>

            <?= $slider_items($props) ?>
                <?php foreach ($children as $child) : ?>
                <?= $slider_item($props, $builder->render($child, ['element' => $props])) ?>
                <?php endforeach ?>
            <?= $slider_items->end() ?>

        <?php if ($slider_container) : ?>
        <?= $slider_container->end() ?>
        <?php endif ?>

        <?php if ($props['slidenav']) : ?>
        <?= $this->render("{$__dir}/template-slidenav") ?>
        <?php endif ?>

        <?php if ($props['nav'] && !$props['nav_below']) : ?>
        <?= $this->render("{$__dir}/template-nav") ?>
        <?php endif ?>

    <?= $container->end() ?>

    <?php if ($props['nav'] && $props['nav_below']) : ?>
    <?= $this->render("{$__dir}/template-nav") ?>
    <?php endif ?>


<?= $el->end() ?>
