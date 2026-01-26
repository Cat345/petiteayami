<?php

if ($props['video']) {
    $src = $props['video'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-video.php";
} elseif ($props['image']) {
    $src = $props['image'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-image.php";
} elseif ($props['hover_video']) {
    $src = $props['hover_video'];
    $focal = $props['hover_image_focal_point'];
    $media = include "{$__dir}/template-video.php";
} elseif ($props['hover_image']) {
    $src = $props['hover_image'];
    $focal = $props['hover_image_focal_point'];
    $media = include "{$__dir}/template-image.php";
}

// Media
$media->attr([

    'class' => [
        'el-image',
        'uk-blend-{media_blend_mode}',
        'uk-transition-{image_transition}',
        'uk-transition-opaque' => $props['image'] || $props['video'],
        'uk-transition-fade {@!image_transition}' => ($props['hover_image'] || $props['hover_video']) && !($props['image'] || $props['video']),
        'uk-flex-1 {@image_expand}'
    ],

    'style' => [
        'min-height: {image_min_height}px;',
    ],

]);

if ($element['image_expand'] || $element['image_min_height'] || ($media->name == 'video' && $element['image_width'] && $element['image_height'])) {

    $media->attr([

        'class' =>  [
            'uk-object-{0}' => $focal,
        ],

        'style' => [
            // Keep video responsiveness but with new proportions (because video isn't cropped like an image)
            'aspect-ratio: {image_width} / {image_height};' => $media->name == 'video',
        ],

    ]);

}

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

<?= $media($element, '') ?>

<?php if ($hover_media) : ?>
<?= $hover_media($element, '') ?>
<?php endif ?>
