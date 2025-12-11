<?php


if ($props['video']) {
    $src = $props['video'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-video.php";
} elseif ($props['image']) {
    $src = $props['image'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-image.php";
} else {
    return;
}

// Media
$media->attr([

    'class' => [
        'el-image',
        'uk-blend-{0} {@!slideshow_animation: push|pull} {@!slideshow_kenburns}' => $props['media_blend_mode'],
    ],

    'uk-cover' => true,
    'uk-video' => false,

]);

?>

<?= $media($element, '') ?>
