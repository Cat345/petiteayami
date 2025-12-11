<?php

$el = $this->el('div', [

    'class' => [
        // Fix stacking context for drops if parallax is enabled
        'uk-position-relative uk-position-z-index {@animation: parallax}',
    ],

]);

$inline = $this->el('div', [

    'class' => [
        'uk-inline',
        'uk-inverse-{marker_color}',
    ],

]);

// Image
$image = $this->el('image', [
    'class' => [
        'uk-text-{image_svg_color}' => $props['image_svg_inline'] && $props['image_svg_color'] && $this->isImage($props['background_image']) == 'svg',
    ],
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
