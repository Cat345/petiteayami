<?php

$el = $this->el($props['html_element'] ?: 'div');

// Subnav
$subnav = $this->el('ul', [

    'class' => [
        'uk-margin-remove-bottom',
        'uk-subnav {@subnav_style: |divider|pill} [uk-subnav-{subnav_style: divider|pill}]',
        'uk-tab {@subnav_style: tab}',
        'uk-flex-{text_align}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]]',
    ],

    'uk-margin' => count($children) > 1,
]);

?>

<?= $el($props, $attrs) ?>

    <?= $subnav($props) ?>
    <?php foreach ($children as $child) : ?>
    <li class="el-item <?= $child->props['active'] ? 'uk-active' : '' ?>"><?= $builder->render($child, ['element' => $props]) ?></li>
    <?php endforeach ?>
    <?= $subnav->end() ?>

<?= $el->end() ?>
