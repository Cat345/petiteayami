<?php

$items = $props['items'];

$el = $this->el('nav', [
    'aria-label' => __('Breadcrumb', 'yootheme'),
]);

$list = $this->el('ul', [
    'class' => [
        'uk-breadcrumb uk-margin-remove-bottom',
        'uk-flex-{text_align}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]]',
    ],
    'vocab' => 'https://schema.org/',
    'typeof' => 'BreadcrumbList',
]);

$li = $this->el('li', [
    'property' => 'itemListElement',
    'typeof' => 'ListItem',
]);

$span = $this->el('span', ['property' => 'name']);

$position = 1;
?>

<?php if ($items) : ?>

<?= $el($props, $attrs) ?>

    <?= $list($props) ?>

    <?php foreach ($items as $key => $item) : ?>

    <?php if (!empty($item->link)) : ?>
        <?= $li() ?>
            <a href="<?= $item->link ?>" property="item" typeof="WebPage"><?= $span([], $item->name) ?></a>
            <meta property="position" content="<?= $position++ ?>">
    <?php elseif ($key !== array_key_last($items)) : ?>
        <li class="uk-disabled">
            <span><?= $item->name ?></span>
    <?php else : ?>
        <?= $li() ?>
            <?= $span([], ['aria-current' => 'page'], $item->name) ?>
            <meta property="position" content="<?= $position++ ?>">
    <?php endif ?>
        <?= $li->end() ?>
    <?php endforeach ?>

    <?= $list->end() ?>

<?= $el->end() ?>

<?php endif ?>
