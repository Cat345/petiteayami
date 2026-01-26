<?php

$el = $this->el('div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',

        // Fix stacking context for drops if parallax is enabled
        'uk-position-relative uk-position-z-index {@animation: parallax}',
    ],

]);

$inline = $this->el('div', [

    'class' => [
        'uk-inline {@!height_expand}',
        'uk-flex-1 uk-flex uk-flex-column {@height_expand} uk-width-fit-content uk-position-relative',

        'uk-inverse-{marker_color}',
    ],

]);

// Image
$image = $this->el('image', [

    'class' => [
        'uk-flex-1 {@height_expand}',
        'uk-border-{background_image_border}',
        'uk-object-{background_image_focal_point}' => $props['height_viewport'] || $props['height_expand'],
    ],

    'style' => [
        'height: 100vh; {@!height_viewport_height} {@height_viewport} {@!height_viewport_offset} {@!height_expand}',
        'height: {height_viewport_height}vh; {@height_viewport} {@!height_viewport_offset} {@!height_expand}',
        // Fix bug in Safari not stretching an image beyond its intrinsic height
        'aspect-ratio: auto; {@height_expand}',
    ],

    'uk-height-viewport' => $props['height_viewport'] && $props['height_viewport_offset'] && !$props['height_expand'] ? [
        'property: height;',
        'offset-top: true; {@height_viewport_offset}',
        'offset-bottom: {0}; {@height_viewport}' => $props['height_viewport_height'] && $props['height_viewport_height'] < 100 ? 100 - (int) $props['height_viewport_height'] : false,
    ] : false,

    'src' => $props['background_image'],
    'alt' => $props['background_image_alt'],
    'loading' => $props['background_image_loading'] ? false : null,
    'width' => $props['background_image_width'],
    'height' => $props['background_image_height'],
    'focal_point' => $props['background_image_focal_point'],
    'uk-svg' => $props['image_svg_inline'],
    'thumbnail' => true,
]);

?>

<?= $el($props, $attrs) ?>
    <?= $inline($props) ?>

        <?= $props['background_image'] ? $image($props) : '' ?>

        <?php foreach ($children as $child) : ?>
        <?= $this->render("{$__dir}/template-marker", ['child' => $child, 'props' => $child->props, 'element' => $props]) ?>
        <?php endforeach ?>

    <?= $inline->end() ?>
<?= $el->end() ?>
