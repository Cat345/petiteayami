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
if ($props['image'] || $props['video']) {

    $media->attr([

        'class' => [
            'uk-transition-{image_transition} uk-transition-opaque' => $props['link'] && ($element['image_link'] || $element['panel_link']),
            'uk-inverse-{image_text_color}',
            'uk-flex-1 {@image_expand}'
        ],

    ]);

    if ($element['image_expand'] || ($media->name == 'video' && $element['image_width'] && $element['image_height'])) {

        $media->attr([

            'class' =>  [
                'uk-object-{0}' => $props['image_focal_point'],
            ],

            'style' => [
                // Keep video responsiveness but with new proportions (because video isn't cropped like an image)
                'aspect-ratio: {image_width} / {image_height};' => $media->name == 'video',
            ],

        ]);

    }

    // Transition + Hover Image
    if ($element['image_transition'] || $props['hover_image'] || $props['hover_video']) {

        $transition = $this->el('div', [
            'class' => [
                'uk-inline-clip',
                'uk-flex-1 uk-flex uk-flex-column {@image_expand}',
            ],
        ]);

    }

    ($transition ?: $media)->attr('class', [
        'uk-border-{image_border}' => !$element['panel_style'] || ($element['panel_style'] && (!$element['panel_image_no_padding'] || $element['image_align'] == 'between')),
    ]);

}

($transition ?: $media)->attr('class', [
    'uk-transition-toggle {@image_link}' => $element['image_transition'] || $props['hover_image'] || $props['hover_video'],
    'uk-margin[-{image_margin}]-top {@!image_margin: remove}' => $element['image_align'] == 'between' || ($element['image_align'] == 'bottom' && !($element['panel_style'] && $element['panel_image_no_padding'])),
]);

// Hover Media
$hover_media = '';
if (($props['hover_image'] || $props['hover_video']) && ($props['image'] || $props['video'])) {

    if ($props['hover_video']) {
        $src = $props['hover_video'];
        $hover_media = include "{$__dir}/template-video.php";

        // Resets
        if ($hover_media->name == 'video') {
            $hover_media->attr('preload', 'none');
        } else {
            $hover_media->attr('loading', 'lazy');
        }

        $hover_media->attr([
            'uk-video' => false,
            'uk-cover' => $hover_media->attrs['uk-video'],
        ]);

    } elseif ($props['hover_image']) {
        $src = $props['hover_image'];
        $focal = $props['hover_image_focal_point'];
        $hover_media = include "{$__dir}/template-image.php";

        // Resets
        $hover_media->attr([
            'alt' => true,
            'loading' => 'lazy',
            'uk-svg' => false,
            'uk-cover' => true,
        ]);
    }

    $hover_media->attr([
        'class' => [
            'el-hover-image',
            'uk-transition-{image_transition}',
            'uk-transition-fade {@!image_transition}',
            'uk-object-{0}' => $props['hover_image_focal_point'], // `uk-cover` already sets object-fit to cover
        ],
    ]);

}

?>

<?php if ($transition) : ?>
<?= $transition($element) ?>
<?php endif ?>

    <?= $media($element, '') ?>

    <?php if ($hover_media) : ?>
    <?= $hover_media($element, '') ?>
    <?php endif ?>

<?php if ($transition) : ?>
<?= $transition->end() ?>
<?php endif ?>
