<?php

// Resets
if ($element['card_link']) {
    $element['title_link'] = '';
    $element['image_link'] = '';
}

// Image
$props['image'] = $this->render("{$__dir}/template-image", compact('props'));

// New logic shortcuts
$element['has_link'] = $props['link'] && $element['card_link'];

// Item
$el = $this->el($props['item_element'] ?: 'div', [

    'class' => [
        'el-item',

        // Match link container height
        'uk-grid-item-match {@has_link}',
    ],

]);

// Link Container
$link_container = $element['has_link'] ? $this->el('a') : null;

($element['has_link'] ? $link_container : $el)->attr([

    'class' => [
        'uk-card uk-card-{card_style}',
        'uk-card-{card_size}',
        'uk-card-hover {@link_type: element}' => $props['link'],
        'uk-card-body' => !($props['image'] && $element['image_card_padding']),
        'uk-margin-remove-first-child' => !($props['image'] && $element['image_card_padding']),
    ],

]);

// Content
$content = $this->el('div', [

    'class' => [
        'uk-card-body uk-margin-remove-first-child' => $props['image'] && $element['image_card_padding'],
    ],

]);

// Link
$link = include "{$__dir}/template-link.php";

// Card media
if ($props['image'] && $element['image_card_padding']) {
    $props['image'] = $this->el('div', ['class' => [
        'uk-card-media-top',
    ]], $props['image'])->render($element);
}

?>

<?= $el($element) ?>

    <?php if ($link_container) : ?>
    <?= $link_container($element) ?>
    <?php endif ?>

        <?= $props['image'] ?>

        <?php if ($this->expr($content->attrs['class'], $element)) : ?>
            <?= $content($element, $this->render("{$__dir}/template-content", compact('props', 'link'))) ?>
        <?php else : ?>
            <?= $this->render("{$__dir}/template-content", compact('props', 'link')) ?>
        <?php endif ?>

    <?php if ($link_container) : ?>
    <?= $link_container->end() ?>
    <?php endif ?>

<?= $el->end() ?>
