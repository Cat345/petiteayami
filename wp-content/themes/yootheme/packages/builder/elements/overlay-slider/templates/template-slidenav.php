<?php

$slidenav = $this->el('a', [

    'class' => [
        'el-slidenav',
        'uk-slidenav-large {@slidenav_large}',
    ],

    'href' => '#', // WordPress Preview reloads if `href` is empty
]);

$attrs_slidenav_next = [

    'uk-slidenav-next' => true,
    'uk-slider-item' => 'next',

];

$attrs_slidenav_previous = [

    'uk-slidenav-previous' => true,
    'uk-slider-item' => 'previous',

];

$slidenav_container = $this->el('div', [

    'class' => [
        'uk-visible@{slidenav_breakpoint}',
        'uk-hidden-hover uk-hidden-touch {@slidenav_hover}',

        // Initial text color
        'uk-{text_color} {@!slidenav: outside}',
    ],

    'uk-inverse' => true,

]);

if (in_array($props['slidenav'], ['default', 'outside'])) {

    $slidenav_container->attr([

        'class' => [
            'uk-position-{slidenav_margin}',
        ],

    ]);

    $attrs_slidenav_container_next = [
        'class' => [
            'uk-position-center-right {@slidenav: default}',
            'uk-position-center-right-out {@slidenav: outside}',
        ],
        'uk-toggle' => [
            'cls: uk-position-center-right-out uk-position-center-right; mode: media; media: @{slidenav_outside_breakpoint} {@slidenav: outside}',
        ],
    ];

    $attrs_slidenav_container_previous = [
        'class' => [
            'uk-position-center-left {@slidenav: default}',
            'uk-position-center-left-out {@slidenav: outside}',
        ],
        'uk-toggle' => [
            'cls: uk-position-center-left-out uk-position-center-left; mode: media; media: @{slidenav_outside_breakpoint} {@slidenav: outside}',
        ],
    ];

} else {

    $slidenav_container->attr([

        'class' => [
            'uk-slidenav-container uk-position-{slidenav} [uk-position-{slidenav_margin}]',
        ],

    ]);

}

?>

<?php if (!in_array($props['slidenav'], ['default', 'outside'])) : ?>
<?= $slidenav_container($props) ?>

    <?= $slidenav($props, $attrs_slidenav_previous, '') ?>
    <?= $slidenav($props, $attrs_slidenav_next, '') ?>

<?= $slidenav_container->end() ?>
<?php else : ?>

    <?= $slidenav_container($props, $attrs_slidenav_container_previous) ?>
    <?= $slidenav($props, $attrs_slidenav_previous, '') ?>
    <?= $slidenav_container->end() ?>

    <?= $slidenav_container($props, $attrs_slidenav_container_next) ?>
    <?= $slidenav($props, $attrs_slidenav_next, '') ?>
    <?= $slidenav_container->end() ?>

<?php endif ?>