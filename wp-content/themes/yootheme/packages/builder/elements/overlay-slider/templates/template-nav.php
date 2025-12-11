<?php

$nav = $this->el('ul', [

    'class' => [
        'el-nav uk-slider-nav',
        'uk-{nav}',

        // Alignment
        'uk-flex-{nav_align} {@nav_below}',

        // Vertical
        'uk-{nav}-vertical {@nav_vertical} {@!nav_below}',

        // Wrapping
        'uk-flex-right {@!nav_vertical} {@!nav_below} {@nav_position: .*-right}',
        'uk-flex-center {@!nav_vertical} {@!nav_below} {@nav_position: bottom-center}',
    ],

    'uk-margin' => !$props['nav_vertical'],
]);

$nav_container = $this->el('div', [

    'class' => [
        // Margin
        'uk-margin[-{nav_margin}]-top {@nav_below}',

        // Position
        'uk-position-{nav_position} {@!nav_below}',

        // Margin
        'uk-position-{nav_position_margin} {@!nav_below}',

        // Hover
        'uk-hidden-hover uk-hidden-touch {@nav_hover}',

        // Breakpoint
        'uk-visible@{nav_breakpoint}',

        // Initial text color
        'uk-{text_color} {@!nav_below}',
    ],

    'uk-inverse' => true,

]);

?>

<?= $nav_container($props) ?>

    <?= $nav($props, '') ?>

<?= $nav_container->end() ?>
