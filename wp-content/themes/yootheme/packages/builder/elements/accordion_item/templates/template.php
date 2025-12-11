<?php

$el = $this->el($props['item_element'] ?: 'div', [

    'class' => [
        'el-item',
    ],

]);

// Content
$content = $this->el('div', [

    'class' => [
        'uk-accordion-content',
    ],

]);

// Image
$image = $this->render("{$__dir}/template-image");

// Image align
$grid = $this->el('div', [

    'class' => [
        'uk-child-width-expand',
        $element['image_grid_column_gap'] == $element['image_grid_row_gap'] ? 'uk-grid-{image_grid_column_gap}' : '[uk-grid-column-{image_grid_column_gap}] [uk-grid-row-{image_grid_row_gap}]',
        'uk-flex-middle {@image_vertical_align}',
    ],

    'uk-grid' => true,
]);

$cell_image = $this->el('div', [

    'class' => [
        'uk-width-{image_grid_width}@{image_grid_breakpoint}',
        'uk-flex-last@{image_grid_breakpoint} {@image_align: right}',
    ],

]);

$cell_content = $this->el('div', [

    'class' => [
        'uk-margin-remove-first-child',
    ],

]);

?>

<?= $el($element, $attrs) ?>

    <a class="el-title uk-accordion-title" href><?= $props['title'] ?></a>

    <?= $content($element) ?>

    <?php if ($image && in_array($element['image_align'], ['left', 'right'])) : ?>

        <?= $grid($element) ?>
            <?= $cell_image($element, $image) ?>
            <?= $cell_content($element) ?>
                <?= $this->render("{$__dir}/template-content") ?>
                <?= $this->render("{$__dir}/template-link") ?>
            <?= $cell_content->end() ?>
        <?= $grid->end() ?>

    <?php else : ?>

        <?= $element['image_align'] == 'top' ? $image : '' ?>
        <?= $this->render("{$__dir}/template-content") ?>
        <?= $this->render("{$__dir}/template-link") ?>
        <?= $element['image_align'] == 'bottom' ? $image : '' ?>

    <?php endif ?>

    <?= $content->end() ?>

<?= $el->end() ?>
