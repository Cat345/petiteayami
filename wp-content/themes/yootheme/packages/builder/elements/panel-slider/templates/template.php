<?php

// Resets
if ($props['panel_link']) {
    $props['title_link'] = '';
    $props['image_link'] = '';
}
if ($props['slider_parallax']) {
    $props['slidenav'] = '';
}

// Override default settings
if (!$props['slider_width']) {
    $props['height_expand'] = '';
    $props['panel_match'] = true;
    $props['panel_expand'] = 'image';
}
if ($props['height_expand']) {
    $props['panel_match'] = true;
}

$el = $this->el('div', [

    'class' => [
        'uk-slider-container {@!slidenav: outside}',
        'uk-slider-container-offset {@panel_style} {@panel_card_offset} {@!slidenav: outside}',
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
    ], $props) ?: true,

]);

// Container
$container = $this->el('div', [

    'class' => [
        'uk-position-relative',
        'uk-visible-toggle {@slidenav} {@slidenav_hover}',
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

    'tabindex' => ['-1 {@slidenav} {@slidenav_hover}'],

]);

// Slider Container
$slider_container = $props['slidenav'] == 'outside' ? $this->el('div', [

    'class' => [
        'uk-slider-container',
        'uk-slider-container-offset {@panel_style} {@panel_card_offset}',
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

]) : null;

// Slider Items
$slider_items = $this->el('div', [

    'class' => [
        'uk-slider-items',
        'uk-grid [uk-grid-{!slider_gap: default}] {@slider_gap}',
        'uk-grid-divider {@slider_gap} {@slider_divider}',
        'uk-flex-middle {@slider_row_align}',
        'uk-flex-1 {@height_expand}',
    ],

]);

$slider_item = $this->el('div', [

    'class' => [
        'uk-width-{slider_width_default} {@slider_width}',
        'uk-width-{slider_width_small}@s {@slider_width}',
        'uk-width-{slider_width_medium}@m {@slider_width}',
        'uk-width-{slider_width_large}@l {@slider_width}',
        'uk-width-{slider_width_xlarge}@xl {@slider_width}',
        // Can't use `uk-grid-match` on the parent because `flex-wrap: warp` creates a multi-line flex container which doesn't shrink the child height if its content is larger
        'uk-flex {@panel_match}',
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

    <?= $container->end() ?>

    <?php if ($props['nav']): ?>
    <?= $this->render("{$__dir}/template-nav") ?>
    <?php endif ?>

<?= $el->end() ?>
