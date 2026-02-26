<?php

// Link
$el = $this->el('a', [

    'class' => [
        'uk-flex-{text_align: left|right}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]]',
    ],

]);

if ($props['link']) {

    $el->attr([

        'class' => [
            'el-link',
            'uk-link-{link_style}',
        ],

        'href' => $props['link'],
        'uk-scroll' => !$element['scrollspy_nav'] && str_contains((string) $props['link'], '#'),
        'target' => $props['link_target'] ? '_blank' : false,
        'download' => $props['link_download'],
        'rel' => [
            'nofollow' => $props['link_rel_nofollow'],
            'noreferrer' => $props['link_rel_noreferrer']
        ],

    ]);

} else {

    $el->attr([

        'class' => [
            'el-content uk-disabled',
        ],

    ]);

}

// Subtitle
$meta = $this->el('div', [

    'class' => [
        'uk-nav-subtitle',
    ],

]);

// Image Align
$grid = $this->el('div', [

    'class' => [
        'uk-grid uk-grid-small uk-child-width-expand uk-flex-nowrap',
        'uk-flex-middle {@image_vertical_align}',
    ],

]);

$cell_image = $this->el('div', [

    'class' => [
        'uk-width-auto'
    ],

]);

// Image
$props['image'] = $this->render("{$__dir}/template-media", compact('props', 'element'));

?>

<?= $el($element, $attrs) ?>

    <?php if ($props['image'] && $props['meta'] != '') : ?>

        <?= $grid($element) ?>
            <?= $cell_image($element, $props['image']) ?>
            <div>
                <?= $props['content'] ?>
                <?= $meta($element, $props['meta']) ?>
            </div>
        <?= $grid->end() ?>

    <?php else : ?>

        <?= $props['image'] ?>

        <?php if ($props['meta'] != '') : ?>
        <div>
            <?= $props['content'] ?>
            <?= $meta($element, $props['meta']) ?>
        </div>
        <?php else : ?>
            <?= $props['content'] ?>
        <?php endif ?>

    <?php endif ?>

<?= $el->end() ?>
