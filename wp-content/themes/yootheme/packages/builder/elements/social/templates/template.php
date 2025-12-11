<?php

$el = $this->el('div');

// Grid
$grid = $this->el('ul', [

    'class' => [
        'uk-{link_style: iconnav|thumbnav} [uk-{link_style: iconnav|thumbnav}-vertical {@grid: vertical}]',
        'uk-child-width-auto {@!link_style: iconnav|thumbnav}',
        'uk-flex-column {@grid: vertical} {@!link_style: iconnav|thumbnav}',
        !in_array($props['link_style'], ['iconnav', 'thumbnav']) || (in_array($props['link_style'], ['iconnav', 'thumbnav']) && $props['grid'] == 'horizontal')
            ? $props['grid_column_gap'] == $props['grid_row_gap']
                ? 'uk-grid-{grid_column_gap}'
                : '[uk-grid-column-{grid_column_gap}] [uk-grid-row-{grid_row_gap}]'
            : '',
        'uk-flex-inline', // allow text alignment
        'uk-flex-middle', // center images with different heights
    ],

    'uk-grid' => !in_array($props['link_style'], ['iconnav', 'thumbnav']) || (in_array($props['link_style'], ['iconnav', 'thumbnav']) && $props['grid'] == 'horizontal'),

    'uk-toggle' => [
        'cls: uk-flex-column; mode: media; media: @{grid_vertical_breakpoint} {@grid: vertical} {@!link_style: iconnav|thumbnav}',
    ],

]);

?>

<?= $el($props, $attrs) ?>
    <?= $grid($props) ?>

    <?php foreach ($children as $child) : ?>
        <li class="el-item"><?= $builder->render($child, ['element' => $props]) ?></li>
    <?php endforeach ?>

    <?= $grid->end() ?>
<?= $el->end() ?>
