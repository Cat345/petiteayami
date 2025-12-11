<?php

// Resets
if ($props['overlay_link']) { $props['title_link'] = ''; }

// Override default settings
if (!$props['grid_parallax'] && $props['grid_parallax_justify']) {
    $props['grid_parallax'] = '0';
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

    'uk-filter' => $tags ? [
        'target: .js-filter;',
        'animation: {filter_animation};',
    ] : false,

]);

// Grid
$grid = $this->el('div', [

    'class' => [
        'uk-grid',
        'js-filter' => $tags,
        'uk-child-width-[1-{@!grid_default: auto}]{grid_default}',
        'uk-child-width-[1-{@!grid_small: auto}]{grid_small}@s',
        'uk-child-width-[1-{@!grid_medium: auto}]{grid_medium}@m',
        'uk-child-width-[1-{@!grid_large: auto}]{grid_large}@l',
        'uk-child-width-[1-{@!grid_xlarge: auto}]{grid_xlarge}@xl',
        'uk-flex-center {@grid_column_align} {@!grid_masonry}',
        'uk-flex-middle {@grid_row_align} {@!grid_masonry}',
        $props['grid_column_gap'] == $props['grid_row_gap'] ? 'uk-grid-{grid_column_gap}' : '[uk-grid-column-{grid_column_gap}] [uk-grid-row-{grid_row_gap}]',
        'uk-grid-divider {@grid_divider} {@!grid_column_gap: collapse} {@!grid_row_gap:collapse}' => count($children) > 1,
    ],

    'uk-grid' => $this->expr([
        'masonry: {grid_masonry};',
        'parallax: {grid_parallax};',
        'parallax-justify: true; {@grid_parallax_justify}',
        'parallax-start: {grid_parallax_start};' => $props['grid_parallax'] || $props['grid_parallax_justify'],
        'parallax-end: {grid_parallax_end};' => $props['grid_parallax'] || $props['grid_parallax_justify'],
    ], $props) ?: count($children) > 1,

    'uk-lightbox' => $props['lightbox'] ? [
        'toggle: a[data-type];',
        'animation: {lightbox_animation};',
        'nav: {lightbox_nav}; slidenav: false;',
        'delay-controls: 0;' => $props['lightbox_controls'],
        'counter: true;' => $props['lightbox_counter'],
        'bg-close: false;' => !$props['lightbox_bg_close'],
        'video-autoplay: {lightbox_video_autoplay};',
    ] : false,

]);

$cell = $this->el('div', [

    'class' => [
        'uk-flex uk-flex-center uk-flex-middle {@image_orientation}',
        // Can't use `uk-grid-match` on the parent because `flex-wrap: warp` creates a multi-line flex container which doesn't shrink the child height if its content is larger
        'uk-flex {@!grid_masonry} {@image_expand}',
        'uk-flex-{text_align}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]] {@!grid_masonry} {@image_expand}',
    ],

]);

// Filter
$filter_grid = $this->el('div', [

    'class' => [
        'uk-grid',
        'uk-child-width-expand',
        $props['filter_grid_column_gap'] == $props['filter_grid_row_gap'] ? 'uk-grid-{filter_grid_column_gap}' : '[uk-grid-column-{filter_grid_column_gap}] [uk-grid-row-{filter_grid_row_gap}]',
    ],

    'uk-grid' => count($children) > 1,
]);

$filter_cell = $this->el('div', [

    'class' => [
        'uk-width-{filter_grid_width}@{filter_grid_breakpoint}',
        'uk-flex-last@{filter_grid_breakpoint} {@filter_position: right}',
    ],

]);

?>

<?= $el($props, $attrs) ?>

    <?php if ($tags) : ?>

        <?php if ($filter_horizontal = in_array($props['filter_position'], ['left', 'right'])) : ?>
        <?= $filter_grid($props) ?>
            <?= $filter_cell($props) ?>
        <?php endif ?>

            <?= $this->render("{$__dir}/template-nav", compact('props')) ?>

        <?php if ($filter_horizontal) : ?>
            </div>
            <div>
        <?php endif ?>

            <?= $grid($props) ?>
            <?php foreach ($children as $child) : ?>
            <?= $cell($props, ['data-tag' => $child->tags], $builder->render($child, ['element' => $props])) ?>
            <?php endforeach ?>
            <?= $grid->end() ?>

        <?php if ($filter_horizontal) : ?>
            </div>
        </div>
        <?php endif ?>

    <?php else : ?>

        <?= $grid($props) ?>
        <?php foreach ($children as $child) : ?>
        <?= $cell($props, $builder->render($child, ['element' => $props])) ?>
        <?php endforeach ?>
        <?= $grid->end() ?>

    <?php endif ?>

<?= $el->end() ?>
