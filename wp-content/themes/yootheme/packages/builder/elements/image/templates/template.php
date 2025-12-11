<?php

$props['image_hover_box_shadow'] = $props['link'] ? $props['image_hover_box_shadow'] : false;

$el = $this->el('div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 uk-flex {@height_expand}',
    ],

]);

// Image
$image = $this->el('image', [

    'class' => [
        'el-image',
        'uk-border-{image_border}',
        'uk-box-shadow-{image_box_shadow}',
        'uk-box-shadow-hover-{image_hover_box_shadow}',
        'uk-text-{image_svg_color} {@image_svg_inline}' => $this->isImage($props['image']) == 'svg',
        'uk-object-cover [uk-object-{image_focal_point}]' => $props['image_viewport_height'] || $props['height_expand'],
        'uk-inverse-{text_color}',
    ],

    'style' => [
        'height: 100vh; {@image_viewport_height} {@!height_expand}',
        // Fix bug in Safari not stretching an image beyond its intrinsic height
        'aspect-ratio: auto; {@height_expand}',
    ],

    'src' => $props['image'],
    'alt' => $props['image_alt'],
    'loading' => $props['image_loading'] ? false : null,
    'width' => $props['image_width'],
    'height' => $props['image_height'],
    'focal_point' => $props['image_focal_point'],
    'uk-svg' => $props['image_svg_inline'],
    'thumbnail' => true,
]);

// Box decoration
$decoration = $this->el('div', [

    'class' => [
        'uk-box-shadow-bottom {@image_box_decoration: shadow}',
        'tm-mask-default {@image_box_decoration: mask}',
        'tm-box-decoration-{image_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@image_box_decoration_inverse} {@image_box_decoration: default|primary|secondary}',
        'uk-inline {@!image_box_decoration: |shadow}',
        'uk-flex {@height_expand}',
    ],

]);

// Link and Lightbox
$link = $this->el('a', [

    'class' => [
        'el-link',

        'uk-box-shadow-bottom {@image_box_decoration: shadow}',
        'tm-mask-default {@image_box_decoration: mask}',
        'tm-box-decoration-{image_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@image_box_decoration_inverse} {@image_box_decoration: default|primary|secondary}',
        'uk-inline {@!image_box_decoration: |shadow}',
        'uk-flex {@height_expand}',
    ],

    'href' => ['{link}'],
    'aria-label' => ['{link_aria_label}'],
    'target' => ['_blank {@link_target: blank}'],
    'uk-scroll' => str_contains((string) $props['link'], '#'),

    // Target Modal?
    'uk-toggle' => $props['link_target'] === 'modal',
]);

if ($props['link'] && $props['link_target'] === 'modal') {

    $target = $this->el('image', [
        'src' => $props['link'],
        'width' => $props['lightbox_width'],
        'height' => $props['lightbox_height'],
    ]);

    if ($this->isImage($props['link'])) {

        $lightbox = $target($props, [
            'focal_point' => $props['lightbox_image_focal_point'],
            'thumbnail' => true,
        ]);

    } else {

        $video = $this->isVideo($props['link']);
        $iframe = $this->iframeVideo($props['link'], [], false);
        $lightbox = $video && !$iframe ? $target($props, [

            // Video
            'controls' => true,
            'uk-video' => true,

        ], '', 'video') : $target($props, [

            // Iframe
            'src' => $iframe ?: $props['link'],
            'allowfullscreen' => true,
            'loading' => 'lazy',
            'uk-video' => $video || $iframe,
            'uk-responsive' => true,

        ], '', 'iframe');

    }

    $connect_id = "js-{$this->uid()}";
    $props['link'] = "#{$connect_id}";
}

?>

<?= $el($props, $attrs) ?>

    <?php if ($props['link']) : ?>
    <?= $link($props, $image($props)) ?>
    <?php elseif ($props['image_box_decoration']) : ?>
    <?= $decoration($props, $image($props)) ?>
    <?php else : ?>
    <?= $image($props) ?>
    <?php endif ?>

    <?php if ($props['link'] && $props['link_target'] === 'modal') : ?>
    <?php // uk-flex-top is needed to make vertical margin work for IE11 ?>
    <div id="<?= $connect_id ?>" class="uk-flex-top uk-modal" uk-modal>
        <div class="uk-modal-dialog uk-width-auto uk-margin-auto-vertical">
            <button class="uk-modal-close-outside" type="button" uk-close></button>
            <?= $lightbox ?>
        </div>
    </div>
    <?php endif ?>

<?= $el->end() ?>
