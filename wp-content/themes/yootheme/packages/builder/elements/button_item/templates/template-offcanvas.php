<?php

// Offcanvas
$offcanvas = $this->el('div', [

    'id' => ['{id}'],

    'uk-offcanvas' => [
        'container: true;',
        'flip: {dialog_offcanvas_flip};',
        'overlay: {dialog_offcanvas_overlay};',
    ],

]);

$offcanvas_content = $this->el('div', [

    'class' => [
        'uk-offcanvas-bar',
        'uk-padding-remove {@link}',
    ],

]);

$offcanvas_close = $this->el('button', [

    'class' => [
        'uk-offcanvas-close',
        'uk-close-large {@dialog_close_large}',
    ],

    'uk-close' => true,
    
    'uk-toggle' => $props['dialog_close_large'] ? [
        'cls: uk-close-large {@dialog_close_large};',
        'mode: media;',
        'media: @s;',
    ] : false,



    'type' => 'button',

]);

?>

<?= $offcanvas($props) ?>
    <?= $offcanvas_content($props) ?>

        <?= $offcanvas_close($props, '') ?>

        <?= $props['dialog'] ?>

    <?= $offcanvas_content->end() ?>
<?= $offcanvas->end() ?>
