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
        'uk-blend-{0}' => $props['media_blend_mode'],
        'uk-transition-{image_transition}',
        'uk-transition-opaque' => $props['image'] || $props['video'],
        'uk-transition-fade {@!image_transition}' => ($props['hover_image'] || $props['hover_video']) && !($props['image'] || $props['video']),
        'uk-flex-1 {@image_expand}'
    ],

    'style' => [
        'min-height: {image_min_height}px;',
    ],

]);

if ($props['image_expand'] || $props['image_min_height'] || ($media->name == 'video' && $props['image_width'] && $props['image_height'])) {

    $media->attr([

        'class' =>  [
            'uk-object-cover',
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

<?= $media($props, '') ?>

<?php if ($hover_media) : ?>
<?= $hover_media($props, '') ?>
<?php endif ?>
