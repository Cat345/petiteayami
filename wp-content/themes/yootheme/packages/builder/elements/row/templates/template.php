<?php

$el = $this->el($props['html_element'] ?: 'div');

$el->attr([

    'class' => [

        'uk-grid-margin[-{row_gap}] {@row_gap: |small|medium|large}' => !$props['margin_top'] || !$props['margin_bottom'],

        'uk-container {@width}',
        'uk-container-{width}{@width: xsmall|small|large|xlarge|expand}',
        'uk-padding-remove-horizontal' => ($props['padding_remove_horizontal'] && $props['width'] && $props['width'] != 'expand') || $props['parent'] == 'layout',
        'uk-container-expand-{width_expand} {@width} {@!width:expand}',
    ],

]);

// Grid
$grid = $props['width'] ? $this->el('div') : null;

($grid ?: $el)->attr([

    'class' => [
        'uk-grid',
        'tm-grid-expand {!alignment}',
        'uk-flex-center {@alignment:center}',
        $props['column_gap'] == $props['row_gap'] ? 'uk-grid-{column_gap}' : '[uk-grid-column-{column_gap}] [uk-grid-row-{row_gap}]',
        'uk-grid-divider {@divider} {@!column_gap:collapse} {@!row_gap:collapse}' => count($children) > 1,
        'uk-child-width-1-1 {@!layout}',
        'uk-flex-top {@parallax}',
    ],

    'uk-grid' => $props['parallax'] ? [
        'parallax: 0;',
        'parallax-justify: true;',
        'parallax-start: {parallax_start};',
        'parallax-end: {parallax_end};',
    ] : count($children) > 1,

]);

?>

<?= $el($props, $attrs) ?>

    <?php if ($grid) : ?>
    <?= $grid($props) ?>
    <?php endif ?>

        <?= $builder->render($children) ?>

    <?php if ($grid) : ?>
    <?= $grid->end() ?>
    <?php endif ?>

<?= $el->end() ?>
