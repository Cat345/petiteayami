<?php

// Modal
$modal = $this->el('div', [

    'id' => ['{id}'],

    'uk-modal' => true,

    'class' => [
        'uk-modal',
        'uk-modal-{dialog_modal_width: container} {@!link}',
    ],

]);

$modal_dialog = $this->el('div', [

    'class' => [
        'uk-modal-dialog uk-margin-auto-vertical',
        'uk-width-auto {@link}',
        'uk-modal-body {@!link}',
        'uk-width-1-1 {@dialog_modal_width: expand} {@!link}',
    ],

]);

$modal_close = $this->el('button', [

    'class' => [
        'uk-close-large {@dialog_close_large}',
        'uk-modal-close-default {@!dialog_close_outside}',
        'uk-modal-close-outside {@dialog_close_outside}',
    ],

    'uk-close' => true,

    'uk-toggle' => ($props['dialog_close_large'] || $props['dialog_close_outside']) ? [
        'cls: [uk-close-large {@dialog_close_large}] [uk-modal-close-outside uk-modal-close-default {@dialog_close_outside}];',
        'mode: media;',
        'media: @s;',
    ] : false,

    'type' => 'button',

]);

?>

<?= $modal($props) ?>
    <?= $modal_dialog($props) ?>

        <?= $modal_close($props, '') ?>

        <?php if ($props['link']) : ?>
        <?= $this->render("{$__dir}/template-media", compact('props', 'element')) ?>
        <?php elseif ($props['dialog']) : ?>
        <?= $props['dialog'] ?>
        <?php endif ?>

        <?= $modal_dialog->end() ?>
<?= $modal->end() ?>
