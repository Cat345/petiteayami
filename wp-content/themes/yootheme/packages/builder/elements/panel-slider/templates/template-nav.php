<?php

$nav = $this->el('ul', [

    'class' => [
        'el-nav uk-slider-nav',
        'uk-{nav}',
        'uk-flex-{nav_align}',
    ],

    'uk-margin' => true,
]);

$nav_container = $this->el('div', [

    'class' => [
        'uk-margin[-{nav_margin}]-top',
        'uk-visible@{nav_breakpoint}',
        'uk-position-relative {@panel_style} {@panel_card_offset}', // Fix `uk-slider-container-offset` causing overlaying the nav
    ],

    'uk-inverse' => true,

]);

?>

<?= $nav_container($props) ?>

    <?= $nav($props, '') ?>

<?= $nav_container->end() ?>