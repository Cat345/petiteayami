<?php

$list = null;

if ($props['list_type'] == 'horizontal') {
    $el = $this->el('div');
} elseif ($props['html_element']) {
    $el = $this->el('nav');
    $list = $this->el($props['list_element']);
} else {
    $el = $this->el($props['list_element']);
}

($list ?: $el)->attr([

    'class' => [
        'uk-list {@list_type: vertical}',
        'uk-list-{list_marker} {@list_type: vertical}',
        'uk-list-{list_marker_color} {@list_type: vertical} {@!list_marker: bullet}',
        'uk-list-{list_style} {@list_type: vertical}',
        'uk-list-{list_size} {@list_type: vertical}',
        'uk-column-{column}[@{column_breakpoint}]',
        'uk-column-divider {@column} {@column_divider}',
        'uk-margin-remove {@list_type: vertical}' => $props['position'] == 'absolute' || $props['html_element'],
    ],

]);

$item = $this->el($props['list_type'] == 'horizontal' ? 'span' : 'li', [

    'class' => [
        'el-item'
    ],

]);

?>

<?= $el($props, $attrs) ?>

    <?php if ($list) : ?>
    <?= $list($props) ?>
    <?php endif ?>

    <?php if ($props['list_type'] == 'horizontal') : ?>

        <?php foreach ($children as $i => $child) :
        echo $item($props) . $builder->render($child, ['element' => $props]) . (($i !== array_key_last($children)) ? $props['list_horizontal_separator'] : '') . $item->end();
        endforeach ?>

    <?php else : ?>

        <?php foreach ($children as $i => $child) : ?>
        <?= $item($props) ?>
            <?= $builder->render($child, ['element' => $props]) ?>
        <?= $item->end() ?>
        <?php endforeach ?>

    <?php endif ?>

    <?php if ($list) : ?>
    <?= $list->end() ?>
    <?php endif ?>

<?= $el->end() ?>
