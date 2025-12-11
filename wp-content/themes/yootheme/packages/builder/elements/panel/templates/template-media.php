<?php

if ($props['video']) {
    $src = $props['video'];
    $media = include "{$__dir}/template-video.php";
} elseif ($props['image']) {
    $src = $props['image'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-image.php";
} elseif ($props['icon']) {
    $media = include "{$__dir}/template-icon.php";
} else {
    return;
}

// Media
$media->attr([

    'class' => [
        'el-image',
    ],

]);

$transition = '';
$decoration = '';
if ($props['image'] || $props['video']) {

    $media->attr([

        'class' => [
            'uk-transition-{image_transition} uk-transition-opaque {@link}' => $props['image_link'] || $props['panel_link'],
            'uk-inverse-{image_text_color}',
            'uk-flex-1 {@panel_expand: image|both}',
        ],

    ]);

    if (in_array($props['panel_expand'], ['image', 'both']) || ($media->name == 'video' && $props['image_width'] && $props['image_height'])) {

        $media->attr([

            'class' =>  [
                'uk-object-cover',
                'uk-object-{0}' => $props['image_focal_point'],
            ],

            'style' => [
                // Keep video responsiveness but with new proportions (because video isn't cropped like an image)
                'aspect-ratio: {image_width} / {image_height};' => $media->name == 'video',
            ],

        ]);

    }

    // Transition + Hover Image
    if ($props['image_transition'] || $props['hover_image'] || $props['hover_video']) {

        $transition = $this->el('div', [
            'class' => [
                'uk-inline-clip',
                'uk-flex-1 uk-flex uk-flex-column {@panel_expand: image|both}',
            ],
        ]);

    }

    ($transition ?: $media)->attr('class', [
        'uk-border-{image_border}' => !$props['panel_style'] || ($props['panel_style'] && (!$props['panel_image_no_padding'] || $props['image_align'] == 'between')),
        'uk-box-shadow-{image_box_shadow} {@!panel_style}',
        'uk-box-shadow-hover-{image_hover_box_shadow} {@!panel_style} {@link}' => $props['image_link'] || $props['panel_link'],
    ]);

    // Decoration
    if ($props['image_box_decoration'] || $props['image_transition_border']) {

        $decoration = $this->el('div', [

            'class' => [
                'uk-box-shadow-bottom {@image_box_decoration: shadow}',
                'tm-mask-default {@image_box_decoration: mask}',
                'tm-box-decoration-{image_box_decoration: default|primary|secondary}',
                'tm-box-decoration-inverse {@image_box_decoration_inverse} {@image_box_decoration: default|primary|secondary}',
                'tm-transition-border {@image_transition_border}',

                'uk-inline',
                'uk-flex-1 uk-flex uk-flex-column {@panel_expand: image|both}',
            ],

        ]);

    }

}

($decoration ?: $transition ?: $media)->attr('class', [
    'uk-transition-toggle {@image_link}' => $props['image_transition'] || $props['image_transition_border'] || $props['hover_image'] || $props['hover_video'],
    'uk-margin[-{image_margin}]-top {@!image_margin: remove}' => $props['image_align'] == 'between' || ($props['image_align'] == 'bottom' && !($props['panel_style'] && $props['panel_image_no_padding'])),
]);

// Hover Media
$hover_media = '';
if (($props['hover_image'] || $props['hover_video']) && ($props['image'] || $props['video'])) {

    if ($props['hover_video']) {
        $src = $props['hover_video'];
        $hover_media = include "{$__dir}/template-video.php";
    } elseif ($props['hover_image']) {
        $src = $props['hover_image'];
        $focal = $props['hover_image_focal_point'];
        $hover_media = include "{$__dir}/template-image.php";
    }

    $hover_media->attr([
        'class' => [
            'el-hover-image',
            'uk-transition-{image_transition}',
            'uk-transition-fade {@!image_transition}',
            'uk-object-{hover_image_focal_point}', // `uk-cover` already sets object-fit to cover
        ],

        'uk-cover' => true,
        'uk-video' => false,

        // Resets
        'alt' => true, // Image
        'loading' => false, // Image + Iframe
        'preload' => false, // Video

    ]);

}

?>

<?php if ($decoration) : ?>
<?= $decoration($props) ?>
<?php endif ?>

    <?php if ($transition) : ?>
    <?= $transition($props) ?>
    <?php endif ?>

        <?php if ($media) : ?>
        <?= $media($props, '') ?>
        <?php endif ?>

        <?php if ($hover_media) : ?>
        <?= $hover_media($props, '') ?>
        <?php endif ?>

    <?php if ($transition) : ?>
    <?= $transition->end() ?>
    <?php endif ?>

<?php if ($decoration) : ?>
<?= $decoration->end() ?>
<?php endif ?>
